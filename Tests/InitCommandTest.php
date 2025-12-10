<?php

namespace Tests\InitCommandTest;

use Phpkg\InfrastructureStructure\Files;
use PhpRepos\Cli\Output;
use PhpRepos\Datatype\Arr;
use Tests\CliRunner;
use PhpRepos\TestRunner\Assertions;
use function PhpRepos\TestRunner\Runner\test;

test(
    title: 'it should init a project',
    case: function (string $temp_dir) {
        $output = CliRunner\phpkg('init', [
            "--project=$temp_dir",
        ]);

        $expected = Output\capture(function () {
            Output\line('Init project...');
            Output\success('✅ Project initialized successfully.');
        });

        Output\assert_output($expected, $output);

        Arr\assert_equal([
            'map' => [],
            'autoloads' => [],
            'excludes' => [],
            'entry-points' => [],
            'executables' => [],
            'packages-directory' => 'Packages',
            'import-file' => 'phpkg.imports.php',
            'packages' => [],
            'aliases' => [],
        ], Files\read_json_as_array($temp_dir . '/phpkg.config.json'));
        Arr\assert_equal(['packages' => []], Files\read_json_as_array($temp_dir . '/phpkg.config-lock.json'));
    },
    before: function () {
        $temp_dir = sys_get_temp_dir() . '/' . uniqid('phpkg_init_test');
        Files\make_directory_recursively($temp_dir);
        return $temp_dir;
    },
    after: function (string $temp_dir) {
        Files\force_delete_recursive($temp_dir);
    }
);

test(
    title: 'it should create project directory when it does not exist',
    case: function (string $parent_dir) {
        $non_existent_dir = $parent_dir . '/non_existent_project';
        
        // Verify the directory doesn't exist initially
        Assertions\assert_true(!is_dir($non_existent_dir), 'Directory should not exist before init');
        
        $output = CliRunner\phpkg('init', [
            "--project=$non_existent_dir",
        ]);

        $expected = Output\capture(function () {
            Output\line('Init project...');
            Output\success('✅ Project initialized successfully.');
        });

        Output\assert_output($expected, $output);
        
        // Verify the directory was created
        Assertions\assert_true(is_dir($non_existent_dir), 'Directory should be created by init command');
        
        // Verify the project files were created
        Assertions\assert_true(file_exists($non_existent_dir . '/phpkg.config.json'), 'phpkg.config.json should be created');
        Assertions\assert_true(file_exists($non_existent_dir . '/phpkg.config-lock.json'), 'phpkg.config-lock.json should be created');
        
        // Verify the config content
        Arr\assert_equal([
            'map' => [],
            'autoloads' => [],
            'excludes' => [],
            'entry-points' => [],
            'executables' => [],
            'packages-directory' => 'Packages',
            'import-file' => 'phpkg.imports.php',
            'packages' => [],
            'aliases' => [],
        ], Files\read_json_as_array($non_existent_dir . '/phpkg.config.json'));
        
        Arr\assert_equal(['packages' => []], Files\read_json_as_array($non_existent_dir . '/phpkg.config-lock.json'));
        
        return $non_existent_dir;
    },
    before: function () {
        $temp_dir = sys_get_temp_dir() . '/' . uniqid('phpkg_init_create_dir_test');
        Files\make_directory_recursively($temp_dir);
        return $temp_dir;
    },
    after: function (string $non_existent_dir) {
        Files\force_delete_recursive($non_existent_dir);
    }
);

