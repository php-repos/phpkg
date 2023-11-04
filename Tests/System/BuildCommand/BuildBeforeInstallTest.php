<?php

namespace Tests\System\BuildCommand\BuildBeforeInstallTest;

use function PhpRepos\Cli\Output\assert_error;
use function PhpRepos\Cli\Output\assert_line;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_true;
use function PhpRepos\TestRunner\Runner\test;
use function Tests\Helper\force_delete;
use function Tests\Helper\reset_empty_project;

test(
    title: 'it should show error message when project packages are not installed',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg build --project=TestRequirements/Fixtures/EmptyProject');

        assert_output($output);
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=TestRequirements/Fixtures/EmptyProject');
        shell_exec('php ' . root() . 'phpkg add git@github.com:php-repos/simple-package.git --project=TestRequirements/Fixtures/EmptyProject');
        force_delete(root() . 'TestRequirements/Fixtures/EmptyProject/Packages/php-repos/simple-package');
    },
    after: function () {
        reset_empty_project();
    }
);

function assert_output($output)
{
    $lines = explode("\n", trim($output));

    assert_true(2 === count($lines), 'Number of output lines do not match' . $output);
    assert_line("Start building...", $lines[0] . PHP_EOL);
    assert_error("It seems you didn't run the install command. Please make sure you installed your required packages.", $lines[1] . PHP_EOL);
}
