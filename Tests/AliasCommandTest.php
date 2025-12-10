<?php

use PhpRepos\TestRunner\Assertions;
use Phpkg\InfrastructureStructure\Files;
use Tests\CliRunner;
use function PhpRepos\TestRunner\Runner\test;

test(
    title: 'it should successfully register a new alias',
    case: function (string $temp_dir) {
        // Register a new alias
        $alias = 'test-package';
        $package_url = 'https://github.com/php-repos/simple-package.git';
        
        $output = CliRunner\phpkg('alias', [$alias, $package_url, "--project=$temp_dir"]);
        Assertions\assert_true(str_contains($output, 'Alias registered successfully.'), 'Alias should be registered successfully. Output: ' . $output);
        
        // Verify the alias is saved to the config file
        $config_file = $temp_dir . '/phpkg.config.json';
        Assertions\assert_true(file_exists($config_file), 'Config file should exist');
        
        $config_data = Files\read_json_as_array($config_file);
        Assertions\assert_true(isset($config_data['aliases']), 'Config should have aliases section');
        Assertions\assert_true(isset($config_data['aliases'][$alias]), 'Alias should be found in config');
        Assertions\assert_true($config_data['aliases'][$alias] === $package_url, 'Alias should point to correct package URL');
    },
    before: function () {
        // Create a temporary directory for the test
        $temp_dir = sys_get_temp_dir() . '/' . uniqid('phpkg_alias_test');
        Files\make_directory_recursively($temp_dir);
        
        // Initialize a new project
        $init_output = CliRunner\phpkg('init', ["--project=$temp_dir"]);
        Assertions\assert_true(str_contains($init_output, 'Project initialized successfully'), 'Project should be initialized. Output: ' . $init_output);
        
        return $temp_dir;
    },
    after: function (string $temp_dir) {
        // Clean up: remove temp dir
        Files\force_delete_recursive($temp_dir);
    }
);

test(
    title: 'it should prevent duplicate aliases',
    case: function (string $temp_dir) {
        // First, register an alias
        $alias = 'test-package';
        $package_url1 = 'https://github.com/php-repos/simple-package.git';
        
        $output1 = CliRunner\phpkg('alias', [$alias, $package_url1, "--project=$temp_dir"]);
        Assertions\assert_true(str_contains($output1, 'Alias registered successfully.'), 'First alias should be registered successfully. Output: ' . $output1);
        
        // Try to register the same alias for a different package
        $package_url2 = 'https://github.com/php-repos/complex-package.git';
        $output2 = CliRunner\phpkg('alias', [$alias, $package_url2, "--project=$temp_dir"]);
        Assertions\assert_true(str_contains($output2, 'The alias has been registered for another package.'), 'Should fail to register duplicate alias. Output: ' . $output2);
        
        // Verify the original alias is still intact
        $config_file = $temp_dir . '/phpkg.config.json';
        $config_data = Files\read_json_as_array($config_file);
        Assertions\assert_true($config_data['aliases'][$alias] === $package_url1, 'Original alias should remain unchanged');
        Assertions\assert_true($config_data['aliases'][$alias] !== $package_url2, 'Alias should not point to new package URL');
    },
    before: function () {
        // Create a temporary directory for the test
        $temp_dir = sys_get_temp_dir() . '/' . uniqid('phpkg_alias_duplicate_test');
        Files\make_directory_recursively($temp_dir);
        
        // Initialize a new project
        $init_output = CliRunner\phpkg('init', ["--project=$temp_dir"]);
        Assertions\assert_true(str_contains($init_output, 'Project initialized successfully'), 'Project should be initialized. Output: ' . $init_output);
        
        return $temp_dir;
    },
    after: function (string $temp_dir) {
        // Clean up: remove temp dir
        Files\force_delete_recursive($temp_dir);
    }
);

