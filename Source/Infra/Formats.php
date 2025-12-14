<?php

namespace Phpkg\Infra\Formats;

/**
 * Format bytes to human-readable format (B, KB, MB, GB).
 * Keeps values in the same unit until reaching 4 digits (10000).
 * 
 * @param int|float $bytes Number of bytes
 * @return string Formatted string without width padding (padding handled in progressbar)
 */
function bytes(int|float $bytes): string
{
    // Keep in bytes until >= 10000 bytes
    if ($bytes < 10000) {
        return sprintf("%.0f B", $bytes);
    }
    
    // Convert to KB
    $kb = $bytes / 1024;
    
    // Keep in KB until >= 10000 KB
    if ($kb < 10000) {
        return sprintf("%.1f KB", $kb);
    }
    
    // Convert to MB
    $mb = $bytes / (1024 * 1024);
    
    // Keep in MB until >= 10000 MB
    if ($mb < 10000) {
        return sprintf("%.1f MB", $mb);
    }
    
    // Convert to GB
    $gb = $bytes / (1024 * 1024 * 1024);
    return sprintf("%.1f GB", $gb);
}

/**
 * Format duration in seconds to human-readable format (MM:SS or HH:MM:SS).
 * 
 * @param int $seconds Number of seconds
 * @return string Formatted duration string
 */
function duration(int $seconds): string
{
    if ($seconds < 3600) {
        // Less than 1 hour: show MM:SS
        return sprintf("%02d:%02d", (int)($seconds / 60), $seconds % 60);
    } else {
        // 1 hour or more: show HH:MM:SS
        $hours = (int)($seconds / 3600);
        $minutes = (int)(($seconds % 3600) / 60);
        $secs = $seconds % 60;
        return sprintf("%02d:%02d:%02d", $hours, $minutes, $secs);
    }
}

