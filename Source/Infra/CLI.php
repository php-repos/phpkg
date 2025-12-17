<?php

namespace Phpkg\Infra\CLI;

use Phpkg\Infra\Cache;
use function Phpkg\Infra\Formats\bytes;
use function Phpkg\Infra\Formats\duration;
use function Phpkg\Infra\Strings\truncate;

/**
 * Get the terminal width in columns.
 * Falls back to 80 if unable to detect.
 * 
 * @return int Terminal width in columns
 */
function get_terminal_width(): int
{
    // Try to get from COLUMNS environment variable
    $columns = getenv('COLUMNS');
    if ($columns !== false && is_numeric($columns) && $columns > 0) {
        return (int) $columns;
    }
    
    // Try to get from stty (Unix-like systems)
    if (function_exists('shell_exec') && !in_array(strtolower(ini_get('shell_exec')), ['', 'off', '0', 'false'])) {
        $stty = @shell_exec('stty size 2>/dev/null');
        if ($stty !== null && preg_match('/\d+\s+(\d+)/', trim($stty), $matches)) {
            return (int) $matches[1];
        }
    }
    
    // Try tput (if available)
    if (function_exists('shell_exec') && !in_array(strtolower(ini_get('shell_exec')), ['', 'off', '0', 'false'])) {
        $tput = @shell_exec('tput cols 2>/dev/null');
        if ($tput !== null && is_numeric(trim($tput))) {
            return (int) trim($tput);
        }
    }
    
    // Default fallback
    return 80;
}

/**
 * Strip ANSI color codes from a string to get its actual display width.
 * 
 * @param string $text Text that may contain ANSI codes
 * @return string Text with ANSI codes removed
 */
