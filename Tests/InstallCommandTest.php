<?php

namespace Tests\InstallCommandTest;

use Phpkg\InfrastructureStructure\Files;
use Tests\CliRunner;
use PhpRepos\TestRunner\Assertions;
use function PhpRepos\TestRunner\Runner\test;

test(
    title: 'it should show error when running install in a directory without phpkg.config.json',
    case: function (string $temp_dir) {
        // Try to run install command in a directory without phpkg.config.json
        $install_output = CliRunner\phpkg('install', ["--project=$temp_dir"]);
        
        // Should show error about missing config file
        Assertions\assert_true(str_contains($install_output, 'Failed installing the project'), 'Should show error message. Output: ' . $install_output);
        Assertions\assert_true(str_contains($install_output, 'Could not read the project'), 'Should mention config read failure. Output: ' . $install_output);
        
        // Verify no Packages directory was created
        $packages_dir = $temp_dir . '/Packages';
        Assertions\assert_true(!is_dir($packages_dir), 'Packages directory should not be created');
        
        // Verify no lock file was created
        $lock_file = $temp_dir . '/phpkg.config-lock.json';
        Assertions\assert_true(!file_exists($lock_file), 'Lock file should not be created');
    },
    before: function () {
        // Create a temporary directory for the test (without initializing phpkg project)
        $temp_dir = sys_get_temp_dir() . '/' . uniqid('phpkg_install_no_config_test');
        Files\make_directory_recursively($temp_dir);
        
        return $temp_dir;
    },
    after: function (string $temp_dir) {
        // Clean up: remove temp dir
        Files\force_delete_recursive($temp_dir);
    }
);

