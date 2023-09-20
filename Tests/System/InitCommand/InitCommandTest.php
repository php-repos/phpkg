<?php

namespace Tests\System\InitCommand\InitCommandTest;

use PhpRepos\FileManager\JsonFile;
use function PhpRepos\Cli\Output\assert_line;
use function PhpRepos\Cli\Output\assert_success;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_true;
use function PhpRepos\TestRunner\Runner\test;
use function Tests\Helper\reset_empty_project;

$initial_content = JsonFile\to_array(__DIR__ . DIRECTORY_SEPARATOR . 'initial-config.json');
$initial_content_with_packages_directory = JsonFile\to_array(__DIR__ . DIRECTORY_SEPARATOR . 'initial-with-custom-packages.config.json');
$meta_content = JsonFile\to_array(__DIR__ . DIRECTORY_SEPARATOR . 'initial-meta.json');

test(
    title: 'it makes a new default config file',
    case: function () use ($initial_content, $meta_content) {
        $packages_directory = root() . 'TestRequirements/Fixtures/EmptyProject/Packages';
        $config_path = root() . 'TestRequirements/Fixtures/EmptyProject/phpkg.config.json';
        $meta_file_path = root() . 'TestRequirements/Fixtures/EmptyProject/phpkg.config-lock.json';

        $output = shell_exec('php ' . root() . 'phpkg init --project=TestRequirements/Fixtures/EmptyProject');

        assert_true(file_exists($config_path), 'Config file does not exists: ' . $output);
        assert_true(file_exists($packages_directory), 'Packages directory is not created: ' . $output);
        assert_true(JsonFile\to_array($config_path) === $initial_content, 'Config file content is not correct after running init!');
        assert_true(JsonFile\to_array($meta_file_path) === $meta_content, 'Lock file content is not correct after running init!');

        $lines = explode("\n", trim($output));

        assert_true(2 === count($lines), 'Number of output lines do not match' . $output);
        assert_line("Init project...", $lines[0] . PHP_EOL);
        assert_success("Project has been initialized.", $lines[1] . PHP_EOL);
    },
    after: function () {
        reset_empty_project();
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
        assert_true(JsonFile\to_array($config_path) === $initial_content_with_packages_directory, 'Config file content is not correct after running init!');
    },
    after: function () {
        reset_empty_project();
    }
);
