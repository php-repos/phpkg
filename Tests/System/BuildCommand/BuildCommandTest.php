<?php

namespace Tests\System\BuildCommand\BuildCommandTest;

use function PhpRepos\Cli\IO\Write\assert_error;
use function PhpRepos\Cli\IO\Write\assert_line;
use function PhpRepos\Cli\IO\Write\assert_success;
use function PhpRepos\FileManager\File\delete;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\FileManager\Resolver\realpath;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_true;
use function PhpRepos\TestRunner\Runner\test;
use function Tests\Helper\force_delete;
use function Tests\System\BuildCommand\BuildHelper\replace_build_vars;

test(
    title: 'it should show error message when the project is not initialized',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg build --project=TestRequirements/Fixtures/ProjectWithTests');

        $lines = explode("\n", trim($output));

        assert_true(2 === count($lines), 'Number of output lines do not match' . $output);
        assert_line("Start building...", $lines[0] . PHP_EOL);
        assert_error("Project is not initialized. Please try to initialize using the init command.", $lines[1] . PHP_EOL);
    }
);

test(
    title: 'it should build the project',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg build --project=TestRequirements/Fixtures/ProjectWithTests');

        assert_output($output);
        assert_build_directory_exists('Build directory has not been created!' . $output);
        assert_environment_build_directory_exists('Environment build directory has not been created!' . $output);
        assert_source_has_been_built('Source files has not been built properly!' . $output);
        assert_file_with_package_dependency_has_been_built('File with package dependency has not been built properly!' . $output);
        assert_none_php_files_has_not_been_built('None PHP files has been built properly!' . $output);
        assert_tests_has_been_built('Test files has not been built properly!' . $output);
        assert_file_permissions_are_same('Files permission are not same!' . $output);
        assert_git_directory_excluded('Build copied the git directory!' . $output);
        assert_import_file_created($output);
        assert_executables_are_linked('Executable files did not linked' . $output);
        assert_build_for_project_entry_points('Project entry point does not built properly!' . $output);
        assert_build_for_packages_entry_points('Packages entry point does not built properly!' . $output);
        assert_exclude_not_built('Excludes has been built!' . $output);
        assert_build_for_extended_classes('Extended classes has not been built properly!' . $output);
        assert_build_for_interfaces('Interfaces has not been built properly!' . $output);
        assert_build_for_traits('Traits has not been built properly!' . $output);
        assert_build_for_specified_file('Specified file has not been built properly!' . $output);
        assert_build_for_compound_namespaces('Compounded namespaces has not been built properly!' . $output);
        assert_symlinks_are_linked_properly('Symlinks not linked properly.');
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

function assert_output($output)
{
    $lines = explode("\n", trim($output));

    assert_true(5 === count($lines), 'Number of output lines do not match' . $output);
    assert_line("Start building...", $lines[0] . PHP_EOL);
    assert_line("Loading configs...", $lines[1] . PHP_EOL);
    assert_line("Checking packages...", $lines[2] . PHP_EOL);
    assert_line("Building...", $lines[3] . PHP_EOL);
    assert_success("Build finished successfully.", $lines[4] . PHP_EOL);
}

function delete_build_directory()
{
    force_delete(realpath(root() . 'TestRequirements/Fixtures/ProjectWithTests/builds'));
}

function delete_packages_directory()
{
    force_delete(realpath(root() . 'TestRequirements/Fixtures/ProjectWithTests/Packages'));
}

function assert_build_directory_exists($message)
{
    assert_true(file_exists(realpath(root() . 'TestRequirements/Fixtures/ProjectWithTests/builds')), $message);
}

function assert_environment_build_directory_exists($message)
{
    assert_true(file_exists(realpath(root() . 'TestRequirements/Fixtures/ProjectWithTests/builds/development')), $message);
}

function assert_source_has_been_built($message)
{
    $environment_build_path = root() . 'TestRequirements/Fixtures/ProjectWithTests/builds/development';
    $stubs_directory = root() . 'TestRequirements/Stubs/ProjectWithTests';

    assert_true((
            file_exists(realpath($environment_build_path . '/Source/ImportableSample.php'))
            && file_exists(realpath($environment_build_path . '/Source/ImportableSampleClass.php'))
            && file_exists(realpath($environment_build_path . '/Source/SubDirectory/ClassUseAnotherClass.php'))
            && file_exists(realpath($environment_build_path . '/Source/SubDirectory/SimpleClass.php'))
            && file_exists(realpath($environment_build_path . '/Source/SubDirectory/ClassUseNamespaceTwice.php'))
            && file_exists(realpath($environment_build_path . '/Source/SampleFile.php'))
            && file_exists(realpath($environment_build_path . '/Source/ImportingWithTheUseOperator.php'))
            && file_exists(realpath($environment_build_path . '/Source/ImportingMultipleUseStatements.php'))
            && file_exists(realpath($environment_build_path . '/Source/GroupUseStatements.php'))
            && file_get_contents(realpath($environment_build_path . '/Source/ImportableSample.php')) === replace_build_vars(realpath($environment_build_path), realpath($stubs_directory . '/Source/ImportableSample.stub'))
            && file_get_contents(realpath($environment_build_path . '/Source/ImportableSampleClass.php')) === replace_build_vars(realpath($environment_build_path), realpath($stubs_directory . '/Source/ImportableSampleClass.stub'))
            && file_get_contents(realpath($environment_build_path . '/Source/SubDirectory/ClassUseAnotherClass.php')) === replace_build_vars(realpath($environment_build_path), realpath($stubs_directory . '/Source/SubDirectory/ClassUseAnotherClass.stub'))
            && file_get_contents(realpath($environment_build_path . '/Source/SubDirectory/SimpleClass.php')) === replace_build_vars(realpath($environment_build_path), realpath($stubs_directory . '/Source/SubDirectory/SimpleClass.stub'))
            && file_get_contents(realpath($environment_build_path . '/Source/SubDirectory/ClassUseNamespaceTwice.php')) === replace_build_vars(realpath($environment_build_path), realpath($stubs_directory . '/Source/SubDirectory/ClassUseNamespaceTwice.stub'))
            && file_get_contents(realpath($environment_build_path . '/Source/SampleFile.php')) === replace_build_vars(realpath($environment_build_path), realpath($stubs_directory . '/Source/SampleFile.stub'))
            && file_get_contents(realpath($environment_build_path . '/Source/ImportingWithTheUseOperator.php')) === replace_build_vars(realpath($environment_build_path), realpath($stubs_directory . '/Source/ImportingWithTheUseOperator.stub'))
            && file_get_contents(realpath($environment_build_path . '/Source/ImportingMultipleUseStatements.php')) === replace_build_vars(realpath($environment_build_path), realpath($stubs_directory . '/Source/ImportingMultipleUseStatements.stub'))
            && file_get_contents(realpath($environment_build_path . '/Source/GroupUseStatements.php')) === replace_build_vars(realpath($environment_build_path), realpath($stubs_directory . '/Source/GroupUseStatements.stub'))
        ),
        $message
    );
}

function assert_none_php_files_has_not_been_built($message)
{
    $environment_build_path = root() . 'TestRequirements/Fixtures/ProjectWithTests/builds/development';

    assert_true((
            file_exists(realpath($environment_build_path . '/Source/SubDirectory/FileDontNeedBuild.txt'))
            && file_get_contents(realpath($environment_build_path . '/Source/SubDirectory/FileDontNeedBuild.txt'))
            ===
            replace_build_vars(
                realpath($environment_build_path),
                realpath(root() . 'TestRequirements/Fixtures/ProjectWithTests/Source/SubDirectory/FileDontNeedBuild.txt')
            )
        ),
        $message
    );
}

function assert_tests_has_been_built($message)
{
    $environment_build_path = root() . 'TestRequirements/Fixtures/ProjectWithTests/builds/development';
    $stubs_directory = root() . 'TestRequirements/Stubs/ProjectWithTests';

    assert_true((
            file_exists(realpath($environment_build_path . '/Tests/SampleTest.php'))
            && file_get_contents(realpath($environment_build_path . '/Tests/SampleTest.php'))
            ===
            replace_build_vars(
                realpath($environment_build_path),
                realpath($stubs_directory . '/Tests/SampleTest.stub')
            )
        ),
        $message
    );
}

function assert_file_with_package_dependency_has_been_built($message)
{
    $environment_build_path = root() . 'TestRequirements/Fixtures/ProjectWithTests/builds/development';
    $stubs_directory = root() . 'TestRequirements/Stubs/ProjectWithTests';

    assert_true((
            file_exists(realpath($environment_build_path . '/Source/FileWithPackageDependency.php'))
            && file_get_contents(realpath($environment_build_path . '/Source/FileWithPackageDependency.php'))
            ===
            replace_build_vars(
                realpath($environment_build_path),
                realpath($stubs_directory . '/Source/FileWithPackageDependency.stub')
            )
        ),
        $message
    );
}

function assert_file_permissions_are_same($message)
{
    $environment_build_path = root() . 'TestRequirements/Fixtures/ProjectWithTests/builds/development';

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
    assert_true(! file_exists(realpath(root() . 'TestRequirements/Fixtures/ProjectWithTests/builds/development/.git')), $message . ': .git directory in the project is not excluded!');
    assert_true(! file_exists(realpath(root() . 'TestRequirements/Fixtures/ProjectWithTests/builds/development/Packages/php-repos/simple-package/.git')), $message . ': .git directory in the installed package is not excluded!');
}

function assert_import_file_created($output)
{
    $environment_build_path = root() . 'TestRequirements/Fixtures/ProjectWithTests/builds/development';
    assert_true(file_exists($environment_build_path. '/phpkg.imports.php'), 'import file not found');

    assert_true(
        file_get_contents($environment_build_path . '/phpkg.imports.php')
        ===
        replace_build_vars(realpath($environment_build_path), root() . 'TestRequirements/Stubs/ProjectWithTests/Imports.stub'),
        'The Import file content is not correct'
    );
}

function assert_executables_are_linked($message)
{
    $link_file = root() . 'TestRequirements/Fixtures/ProjectWithTests/builds/development/run-executable';
    $link_source = root() . 'TestRequirements/Fixtures/ProjectWithTests/builds/development/Packages/php-repos/simple-package/run.php';
    $stub = root() . 'TestRequirements/Stubs/ProjectWithTests/simple-package-run-executable.stub';
    $environment_build_path = root() . 'TestRequirements/Fixtures/ProjectWithTests/builds/development';

    assert_true((is_link(realpath($link_file)) && readlink(realpath($link_file)) === realpath($link_source)), $message);
    clearstatcache();
    assert_true(774 == decoct(fileperms(realpath($link_source)) & 0777), 'executable file permission is not correct');
    assert_true((replace_build_vars(realpath($environment_build_path), realpath($stub)) === file_get_contents(realpath($link_source))), 'Executable content is not correct! ' . $message);
}

function assert_build_for_project_entry_points($message)
{
    $environment_build_path = root() . 'TestRequirements/Fixtures/ProjectWithTests/builds/development';
    $stubs_directory = root() . 'TestRequirements/Stubs/ProjectWithTests';

    assert_true((
            file_exists(realpath($environment_build_path . '/entry-point'))
            && file_get_contents(realpath($environment_build_path . '/entry-point')) === replace_build_vars(realpath($environment_build_path), realpath($stubs_directory . '/entry-point.stub'))
        ),
        $message
    );
}

function assert_build_for_packages_entry_points($message)
{
    $environment_build_path = root() . 'TestRequirements/Fixtures/ProjectWithTests/builds/development';
    $stubs_directory = root() . 'TestRequirements/Stubs/SimplePackage';

    assert_true((
            file_exists(realpath($environment_build_path . '/Packages/php-repos/simple-package/entry-point'))
            && file_get_contents(realpath($environment_build_path . '/Packages/php-repos/simple-package/entry-point')) === replace_build_vars(realpath($environment_build_path), realpath($stubs_directory . '/entry-point.stub'))
        ),
        $message
    );
}

function assert_exclude_not_built($message)
{
    assert_true((
            ! file_exists(realpath(root() . 'TestRequirements/Fixtures/ProjectWithTests/builds/development/excluded-file.php'))
            && ! file_exists(realpath(root() . 'TestRequirements/Fixtures/ProjectWithTests/builds/development/excluded-directory'))
        ),
        $message
    );
}

function assert_build_for_extended_classes($message)
{
    $environment_build_path = root() . 'TestRequirements/Fixtures/ProjectWithTests/builds/development';
    $stubs_directory = root() . 'TestRequirements/Stubs/ProjectWithTests';

    assert_true((
            file_exists(realpath($environment_build_path . '/Source/ExtendExamples/ParentAbstractClass.php'))
            && file_exists(realpath($environment_build_path . '/Source/ExtendExamples/AbstractClass.php'))
            && file_exists(realpath($environment_build_path . '/Source/ExtendExamples/ParentClass.php'))
            && file_exists(realpath($environment_build_path . '/Source/ExtendExamples/ChildClass.php'))
            && file_exists(realpath($environment_build_path . '/Source/ExtendExamples/ChildFromSource.php'))
            && file_get_contents(realpath($environment_build_path . '/Source/ExtendExamples/ParentAbstractClass.php')) === replace_build_vars(realpath($environment_build_path), realpath($stubs_directory . '/Source/ExtendExamples/ParentAbstractClass.stub'))
            && file_get_contents(realpath($environment_build_path . '/Source/ExtendExamples/AbstractClass.php')) === replace_build_vars(realpath($environment_build_path), realpath($stubs_directory . '/Source/ExtendExamples/AbstractClass.stub'))
            && file_get_contents(realpath($environment_build_path . '/Source/ExtendExamples/ParentClass.php')) === replace_build_vars(realpath($environment_build_path), realpath($stubs_directory . '/Source/ExtendExamples/ParentClass.stub'))
            && file_get_contents(realpath($environment_build_path . '/Source/ExtendExamples/ChildClass.php')) === replace_build_vars(realpath($environment_build_path), realpath($stubs_directory . '/Source/ExtendExamples/ChildClass.stub'))
            && file_get_contents(realpath($environment_build_path . '/Source/ExtendExamples/ChildFromSource.php')) === replace_build_vars(realpath($environment_build_path), realpath($stubs_directory . '/Source/ExtendExamples/ChildFromSource.stub'))
        ),
        $message
    );
}

function assert_build_for_interfaces($message)
{
    $environment_build_path = root() . 'TestRequirements/Fixtures/ProjectWithTests/builds/development';
    $stubs_directory = root() . 'TestRequirements/Stubs/ProjectWithTests';

    assert_true((
            file_exists(realpath($environment_build_path . '/Source/InterfaceExamples/InnerInterfaces/InnerInterface.php'))
            && file_exists(realpath($environment_build_path . '/Source/InterfaceExamples/InnerInterfaces/OtherInnerInterface.php'))
            && file_exists(realpath($environment_build_path . '/Source/InterfaceExamples/FirstInterface.php'))
            && file_exists(realpath($environment_build_path . '/Source/InterfaceExamples/MyClass.php'))
            && file_exists(realpath($environment_build_path . '/Source/InterfaceExamples/SecondInterface.php'))
            && file_exists(realpath($environment_build_path . '/Source/InterfaceExamples/ThirdInterface.php'))
            && file_get_contents(realpath($environment_build_path . '/Source/InterfaceExamples/InnerInterfaces/InnerInterface.php')) === replace_build_vars(realpath($environment_build_path), realpath($stubs_directory . '/Source/InterfaceExamples/InnerInterfaces/InnerInterface.stub'))
            && file_get_contents(realpath($environment_build_path . '/Source/InterfaceExamples/InnerInterfaces/OtherInnerInterface.php')) === replace_build_vars(realpath($environment_build_path), realpath($stubs_directory . '/Source/InterfaceExamples/InnerInterfaces/OtherInnerInterface.stub'))
            && file_get_contents(realpath($environment_build_path . '/Source/InterfaceExamples/FirstInterface.php')) === replace_build_vars(realpath($environment_build_path), realpath($stubs_directory . '/Source/InterfaceExamples/FirstInterface.stub'))
            && file_get_contents(realpath($environment_build_path . '/Source/InterfaceExamples/MyClass.php')) === replace_build_vars(realpath($environment_build_path), realpath($stubs_directory . '/Source/InterfaceExamples/MyClass.stub'))
            && file_get_contents(realpath($environment_build_path . '/Source/InterfaceExamples/SecondInterface.php')) === replace_build_vars(realpath($environment_build_path), realpath($stubs_directory . '/Source/InterfaceExamples/SecondInterface.stub'))
            && file_get_contents(realpath($environment_build_path . '/Source/InterfaceExamples/ThirdInterface.php')) === replace_build_vars(realpath($environment_build_path), realpath($stubs_directory . '/Source/InterfaceExamples/ThirdInterface.stub'))
        ),
        $message
    );
}

function assert_build_for_traits($message)
{
    $environment_build_path = root() . 'TestRequirements/Fixtures/ProjectWithTests/builds/development';
    $stubs_directory = root() . 'TestRequirements/Stubs/ProjectWithTests';

    assert_true((
            file_exists(realpath($environment_build_path . '/Source/UsedTraits/ClassWithTrait.php'))
            && file_exists(realpath($environment_build_path . '/Source/UsedTraits/FirstTrait.php'))
            && file_exists(realpath($environment_build_path . '/Source/UsedTraits/SecondTrait.php'))
            && file_exists(realpath($environment_build_path . '/Source/UsedTraits/ThirdTrait.php'))
            && file_get_contents(realpath($environment_build_path . '/Source/UsedTraits/ClassWithTrait.php')) === replace_build_vars(realpath($environment_build_path), realpath($stubs_directory . '/Source/UsedTraits/ClassWithTrait.stub'))
            && file_get_contents(realpath($environment_build_path . '/Source/UsedTraits/FirstTrait.php')) === replace_build_vars(realpath($environment_build_path), realpath($stubs_directory . '/Source/UsedTraits/FirstTrait.stub'))
            && file_get_contents(realpath($environment_build_path . '/Source/UsedTraits/SecondTrait.php')) === replace_build_vars(realpath($environment_build_path), realpath($stubs_directory . '/Source/UsedTraits/SecondTrait.stub'))
            && file_get_contents(realpath($environment_build_path . '/Source/UsedTraits/ThirdTrait.php')) === replace_build_vars(realpath($environment_build_path), realpath($stubs_directory . '/Source/UsedTraits/ThirdTrait.stub'))
        ),
        $message
    );
}

function assert_build_for_specified_file($message)
{
    $environment_build_path = root() . 'TestRequirements/Fixtures/ProjectWithTests/builds/development';
    $stubs_directory = root() . 'TestRequirements/Stubs/ProjectWithTests';

    assert_true((
            file_exists(realpath($environment_build_path . '/Source/ClassUsesHelper.php'))
            && file_get_contents(realpath($environment_build_path . '/Source/ClassUsesHelper.php')) === replace_build_vars(realpath($environment_build_path), realpath($stubs_directory . '/Source/ClassUsesHelper.stub'))
        ),
        $message
    );
}

function assert_build_for_compound_namespaces($message)
{
    $environment_build_path = root() . 'TestRequirements/Fixtures/ProjectWithTests/builds/development';
    $stubs_directory = root() . 'TestRequirements/Stubs/ProjectWithTests';

    assert_true((
            file_exists(realpath($environment_build_path . '/Source/CompoundNamespace/UseCompoundNamespace.php'))
            && file_get_contents(realpath($environment_build_path . '/Source/CompoundNamespace/UseCompoundNamespace.php')) === replace_build_vars(realpath($environment_build_path), realpath($stubs_directory . '/Source/CompoundNamespace/UseCompoundNamespace.stub'))
        ),
        $message
    );
}

function assert_symlinks_are_linked_properly($message)
{
    $link_file = root() . 'TestRequirements/Fixtures/ProjectWithTests/builds/development/PublicDirectory/Symlink';
    $link_source = root() . 'TestRequirements/Fixtures/SymlinkSource';

    assert_true((is_link(realpath($link_file)) && readlink(realpath($link_file)) === realpath($link_source)), $message);
}
