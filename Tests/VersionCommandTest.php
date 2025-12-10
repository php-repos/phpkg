<?php

namespace Tests\VersionCommandTest;

use Tests\CliRunner;
use PhpRepos\TestRunner\Assertions;
use function PhpRepos\TestRunner\Runner\test;

test(
    title: 'it should show version',
    case: function () {
        $output = CliRunner\phpkg('version');
        Assertions\assert_true(str_contains($output, 'phpkg'), 'Should contain phpkg in output');
    }
);
