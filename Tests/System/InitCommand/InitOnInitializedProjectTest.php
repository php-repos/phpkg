<?php

namespace Tests\System\InitCommand\InitOnInitializedProject;

use function PhpRepos\Cli\Output\assert_error;
use function PhpRepos\Cli\Output\assert_line;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\TestRunner\Assertions\assert_true;
use function PhpRepos\TestRunner\Runner\test;
use function Tests\Helper\reset_dummy_project;

test(
    title: 'it should return error when project is already initialized',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg init --project=../../DummyProject');

        $lines = explode("\n", trim($output));

        assert_true(2 === count($lines), 'Number of output lines do not match' . $output);
        assert_line("Init project...", $lines[0] . PHP_EOL);
        assert_error("The project is already initialized.", $lines[1] . PHP_EOL);
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=../../DummyProject');
    },
    after: function () {
        reset_dummy_project();
    }
);
