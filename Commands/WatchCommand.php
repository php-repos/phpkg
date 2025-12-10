<?php

use Phpkg\BusinessSpecifications\Project;
use PhpRepos\Console\Attributes\Description;
use PhpRepos\Console\Attributes\LongOption;
use function PhpRepos\Cli\Output\line;
use function PhpRepos\Cli\Output\error;
use function PhpRepos\Cli\Output\success;

/**
 * Enables you to monitor file changes in your project and automatically build the project for each change.
 * When you run this command, `phpkg` continuously watches your project files and builds only when changes are detected.
 */
return function (
    #[LongOption('project')]
    #[Description('The project path to watch. If not provided, uses the current directory.')]
    ?string $project = '',
    #[LongOption('wait')]
    #[Description('Specify the polling interval in seconds when native file watching is not available. Default: 2 seconds.')]
    ?int $wait = 2,
): int
{
    line('Starting watch command...');

    $outcome = Project\build($project);

    if (!$outcome->success) {
        error($outcome->message);
        return 1;
    }

    success($outcome->message);
    
    $root = $outcome->data['root'];
    $build_path = $outcome->data['build_path'];
    
    // Detect which method to use
    $method = null;
    $command = null;
    
    // Helper function to find command (cross-platform)
    $find_command = function($command_name) {
        if (PHP_OS_FAMILY === 'Windows') {
            // On Windows, use 'where' command
            $result = shell_exec("where $command_name 2>nul");
            return $result ? trim($result) : null;
        } else {
            // On Unix-like systems, use 'which' command
            $result = shell_exec("which $command_name 2>/dev/null");
            return $result ? trim($result) : null;
        }
    };
    
    // Mac: Use fswatch if available
    if (PHP_OS_FAMILY === 'Darwin') {
        $fswatch = $find_command('fswatch');
        if ($fswatch) {
            $method = 'fswatch';
            // fswatch --exclude uses extended regex pattern (ERE)
            // Exclude build_path and build directory (parent of build_path)
            $builds_dir = dirname($build_path);
            $build_path_escaped = preg_quote($build_path, '/');
            $builds_dir_escaped = preg_quote($builds_dir, '/');
            // Pattern: match paths starting with build_path or builds_dir
            $exclude_pattern = '^(' . $build_path_escaped . '|' . $builds_dir_escaped . ')';
            $command = escapeshellarg($fswatch) . ' -r -1 --exclude=' . escapeshellarg($exclude_pattern) . ' ' . escapeshellarg($root);
        }
    }
    
    // Linux: Use inotifywait if available
    if (PHP_OS_FAMILY === 'Linux' && $method === null) {
        $inotifywait = $find_command('inotifywait');
        if ($inotifywait) {
            $method = 'inotifywait';
            // Exclude build output directory using regex pattern (inotifywait --exclude uses POSIX extended regex)
            // Escape the build_path for regex and exclude it
            $build_path_escaped = preg_quote($build_path, '/');
            $command = escapeshellarg($inotifywait) . ' -r -m -e modify,create,delete,move --exclude ' . escapeshellarg('^' . $build_path_escaped . '(/.*)?$') . ' ' . escapeshellarg($root) . ' 2>/dev/null';
        }
    }
    
    // Use detected method
    if ($method === 'fswatch') {
        line('Using native file watching (fswatch)...');
        $debounce_seconds = 1; // Wait 1 second after last change
        $last_change = 0;
        $pending_rebuild = false;
        
        $builds_dir = dirname($build_path);
        
        while (true) {
            // fswatch -1 exits after one event, so we loop and call it again
            $output = shell_exec($command);
            
            if ($output !== null) {
                $event_path = trim($output);
                
                // Skip events from build output directory (safety check)
                if (str_starts_with($event_path, $build_path) || str_starts_with($event_path, $builds_dir)) {
                    continue;
                }
                
                $last_change = time();
                $pending_rebuild = true;
                
                // Wait for debounce period (check if more events come)
                while ((time() - $last_change) < $debounce_seconds) {
                    usleep(100000); // 0.1 seconds
                }
                
                if ($pending_rebuild) {
                    line('Changes detected. Rebuilding...');
                    try {
                        $outcome = Project\build($project);
                        if (!$outcome->success) {
                            error($outcome->message);
                        } else {
                            success($outcome->message);
                        }
                    } catch (\Throwable $e) {
                        error('Build error: ' . $e->getMessage());
                    }
                    // Continue watching even if build fails or throws exception
                    $pending_rebuild = false;
                }
            }
        }
    } elseif ($method === 'inotifywait') {
        line('Using native file watching (inotifywait)...');
        
        $process = popen($command, 'r');
        if ($process) {
            $debounce_seconds = 1; // Wait 1 second after last change
            $last_change = 0;
            $pending_rebuild = false;
            
            // Set non-blocking mode so we can check time
            stream_set_blocking($process, false);
            
            while (true) {
                $line = fgets($process);
                
                if ($line !== false) {
                    $line_trimmed = trim($line);
                    // Extract the path from inotifywait output (format: "path EVENT_TYPE")
                    $event_path = explode(' ', $line_trimmed)[0] ?? $line_trimmed;
                    
                    // Skip events from build output directory
                    // build_path is something like /root/build, so we exclude anything under /root/build
                    if (str_starts_with($event_path, $build_path)) {
                        continue;
                    }
                    
                    // Also exclude the build directory itself (parent of build_path)
                    // Extract build directory from build_path: /root/build -> /root/build
                    $builds_dir = dirname($build_path);
                    if (str_starts_with($event_path, $builds_dir)) {
                        continue;
                    }
                    
                    // Event detected
                    $last_change = time();
                    $pending_rebuild = true;
                } else {
                    // No event, check if we should rebuild
                    if ($pending_rebuild && $last_change > 0) {
                        $time_since_last_change = time() - $last_change;
                        if ($time_since_last_change >= $debounce_seconds) {
                            line('Changes detected. Rebuilding...');
                            try {
                                $outcome = Project\build($project);
                                if (!$outcome->success) {
                                    error($outcome->message);
                                } else {
                                    success($outcome->message);
                                }
                            } catch (\Throwable $e) {
                                error('Build error: ' . $e->getMessage());
                            }
                            // Continue watching even if build fails or throws exception
                            $pending_rebuild = false;
                            $last_change = 0;
                        }
                    }
                    usleep(100000); // 0.1 seconds - small sleep when no events
                }
            }
            pclose($process);
        } else {
            error('Failed to start inotifywait process');
            return 1;
        }
    } else {
        // Fallback: Polling
        line("Using polling method (checking every {$wait} seconds)...");
        if (PHP_OS_FAMILY === 'Linux') {
            line('Note: Install inotify-tools for native file watching: sudo apt-get install inotify-tools');
        } elseif (PHP_OS_FAMILY === 'Darwin') {
            line('Note: Install fswatch for native file watching: brew install fswatch');
        } elseif (PHP_OS_FAMILY === 'Windows') {
            line('Note: Native file watching is not available on Windows. Using polling method.');
        }
        
        while (true) {
            sleep($wait);
            line('Rebuilding...');
            try {
                $outcome = Project\build($project);
                if (!$outcome->success) {
                    error($outcome->message);
                } else {
                    success($outcome->message);
                }
            } catch (\Throwable $e) {
                error('Build error: ' . $e->getMessage());
            }
        }
    }
    
    return 0;
};
