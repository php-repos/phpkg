<?php

namespace Tests\System\BuildCommand\BuildBeforeInstallTest;

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
    $expected = <<<EOD
\e[39mStart building...
\e[39mLoading configs...
\e[39mChecking packages...
\e[91mIt seems you didn't run the install command. Please make sure you installed your required packages.\e[39m

EOD;

    assert_true($output === $expected, 'Command output is not correct.' . PHP_EOL . $output . PHP_EOL . $expected);
}

function delete_build_directory()
{
    delete_recursive(realpath(root() . 'TestRequirements/Fixtures/ProjectWithTests/builds'));
}

function delete_packages_directory()
{
    delete_recursive(realpath(root() . 'TestRequirements/Fixtures/ProjectWithTests/Packages'));
}