test(
    title: 'it should initialize project with custom packages directory',
    case: function (string $temp_dir) {
        $output = CliRunner\phpkg('init', [
            "--project=$temp_dir",
            "--packages-directory=CustomPackages"
        ]);

        $expected = Output\capture(function () {
            Output\line('Init project...');
            Output\success('✅ Project initialized successfully.');
        });

        Output\assert_output($expected, $output);
        
        // Verify custom packages directory is set in config
        $config = Files\read_json_as_array($temp_dir . '/phpkg.config.json');
        Assertions\assert_true($config['packages-directory'] === 'CustomPackages', 'Custom packages directory should be set correctly');
        
        // Verify other config values remain correct
        Arr\assert_equal([
            'map' => [],
            'autoloads' => [],
            'excludes' => [],
            'entry-points' => [],
            'executables' => [],
            'packages-directory' => 'CustomPackages',
            'import-file' => 'phpkg.imports.php',
            'packages' => [],
            'aliases' => [],
        ], $config);
    },
    before: function () {
        $temp_dir = sys_get_temp_dir() . '/' . uniqid('phpkg_init_custom_packages_test');
        Files\make_directory_recursively($temp_dir);
        return $temp_dir;
    },
    after: function (string $temp_dir) {
        Files\force_delete_recursive($temp_dir);
    }
);

test(
    title: 'it should handle absolute paths correctly',
    case: function () {
        $absolute_path = '/tmp/phpkg_absolute_test_' . uniqid();
        
        $output = CliRunner\phpkg('init', [
            "--project=$absolute_path",
        ]);

        $expected = Output\capture(function () {
            Output\line('Init project...');
            Output\success('✅ Project initialized successfully.');
        });

        Output\assert_output($expected, $output);
        
        // Verify the absolute path was created
        Assertions\assert_true(is_dir($absolute_path), 'Absolute path should be created');
        
        // Verify the project files were created
        Assertions\assert_true(file_exists($absolute_path . '/phpkg.config.json'), 'phpkg.config.json should be created');
        Assertions\assert_true(file_exists($absolute_path . '/phpkg.config-lock.json'), 'phpkg.config-lock.json should be created');
        
        // Clean up
        Files\force_delete_recursive($absolute_path);
    }
);

test(
    title: 'it should not reinitialize an existing project',
    case: function (string $temp_dir) {
        // Initialize project first time
        $first_output = CliRunner\phpkg('init', ["--project=$temp_dir"]);
        
        $expected = Output\capture(function () {
            Output\line('Init project...');
            Output\success('✅ Project initialized successfully.');
        });
        Output\assert_output($expected, $first_output);
        
        // Try to initialize again
        $second_output = CliRunner\phpkg('init', ["--project=$temp_dir"]);
        
        Assertions\assert_true(str_contains($second_output, 'Failed to initialize project'), 'Should prevent re-initialization');
    },
    before: function () {
        $temp_dir = sys_get_temp_dir() . '/' . uniqid('phpkg_init_reinit_test');
        Files\make_directory_recursively($temp_dir);
        return $temp_dir;
    },
    after: function (string $temp_dir) {
        Files\force_delete_recursive($temp_dir);
    }
);

test(
    title: 'it should handle invalid paths gracefully',
    case: function () {
        $invalid_path = '/root/invalid/path';
        
        $output = CliRunner\phpkg('init', [
            "--project=$invalid_path",
        ]);
        
        Assertions\assert_true(str_contains($output, 'is not writable'), 'Should show proper permission error message:' . $output);
    }
);

test(
    title: 'it should initialize project in current directory when no path specified',
    case: function (string $temp_dir) {
        // Change to temp directory first
        $original_dir = getcwd();
        chdir($temp_dir);
        
        $output = CliRunner\phpkg('init');
        
        $expected = Output\capture(function () {
            Output\line('Init project...');
            Output\success('✅ Project initialized successfully.');
        });
        
        Output\assert_output($expected, $output);
        
        // Verify files were created in current directory
        Assertions\assert_true(file_exists('phpkg.config.json'), 'Should create config in current directory');
        Assertions\assert_true(file_exists('phpkg.config-lock.json'), 'Should create lock file in current directory');
        
        // Change back to original directory
        chdir($original_dir);
        
        // Clean up
        unlink($temp_dir . '/phpkg.config.json');
        unlink($temp_dir . '/phpkg.config-lock.json');
    },
    before: function () {
        $temp_dir = sys_get_temp_dir() . '/' . uniqid('phpkg_init_current_dir_test');
        Files\make_directory_recursively($temp_dir);
        return $temp_dir;
    },
    after: function (string $temp_dir) {
        Files\force_delete_recursive($temp_dir);
    }
);

