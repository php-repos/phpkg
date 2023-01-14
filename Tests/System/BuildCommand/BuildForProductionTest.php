<?php

namespace Tests\System\BuildCommand\BuildForProductionTest;

use function PhpRepos\FileManager\Directory\delete_recursive;
use function PhpRepos\FileManager\File\delete;
use function PhpRepos\FileManager\Resolver\realpath;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_true;
use function PhpRepos\TestRunner\Runner\test;

test(
    title: 'it should build the project',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg build production --project=TestRequirements/Fixtures/ProjectWithTests');

        assert_build_directory_exists('Build directory has not been created!' . $output);
        assert_environment_build_directory_exists('Environment build directory has not been created!' . $output);
        assert_source_has_been_built('Source files has not been built properly!' . $output);
        assert_file_with_package_dependency_has_been_built('File with package dependency has not been built properly!' . $output);
        assert_none_php_files_has_not_been_built('None PHP files has been built properly!' . $output);
        assert_tests_has_been_built('Test files has not been built properly!' . $output);
        assert_file_permissions_are_same('Files permission are not same!' . $output);
        assert_git_directory_excluded('Build copied the git directory!' . $output);
        assert_executables_are_linked('Executable files did not linked' . $output);
        assert_build_for_project_entry_points('Project entry point does not built properly!' . $output);
        assert_build_for_packages_entry_points('Packages entry point does not built properly!' . $output);
    },
    before: function () {
        copy(
            realpath(root() . 'TestRequirements/Stubs/ProjectWithTests/phpkg.config.json'),
            realpath(root() . 'TestRequirements/Fixtures/ProjectWithTests/phpkg.config.json')
        );
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
    delete_recursive(realpath(root() . 'TestRequirements/Fixtures/ProjectWithTests/builds'));
}

function delete_packages_directory()
{
    delete_recursive(realpath(root() . 'TestRequirements/Fixtures/ProjectWithTests/Packages'));
}

function assert_build_directory_exists($message)
{
    assert_true((file_exists(realpath(root() . 'TestRequirements/Fixtures/ProjectWithTests/builds'))), $message);
}

function assert_environment_build_directory_exists($message)
{
    assert_true((file_exists(realpath(root() . 'TestRequirements/Fixtures/ProjectWithTests/builds/production'))), $message);
}

function assert_source_has_been_built($message)
{
    $environment_build_path = root() . 'TestRequirements/Fixtures/ProjectWithTests/builds/production';
    $stubs_directory = root() . 'TestRequirements/Stubs/ProjectWithTests';

    assert_true((
            file_exists(realpath($environment_build_path . '/Source/SubDirectory/ClassUseAnotherClass.php'))
            && file_exists(realpath($environment_build_path . '/Source/SubDirectory/SimpleClass.php'))
            && file_exists(realpath($environment_build_path . '/Source/SampleFile.php'))
            && file_get_contents(realpath($environment_build_path . '/Source/SubDirectory/ClassUseAnotherClass.php')) === str_replace('$environment_build_path', realpath($environment_build_path), file_get_contents(realpath($stubs_directory . '/Source/SubDirectory/ClassUseAnotherClass.stub')))
            && file_get_contents(realpath($environment_build_path . '/Source/SubDirectory/SimpleClass.php')) === str_replace('$environment_build_path', realpath($environment_build_path), file_get_contents(realpath($stubs_directory . '/Source/SubDirectory/SimpleClass.stub')))
            && file_get_contents(realpath($environment_build_path . '/Source/SampleFile.php')) === str_replace('$environment_build_path', realpath($environment_build_path), file_get_contents(realpath($stubs_directory . '/Source/SampleFile.stub')))
        ),
        $message
    );
}

function assert_none_php_files_has_not_been_built($message)
{
    $environment_build_path = root() . 'TestRequirements/Fixtures/ProjectWithTests/builds/production';

    assert_true((
            file_exists(realpath($environment_build_path . '/Source/SubDirectory/FileDontNeedBuild.txt'))
            && file_get_contents(realpath($environment_build_path . '/Source/SubDirectory/FileDontNeedBuild.txt')) === str_replace('$environment_build_path', realpath($environment_build_path), file_get_contents(realpath(root() . 'TestRequirements/Fixtures/ProjectWithTests/Source/SubDirectory/FileDontNeedBuild.txt')))
        ),
        $message
    );
}

