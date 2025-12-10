<?php

namespace Tests\UpdateCommandTest;

use Phpkg\InfrastructureStructure\Files;
use Tests\CliRunner;
use PhpRepos\TestRunner\Assertions;
use function PhpRepos\TestRunner\Runner\test;

test(
    title: 'it should successfully update a package to a minor version',
    case: function (string $temp_dir) {
        // Update datatype from v1.0.0 to v1.1.0
        $update_output = CliRunner\phpkg('update', ['https://github.com/php-repos/datatype.git', '--version=v1.1.0', "--project=$temp_dir"]);
        
        // Should show success message
        Assertions\assert_true(str_contains($update_output, 'Package updated successfully.'), 'Should show success message. Output: ' . $update_output);
        
        // Verify the package was updated in the lock file
        $lock_file = $temp_dir . '/phpkg.config-lock.json';
        Assertions\assert_true(file_exists($lock_file), 'Lock file should exist');
        
        $lock_data = Files\read_json_as_array($lock_file);
        Assertions\assert_true(isset($lock_data['packages']), 'Lock file should have packages section');
        
        // Find the datatype package in the packages
        $package_key = 'https://github.com/php-repos/datatype.git';
        Assertions\assert_true(isset($lock_data['packages'][$package_key]), 'Datatype package should be found in lock file');
        
        $package_info = $lock_data['packages'][$package_key];
        Assertions\assert_true($package_info['version'] === 'v1.1.0', 'Package version should be updated to v1.1.0');
        Assertions\assert_true($package_info['hash'] === '3b068db2d678d9a2eb803951cc602ad6a09fbee9', 'Commit hash should match v1.1.0');
        
        // Verify the project config was also updated
        $config_file = $temp_dir . '/phpkg.config.json';
        Assertions\assert_true(file_exists($config_file), 'Config file should exist');
        
        $config_data = Files\read_json_as_array($config_file);
        Assertions\assert_true(isset($config_data['packages'][$package_key]), 'Config should contain updated package');
        Assertions\assert_true($config_data['packages'][$package_key] === 'v1.1.0', 'Config should show updated version');
    },
    before: function () {
        // Create a temporary directory for the test
        $temp_dir = sys_get_temp_dir() . '/' . uniqid('phpkg_update_test');
        Files\make_directory_recursively($temp_dir);
        
        // Initialize a new project
        $init_output = CliRunner\phpkg('init', ["--project=$temp_dir"]);
        Assertions\assert_true(str_contains($init_output, 'Project initialized successfully'), 'Project should be initialized. Output: ' . $init_output);
        
        // Add datatype v1.0.0 first
        $add_output = CliRunner\phpkg('add', ['https://github.com/php-repos/datatype.git', '--version=v1.0.0', "--project=$temp_dir"]);
        Assertions\assert_true(str_contains($add_output, 'Package added successfully.'), 'Datatype v1.0.0 should be added. Output: ' . $add_output);
        
        return $temp_dir;
    },
    after: function (string $temp_dir) {
        // Clean up: remove temp dir
        Files\force_delete_recursive($temp_dir);
    }
);