test(
    title: 'it should show success message when running install in initialized project with no packages',
    case: function (string $temp_dir) {
        // Run install command in initialized project with no packages
        $install_output = CliRunner\phpkg('install', ["--project=$temp_dir"]);
        
        // Should show success message
        Assertions\assert_true(str_contains($install_output, 'Project installed successfully'), 'Should show success message. Output: ' . $install_output);
        
        // Verify Packages directory was created
        $packages_dir = $temp_dir . '/Packages';
        Assertions\assert_true(is_dir($packages_dir), 'Packages directory should be created');
        
        // Verify Packages directory is empty
        $packages_contents = scandir($packages_dir);
        $packages_contents = array_diff($packages_contents, ['.', '..']);
        Assertions\assert_true(empty($packages_contents), 'Packages directory should be empty');
        
        // Verify lock file was created
        $lock_file = $temp_dir . '/phpkg.config-lock.json';
        Assertions\assert_true(file_exists($lock_file), 'Lock file should be created');
        
        // Verify lock file has empty packages section
        $lock_data = Files\read_json_as_array($lock_file);
        Assertions\assert_true(isset($lock_data['packages']), 'Lock file should have packages section');
        Assertions\assert_true(empty($lock_data['packages']), 'Lock file should have empty packages section');
    },
    before: function () {
        // Create a temporary directory for the test
        $temp_dir = sys_get_temp_dir() . '/' . uniqid('phpkg_install_empty_test');
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
    title: 'it should show error when running install in project with existing packages',
    case: function (string $temp_dir) {
        // Try to run install command in project with existing packages
        $install_output = CliRunner\phpkg('install', ["--project=$temp_dir"]);
        
        // Should show error about existing packages
        Assertions\assert_true(str_contains($install_output, 'Failed installing the project'), 'Should show error message. Output: ' . $install_output);
        Assertions\assert_true(str_contains($install_output, 'The packages directory is not empty.'), 'Should mention project is already installed. Output: ' . $install_output);
        
        // Verify existing packages were not modified
        $packages_dir = $temp_dir . '/Packages';
        Assertions\assert_true(is_dir($packages_dir), 'Packages directory should still exist');
        
        $datatype_dir = $packages_dir . '/php-repos/datatype';
        Assertions\assert_true(is_dir($datatype_dir), 'Existing datatype package should remain');
        
        // Verify lock file was not modified
        $lock_file = $temp_dir . '/phpkg.config-lock.json';
        Assertions\assert_true(file_exists($lock_file), 'Lock file should still exist');
        
        $lock_data = Files\read_json_as_array($lock_file);
        Assertions\assert_true(isset($lock_data['packages']['https://github.com/php-repos/datatype.git']), 'Existing package should remain in lock file');
    },
    before: function () {
        // Create a temporary directory for the test
        $temp_dir = sys_get_temp_dir() . '/' . uniqid('phpkg_install_existing_test');
        Files\make_directory_recursively($temp_dir);
        
        // Initialize a new project
        $init_output = CliRunner\phpkg('init', ["--project=$temp_dir"]);
        Assertions\assert_true(str_contains($init_output, 'Project initialized successfully'), 'Project should be initialized. Output: ' . $init_output);
        
        // Add a package to create existing packages
        $add_output = CliRunner\phpkg('add', ['https://github.com/php-repos/datatype.git', '--version=v4.1.0', "--project=$temp_dir"]);
        Assertions\assert_true(str_contains($add_output, 'Package added successfully.'), 'Datatype package should be added. Output: ' . $add_output);
        
        return $temp_dir;
    },
    after: function (string $temp_dir) {
        // Clean up: remove temp dir
        Files\force_delete_recursive($temp_dir);
    }
);

test(
    title: 'it should successfully install when using force option with existing packages',
    case: function (string $temp_dir) {
        // Run install command with --force option in project with existing packages
        $install_output = CliRunner\phpkg('install', ['--force', "--project=$temp_dir"]);
        
        // Should show success message
        Assertions\assert_true(str_contains($install_output, 'Project installed successfully'), 'Should show success message. Output: ' . $install_output);
        
        // Verify packages were reinstalled
        $packages_dir = $temp_dir . '/Packages';
        Assertions\assert_true(is_dir($packages_dir), 'Packages directory should exist');
        
        $datatype_dir = $packages_dir . '/php-repos/datatype';
        Assertions\assert_true(is_dir($datatype_dir), 'Datatype package should be reinstalled');
        
        // Verify lock file was updated
        $lock_file = $temp_dir . '/phpkg.config-lock.json';
        Assertions\assert_true(file_exists($lock_file), 'Lock file should exist');
        
        $lock_data = Files\read_json_as_array($lock_file);
        Assertions\assert_true(isset($lock_data['packages']['https://github.com/php-repos/datatype.git']), 'Package should be in lock file');
    },
    before: function () {
        // Create a temporary directory for the test
        $temp_dir = sys_get_temp_dir() . '/' . uniqid('phpkg_install_force_test');
        Files\make_directory_recursively($temp_dir);
        
        // Initialize a new project
        $init_output = CliRunner\phpkg('init', ["--project=$temp_dir"]);
        Assertions\assert_true(str_contains($init_output, 'Project initialized successfully'), 'Project should be initialized. Output: ' . $init_output);
        
        // Add a package to create existing packages
        $add_output = CliRunner\phpkg('add', ['https://github.com/php-repos/datatype.git', '--version=v4.1.0', "--project=$temp_dir"]);
        Assertions\assert_true(str_contains($add_output, 'Package added successfully.'), 'Datatype package should be added. Output: ' . $add_output);
        
        return $temp_dir;
    },
    after: function (string $temp_dir) {
        // Clean up: remove temp dir
        Files\force_delete_recursive($temp_dir);
    }
);

test(
    title: 'it should successfully install packages when Packages directory is deleted but lock file exists',
    case: function (string $temp_dir) {
        // Run install command
        $install_output = CliRunner\phpkg('install', ["--project=$temp_dir"]);
        
        // Should show success message
        Assertions\assert_true(str_contains($install_output, 'Project installed successfully'), 'Should show success message. Output: ' . $install_output);
        
        // Verify Packages directory was recreated
        $packages_dir = $temp_dir . '/Packages';
        Assertions\assert_true(is_dir($packages_dir), 'Packages directory should be recreated');
        
        // Verify packages were reinstalled
        $datatype_dir = $packages_dir . '/php-repos/datatype';
        Assertions\assert_true(is_dir($datatype_dir), 'Datatype package should be reinstalled');
        
        // Verify lock file still exists and wasn't modified
        $lock_file = $temp_dir . '/phpkg.config-lock.json';
        Assertions\assert_true(file_exists($lock_file), 'Lock file should still exist');
        
        $lock_data = Files\read_json_as_array($lock_file);
        Assertions\assert_true(isset($lock_data['packages']['https://github.com/php-repos/datatype.git']), 'Package should remain in lock file');
    },
    before: function () {
        // Create a temporary directory for the test
        $temp_dir = sys_get_temp_dir() . '/' . uniqid('phpkg_install_missing_packages_test');
        Files\make_directory_recursively($temp_dir);
        
        // Initialize a new project
        $init_output = CliRunner\phpkg('init', ["--project=$temp_dir"]);
        Assertions\assert_true(str_contains($init_output, 'Project initialized successfully'), 'Project should be initialized. Output: ' . $init_output);
        
        // Add a package to create existing packages and lock file
        $add_output = CliRunner\phpkg('add', ['https://github.com/php-repos/datatype.git', '--version=v4.1.0', "--project=$temp_dir"]);
        Assertions\assert_true(str_contains($add_output, 'Package added successfully.'), 'Datatype package should be added. Output: ' . $add_output);
        
        // Delete the Packages directory but keep the lock file
        $packages_dir = $temp_dir . '/Packages';
        Files\force_delete_recursive($packages_dir);
        
        return $temp_dir;
    },
    after: function (string $temp_dir) {
        // Clean up: remove temp dir
        Files\force_delete_recursive($temp_dir);
    }
);

test(
    title: 'it should successfully install packages and create lock file when neither Packages directory nor lock file exist',
    case: function (string $temp_dir) {
        // Run install command
        $install_output = CliRunner\phpkg('install', ["--project=$temp_dir"]);
        
        // Should show success message
        Assertions\assert_true(str_contains($install_output, 'Project installed successfully'), 'Should show success message. Output: ' . $install_output);
        
        // Verify Packages directory was created
        $packages_dir = $temp_dir . '/Packages';
        Assertions\assert_true(is_dir($packages_dir), 'Packages directory should be created');
        
        // Verify packages were installed
        $datatype_dir = $packages_dir . '/php-repos/datatype';
        Assertions\assert_true(is_dir($datatype_dir), 'Datatype package should be installed');
        
        // Verify lock file was created
        $lock_file = $temp_dir . '/phpkg.config-lock.json';
        Assertions\assert_true(file_exists($lock_file), 'Lock file should be created');
        
        // Verify lock file has correct package information
        $lock_data = Files\read_json_as_array($lock_file);
        Assertions\assert_true(isset($lock_data['packages']), 'Lock file should have packages section');
        Assertions\assert_true(isset($lock_data['packages']['https://github.com/php-repos/datatype.git']), 'Package should be in lock file');
        
        $package_info = $lock_data['packages']['https://github.com/php-repos/datatype.git'];
        Assertions\assert_true(isset($package_info['version']), 'Package should have version information');
        Assertions\assert_true(isset($package_info['hash']), 'Package should have commit hash');
        Assertions\assert_true(isset($package_info['owner']), 'Package should have owner information');
        Assertions\assert_true(isset($package_info['repo']), 'Package should have repo information');
    },
    before: function () {
        // Create a temporary directory for the test
        $temp_dir = sys_get_temp_dir() . '/' . uniqid('phpkg_install_fresh_test');
        Files\make_directory_recursively($temp_dir);
        
        // Initialize a new project
        $init_output = CliRunner\phpkg('init', ["--project=$temp_dir"]);
        Assertions\assert_true(str_contains($init_output, 'Project initialized successfully'), 'Project should be initialized. Output: ' . $init_output);
        
        // Add a package to create config entries
        $add_output = CliRunner\phpkg('add', ['https://github.com/php-repos/datatype.git', '--version=v4.1.0', "--project=$temp_dir"]);
        Assertions\assert_true(str_contains($add_output, 'Package added successfully.'), 'Datatype package should be added. Output: ' . $add_output);
        
        // Delete both Packages directory and lock file
        $packages_dir = $temp_dir . '/Packages';
        $lock_file = $temp_dir . '/phpkg.config-lock.json';
        
        Files\force_delete_recursive($packages_dir);
        if (file_exists($lock_file)) {
            unlink($lock_file);
        }
        
        return $temp_dir;
    },
    after: function (string $temp_dir) {
        // Clean up: remove temp dir
        Files\force_delete_recursive($temp_dir);
    }
);

test(
    title: 'it should fail installation when lock file checksum is tampered with',
    case: function (string $temp_dir) {
        // Run install command - should fail due to checksum mismatch
        $install_output = CliRunner\phpkg('install', ["--project=$temp_dir"]);
        
        // Should show error message about checksum verification failure
        Assertions\assert_true(str_contains($install_output, 'Failed installing the project'), 'Should show error message. Output: ' . $install_output);
        Assertions\assert_true(str_contains($install_output, 'Checksum verification failed'), 'Should mention checksum verification failure. Output: ' . $install_output);
    },
    before: function () {
        // Create a temporary directory for the test
        $temp_dir = sys_get_temp_dir() . '/' . uniqid('phpkg_install_checksum_test');
        Files\make_directory_recursively($temp_dir);
        
        // Initialize a new project
        $init_output = CliRunner\phpkg('init', ["--project=$temp_dir"]);
        Assertions\assert_true(str_contains($init_output, 'Project initialized successfully'), 'Project should be initialized. Output: ' . $init_output);
        
        // Add a package to create existing packages and lock file
        $add_output = CliRunner\phpkg('add', ['https://github.com/php-repos/datatype.git', '--version=v4.1.0', "--project=$temp_dir"]);
        Assertions\assert_true(str_contains($add_output, 'Package added successfully.'), 'Datatype package should be added. Output: ' . $add_output);
        
        // Delete the Packages directory but keep the lock file
        $packages_dir = $temp_dir . '/Packages';
        Files\force_delete_recursive($packages_dir);
        
        // Tamper with the checksum in the lock file to simulate corruption
        $lock_file = $temp_dir . '/phpkg.config-lock.json';
        $lock_data = Files\read_json_as_array($lock_file);
        $lock_data['packages']['https://github.com/php-repos/datatype.git']['checksum'] = 'tampered_checksum_' . uniqid();

        // Write the tampered lock file back
        Files\save_array_as_json($lock_file, $lock_data);
        
        return $temp_dir;
    },
    after: function (string $temp_dir) {
        // Clean up: remove temp dir
        Files\force_delete_recursive($temp_dir);
    }
);
