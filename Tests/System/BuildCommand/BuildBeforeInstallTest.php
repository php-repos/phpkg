<?php

namespace Tests\System\BuildCommand\BuildBeforeInstallTest;

use function PhpRepos\Cli\IO\Write\assert_error;
use function PhpRepos\Cli\IO\Write\assert_line;
use function PhpRepos\FileManager\Directory\delete_recursive;
use function PhpRepos\FileManager\File\delete;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\FileManager\Resolver\realpath;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_true;
use function PhpRepos\TestRunner\Runner\test;

test(
    title: 'it should show error message when project packages are not installed',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg build --project=TestRequirements/Fixtures/ProjectWithTests');

        assert_output($output);
    },
    before: function () {
        copy(
            realpath(root() . 'TestRequirements/Stubs/ProjectWithTests/phpkg.config.json'),
            realpath(root() . 'TestRequirements/Fixtures/ProjectWithTests/phpkg.config.json')
        );
        shell_exec('php ' . root() . 'phpkg add git@github.com:php-repos/simple-package.git --project=TestRequirements/Fixtures/ProjectWithTests');
        delete_recursive(root() . 'TestRequirements/Fixtures/ProjectWithTests/Packages/php-repos/simple-package');
    },
    after: function () {
        delete_packages_directory();
        delete(realpath(root() . 'TestRequirements/Fixtures/ProjectWithTests/phpkg.config.json'));
        delete(realpath(root() . 'TestRequirements/Fixtures/ProjectWithTests/phpkg.config-lock.json'));
    }
);

function assert_output($output)
{
    $lines = explode("\n", trim($output));

    assert_true(4 === count($lines), 'Number of output lines do not match' . $output);
    assert_line("Start building...", $lines[0] . PHP_EOL);
    assert_line("Loading configs...", $lines[1] . PHP_EOL);
    assert_line("Checking packages...", $lines[2] . PHP_EOL);
    assert_error("It seems you didn't run the install command. Please make sure you installed your required packages.", $lines[3] . PHP_EOL);
}

function delete_packages_directory()
{
    delete_recursive(realpath(root() . 'TestRequirements/Fixtures/ProjectWithTests/Packages'));
}
