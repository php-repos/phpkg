<?php

namespace Phpkg\InfrastructureStructure\CLI;

use Phpkg\InfrastructureStructure\Cache;
use function Phpkg\InfrastructureStructure\Formats\bytes;
use function Phpkg\InfrastructureStructure\Formats\duration;
use function Phpkg\InfrastructureStructure\Strings\truncate;

/**
 * Display a progress bar for download progress.
 * 
 * @param string $id Unique identifier for this download (e.g., "github.com/owner/repo@hash")
 * @param string|null $url Optional URL to make the ID clickable (e.g., GitHub commit URL)
 * @param int|float $downloaded Bytes downloaded so far
 * @param int|float $download_size Total bytes to download (may be -1 if unknown)
 * @param float $time Elapsed time in seconds
 * @return void
 */
function progressbar(string $id, ?string $url, int|float $downloaded, int|float $download_size, float $time)
{
    $cache = Cache::load();
    $downloads_key = 'progressbar:downloads';
    $shown_ids_key = 'progressbar:shown_ids';
    
    // Initialize or get download state
    $items = $cache->items();
    if (!isset($items[$downloads_key])) {
        try {
            $cache->set($downloads_key, []);
        } catch (\RuntimeException $e) {
            // Key already exists, ignore
        }
        $items = $cache->items();
    }
    
    $downloads = $items[$downloads_key] ?? [];
    if (!isset($downloads[$id])) {
        $downloads[$id] = [
            'last_update' => $time,
            'last_progress' => 0,
            'completed' => false,
        ];
        $cache->update($downloads_key, $downloads);
    }
    
    $state = $downloads[$id];
    
    // Skip if already completed
    if ($state['completed']) {
        return;
    }
    
    // $time is already elapsed time in seconds
    $elapsed = $time;
    
    // Calculate speed (bytes per second)
    $time_diff = $time - $state['last_update'];
    if ($time_diff > 0 && $downloaded > $state['last_progress']) {
        $progress_diff = $downloaded - $state['last_progress'];
        $speed = $progress_diff / $time_diff; // bytes per second
    } else {
        $speed = 0;
    }
    
    // Handle unknown total size (download_size = -1)
    $size_known = $download_size > 0;
    
    // Calculate percentage
    $percentage = $size_known ? min(($downloaded / $download_size) * 100, 100) : 0;
    
    // Calculate ETA (estimated time remaining)
    $remaining = $size_known ? max($download_size - $downloaded, 0) : 0;
    $eta_seconds = $speed > 0 && $remaining > 0 ? (int) ($remaining / $speed) : 0;
    $eta_formatted = duration($eta_seconds);
    
    // Calculate average speed (overall)
    $avg_speed = $elapsed > 0 ? $downloaded / $elapsed : 0;
    
    // Calculate estimated finish time
    $estimated_finish = $eta_seconds > 0 ? time() + $eta_seconds : null;
    $finish_time_formatted = $estimated_finish ? date('H:i:s', $estimated_finish) : '--:--:--';
    
    // ANSI color codes
    $reset = "\033[0m";
    $bold = "\033[1m";
    $dim = "\033[2m";
    $cyan = "\033[36m";
    $green = "\033[32m";
    $bright_green = "\033[92m";
    $yellow = "\033[33m";
    $blue = "\033[34m";
    $dark_blue = "\033[34m"; // Dark blue for Windows 98 style
    $magenta = "\033[35m";
    $gray = "\033[90m";
    
    // Clear the line
    echo "\r\033[K";
    
    // Truncate id to fit nicely (120 chars since we have two-line format)
    $id_display = truncate($id, 120);
    
    // Create clickable link if URL is provided (terminal hyperlink using OSC 8)
    if ($url !== null) {
        // OSC 8 escape sequence: \033]8;;URL\033\\TEXT\033]8;;\033\\
        $id_display = "\033]8;;" . $url . "\033\\" . $id_display . "\033]8;;\033\\";
    }
    
    // Show package id on first line (only once per download)
    $items = $cache->items();
    if (!isset($items[$shown_ids_key])) {
        try {
            $cache->set($shown_ids_key, []);
        } catch (\RuntimeException $e) {
            // Key already exists, ignore
        }
        $items = $cache->items();
    }
    
    $shown_ids = $items[$shown_ids_key] ?? [];
    if (!isset($shown_ids[$id])) {
        echo "📦 " . $cyan . $id_display . $reset . "\n";
        $shown_ids[$id] = true;
        $cache->update($shown_ids_key, $shown_ids);
    }
    
    // Create simple progress bar
    $bar_length = 34;
    $filled = $size_known 
        ? (int) ($percentage / 100 * $bar_length) 
        : intval(fmod($elapsed, 4.0) * ($bar_length / 4));
    $empty = $bar_length - $filled;
    $filled_bar = $bright_green . str_repeat('█', $filled) . $reset;
    $empty_bar = $gray . str_repeat('░', $empty) . $reset;
    $bar = '[' . $filled_bar . $empty_bar . ']';
    
    // Format percentage
    $percentage_str = $bold . $yellow . sprintf("%3d%%", (int)$percentage) . $reset;
    
    // Format bytes downloaded/total with centered alignment
    // Each part needs to accommodate up to "9999.9 KB" (9 chars) for consistent alignment
    $downloaded_raw = bytes($downloaded);
    $total_raw = $size_known ? bytes($download_size) : '?';
    // Center each part within 9 characters, then join with slash
    $downloaded_padded = str_pad($downloaded_raw, 9, ' ', STR_PAD_BOTH);
    $total_padded = str_pad($total_raw, 9, ' ', STR_PAD_BOTH);
    $size_display = $downloaded_padded . '/' . $total_padded;
    
    // Format speeds with colors
    $speed_formatted = $yellow . bytes($speed) . '/s' . $reset;
    $avg_speed_formatted = $dim . bytes($avg_speed) . '/s' . $reset;
    
    // Format elapsed time
    $elapsed_formatted = duration((int)$elapsed);
    
    // Build the progress line
    if ($size_known) {
        $eta_str = $blue . $eta_formatted . $reset;
        $finish_str = $magenta . $finish_time_formatted . $reset;
        $time_str = $dim . $elapsed_formatted . $reset;
        
        printf(
            "   ⬇️  %s %s %s | Speed: %s (avg: %s) | ETA: %s (finish: %s) | Time: %s",
            $bar,
            $percentage_str,
            $size_display,
            $speed_formatted,
            $avg_speed_formatted,
            $eta_str,
            $finish_str,
            $time_str
        );
    } else {
        // Unknown size - show indeterminate progress
        $time_str = $dim . $elapsed_formatted . $reset;
        printf(
            "   ⬇️  %s %s | Speed: %s (avg: %s) | Time: %s",
            $bar,
            $size_display,
            $speed_formatted,
            $avg_speed_formatted,
            $time_str
        );
    }
    
    // Flush output
    flush();
    
    // Update tracking variables
    $state['last_update'] = $time;
    $state['last_progress'] = $downloaded;
    $downloads = $cache->items()[$downloads_key];
    $downloads[$id] = $state;
    $cache->update($downloads_key, $downloads);
    
    // If complete, show final message and mark as completed
    if ($size_known && $downloaded >= $download_size) {
        // ANSI color codes for completion
        $reset = "\033[0m";
        $bold = "\033[1m";
        $dim = "\033[2m";
        $green = "\033[32m";
        $bright_green = "\033[92m";
        $yellow = "\033[33m";
        
        echo "\r\033[K";
        $bar = '[' . $bright_green . str_repeat('█', 34) . $reset . ']';
        $downloaded_raw = bytes($downloaded);
        $total_raw = bytes($download_size);
        // Center each part within 9 characters for consistent alignment
        $downloaded_padded = str_pad($downloaded_raw, 9, ' ', STR_PAD_BOTH);
        $total_padded = str_pad($total_raw, 9, ' ', STR_PAD_BOTH);
        $size_display = $downloaded_padded . '/' . $total_padded;
        $percentage_str = $bold . $yellow . "100%" . $reset;
        
        printf(
            "   ⬇️  %s %s %s | %sCompleted%s in %s%s%s | Avg Speed: %s%s%s\n",
            $bar,
            $percentage_str,
            $size_display,
            $bold . $green,
            $reset,
            $dim,
            duration((int)$elapsed),
            $reset,
            $yellow,
            bytes($avg_speed) . '/s',
            $reset
        );
        
        // Mark as completed
        $state['completed'] = true;
        $downloads[$id] = $state;
        $cache->update($downloads_key, $downloads);
    }
}

