<?php

use Phpkg\Business\Project;
use PhpRepos\Console\Attributes\Argument;
use PhpRepos\Console\Attributes\Description;
use PhpRepos\Console\Attributes\ExcessiveArguments;
use PhpRepos\Console\Attributes\LongOption;
use function PhpRepos\Cli\Output\error;
use function PhpRepos\Cli\Output\line;

/**
 * Runs a project on-the-fly.
 * This command seamlessly manages the downloading, building, and running of the specified project.
 *
 * To use this command, provide a valid Git URL (either SSH or HTTPS) as the `url_or_path` argument.
 * For local projects, use an absolute or relative path from your current working directory.
 *
 * If the project has multiple entry points, you can specify the desired entry point as an optional second argument.
 */
return function (
    #[Argument]
    #[Description("URL or path to the package.\nThe Git repository URL (HTTPS or SSH) of the package you want to run. In case you want to run a local package, pass an absolute path to the package, or a relative path from your current working directory.")]
    string $url_or_path,
    #[Argument]
    #[Description("The entry point you want to execute within the package. If not provided, it will use the first\navailable entry point.")]
    ?string $entry_point = null,
    #[LongOption('version')]
    #[Description("Specify the version of the project to run.\nTo run a specific version, use the `version` option. To run a version based on a specific commit hash, use `development#{commit-hash}`.")]
    ?string $version = null,
    #[LongOption('input-pipe')]
    #[Description("Custom input configuration for the process. Format: 'pipe,r' for pipe or 'file,filename.txt,r' for file. Defaults to STDIN.")]
    ?string $input_pipe = null,
    #[LongOption('output-pipe')]
    #[Description("Custom output configuration for the process. Format: 'pipe,w' for pipe or 'file,filename.txt,w' for file. Defaults to STDOUT.")]
    ?string $output_pipe = null,
    #[LongOption('error-pipe')]
    #[Description("Custom error configuration for the process. Format: 'pipe,w' for pipe or 'file,filename.txt,w' for file. Defaults to STDERR.")]
    ?string $error_pipe = null,
    #[Description("Additional arguments to pass to the entry point script.")]
    #[ExcessiveArguments]
    array $entry_point_arguments = []
) {
    line("Running $url_or_path...");

    $outcome = Project\run($url_or_path, $version, $entry_point);

    if (!$outcome->success) {
        error('Failed to run the project. ' . $outcome->message);
        return 1;
    }

    $entry_point_path = $outcome->data['entry_point'];

    // Build command with proper escaping for cross-platform compatibility
    $entry_point_escaped = escapeshellarg($entry_point_path);
    $arguments_escaped = array_map('escapeshellarg', $entry_point_arguments);
    $command = 'php -d memory_limit=-1 ' . $entry_point_escaped . ' ' . implode(' ', $arguments_escaped);

    // Determine pipe configuration based on options
    $input_pipe_config = $input_pipe ? explode(',', $input_pipe) : ['pipe', 'r'];
    $output_pipe_config = $output_pipe ? explode(',', $output_pipe) : ['pipe', 'w'];
    $error_pipe_config = $error_pipe ? explode(',', $error_pipe) : ['pipe', 'w'];

    $process = proc_open($command, [$input_pipe_config, $output_pipe_config, $error_pipe_config], $pipes);

    if (!is_resource($process)) {
        error('Failed to start the process');
        return 1;
    }

    // Handle pipes (forward stdin, read stdout/stderr)
    $stdin_pipe = $pipes[0] ?? null;
    $stdout_pipe = $pipes[1] ?? null;
    $stderr_pipe = $pipes[2] ?? null;

    // Set stdout and stderr to non-blocking mode
    if ($stdout_pipe && is_resource($stdout_pipe)) {
        stream_set_blocking($stdout_pipe, false);
    }
    if ($stderr_pipe && is_resource($stderr_pipe)) {
        stream_set_blocking($stderr_pipe, false);
    }

    // Process I/O until the process completes
    $process_running = true;
    while ($process_running) {
        $status = proc_get_status($process);
        $process_running = $status && $status['running'];

        // Forward STDIN to child process if data is available
        if ($stdin_pipe && is_resource($stdin_pipe)) {
            $read = [STDIN];
            $write = [];
            $except = [];
            if (stream_select($read, $write, $except, 0, 0) > 0) {
                $input = fread(STDIN, 8192);
                if ($input !== false && $input !== '') {
                    fwrite($stdin_pipe, $input);
                } elseif (feof(STDIN)) {
                    fclose($stdin_pipe);
                    $stdin_pipe = null;
                }
            }
        }

        // Read and output stdout (non-blocking)
        if ($stdout_pipe && is_resource($stdout_pipe)) {
            $output = stream_get_contents($stdout_pipe);
            if ($output !== false && $output !== '') {
                echo $output;
            }
        }

        // Read and output stderr (non-blocking)
        if ($stderr_pipe && is_resource($stderr_pipe)) {
            $error = stream_get_contents($stderr_pipe);
            if ($error !== false && $error !== '') {
                fwrite(STDERR, $error);
            }
        }

        if ($process_running) {
            usleep(10000); // 0.01 seconds
        }
    }

    // Read any remaining output after process completes
    if ($stdout_pipe && is_resource($stdout_pipe)) {
        stream_set_blocking($stdout_pipe, true);
        while (!feof($stdout_pipe)) {
            $output = fread($stdout_pipe, 8192);
            if ($output !== false && $output !== '') {
                echo $output;
            } else {
                break;
            }
        }
        fclose($stdout_pipe);
    }

    if ($stderr_pipe && is_resource($stderr_pipe)) {
        stream_set_blocking($stderr_pipe, true);
        while (!feof($stderr_pipe)) {
            $error = fread($stderr_pipe, 8192);
            if ($error !== false && $error !== '') {
                fwrite(STDERR, $error);
            } else {
                break;
            }
        }
        fclose($stderr_pipe);
    }

    if ($stdin_pipe && is_resource($stdin_pipe)) {
        fclose($stdin_pipe);
    }

    return proc_close($process);
};
