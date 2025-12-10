<?php

use Phpkg\BusinessSpecifications\Project;
use PhpRepos\Console\Attributes\Argument;
use PhpRepos\Console\Attributes\Description;
use PhpRepos\Console\Attributes\ExcessiveArguments;
use PhpRepos\Console\Attributes\LongOption;
use function PhpRepos\Cli\Output\error;
use function PhpRepos\Cli\Output\line;
use function PhpRepos\FileManager\Paths\parent;

/**
 * Serves an external project using PHP's built-in server on-the-fly.
 * This command seamlessly manages the downloading, building, and serving of the specified project using PHP built-in server.
 *
 * To use this command, provide a valid Git URL (either SSH or HTTPS) as the `url_or_path` argument.
 * For local projects, use an absolute or relative path from your current working directory.
 *
 * If the project has multiple entry points, you can specify the desired entry point as an optional second argument.
 */
return function (
    #[Argument]
    #[Description("URL or path to the package.\nThe Git repository URL (HTTPS or SSH) of the package you want to serve. In case you want to serve a local package, pass an absolute path to the package, or a relative path from your current working directory.")]
    string $url_or_path,
    #[Argument]
    #[Description("The entry point you want to execute within the project. If not provided, it will use the first\navailable entry point.")]
    ?string $entry_point = null,
    #[LongOption('host')]
    #[Description("Host address to bind the server to. Defaults to localhost.")]
    string $host = 'localhost',
    #[LongOption('port')]
    #[Description("Port number to bind the server to. Defaults to 8000.")]
    int $port = 8000,
    #[LongOption('input-pipe')]
    #[Description("Custom input configuration for the process. Format: 'pipe,r' for pipe or 'file,filename.txt,r' for file. Defaults to STDIN.")]
    ?string $input_pipe = null,
    #[LongOption('output-pipe')]
    #[Description("Custom output configuration for the process. Format: 'pipe,w' for pipe or 'file,filename.txt,w' for file. Defaults to STDOUT.")]
    ?string $output_pipe = null,
    #[LongOption('error-pipe')]
    #[Description("Custom error configuration for the process. Format: 'pipe,w' for pipe or 'file,filename.txt,w' for file. Defaults to STDERR.")]
    ?string $error_pipe = null,
    #[LongOption('version')]
    #[Description("Specify the version of the project to serve.\nTo serve a specific version, use the `version` option. To serve a version based on a specific commit hash, use `development#{commit-hash}`.")]
    ?string $version = null,
    #[ExcessiveArguments]
    array $entry_point_arguments = []
) {
    line("Serving $url_or_path on http://$host:$port");

    $outcome = Project\run($url_or_path, $version, $entry_point);
    if (!$outcome->success) {
        error('Failed to run the project. ' . $outcome->message);
        return 1;
    }

    $document_root = parent($outcome->data['entry_point']);
    $arguments_escaped = array_map('escapeshellarg', $entry_point_arguments);
    $command = 'php -S ' . escapeshellarg($host) . ':' . $port . ' -t ' . escapeshellarg($document_root) . ' ' . implode(' ', $arguments_escaped);

    // Determine pipe configuration based on options
    $input_pipe_config = $input_pipe ? explode(',', $input_pipe) : STDIN;
    $output_pipe_config = $output_pipe ? explode(',', $output_pipe) : STDOUT;
    $error_pipe_config = $error_pipe ? explode(',', $error_pipe) : STDERR;

    $process = proc_open($command, [$input_pipe_config, $output_pipe_config, $error_pipe_config], $pipes);

    if (!is_resource($process)) {
        error('Failed to start the server process');
        return 1;
    }

    // Write the process ID to standard output for process management
    $status = proc_get_status($process);
    $pid = $status['pid'];
    line("Your server PID is: $pid");
    line("Press Ctrl+C to stop the server.");

    // Cross-platform signal/process handling
    if (PHP_OS_FAMILY === 'Windows') {
        // Windows: Use proc_terminate and check process status
        // Register shutdown function to clean up on exit (handles Ctrl+C)
        $cleanup = function() use (&$process) {
            if (is_resource($process)) {
                $status = proc_get_status($process);
                if ($status && $status['running']) {
                    proc_terminate($process);
                    // Give it a moment to terminate gracefully
                    usleep(500000); // 0.5 seconds
                    // Check again and force if needed
                    $status = proc_get_status($process);
                    if ($status && $status['running']) {
                        // On Windows, proc_terminate doesn't support signal numbers,
                        // but calling it again should help, or we can use taskkill
                        proc_terminate($process);
                    }
                }
                if (is_resource($process)) {
                    proc_close($process);
                }
            }
        };
        register_shutdown_function($cleanup);

        // Poll for process status and handle Ctrl+C
        // On Windows, Ctrl+C will trigger the shutdown function
        while (true) {
            $status = proc_get_status($process);
            
            // Check if process has exited
            if (!$status || !$status['running']) {
                $exit_code = $status ? $status['exitcode'] : -1;
                if ($exit_code !== 0 && $exit_code !== -1) {
                    error("Server process exited with code: $exit_code");
                    proc_close($process);
                    return $exit_code;
                }
                proc_close($process);
                break;
            }

            // Small sleep to avoid busy-waiting
            usleep(100000); // 0.1 seconds
        }
    } else {
        // Unix-like: Use pcntl for proper signal handling
        if (!function_exists('pcntl_signal')) {
            error('The pcntl extension is required on Unix-like systems for proper signal handling.');
            proc_terminate($process);
            return 1;
        }

        $terminate_server = function ($signal) use ($process) {
            $status = proc_get_status($process);
            if ($status['running']) {
                proc_terminate($process);
                // Give it a moment to terminate gracefully
                usleep(500000); // 0.5 seconds
                // Force kill if still running
                if (proc_get_status($process)['running']) {
                    proc_terminate($process, $signal);
                }
            }
            exit();
        };

        pcntl_signal(SIGTERM, $terminate_server);
        pcntl_signal(SIGINT, $terminate_server);

        while (true) {
            $status = proc_get_status($process);
            
            // Check if process has exited
            if (!$status['running']) {
                $exit_code = $status['exitcode'];
                if ($exit_code !== 0) {
                    error("Server process exited with code: $exit_code");
                    return $exit_code;
                }
                break;
            }

            pcntl_signal_dispatch();
            usleep(100);
        }
    }

    return 0;
};