test(
    title: 'it should reject invalid package URLs',
    case: function (string $temp_dir) {
        // Test with various invalid URLs
        $invalid_urls = [
            'not-a-url',
            'ftp://github.com/php-repos/simple-package.git',
            'git://github.com/php-repos/simple-package.git',
            '',
        ];
        
        foreach ($invalid_urls as $invalid_url) {
            $alias = 'test-' . uniqid();
            $output = CliRunner\phpkg('alias', [$alias, $invalid_url, "--project=$temp_dir"]);
            Assertions\assert_true(str_contains($output, 'The given package URL seems invalid.'), 'Should fail to register alias with invalid URL: ' . $invalid_url . '. Output: ' . $output);
        }
        
        // Verify no aliases were added
        $config_file = $temp_dir . '/phpkg.config.json';
        $config_data = Files\read_json_as_array($config_file);
        Assertions\assert_true(empty($config_data['aliases']), 'No aliases should be added for invalid URLs');
    },
    before: function () {
        // Create a temporary directory for the test
        $temp_dir = sys_get_temp_dir() . '/' . uniqid('phpkg_alias_invalid_url_test');
        Files\make_directory_recursively($temp_dir);
        
        // Initialize a new project
        $init_output = CliRunner\phpkg('init', ["--project=$temp_dir"]);
        Assertions\assert_true(str_contains($init_output, 'Project initialized successfully'), 'Project should be initialized. Output: ' . $init_output);
        
        return $temp_dir;
    },
    after: function (string $temp_dir) {
        // Clean up: remove temp dir
        Files\force_delete_recursive($temp_dir);
    }
);

test(
    title: 'it should handle project path options correctly',
    case: function (string $temp_dir) {
        // Test with absolute project path
        $alias1 = 'test-package-1';
        $package_url1 = 'https://github.com/php-repos/simple-package.git';
        
        $output1 = CliRunner\phpkg('alias', [$alias1, $package_url1, "--project=$temp_dir"]);
        Assertions\assert_true(str_contains($output1, 'Alias registered successfully.'), 'Alias should be registered with absolute project path. Output: ' . $output1);
        
        // Test with relative project path (from parent directory)
        $parent_dir = dirname($temp_dir);
        $relative_path = basename($temp_dir);
        
        $alias2 = 'test-package-2';
        $package_url2 = 'https://github.com/php-repos/complex-package.git';
        
        // Change to parent directory and use relative path
        $original_cwd = getcwd();
        chdir($parent_dir);
        
        $output2 = CliRunner\phpkg('alias', [$alias2, $package_url2, "--project=$relative_path"]);
        Assertions\assert_true(str_contains($output2, 'Alias registered successfully.'), 'Alias should be registered with relative project path. Output: ' . $output2);
        
        // Return to original directory
        chdir($original_cwd);
        
        // Verify both aliases are in the same config file
        $config_file = $temp_dir . '/phpkg.config.json';
        $config_data = Files\read_json_as_array($config_file);
        Assertions\assert_true(isset($config_data['aliases'][$alias1]), 'First alias should be found in config');
        Assertions\assert_true(isset($config_data['aliases'][$alias2]), 'Second alias should be found in config');
        Assertions\assert_true($config_data['aliases'][$alias1] === $package_url1, 'First alias should point to correct package URL');
        Assertions\assert_true($config_data['aliases'][$alias2] === $package_url2, 'Second alias should point to correct package URL');
    },
    before: function () {
        // Create a temporary directory for the test
        $temp_dir = sys_get_temp_dir() . '/' . uniqid('phpkg_alias_path_test');
        Files\make_directory_recursively($temp_dir);
        
        // Initialize a new project
        $init_output = CliRunner\phpkg('init', ["--project=$temp_dir"]);
        Assertions\assert_true(str_contains($init_output, 'Project initialized successfully'), 'Project should be initialized. Output: ' . $init_output);
        
        return $temp_dir;
    },
    after: function (string $temp_dir) {
        // Clean up: remove temp dir
        Files\force_delete_recursive($temp_dir);
    }
);
