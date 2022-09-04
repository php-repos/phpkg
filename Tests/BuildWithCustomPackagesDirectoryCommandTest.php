<?php

namespace Tests\BuildWithCustomPackageDirectoryTest;

test(
    title: 'it should build the project with custom packages directory',
    case: function () {
        shell_exec("{$_SERVER['PWD']}/saeghe --command=initialize --project=TestRequirements/Fixtures/ProjectWithTests --packages-directory=vendor");
        shell_exec($_SERVER['PWD'] . '/saeghe --command=add --project=TestRequirements/Fixtures/ProjectWithTests --path=git@github.com:saeghe/simple-package.git');

        $output = shell_exec($_SERVER['PWD'] . '/saeghe --command=build --project=TestRequirements/Fixtures/ProjectWithTests');

        assertFileWithPackageDependencyHasBeenBuilt('File with package dependency has not been built properly!' . $output);
    },
    before: function () {
        deleteBuildDirectory();
        deletePackagesDirectory();
        copy($_SERVER['PWD'] . '/TestRequirements/Stubs/ProjectWithTests/build-with-custom-packages-directory.json', $_SERVER['PWD'] . '/TestRequirements/Fixtures/ProjectWithTests/build.json');
    },
    after: function () {
        deleteBuildDirectory();
        deletePackagesDirectory();
        shell_exec('rm -f ' . $_SERVER['PWD'] . '/TestRequirements/Fixtures/ProjectWithTests/build.json');
        shell_exec('rm -f ' . $_SERVER['PWD'] . '/TestRequirements/Fixtures/ProjectWithTests/build.lock');
    }
);

function deleteBuildDirectory()
{
    shell_exec('rm -fR ' . $_SERVER['PWD'] . '/TestRequirements/Fixtures/ProjectWithTests/builds');
}

function deletePackagesDirectory()
{
    shell_exec('rm -fR ' . $_SERVER['PWD'] . '/TestRequirements/Fixtures/ProjectWithTests/vendor');
}

function assertFileWithPackageDependencyHasBeenBuilt($message)
{
    $environmentBuildPath = $_SERVER['PWD'] . '/TestRequirements/Fixtures/ProjectWithTests/builds/development';
    $stubsDirectory = $_SERVER['PWD'] . '/TestRequirements/Stubs/ProjectWithTests';

    assert(
        file_exists($environmentBuildPath . '/Source/FileUsingVendor.php')
        && file_get_contents($environmentBuildPath . '/Source/FileUsingVendor.php') === str_replace('$environmentBuildPath', $environmentBuildPath, file_get_contents($stubsDirectory . '/Source/FileUsingVendor.stub')),
        $message
    );
}