function strip_ansi_codes(string $text): string
{
    // Remove ANSI escape sequences (colors, formatting, etc.)
    return preg_replace('/\033\[[0-9;]*m/', '', $text);
}

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
function download_progressbar(string $id, ?string $url, int|float $downloaded, int|float $download_size, float $time)
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
            'max_elapsed' => 0,
            'completed' => false,
        ];
        $cache->update($downloads_key, $downloads);
    }
    
    $state = $downloads[$id];
    
    // Skip if already completed
    if ($state['completed']) {
        return;
    }
    
    // $time is already elapsed time in seconds since download started
    // Track the maximum elapsed time we've seen to handle edge cases
    $elapsed = max(0, $time);
    $max_elapsed = max($elapsed, $state['max_elapsed'] ?? 0);
    
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
    $magenta = "\033[35m";
    $gray = "\033[90m";
    
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
    $package_id_shown = isset($shown_ids[$id]);
    
    // Show package id on first line (only once per download)
    if (!$package_id_shown) {
        fwrite(STDOUT, "📦 " . $cyan . $id_display . $reset . "\n");
        $shown_ids[$id] = true;
        $cache->update($shown_ids_key, $shown_ids);
    }
    
    // Get terminal width to ensure output fits
    $terminal_width = get_terminal_width();
    
    // Always clear the current line and move to beginning before printing progress
    // Clear the entire line width to handle wrapped text
    fwrite(STDOUT, "\r\033[K");
    
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
    
    // Build the progress line with full details first
    if ($size_known) {
        $eta_str = $blue . $eta_formatted . $reset;
        $finish_str = $magenta . $finish_time_formatted . $reset;
        $time_str = $dim . $elapsed_formatted . $reset;
        
        $progress_line = sprintf(
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
        $progress_line = sprintf(
            "   ⬇️  %s %s | Speed: %s (avg: %s) | Time: %s",
            $bar,
            $size_display,
            $speed_formatted,
            $avg_speed_formatted,
            $time_str
        );
    }
    
    // Check if the line fits in terminal width (accounting for ANSI codes)
    $display_width = mb_strlen(strip_ansi_codes($progress_line));
    
    // If too wide, create a simplified version
    if ($display_width > $terminal_width) {
        // Simplified format: just bar, percentage, size, and time
        if ($size_known) {
            $progress_line = sprintf(
                "   ⬇️  %s %s %s | Time: %s",
                $bar,
                $percentage_str,
                $size_display,
                $time_str
            );
        } else {
            $progress_line = sprintf(
                "   ⬇️  %s %s | Time: %s",
                $bar,
                $size_display,
                $time_str
            );
        }
        
        // If still too wide, truncate the bar
        $display_width = mb_strlen(strip_ansi_codes($progress_line));
        if ($display_width > $terminal_width) {
            // Calculate how much space we have for the bar
            $prefix = "   ⬇️  [";
            $suffix = $size_known 
                ? "] " . $percentage_str . " " . $size_display . " | Time: " . $time_str
                : "] " . $size_display . " | Time: " . $time_str;
            $suffix_width = mb_strlen(strip_ansi_codes($suffix));
            $available_width = $terminal_width - mb_strlen(strip_ansi_codes($prefix)) - $suffix_width;
            
            if ($available_width > 5) {
                // Adjust bar length to fit
                $bar_length = max(5, $available_width - 2); // -2 for brackets
                $filled = $size_known 
                    ? (int) ($percentage / 100 * $bar_length) 
                    : intval(fmod($elapsed, 4.0) * ($bar_length / 4));
                $empty = $bar_length - $filled;
                $filled_bar = $bright_green . str_repeat('█', $filled) . $reset;
                $empty_bar = $gray . str_repeat('░', $empty) . $reset;
                $bar = '[' . $filled_bar . $empty_bar . ']';
                
                if ($size_known) {
                    $progress_line = sprintf(
                        "   ⬇️  %s %s %s | Time: %s",
                        $bar,
                        $percentage_str,
                        $size_display,
                        $time_str
                    );
                } else {
                    $progress_line = sprintf(
                        "   ⬇️  %s %s | Time: %s",
                        $bar,
                        $size_display,
                        $time_str
                    );
                }
            } else {
                // Very narrow terminal - minimal display
                $progress_line = $size_known
                    ? sprintf("   ⬇️  %s %s", $percentage_str, $size_display)
                    : sprintf("   ⬇️  %s", $size_display);
            }
        }
    }
    
    // Print the progress line
    fwrite(STDOUT, $progress_line);
    
    // Flush output immediately to ensure it's displayed
    fflush(STDOUT);
    
    // Update tracking variables
    $state['last_update'] = $time;
    $state['last_progress'] = $downloaded;
    $state['max_elapsed'] = $max_elapsed;
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
        
        // Clear the line and show completion message
        fwrite(STDOUT, "\r\033[K");
        
        // Use the maximum elapsed time we've tracked to ensure accuracy
        // This handles cases where the final $time might be 0 or incorrect
        $final_elapsed = max($max_elapsed, $elapsed, 0);
        $final_avg_speed = $final_elapsed > 0 ? $downloaded / $final_elapsed : 0;
        
        // Build completion bar - adjust length to fit terminal
        $completion_bar_length = min(34, max(10, $terminal_width - 60));
        $bar = '[' . $bright_green . str_repeat('█', $completion_bar_length) . $reset . ']';
        $downloaded_raw = bytes($downloaded);
        $total_raw = bytes($download_size);
        // Center each part within 9 characters for consistent alignment
        $downloaded_padded = str_pad($downloaded_raw, 9, ' ', STR_PAD_BOTH);
        $total_padded = str_pad($total_raw, 9, ' ', STR_PAD_BOTH);
        $size_display = $downloaded_padded . '/' . $total_padded;
        $percentage_str = $bold . $yellow . "100%" . $reset;
        
        // Build completion message
        $completion_line = sprintf(
            "   ⬇️  %s %s %s | %sCompleted%s in %s%s%s | Avg Speed: %s%s%s",
            $bar,
            $percentage_str,
            $size_display,
            $bold . $green,
            $reset,
            $dim,
            duration((int)$final_elapsed),
            $reset,
            $yellow,
            bytes($final_avg_speed) . '/s',
            $reset
        );
        
        // Check if completion line fits, simplify if needed
        $completion_width = mb_strlen(strip_ansi_codes($completion_line));
        if ($completion_width > $terminal_width) {
            // Simplified completion message
            $completion_line = sprintf(
                "   ⬇️  %s %s %s | %sCompleted%s in %s%s%s",
                $bar,
                $percentage_str,
                $size_display,
                $bold . $green,
                $reset,
                $dim,
                duration((int)$final_elapsed),
                $reset
            );
            
            // If still too wide, further simplify
            $completion_width = mb_strlen(strip_ansi_codes($completion_line));
            if ($completion_width > $terminal_width) {
                $completion_line = sprintf(
                    "   ⬇️  %s %s | %sCompleted%s",
                    $percentage_str,
                    $size_display,
                    $bold . $green,
                    $reset
                );
            }
        }
        
        fwrite(STDOUT, $completion_line . "\n");
        fflush(STDOUT);
        
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
    
    // ANSI color codes - using yellow/orange color like download_progressbar percentage
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