/**
 * Show a spinner with a label on the last line of the terminal.
 * Cycles through spinner characters on each call to create animation effect.
 * 
 * @param string $label Label text to display on the right side of the spinner
 * @return void
 */
function show_spinner(string $label): void
{
    static $frame = 0;
    
    // ANSI color codes - using yellow/orange color like progressbar percentage
    $reset = "\033[0m";
    $yellow = "\033[33m";
    
    // Spinner character sequence
    $spinner_chars = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];
    
    // Get the current spinner character
    $spinner_char = $spinner_chars[$frame % count($spinner_chars)];
    
    // Save cursor position, move to last line, show spinner, restore cursor
    echo "\033[s"; // Save cursor position
    echo "\033[999;1H"; // Move to last line (row 999, column 1)
    echo "\033[K"; // Clear the line
    echo $yellow . $spinner_char . $reset . " " . $label;
    echo "\033[u"; // Restore cursor position
    flush();
    
    // Increment frame for next call
    $frame++;
}

/**
 * Hide the spinner from the last line of the terminal.
 * 
 * @return void
 */
function hide_spinner(): void
{
    // Save cursor position, move to last line, clear it, restore cursor
    echo "\033[s"; // Save cursor position
    echo "\033[999;1H"; // Move to last line
    echo "\033[K"; // Clear the line
    echo "\033[u"; // Restore cursor position
    flush();
}

