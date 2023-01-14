<?php

namespace Tests\System\InitCommand\InitCommandTest;

use function PhpRepos\FileManager\Directory\clean;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\FileManager\Resolver\realpath;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_true;
use function PhpRepos\TestRunner\Runner\test;

$initial_content = <<<EOD
{
    "map": [],
    "entry-points": [],
    "excludes": [],
    "executables": [],
    "packages-directory": "Packages",
    "packages": []
}

EOD;

$initial_content_with_packages_directory = <<<EOD
{
    "map": [],
    "entry-points": [],
    "excludes": [],
    "executables": [],
    "packages-directory": "vendor",
    "packages": []
}

EOD;

$meta_content = <<<EOD
{
    "packages": []
}

EOD;


test(
    title: 'it makes a new default config file',
    case: function () use ($initial_content, $meta_content) {
        $packages_directory = root() . 'TestRequirements/Fixtures/EmptyProject/Packages';
        $config_path = root() . 'TestRequirements/Fixtures/EmptyProject/phpkg.config.json';
        $meta_file_path = root() . 'TestRequirements/Fixtures/EmptyProject/phpkg.config-lock.json';

        $output = shell_exec('php ' . root() . 'phpkg init --project=TestRequirements/Fixtures/EmptyProject');

        assert_true(file_exists($config_path), 'Config file does not exists: ' . $output);
        assert_true(file_exists($packages_directory), 'Packages directory is not created: ' . $output);
        assert_true(file_get_contents($config_path) === $initial_content, 'Config file content is not correct after running init!');
        assert_true(file_get_contents($meta_file_path) === $meta_content, 'Lock file content is not correct after running init!');
        $expected = <<<EOD
\e[39mInit project...
\e[92mProject has been initialized.\e[39m

EOD;
        assert_true($expected === $output, 'Output is not correct:' . PHP_EOL . $expected . PHP_EOL . $output);
    },
    after: function () {
        clean(realpath(root() . 'TestRequirements/Fixtures/EmptyProject'));
    }
);

test(
    title: 'it makes a new config file with given packages directory',
    case: function () use ($initial_content_with_packages_directory) {
        $config_path = root() . 'TestRequirements/Fixtures/EmptyProject/phpkg.config.json';
        $meta_file_path = root() . 'TestRequirements/Fixtures/EmptyProject/phpkg.config-lock.json';
        $packages_directory = root() . 'TestRequirements/Fixtures/EmptyProject/vendor';

        $output = shell_exec('php ' . root() . 'phpkg init --project=TestRequirements/Fixtures/EmptyProject --packages-directory=vendor');

        assert_true(file_exists($packages_directory), 'packages directory has not been created: ' . $output);
        assert_true(file_exists($config_path), 'Config file does not exists: ' . $output);
        assert_true(file_exists($meta_file_path), 'Config lock file does not exists: ' . $output);
        assert_true(file_get_contents($config_path) === $initial_content_with_packages_directory, 'Config file content is not correct after running init!');
    },
    after: function () {
        clean(realpath(root() . 'TestRequirements/Fixtures/EmptyProject'));
    }
);
