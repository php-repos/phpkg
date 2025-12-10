<?php

namespace Tests\RemoveCommandTest;

use Phpkg\InfrastructureStructure\Files;
use Tests\CliRunner;
use PhpRepos\TestRunner\Assertions;
use function PhpRepos\TestRunner\Runner\test;

test(
    title: 'it should successfully remove a standalone package with no dependents',
    case: function (string $temp_dir) {
        // Remove the datatype package (standalone package)
        $remove_output = CliRunner\phpkg('remove', ['https://github.com/php-repos/datatype.git', "--project=$temp_dir"]);
        
        // Should show success message
        Assertions\assert_true(str_contains($remove_output, 'Package removed successfully.'), 'Should show success message. Output: ' . $remove_output);
        
        // Verify the package was removed from the lock file
        $lock_file = $temp_dir . '/phpkg.config-lock.json';
        Assertions\assert_true(file_exists($lock_file), 'Lock file should exist');
        
        $lock_data = Files\read_json_as_array($lock_file);
        Assertions\assert_true(isset($lock_data['packages']), 'Lock file should have packages section');
        
        // Verify the datatype package was removed
        $package_key = 'https://github.com/php-repos/datatype.git';
        Assertions\assert_true(!isset($lock_data['packages'][$package_key]), 'Datatype package should be removed from lock file');
        
        // Verify the project config was also updated
        $config_file = $temp_dir . '/phpkg.config.json';
        Assertions\assert_true(file_exists($config_file), 'Config file should exist');
        
        $config_data = Files\read_json_as_array($config_file);
        Assertions\assert_true(!isset($config_data['packages'][$package_key]), 'Config should not contain removed package');
        
        // Verify the package directory was removed
        $package_dir = $temp_dir . '/Packages/php-repos/datatype';
        Assertions\assert_true(!is_dir($package_dir), 'Package directory should be removed');
    },
    before: function () {
        // Create a temporary directory for the test
        $temp_dir = sys_get_temp_dir() . '/' . uniqid('phpkg_remove_standalone_test');
        Files\make_directory_recursively($temp_dir);
        
        // Initialize a new project
        $init_output = CliRunner\phpkg('init', ["--project=$temp_dir"]);
        Assertions\assert_true(str_contains($init_output, 'Project initialized successfully'), 'Project should be initialized. Output: ' . $init_output);
        
        // Add datatype package first (standalone package)
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
    title: 'it should remove both package and its dependencies when removing a package with dependencies',
    case: function (string $temp_dir) {
        // Remove the file-manager package (which has datatype as dependency)
        $remove_output = CliRunner\phpkg('remove', ['https://github.com/php-repos/file-manager.git', "--project=$temp_dir"]);
        
        // Should show success message
        Assertions\assert_true(str_contains($remove_output, 'Package removed successfully.'), 'Should show success message. Output: ' . $remove_output);
        
        // Verify both packages were removed from the lock file
        $lock_file = $temp_dir . '/phpkg.config-lock.json';
        Assertions\assert_true(file_exists($lock_file), 'Lock file should exist');
        
        $lock_data = Files\read_json_as_array($lock_file);
        Assertions\assert_true(isset($lock_data['packages']), 'Lock file should have packages section');
        
        // Verify both packages were removed
        $file_manager_key = 'https://github.com/php-repos/file-manager.git';
        $datatype_key = 'https://github.com/php-repos/datatype.git';
        
        Assertions\assert_true(!isset($lock_data['packages'][$file_manager_key]), 'File-manager package should be removed from lock file');
        Assertions\assert_true(!isset($lock_data['packages'][$datatype_key]), 'Datatype package should also be removed from lock file');
        
        // Verify the project config was also updated
        $config_file = $temp_dir . '/phpkg.config.json';
        Assertions\assert_true(file_exists($config_file), 'Config file should exist');
        
        $config_data = Files\read_json_as_array($config_file);
        Assertions\assert_true(!isset($config_data['packages'][$file_manager_key]), 'Config should not contain removed file-manager package');
        Assertions\assert_true(!isset($config_data['packages'][$datatype_key]), 'Config should not contain removed datatype package');
        
        // Verify both package directories were removed
        $file_manager_dir = $temp_dir . '/Packages/php-repos/file-manager';
        $datatype_dir = $temp_dir . '/Packages/php-repos/datatype';
        
        Assertions\assert_true(!is_dir($file_manager_dir), 'File-manager package directory should be removed');
        Assertions\assert_true(!is_dir($datatype_dir), 'Datatype package directory should be removed');
    },
    before: function () {
        // Create a temporary directory for the test
        $temp_dir = sys_get_temp_dir() . '/' . uniqid('phpkg_remove_with_deps_test');
        Files\make_directory_recursively($temp_dir);
        
        // Initialize a new project
        $init_output = CliRunner\phpkg('init', ["--project=$temp_dir"]);
        Assertions\assert_true(str_contains($init_output, 'Project initialized successfully'), 'Project should be initialized. Output: ' . $init_output);
        
        // Add file-manager package first (which will also add datatype as dependency)
        $add_output = CliRunner\phpkg('add', ['https://github.com/php-repos/file-manager.git', '--version=v5.0.0', "--project=$temp_dir"]);
        Assertions\assert_true(str_contains($add_output, 'Package added successfully.'), 'File-manager package should be added. Output: ' . $add_output);
        
        return $temp_dir;
    },
    after: function (string $temp_dir) {
        // Clean up: remove temp dir
        Files\force_delete_recursive($temp_dir);
    }
);

test(
    title: 'it should remove package but leave shared dependencies when removing a package that uses shared dependencies',
    case: function (string $temp_dir) {
        // Remove the console package (which uses observer, but observer is also used by web-router)
        $remove_output = CliRunner\phpkg('remove', ['https://github.com/php-repos/console.git', "--project=$temp_dir"]);
        
        // Should show success message
        Assertions\assert_true(str_contains($remove_output, 'Package removed successfully.'), 'Should show success message. Output: ' . $remove_output);
        
        // Verify console package was removed from the lock file
        $lock_file = $temp_dir . '/phpkg.config-lock.json';
        Assertions\assert_true(file_exists($lock_file), 'Lock file should exist');
        
        $lock_data = Files\read_json_as_array($lock_file);
        Assertions\assert_true(isset($lock_data['packages']), 'Lock file should have packages section');
        
        // Verify console package was removed
        $console_key = 'https://github.com/php-repos/console.git';
        Assertions\assert_true(!isset($lock_data['packages'][$console_key]), 'Console package should be removed from lock file');
        
        // Verify shared dependencies are still in lock file (used by web-router)
        
        Assertions\assert_true(isset($lock_data['packages']['https://github.com/php-repos/observer.git']), 'Observer package should remain in lock file (shared dependency)');
        Assertions\assert_true(isset($lock_data['packages']['git@github.com:php-repos/datatype.git']), 'Datatype package should remain in lock file (shared dependency)');
        
        // Verify the project config was updated
        $config_file = $temp_dir . '/phpkg.config.json';
        Assertions\assert_true(file_exists($config_file), 'Config file should exist');
        
        $config_data = Files\read_json_as_array($config_file);
        Assertions\assert_true(!isset($config_data['packages'][$console_key]), 'Config should not contain removed console package');
        
        // Verify console package directory was removed
        $console_dir = $temp_dir . '/Packages/php-repos/console';
        Assertions\assert_false(is_dir($console_dir), 'Console package directory should be removed');
        
        // Verify shared dependency directories still exist
        $observer_dir = $temp_dir . '/Packages/php-repos/observer';
        $datatype_dir = $temp_dir . '/Packages/php-repos/datatype';
        
        Assertions\assert_true(is_dir($observer_dir), 'Observer package directory should remain (shared dependency)');
        Assertions\assert_true(is_dir($datatype_dir), 'Datatype package directory should remain (shared dependency)');
    },
    before: function () {
        // Create a temporary directory for the test
        $temp_dir = sys_get_temp_dir() . '/' . uniqid('phpkg_remove_shared_deps_test');
        Files\make_directory_recursively($temp_dir);
        
        // Initialize a new project
        $init_output = CliRunner\phpkg('init', ["--project=$temp_dir"]);
        Assertions\assert_true(str_contains($init_output, 'Project initialized successfully'), 'Project should be initialized. Output: ' . $init_output);
        
        // Add web-router package first (which will add observer and datatype as dependencies)
        $add_web_router = CliRunner\phpkg('add', ['https://github.com/php-repos/web-router.git', '--version=v0.1.0', "--project=$temp_dir"]);
        Assertions\assert_true(str_contains($add_web_router, 'Package added successfully.'), 'Web-router package should be added. Output: ' . $add_web_router);
        
        // Add console package (which also uses observer and datatype - creating shared dependencies)
        $add_console = CliRunner\phpkg('add', ['https://github.com/php-repos/console.git', '--version=v5.0.0', "--project=$temp_dir"]);
        Assertions\assert_true(str_contains($add_console, 'Package added successfully.'), 'Console package should be added. Output: ' . $add_console);
        
        return $temp_dir;
    },
    after: function (string $temp_dir) {
        // Clean up: remove temp dir
        Files\force_delete_recursive($temp_dir);
    }
);

test(
    title: 'it should remove shared dependency from config but leave in lock file when removing a shared dependency itself',
    case: function (string $temp_dir) {
        // Remove the observer package (which is a shared dependency for both web-router and console)
        $remove_output = CliRunner\phpkg('remove', ['https://github.com/php-repos/observer.git', "--project=$temp_dir"]);
        
        // Should show success message
        Assertions\assert_true(str_contains($remove_output, 'Package removed successfully.'), 'Should show success message. Output: ' . $remove_output);
        
        // Verify the package was removed from the project config
        $config_file = $temp_dir . '/phpkg.config.json';
        Assertions\assert_true(file_exists($config_file), 'Config file should exist');
        
        $config_data = Files\read_json_as_array($config_file);
        $observer_key = 'https://github.com/php-repos/observer.git';
        Assertions\assert_true(!isset($config_data['packages'][$observer_key]), 'Observer package should be removed from config file');
        
        // Verify the package is still in the lock file (shared dependency)
        $lock_file = $temp_dir . '/phpkg.config-lock.json';
        Assertions\assert_true(file_exists($lock_file), 'Lock file should exist');
        
        $lock_data = Files\read_json_as_array($lock_file);
        Assertions\assert_true(isset($lock_data['packages']), 'Lock file should have packages section');
        Assertions\assert_true(isset($lock_data['packages'][$observer_key]), 'Observer package should remain in lock file (shared dependency)');
        
        // Verify the package directory still exists on file system
        $observer_dir = $temp_dir . '/Packages/php-repos/observer';
        Assertions\assert_true(is_dir($observer_dir), 'Observer package directory should remain on file system (shared dependency)');
        
        // Verify other packages that depend on observer are still in config
        $web_router_key = 'https://github.com/php-repos/web-router.git';
        $console_key = 'https://github.com/php-repos/console.git';
        
        Assertions\assert_true(isset($config_data['packages'][$web_router_key]), 'Web-router package should remain in config');
        Assertions\assert_true(isset($config_data['packages'][$console_key]), 'Console package should remain in config');
    },
    before: function () {
        // Create a temporary directory for the test
        $temp_dir = sys_get_temp_dir() . '/' . uniqid('phpkg_remove_shared_dep_test');
        Files\make_directory_recursively($temp_dir);
        
        // Initialize a new project
        $init_output = CliRunner\phpkg('init', ["--project=$temp_dir"]);
        Assertions\assert_true(str_contains($init_output, 'Project initialized successfully'), 'Project should be initialized. Output: ' . $init_output);
        
        // Add multiple packages that share observer as dependency
        $add_web_router = CliRunner\phpkg('add', ['https://github.com/php-repos/web-router.git', '--version=v0.1.0', "--project=$temp_dir"]);
        Assertions\assert_true(str_contains($add_web_router, 'Package added successfully.'), 'Web-router package should be added. Output: ' . $add_web_router);
        
        $add_console = CliRunner\phpkg('add', ['https://github.com/php-repos/console.git', '--version=v5.0.0', "--project=$temp_dir"]);
        Assertions\assert_true(str_contains($add_console, 'Package added successfully.'), 'Console package should be added. Output: ' . $add_console);

        $add_console = CliRunner\phpkg('add', ['https://github.com/php-repos/observer.git', '--version=v1.0.0', "--project=$temp_dir"]);
        Assertions\assert_true(str_contains($add_console, 'Package added successfully.'), 'Observer package should be added. Output: ' . $add_console);
        
        return $temp_dir;
    },
    after: function (string $temp_dir) {
        // Clean up: remove temp dir
        Files\force_delete_recursive($temp_dir);
    }
);
