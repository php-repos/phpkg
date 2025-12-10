<?php

use Phpkg\BusinessSpecifications\Project;
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
    #[Description("Version number that you want to use for this project to run.")]
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

    // Determine pipe configuration based on options
    $input_pipe_config = $input_pipe ? explode(',', $input_pipe) : STDIN;
    $output_pipe_config = $output_pipe ? explode(',', $output_pipe) : STDOUT;
    $error_pipe_config = $error_pipe ? explode(',', $error_pipe) : STDERR;

    // Build command with proper escaping for cross-platform compatibility
    $entry_point_escaped = escapeshellarg($entry_point_path);
    $arguments_escaped = array_map('escapeshellarg', $entry_point_arguments);
    $command = 'php -d memory_limit=-1 ' . $entry_point_escaped . ' ' . implode(' ', $arguments_escaped);

    // Start the process
    $process = proc_open($command,
        [$input_pipe_config, $output_pipe_config, $error_pipe_config], 
        $pipes
    );

    if (!is_resource($process)) {
        error('Failed to start the process');
        return 1;
    }

    return proc_close($process);
};
