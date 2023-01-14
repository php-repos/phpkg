<?php

namespace Tests\System\MigrateCommand\MigrateCommandWithoutProjectTest;

use PhpRepos\Cli\IO\Write;
use function PhpRepos\FileManager\Directory\delete_recursive;
use function PhpRepos\FileManager\Resolver\realpath;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\TestRunner\Runner\test;

test(
    title: 'it should show proper message when there is no composer.json file',
    case: function ($project) {
        $output = shell_exec('php ' . root() . 'phpkg migrate --project=TestRequirements/' . $project);

        Write\assert_error('There is no composer.json file in this project!', $output);

        return $project;
    },
    before: function () {
        $project = 'EmptyComposerProject';

        mkdir(realpath(root() . 'TestRequirements/' . $project));

        return $project;
    },
    after: function ($project) {
        delete_recursive(realpath(root() . 'TestRequirements/' . $project));
    },
);

test(
    title: 'it should show proper message when there is no composer.lock file',
    case: function ($project) {
        $output = shell_exec('php ' . root() . 'phpkg migrate --project=TestRequirements/' . $project);

        Write\assert_error('There is no composer.lock file in this project!', $output);

        return $project;
    },
    before: function () {
        $project = 'EmptyComposerProject';

        mkdir(realpath(root() . 'TestRequirements/' . $project));
        copy(
            realpath(root() . 'TestRequirements/Fixtures/composer-package/composer.json'),
            realpath(root() . 'TestRequirements/EmptyComposerProject/composer.json')
        );

        return $project;
    },
    after: function ($project) {
        delete_recursive(realpath(root() . 'TestRequirements/' . $project));
    },
);