test(
    title: 'it should successfully update main major upgrade',
    case: function (string $temp_dir) {
        // Update datatype from v1.0.0 to v4.0.0
        $update_output = CliRunner\phpkg('update', ['https://github.com/php-repos/datatype.git', '--version=v4.0.0', "--project=$temp_dir"]);
        
        // Should show success message
        Assertions\assert_true(str_contains($update_output, 'Package updated successfully.'), 'Should show success message. Output: ' . $update_output);
        
        // Verify the package was updated in the lock file
        $lock_file = $temp_dir . '/phpkg.config-lock.json';
        Assertions\assert_true(file_exists($lock_file), 'Lock file should exist');
        
        $lock_data = Files\read_json_as_array($lock_file);
        Assertions\assert_true(isset($lock_data['packages']), 'Lock file should have packages section');
        // Find the datatype package in the packages
        $package_key = 'git@github.com:php-repos/datatype.git';
        Assertions\assert_true(isset($lock_data['packages'][$package_key]), 'Datatype package should be found in lock file');
        
        $package_info = $lock_data['packages'][$package_key];
        Assertions\assert_true($package_info['version'] === 'v4.0.0', 'Package version should be updated to v4.0.0');
        Assertions\assert_true($package_info['hash'] === 'd29dc1c67d83217b20768a74a973f1b5fc4513ea', 'Commit hash should match v4.0.0');
        
        // Verify the project config was also updated
        $config_file = $temp_dir . '/phpkg.config.json';
        Assertions\assert_true(file_exists($config_file), 'Config file should exist');
        
        $config_data = Files\read_json_as_array($config_file);
        $package_key = 'https://github.com/php-repos/datatype.git';
        Assertions\assert_true(isset($config_data['packages'][$package_key]), 'Config should contain updated package');
        Assertions\assert_true($config_data['packages'][$package_key] === 'v4.0.0', 'Config should show updated version');
    },
    before: function () {
        // Create a temporary directory for the test
        $temp_dir = sys_get_temp_dir() . '/' . uniqid('phpkg_update_major_test');
        Files\make_directory_recursively($temp_dir);
        
        // Initialize a new project
        $init_output = CliRunner\phpkg('init', ["--project=$temp_dir"]);
        Assertions\assert_true(str_contains($init_output, 'Project initialized successfully'), 'Project should be initialized. Output: ' . $init_output);
        
        // Add datatype v1.0.0 first
        $add_output = CliRunner\phpkg('add', ['https://github.com/php-repos/datatype.git', '--version=v1.0.0', "--project=$temp_dir"]);
        Assertions\assert_true(str_contains($add_output, 'Package added successfully.'), 'Datatype v1.0.0 should be added. Output: ' . $add_output);
        
        return $temp_dir;
    },
    after: function (string $temp_dir) {
        // Clean up: remove temp dir
        Files\force_delete_recursive($temp_dir);
    }
);

