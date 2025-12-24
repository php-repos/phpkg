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
    
    // Create progress bar with percentage inside
    $bar_length = 34;

    // Calculate how many characters should be filled
    $filled_count = $size_known
        ? (int) ($percentage / 100 * $bar_length)
        : intval(fmod($elapsed, 4.0) * ($bar_length / 4));

    // Build percentage text to display in the center
    $percentage_text = sprintf(" %3d%% ", (int)$percentage);
    $percentage_length = 5; // " XX% " is always 5 characters

    // Calculate center position
    $center = (int)($bar_length / 2);
    $text_start = $center - (int)($percentage_length / 2);
    $text_end = $text_start + $percentage_length;

    // Build bar character by character
    $bar_content = '';
    for ($i = 0; $i < $bar_length; $i++) {
        if ($i >= $text_start && $i < $text_end) {
            // Insert percentage text character (without color)
            $char_index = $i - $text_start;
            $bar_content .= $bold . $yellow . $percentage_text[$char_index] . $reset;
        } elseif ($i < $filled_count) {
            // Filled portion
            $bar_content .= $bright_green . '█' . $reset;
        } else {
            // Empty portion
            $bar_content .= $gray . '░' . $reset;
        }
    }
    $bar = '[' . $bar_content . ']';

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
            "   ⬇️  %s %s | Speed: %s (avg: %s) | ETA: %s (finish: %s) | Time: %s",
            $bar,
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
        // Simplified format: just bar, size, and time (percentage already in bar)
        if ($size_known) {
            $progress_line = sprintf(
                "   ⬇️  %s %s | Time: %s",
                $bar,
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
            $suffix = "] " . $size_display . " | Time: " . $time_str;
            $suffix_width = mb_strlen(strip_ansi_codes($suffix));
            $available_width = $terminal_width - mb_strlen(strip_ansi_codes($prefix)) - $suffix_width;

            if ($available_width > 10) {
                // Adjust bar length to fit (minimum 10 to fit percentage text)
                $bar_length = max(10, $available_width);
                $filled_count = $size_known
                    ? (int) ($percentage / 100 * $bar_length)
                    : intval(fmod($elapsed, 4.0) * ($bar_length / 4));

                // Build bar with percentage inside
                $percentage_text = sprintf(" %3d%% ", (int)$percentage);
                $percentage_length = 5;
                $center = (int)($bar_length / 2);
                $text_start = $center - (int)($percentage_length / 2);
                $text_end = $text_start + $percentage_length;

                $bar_content = '';
                for ($i = 0; $i < $bar_length; $i++) {
                    if ($i >= $text_start && $i < $text_end) {
                        $char_index = $i - $text_start;
                        $bar_content .= $bold . $yellow . $percentage_text[$char_index] . $reset;
                    } elseif ($i < $filled_count) {
                        $bar_content .= $bright_green . '█' . $reset;
                    } else {
                        $bar_content .= $gray . '░' . $reset;
                    }
                }
                $bar = '[' . $bar_content . ']';

                $progress_line = sprintf(
                    "   ⬇️  %s %s | Time: %s",
                    $bar,
                    $size_display,
                    $time_str
                );
            } else {
                // Very narrow terminal - minimal display with percentage
                $progress_line = sprintf("   ⬇️  %d%% %s", (int)$percentage, $size_display);
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
        
        // Build completion bar - use same length as progress bar for consistency
        // No percentage text needed - a full green bar is self-explanatory
        $completion_bar_length = 34;
        $bar = '[' . $bright_green . str_repeat('█', $completion_bar_length) . $reset . ']';

        $downloaded_raw = bytes($downloaded);
        $total_raw = bytes($download_size);
        // Center each part within 9 characters for consistent alignment
        $downloaded_padded = str_pad($downloaded_raw, 9, ' ', STR_PAD_BOTH);
        $total_padded = str_pad($total_raw, 9, ' ', STR_PAD_BOTH);
        $size_display = $downloaded_padded . '/' . $total_padded;
        
        // Build completion message (percentage is now inside the bar)
        $completion_line = sprintf(
            "   ⬇️  %s %s | %sCompleted%s in %s%s%s | Avg Speed: %s%s%s",
            $bar,
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
            // Simplified completion message - keep the time, drop avg speed
            $completion_line = sprintf(
                "   ⬇️  %s %s | %sCompleted%s in %s%s%s",
                $bar,
                $size_display,
                $bold . $green,
                $reset,
                $dim,
                duration((int)$final_elapsed),
                $reset
            );

            // If still too wide, show time without "Completed in" text
            $completion_width = mb_strlen(strip_ansi_codes($completion_line));
            if ($completion_width > $terminal_width) {
                $completion_line = sprintf(
                    "   ⬇️  %s %s | %s%s%s",
                    $bar,
                    $size_display,
                    $dim,
                    duration((int)$final_elapsed),
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
 * Show a spinner with a label on the current line.
 * Cycles through spinner characters on each call to create animation effect.
 * Uses simple carriage return for cross-platform compatibility (works on PowerShell, Linux, Mac).
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

    // Use simple carriage return to overwrite the same line
    // \r moves to beginning of line, \033[K clears from cursor to end of line
    // This works reliably across all terminals including PowerShell
    echo "\r" . $yellow . $spinner_char . $reset . " " . $label . "\033[K";
    flush();

    // Increment frame for next call
    $frame++;
}

/**
 * Hide the spinner by clearing the current line.
 *
 * @return void
 */
function hide_spinner(): void
{
    // Clear the current line using carriage return
    echo "\r\033[K";
    flush();
}

/**
 * Display data in a formatted table with headers and rows.
 *
 * @param array<string> $headers Array of header column names
 * @param array<array<string>> $rows Array of rows, where each row is an array of cell values
 * @return void
 */
function table(array $headers, array $rows): void
{
    // ANSI color codes
    $reset = "\033[0m";
    $bold = "\033[1m";
    $cyan = "\033[36m";
    $gray = "\033[90m";

    // Calculate column widths based on content
    $column_widths = [];
    foreach ($headers as $index => $header) {
        $column_widths[$index] = mb_strlen(strip_ansi_codes($header));
    }

    foreach ($rows as $row) {
        foreach ($row as $index => $cell) {
            $cell_width = mb_strlen(strip_ansi_codes($cell));
            if (!isset($column_widths[$index]) || $cell_width > $column_widths[$index]) {
                $column_widths[$index] = $cell_width;
            }
        }
    }

    // Box-drawing characters
    $top_left = '┌';
    $top_right = '┐';
    $bottom_left = '└';
    $bottom_right = '┘';
    $horizontal = '─';
    $vertical = '│';
    $top_separator = '┬';
    $middle_separator = '┼';
    $bottom_separator = '┴';
    $left_separator = '├';
    $right_separator = '┤';

    // Build top border
    $top_border = $gray . $top_left;
    foreach ($column_widths as $index => $width) {
        $top_border .= str_repeat($horizontal, $width + 2);
        $top_border .= ($index < count($column_widths) - 1) ? $top_separator : $top_right;
    }
    $top_border .= $reset;
    echo $top_border . "\n";

    // Build header row
    $header_row = $gray . $vertical . $reset;
    foreach ($headers as $index => $header) {
        $width = $column_widths[$index];
        $padding = $width - mb_strlen(strip_ansi_codes($header));
        $header_row .= ' ' . $bold . $cyan . $header . $reset . str_repeat(' ', $padding) . ' ';
        $header_row .= $gray . $vertical . $reset;
    }
    echo $header_row . "\n";

    // Build middle separator
    $middle_border = $gray . $left_separator;
    foreach ($column_widths as $index => $width) {
        $middle_border .= str_repeat($horizontal, $width + 2);
        $middle_border .= ($index < count($column_widths) - 1) ? $middle_separator : $right_separator;
    }
    $middle_border .= $reset;
    echo $middle_border . "\n";

    // Build data rows
    foreach ($rows as $row) {
        $data_row = $gray . $vertical . $reset;
        foreach ($row as $index => $cell) {
            $width = $column_widths[$index];
            $padding = $width - mb_strlen(strip_ansi_codes($cell));
            $data_row .= ' ' . $cell . str_repeat(' ', $padding) . ' ';
            $data_row .= $gray . $vertical . $reset;
        }
        echo $data_row . "\n";
    }

    // Build bottom border
    $bottom_border = $gray . $bottom_left;
    foreach ($column_widths as $index => $width) {
        $bottom_border .= str_repeat($horizontal, $width + 2);
        $bottom_border .= ($index < count($column_widths) - 1) ? $bottom_separator : $bottom_right;
    }
    $bottom_border .= $reset;
    echo $bottom_border . "\n";
}

