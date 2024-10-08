<?php

namespace Tests\System\FlushCommandTest;

use function PhpRepos\Cli\Output\assert_success;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\FileManager\Resolver\realpath;
use function PhpRepos\TestRunner\Assertions\assert_true;
use function PhpRepos\TestRunner\Runner\test;
use function Tests\Helper\reset_dummy_project;

test(
    title: 'it should flush builds',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg flush --project=../../DummyProject');

        assert_development_build_is_empty('Development build directory is not empty.' . $output);
        assert_production_build_is_empty('Production build directory is not empty.' . $output);
        $lines = explode("\n", trim($output));
        assert_true(1 === count($lines), 'Number of output lines do not match' . $output);
        assert_success('Build directory has been flushed.', $lines[0] . PHP_EOL);
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=../../DummyProject');
        shell_exec('php ' . root() . 'phpkg build --project=../../DummyProject');
        shell_exec('php ' . root() . 'phpkg build production --project=../../DummyProject');
    },
    after: function () {
        reset_dummy_project();
    }
);

function assert_development_build_is_empty($message)
{
    assert_true(['.', '..'] === scandir(realpath(root() . '../../DummyProject/builds/development')), $message);
}

function assert_production_build_is_empty($message)
{
    assert_true(['.', '..'] === scandir(realpath(root() . '../../DummyProject/builds/production')), $message);
}
