<?php

namespace Tests\System\HelpCommandTest;

use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_true;
use function PhpRepos\TestRunner\Runner\test;
use function Tests\Helper\CRLF_to_EOL;

$help_content = CRLF_to_EOL(<<<'EOD'
usage: phpkg [-v | --version] [-h | --help] [--man]
           <command> [<args>]

These are common phpkg commands used in various situations:

start a working area
    init                Initialize an empty project or reinitialize an existing one
    migrate             Migrate from a composer project

work with packages
    credential          Add credential for given provider
    alias               Define an alias for a package 
    add                 Add a git repository as a package
    remove              Remove a git repository from packages
    update              Update the version of given package
    install             Installs package dependencies
    
work on an existing project
    build               Build the project
    watch               Watch file changes and build the project for each change
    flush               Flush files in build directory

global access
    run                 Run a project on the fly
    serve               Serve a project on the fly
    version             Print current version number
EOD);

test(
    title: 'it should show help output',
    case: function () use ($help_content) {
        $output = shell_exec('php ' . root() . 'phpkg -h');

        assert_true(str_contains($output, $help_content), 'Help output is not what we want!' . $output);
    }
);

test(
    title: 'it should show help output when help option passed',
    case: function () use ($help_content) {
        $output = shell_exec('php ' . root() . 'phpkg --help');

        assert_true(str_contains($output, $help_content), 'Help output is not what we want!' . $output);
    }
);

test(
    title: 'it should show help output when no command passed',
    case: function () use ($help_content) {
        $output = shell_exec('php ' . root() . 'phpkg');

        assert_true(str_contains($output, $help_content), 'Help output is not what we want!' . $output);
    }
);
