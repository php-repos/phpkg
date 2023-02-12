<?php

namespace Tests\System\MigrateCommand\MigratePreRequirementsTest;

use PhpRepos\FileManager\Path;
use function PhpRepos\Cli\IO\Write\assert_error;
use function PhpRepos\FileManager\File\create;
use function PhpRepos\FileManager\File\delete;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\TestRunner\Runner\test;

test(
    title: 'it should show error message when there is no composer file',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg migrate --project=TestRequirements/Fixtures/EmptyProject');

        assert_error('There is no composer.json file.', $output);
    }
);

test(
    title: 'it should show error message when there is no composer lock file',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg migrate --project=TestRequirements/Fixtures/EmptyProject');

        assert_error('There is no composer.lock file.', $output);
    },
    before: function () {
        create(Path::from_string(root() . '/TestRequirements/Fixtures/EmptyProject/composer.json'), '');
    },
    after: function () {
        delete(Path::from_string(root() . '/TestRequirements/Fixtures/EmptyProject/composer.json'));
    }
);
