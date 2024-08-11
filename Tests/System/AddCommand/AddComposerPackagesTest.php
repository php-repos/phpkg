<?php

namespace Tests\System\AddComand\AddComposerPackagesTest;

use PhpRepos\FileManager\Directory;
use PhpRepos\FileManager\File;
use PhpRepos\FileManager\JsonFile;
use PhpRepos\FileManager\Path;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\TestRunner\Assertions\assert_true;
use function PhpRepos\TestRunner\Runner\test;
use function Tests\Helper\reset_empty_project;

test(
    title: 'it should add a composer package',
    case: function () {
         shell_exec('php ' . root() . 'phpkg add https://github.com/sebastianbergmann/phpunit.git --force --project=TestRequirements/Fixtures/EmptyProject');

        $project = Path::from_string(root() . '/TestRequirements/Fixtures/EmptyProject');
        assert_true(Directory\exists($project->append('Packages/sebastianbergmann/phpunit')), 'Composer package is not installed');
        assert_true(File\exists($project->append('Packages/sebastianbergmann/phpunit/phpkg.config.json')), 'Config file not generated while adding composer package');
        assert_true(Directory\exists($project->append('Packages/phar-io/manifest')), 'Composer sub package is not installed');
        assert_true(Directory\exists($project->append('Packages/phar-io/version')), 'Composer recursive sub package is not installed');
        assert_true(File\exists($project->append('Packages/phar-io/version/phpkg.config.json')), 'Config file not generated while adding composer package on sub package');

        $config = JsonFile\to_array($project->append('phpkg.config.json'));
        $meta = JsonFile\to_array($project->append('phpkg.config-lock.json'));

        assert_true(isset($config['packages']['https://github.com/sebastianbergmann/phpunit.git']), 'config is not correct');
        assert_true(isset($meta['packages']['https://github.com/phar-io/manifest.git']), 'meta is not correct');
        assert_true(isset($meta['packages']['https://github.com/phar-io/version.git']), 'meta for recursive sub package is not correct');
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=TestRequirements/Fixtures/EmptyProject');
    },
    after: function () {
        reset_empty_project();
    }
);