test(
    title: 'it should create valid config files with correct structure',
    case: function (string $temp_dir) {
        CliRunner\phpkg('init', ["--project=$temp_dir"]);
        
        $config = Files\read_json_as_array($temp_dir . '/phpkg.config.json');
        $lock = Files\read_json_as_array($temp_dir . '/phpkg.config-lock.json');
        
        // Verify all required keys exist in config
        $required_keys = ['map', 'autoloads', 'excludes', 'entry-points', 'executables', 'packages-directory', 'import-file', 'packages', 'aliases'];
        foreach ($required_keys as $key) {
            Assertions\assert_true(array_key_exists($key, $config), "Config should contain key: $key");
        }
        
        // Verify lock file structure
        Assertions\assert_true(array_key_exists('packages', $lock), 'Lock file should contain packages key');
        
        // Verify default values
        Arr\assert_equal([], $config['map'], 'Map should be empty array');
        Arr\assert_equal([], $config['autoloads'], 'Autoloads should be empty array');
        Arr\assert_equal([], $config['excludes'], 'Excludes should be empty array');
        Arr\assert_equal([], $config['entry-points'], 'Entry points should be empty array');
        Arr\assert_equal([], $config['executables'], 'Executables should be empty array');
        Assertions\assert_true($config['packages-directory'] === 'Packages', 'Default packages directory should be Packages');
        Assertions\assert_true($config['import-file'] === 'phpkg.imports.php', 'Default import file should be phpkg.imports.php');
        Arr\assert_equal([], $config['packages'], 'Packages should be empty array');
        Arr\assert_equal([], $config['aliases'], 'Aliases should be empty array');
        
        // Verify lock file content
        Arr\assert_equal(['packages' => []], $lock, 'Lock file should contain empty packages array');
    },
    before: function () {
        $temp_dir = sys_get_temp_dir() . '/' . uniqid('phpkg_init_config_structure_test');
        Files\make_directory_recursively($temp_dir);
        return $temp_dir;
    },
    after: function (string $temp_dir) {
        Files\force_delete_recursive($temp_dir);
    }
);

test(
    title: 'it should handle relative paths correctly',
    case: function (string $temp_dir) {
        $relative_path = 'relative_project';
        $full_path = $temp_dir . '/' . $relative_path;
        
        // Change to parent directory to test relative path resolution
        $original_dir = getcwd();
        chdir($temp_dir);
        
        $output = CliRunner\phpkg('init', [
            "--project=$relative_path",
        ]);

        $expected = Output\capture(function () {
            Output\line('Init project...');
            Output\success('✅ Project initialized successfully.');
        });

        Output\assert_output($expected, $output);
        
        // Verify the relative path was created
        Assertions\assert_true(is_dir($relative_path), 'Relative path should be created');
        Assertions\assert_true(is_dir($full_path), 'Full path should exist');
        
        // Verify the project files were created
        Assertions\assert_true(file_exists($relative_path . '/phpkg.config.json'), 'phpkg.config.json should be created in relative path');
        Assertions\assert_true(file_exists($relative_path . '/phpkg.config-lock.json'), 'phpkg.config-lock.json should be created in relative path');
        
        // Verify config content
        $config = Files\read_json_as_array($relative_path . '/phpkg.config.json');
        Arr\assert_equal([
            'map' => [],
            'autoloads' => [],
            'excludes' => [],
            'entry-points' => [],
            'executables' => [],
            'packages-directory' => 'Packages',
            'import-file' => 'phpkg.imports.php',
            'packages' => [],
            'aliases' => [],
        ], $config);
        
        // Change back to original directory
        chdir($original_dir);
        
        // Clean up
        Files\force_delete_recursive($full_path);
    },
    before: function () {
        $temp_dir = sys_get_temp_dir() . '/' . uniqid('phpkg_init_relative_path_test');
        Files\make_directory_recursively($temp_dir);
        return $temp_dir;
    },
    after: function (string $temp_dir) {
        // Cleanup is handled in the test case
    }
);