function assert_tests_has_been_built($message)
{
    $environment_build_path = root() . 'TestRequirements/Fixtures/ProjectWithTests/builds/production';
    $stubs_directory = root() . 'TestRequirements/Stubs/ProjectWithTests';

    assert_true((
            file_exists(realpath($environment_build_path . '/Tests/SampleTest.php'))
            && file_get_contents(realpath($environment_build_path . '/Tests/SampleTest.php')) === str_replace('$environment_build_path', realpath($environment_build_path), file_get_contents(realpath($stubs_directory . '/Tests/SampleTest.stub')))
        ),
        $message
    );
}

function assert_file_with_package_dependency_has_been_built($message)
{
    $environment_build_path = root() . 'TestRequirements/Fixtures/ProjectWithTests/builds/production';
    $stubs_directory = root() . 'TestRequirements/Stubs/ProjectWithTests';

    assert_true((
            file_exists(realpath($environment_build_path . '/Source/FileWithPackageDependency.php'))
            && file_get_contents(realpath($environment_build_path . '/Source/FileWithPackageDependency.php')) === str_replace('$environment_build_path', realpath($environment_build_path), file_get_contents(realpath($stubs_directory . '/Source/FileWithPackageDependency.stub')))
        ),
        $message
    );
}

function assert_file_permissions_are_same($message)
{
    $environment_build_path = root() . 'TestRequirements/Fixtures/ProjectWithTests/builds/production';

    assert_true(
        fileperms(realpath($environment_build_path . '/PublicDirectory'))
        ===
        fileperms(realpath(root() . 'TestRequirements/Fixtures/ProjectWithTests/PublicDirectory')),
        'Directory permission is not set properly!' . $message
    );
    assert_true(
        fileperms(realpath($environment_build_path . '/PublicDirectory/ExecutableFile.php'))
        ===
        fileperms(realpath(root() . 'TestRequirements/Fixtures/ProjectWithTests/PublicDirectory/ExecutableFile.php')),
        'PHP file permission is not set properly!' . $message
    );
    assert_true(
        fileperms(realpath($environment_build_path . '/PublicDirectory/AnotherExecutableFile'))
        ===
        fileperms(realpath(root() . 'TestRequirements/Fixtures/ProjectWithTests/PublicDirectory/AnotherExecutableFile')),
        'Other file permission is not set properly!' . $message
    );
}

function assert_git_directory_excluded($message)
{
    assert_true((
            ! file_exists(realpath(root() . 'TestRequirements/Fixtures/ProjectWithTests/builds/production/.git'))
            &&
            ! file_exists(realpath(root() . 'TestRequirements/Fixtures/ProjectWithTests/builds/production/Packages/php-repos/simple-package/.git'))
        ),
        $message
    );
}

function assert_executables_are_linked($message)
{
    $link_file = root() . 'TestRequirements/Fixtures/ProjectWithTests/builds/production/run-executable';
    $link_source = root() . 'TestRequirements/Fixtures/ProjectWithTests/builds/production/Packages/php-repos/simple-package/run.php';

    assert_true((
            is_link(realpath($link_file))
            && readlink(realpath($link_file)) === realpath($link_source)
        ),
        $message
    );
}

function assert_build_for_project_entry_points($message)
{
    $environment_build_path = root() . 'TestRequirements/Fixtures/ProjectWithTests/builds/production';
    $stubs_directory = root() . 'TestRequirements/Stubs/ProjectWithTests';

    assert_true((
            file_exists(realpath($environment_build_path . '/entry-point'))
            && file_get_contents(realpath($environment_build_path . '/entry-point')) === str_replace('$environment_build_path', realpath($environment_build_path), file_get_contents(realpath($stubs_directory . '/entry-point.stub')))
        ),
        $message
    );
}

function assert_build_for_packages_entry_points($message)
{
    $environment_build_path = root() . 'TestRequirements/Fixtures/ProjectWithTests/builds/production';
    $stubs_directory = root() . 'TestRequirements/Stubs/SimplePackage';

    assert_true((
            file_exists(realpath($environment_build_path . '/Packages/php-repos/simple-package/entry-point'))
            && file_get_contents(realpath($environment_build_path . '/Packages/php-repos/simple-package/entry-point')) === str_replace('$environment_build_path', realpath($environment_build_path), file_get_contents(realpath($stubs_directory . '/entry-point.stub')))
        ),
        $message
    );
}
