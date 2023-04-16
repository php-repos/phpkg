<?php

namespace Tests\System\BuildCommand\BuildWithCustomPackageDirectoryTest;

use function PhpRepos\FileManager\File\delete;
use function PhpRepos\FileManager\Resolver\realpath;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_true;
use function PhpRepos\TestRunner\Runner\test;
use function Tests\Helper\force_delete;
use function Tests\System\BuildCommand\BuildHelper\replace_build_vars;

test(
    title: 'it should build the project with custom packages directory',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg build --project=TestRequirements/Fixtures/ProjectWithTests');

        assert_file_with_package_dependency_has_been_built('File with package dependency has not been built properly!' . $output);
    },
    before: function () {
        copy(
            realpath(root() . 'TestRequirements/Stubs/ProjectWithTests/build-with-custom-packages-directory.json'),
            realpath(root() . 'TestRequirements/Fixtures/ProjectWithTests/phpkg.config.json')
        );
        shell_exec('php ' . root() . 'phpkg init --project=TestRequirements/Fixtures/ProjectWithTests --packages-directory=vendor');
        shell_exec('php ' . root() . 'phpkg add git@github.com:php-repos/simple-package.git --project=TestRequirements/Fixtures/ProjectWithTests');
    },
    after: function () {
        delete_build_directory();
        delete_packages_directory();
        delete(realpath(root() . 'TestRequirements/Fixtures/ProjectWithTests/phpkg.config.json'));
        delete(realpath(root() . 'TestRequirements/Fixtures/ProjectWithTests/phpkg.config-lock.json'));
    }
);

function delete_build_directory()
{
    force_delete(realpath(root() . 'TestRequirements/Fixtures/ProjectWithTests/builds'));
}

function delete_packages_directory()
{
    force_delete(realpath(root() . 'TestRequirements/Fixtures/ProjectWithTests/vendor'));
}

function assert_file_with_package_dependency_has_been_built($message)
{
    $environment_build_path = root() . 'TestRequirements/Fixtures/ProjectWithTests/builds/development';
    $stubs_directory = root() . 'TestRequirements/Stubs/ProjectWithTests';

    assert_true((
            file_exists(realpath($environment_build_path . '/Source/FileUsingVendor.php'))
            && file_get_contents(realpath($environment_build_path . '/Source/FileUsingVendor.php')) === replace_build_vars(realpath($environment_build_path), realpath($stubs_directory . '/Source/FileUsingVendor.stub'))
        ),
        $message
    );
}
