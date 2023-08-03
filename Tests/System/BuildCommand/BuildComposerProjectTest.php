<?php

namespace Tests\System\BuildCommand\BuildComposerProjectTest;

use PhpRepos\FileManager\Path;
use function PhpRepos\FileManager\Directory\make_recursive;
use function PhpRepos\FileManager\File\create;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_true;
use function PhpRepos\TestRunner\Runner\test;
use function Tests\Helper\reset_empty_project;

test(
    title: 'it should build a composer project',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg build --project=TestRequirements/Fixtures/EmptyProject');

        assert_true(str_contains($output, 'Build finished successfully.'));

        assert_true(
            file_exists(root() . '/TestRequirements/Fixtures/EmptyProject/builds/development/vendor/composer/package/file.php'),
            'Build for vendor directory failed.'
        );

        assert_true(
            file_exists(root() . '/TestRequirements/Fixtures/EmptyProject/builds/development/vendor/bin/file'),
            'Build for vendor/bin directory failed.'
        );

        assert_true(
            file_exists(root() . '/TestRequirements/Fixtures/EmptyProject/builds/development/vendor/autoload.php'),
            'Build for autoload file failed.'
        );
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=TestRequirements/Fixtures/EmptyProject --packages-directory=vendor');
        $project_directory = Path::from_string(root() . '/TestRequirements/Fixtures/EmptyProject');
        // Simulate a package
        make_recursive($project_directory->append('vendor/composer/package'));
        $file = $project_directory->append('vendor/composer/package/file.php');
        create($file, '');

        // Simulate the bin directory
        make_recursive($project_directory->append('vendor/bin'));
        $file = $project_directory->append('vendor/bin/file');
        create($file, '');

        // Simulate the composer autoload file
        $file = $project_directory->append('vendor/autoload.php');
        create($file, '');
    },
    after: function () {
        reset_empty_project();
    }
);
