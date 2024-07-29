<?php

namespace Tests\System\HelpCommandTest;

use function PhpRepos\Cli\Output\assert_output;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\TestRunner\Runner\test;
use function Tests\Helper\CRLF_to_EOL;

$help_content = CRLF_to_EOL(<<<EOD
\e[39mUsage: phpkg [-v | --version] [-h | --help]
               <command> [<options>] [<args>]

Here you can see a list of available commands:
\e[39m    add           Adds the specified package to your project.
\e[39m    alias         Defines the provided alias for a given package, allowing you to use the alias in other commands where a package URL is required.
\e[39m    build         Compiles and adds project files to the build directory.
\e[39m    credential    The `credential` command is used to add a security token for a specified Git provider to the credential file.
\e[39m    flush         If you need to remove any built files, running this command will create a fresh builds directory.
\e[39m    init          This command initializes the project by adding the necessary files and directories.
\e[39m    install       Downloads and installs added packages into your project's directory.
\e[39m    migrate       The `migrate` command is used to migrate from a Composer project to a `phpkg` project.
\e[39m    remove        Removes the specified package from your project.
\e[39m    run           Runs a project on-the-fly.
\e[39m    serve         Serves an external project using PHP's built-in server on-the-fly.
\e[39m    update        Allows you to update the version of a specified package in your PHP project.
\e[39m    watch         Enables you to monitor file changes in your project and automatically build the project for each change.

EOD);

test(
    title: 'it should show help output',
    case: function () use ($help_content) {
        $output = shell_exec('php ' . root() . 'phpkg -h');

        assert_output($help_content, $output);
    }
);

test(
    title: 'it should show help output when help option passed',
    case: function () use ($help_content) {
        $output = shell_exec('php ' . root() . 'phpkg --help');

        assert_output($help_content, $output);
    }
);

test(
    title: 'it should show help output when no command passed',
    case: function () use ($help_content) {
        $output = shell_exec('php ' . root() . 'phpkg');

        assert_output($help_content, $output);
    }
);
