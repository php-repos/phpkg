<?php

namespace Tests\System\BuildCommand\BuildComposerProjectTest;

use PhpRepos\FileManager\Filesystem\Directory;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_true;
use function PhpRepos\TestRunner\Runner\test;

test(
    title: 'it should build a composer project',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg build --project=TestRequirements/Fixtures/EmptyProject');

        assert_true(str_contains($output, 'Build finished successfully.'));

        assert_true(
            file_exists(root() . '/TestRequirements/Fixtures/EmptyProject/builds/development/vendor/composer/package/file.php'),
            'Build for vendor directory failed.'
        );
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=TestRequirements/Fixtures/EmptyProject --packages-directory=vendor');
        $project_directory = Directory::from_string(root() . '/TestRequirements/Fixtures/EmptyProject');
        $project_directory->subdirectory('vendor/composer/package')->make_recursive();
        $file = $project_directory->file('vendor/composer/package/file.php');
        $file->create("");
    },
    after: function () {
        Directory::from_string(root() . 'TestRequirements/Fixtures/EmptyProject')->clean();
    }
);
