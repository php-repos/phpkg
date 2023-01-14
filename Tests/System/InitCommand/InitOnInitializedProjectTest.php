<?php

namespace Tests\System\InitCommand\InitOnInitializedProject;

use function PhpRepos\FileManager\Directory\clean;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_true;
use function PhpRepos\TestRunner\Runner\test;

test(
    title: 'it should return error when project is already initialized',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg init --project=TestRequirements/Fixtures/EmptyProject');

        $expected = <<<EOD
\e[39mInit project...
\e[91mThe project is already initialized.\e[39m

EOD;

        assert_true($expected === $output, 'Output is not correct:' . PHP_EOL . $expected . PHP_EOL . $output);
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=TestRequirements/Fixtures/EmptyProject');
    },
    after: function () {
        clean(realpath(root() . 'TestRequirements/Fixtures/EmptyProject'));
    }
);