test(
    title: 'it should reject major version update without force option',
    case: function (string $temp_dir) {
        // Add datatype v1.0.0 first
        $add_datatype_output = CliRunner\phpkg('add', ['https://github.com/php-repos/datatype.git', '--version=v1.0.0', "--project=$temp_dir"]);
        Assertions\assert_true(str_contains($add_datatype_output, 'Package added successfully.'), 'Datatype v1.0.0 should be added. Output: ' . $add_datatype_output);
        
        // Add file-manager v1.0.0
        $add_filemanager_output = CliRunner\phpkg('add', ['https://github.com/php-repos/file-manager.git', '--version=v1.0.0', "--project=$temp_dir"]);
        Assertions\assert_true(str_contains($add_filemanager_output, 'Package added successfully.'), 'File-manager v1.0.0 should be added. Output: ' . $add_filemanager_output);
        
        // Try to update datatype to v1.1.0 (this should trigger file-manager to update to v2.0.0)
        $update_output = CliRunner\phpkg('update', ['https://github.com/php-repos/datatype.git', '--version=v1.1.0', "--project=$temp_dir"]);
        
        // Should show error about version incompatibility
        Assertions\assert_true(str_contains($update_output, 'version incompatibility') || str_contains($update_output, 'incompatible') || str_contains($update_output, 'failed'), 'Should show version incompatibility error. Output: ' . $update_output);
        
        // Verify the packages were NOT updated in the lock file
        $lock_file = $temp_dir . '/phpkg.config-lock.json';
        Assertions\assert_true(file_exists($lock_file), 'Lock file should exist');
        
        $lock_data = Files\read_json_as_array($lock_file);
        Assertions\assert_true(isset($lock_data['packages']), 'Lock file should have packages section');
        
        // Check datatype package remains unchanged
        $datatype_key = 'https://github.com/php-repos/datatype.git';
        Assertions\assert_true(isset($lock_data['packages'][$datatype_key]), 'Datatype package should be found in lock file');
        $datatype_info = $lock_data['packages'][$datatype_key];
        Assertions\assert_true($datatype_info['version'] === 'v1.0.0', 'Datatype package version should remain v1.0.0');
        
        // Check file-manager package remains unchanged
        $filemanager_key = 'https://github.com/php-repos/file-manager.git';
        Assertions\assert_true(isset($lock_data['packages'][$filemanager_key]), 'File-manager package should be found in lock file');
        $filemanager_info = $lock_data['packages'][$filemanager_key];
        Assertions\assert_true($filemanager_info['version'] === 'v1.0.0', 'File-manager package version should remain v1.0.0');
        
        // Verify the project config was also NOT updated
        $config_file = $temp_dir . '/phpkg.config.json';
        Assertions\assert_true(file_exists($config_file), 'Config file should exist');
        
        $config_data = Files\read_json_as_array($config_file);
        Assertions\assert_true(isset($config_data['packages'][$datatype_key]), 'Config should contain datatype package');
        Assertions\assert_true($config_data['packages'][$datatype_key] === 'v1.0.0', 'Config should show unchanged datatype version');
        Assertions\assert_true(isset($config_data['packages'][$filemanager_key]), 'Config should contain file-manager package');
        Assertions\assert_true($config_data['packages'][$filemanager_key] === 'v1.0.0', 'Config should show unchanged file-manager version');
    },
    before: function () {
        // Create a temporary directory for the test
        $temp_dir = sys_get_temp_dir() . '/' . uniqid('phpkg_update_reject_major_test');
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
    title: 'it should successfully update to major version with force option',
    case: function (string $temp_dir) {
        // Add datatype v1.0.0 first
        $add_datatype_output = CliRunner\phpkg('add', ['https://github.com/php-repos/datatype.git', '--version=v1.0.0', "--project=$temp_dir"]);
        Assertions\assert_true(str_contains($add_datatype_output, 'Package added successfully.'), 'Datatype v1.0.0 should be added. Output: ' . $add_datatype_output);
        
        // Add file-manager v1.0.0
        $add_filemanager_output = CliRunner\phpkg('add', ['https://github.com/php-repos/file-manager.git', '--version=v1.0.0', "--project=$temp_dir"]);
        Assertions\assert_true(str_contains($add_filemanager_output, 'Package added successfully.'), 'File-manager v1.0.0 should be added. Output: ' . $add_filemanager_output);
        
        // Update datatype to v1.1.0 with --force (this should allow file-manager to update to v2.0.0)
        $update_output = CliRunner\phpkg('update', ['https://github.com/php-repos/datatype.git', '--version=v1.1.0', '--force', "--project=$temp_dir"]);
        
        // Should show success message
        Assertions\assert_true(str_contains($update_output, 'Package updated successfully.'), 'Should show success message. Output: ' . $update_output);
        
        // Verify the packages were updated in the lock file
        $lock_file = $temp_dir . '/phpkg.config-lock.json';
        Assertions\assert_true(file_exists($lock_file), 'Lock file should exist');
        
        $lock_data = Files\read_json_as_array($lock_file);
        Assertions\assert_true(isset($lock_data['packages']), 'Lock file should have packages section');
        
        // Check datatype package was updated
        $datatype_key = 'https://github.com/php-repos/datatype.git';
        Assertions\assert_true(isset($lock_data['packages'][$datatype_key]), 'Datatype package should be found in lock file');
        $datatype_info = $lock_data['packages'][$datatype_key];
        Assertions\assert_true($datatype_info['version'] === 'v1.1.0', 'Datatype package version should be updated to v1.1.0');
        
        // Check file-manager package was updated to v2.0.0
        $filemanager_key = 'https://github.com/php-repos/file-manager.git';
        Assertions\assert_true(isset($lock_data['packages'][$filemanager_key]), 'File-manager package should be found in lock file');
        $filemanager_info = $lock_data['packages'][$filemanager_key];
        Assertions\assert_true($filemanager_info['version'] === 'v2.0.0', 'File-manager package version should be updated to v2.0.0');
        
        // Verify the project config was also updated
        $config_file = $temp_dir . '/phpkg.config.json';
        Assertions\assert_true(file_exists($config_file), 'Config file should exist');
        
        $config_data = Files\read_json_as_array($config_file);
        Assertions\assert_true(isset($config_data['packages'][$datatype_key]), 'Config should contain updated datatype package');
        Assertions\assert_true($config_data['packages'][$datatype_key] === 'v1.1.0', 'Config should show updated datatype version');
        Assertions\assert_true(isset($config_data['packages'][$filemanager_key]), 'Config should contain updated file-manager package');
        Assertions\assert_true($config_data['packages'][$filemanager_key] === 'v2.0.0', 'Config should show updated file-manager version');
    },
    before: function () {
        // Create a temporary directory for the test
        $temp_dir = sys_get_temp_dir() . '/' . uniqid('phpkg_update_force_test');
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
    title: 'it should successfully update a Composer package to a newer version',
    case: function (string $temp_dir) {
        $update_output = CliRunner\phpkg('update', ['https://github.com/ramsey/uuid.git', '--version=3.9.7', "--project=$temp_dir"]);
        
        // Should show success message
        Assertions\assert_true(str_contains($update_output, 'Package updated successfully.'), 'Should show success message. Output: ' . $update_output);
        
        // Verify the package was updated in the lock file
        $lock_file = $temp_dir . '/phpkg.config-lock.json';
        Assertions\assert_true(file_exists($lock_file), 'Lock file should exist');
        
        $lock_data = Files\read_json_as_array($lock_file);
        Assertions\assert_true(isset($lock_data['packages']), 'Lock file should have packages section');
        
        // Find the ramsey/uuid package in the packages
        $package_key = 'https://github.com/ramsey/uuid.git';
        Assertions\assert_true(isset($lock_data['packages'][$package_key]), 'Ramsey UUID package should be found in lock file');
        
        $package_info = $lock_data['packages'][$package_key];
        Assertions\assert_true($package_info['version'] === '3.9.7', 'Package version should be updated to 3.9.7');
        Assertions\assert_true($package_info['hash'] === 'dc75aa439eb4c1b77f5379fd958b3dc0e6014178', 'Commit hash should match 3.9.7');
        
        // Verify the project config was also updated
        $config_file = $temp_dir . '/phpkg.config.json';
        Assertions\assert_true(file_exists($config_file), 'Config file should exist');
        
        $config_data = Files\read_json_as_array($config_file);
        Assertions\assert_true(isset($config_data['packages'][$package_key]), 'Config should contain updated package');
        Assertions\assert_true($config_data['packages'][$package_key] === '3.9.7', 'Config should show updated version');
    },
    before: function () {
        // Create a temporary directory for the test
        $temp_dir = sys_get_temp_dir() . '/' . uniqid('phpkg_update_composer_test');
        Files\make_directory_recursively($temp_dir);
        
        // Initialize a new project
        $init_output = CliRunner\phpkg('init', ["--project=$temp_dir"]);
        Assertions\assert_true(str_contains($init_output, 'Project initialized successfully'), 'Project should be initialized. Output: ' . $init_output);
        
        // Add ramsey/uuid v3.9.6 first
        $add_output = CliRunner\phpkg('add', ['https://github.com/ramsey/uuid.git', '--version=3.9.6', "--project=$temp_dir"]);
        Assertions\assert_true(str_contains($add_output, 'Package added successfully.'), 'Ramsey UUID 3.9.6 should be added. Output: ' . $add_output);
        
        return $temp_dir;
    },
    after: function (string $temp_dir) {
        // Clean up: remove temp dir
        Files\force_delete_recursive($temp_dir);
    }
);
