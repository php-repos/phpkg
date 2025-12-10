<?php

namespace Tests\AddCommandTest;

use Phpkg\InfrastructureStructure\Files;
use Tests\CliRunner;
use PhpRepos\TestRunner\Assertions;
use function PhpRepos\TestRunner\Runner\test;

test(
    title: 'it should successfully add a package to a project',
    case: function (string $temp_dir) {
        $package_url = 'https://github.com/php-repos/simple-package.git';
        $version = 'development';
        
        // Add the package using the add command
        $output = CliRunner\phpkg('add', [$package_url, "--version=$version", "--project=$temp_dir"]);
        
        // Should show success message
        Assertions\assert_true(str_contains($output, 'Package added successfully.'), 'Should show success message. Output: ' . $output);
        
        // Verify the package was added to the lock file
        $lock_file = $temp_dir . '/phpkg.config-lock.json';
        Assertions\assert_true(file_exists($lock_file), 'Lock file should exist');
        
        $lock_data = Files\read_json_as_array($lock_file);
        Assertions\assert_true(isset($lock_data['packages']), 'Lock file should have packages section');
        
        // Find the simple-package in the packages
        $package_key = $package_url;
        Assertions\assert_true(isset($lock_data['packages'][$package_key]), 'Simple package should be found in lock file');
        
        $package_info = $lock_data['packages'][$package_key];
        Assertions\assert_true($package_info['version'] === $version, 'Package version should match');
        Assertions\assert_true($package_info['hash'] === '1022f2004a8543326a92c0ba606439db530a23c9', 'Commit hash should match');
        Assertions\assert_true($package_info['owner'] === 'php-repos', 'Package owner should be php-repos');
        Assertions\assert_true($package_info['repo'] === 'simple-package', 'Package repo should be simple-package');
        
        // Verify the package files were added to Packages directory
        $package_dir = $temp_dir . '/Packages/php-repos/simple-package';
        Assertions\assert_true(is_dir($package_dir), 'Package directory should exist');
        
        // Check for phpkg.config.json in the package
        $package_config = $package_dir . '/phpkg.config.json';
        Assertions\assert_true(file_exists($package_config), 'Package config file should exist');
        
        $package_config_data = Files\read_json_as_array($package_config);
        Assertions\assert_true(isset($package_config_data['map']), 'Package config should have map section');
        Assertions\assert_true(isset($package_config_data['map']['PhpRepos\\SimplePackage']), 'Map should contain PhpRepos\\SimplePackage');
        Assertions\assert_true($package_config_data['map']['PhpRepos\\SimplePackage'] === 'Source', 'Map should point to Source directory');
    },
    before: function () {
        // Create a temporary directory for the test
        $temp_dir = sys_get_temp_dir() . '/' . uniqid('phpkg_add_test');
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
    title: 'it should successfully add a package with dependencies when dependencies are already present',
    case: function (string $temp_dir) {
        // The simple-package (dependency) is already added in the before hook
        // Now add the complex-package which depends on simple-package
        $complex_package_url = 'https://github.com/php-repos/complex-package.git';
        $complex_version = 'development';
        
        $complex_output = CliRunner\phpkg('add', [$complex_package_url, "--version=$complex_version", "--project=$temp_dir"]);
        Assertions\assert_true(str_contains($complex_output, 'Package added successfully.'), 'Complex package should be added successfully. Output: ' . $complex_output);
        
        // Verify both packages are in the lock file
        $lock_file = $temp_dir . '/phpkg.config-lock.json';
        Assertions\assert_true(file_exists($lock_file), 'Lock file should exist');
        
        $lock_data = Files\read_json_as_array($lock_file);
        Assertions\assert_true(isset($lock_data['packages']), 'Lock file should have packages section');
        
        // Verify simple-package is in lock file (should already be there from before hook)
        $simple_package_url = 'https://github.com/php-repos/simple-package.git';
        $simple_package_key = $simple_package_url;
        Assertions\assert_true(isset($lock_data['packages'][$simple_package_key]), 'Simple package should be found in lock file');
        
        $simple_package_info = $lock_data['packages'][$simple_package_key];
        Assertions\assert_true($simple_package_info['version'] === 'development', 'Simple package version should be development');
        Assertions\assert_true($simple_package_info['hash'] === '1022f2004a8543326a92c0ba606439db530a23c9', 'Simple package commit hash should match');
        
        // Verify complex-package is in lock file
        $complex_package_key = $complex_package_url;
        Assertions\assert_true(isset($lock_data['packages'][$complex_package_key]), 'Complex package should be found in lock file');
        
        $complex_package_info = $lock_data['packages'][$complex_package_key];
        Assertions\assert_true($complex_package_info['version'] === $complex_version, 'Complex package version should match');
        Assertions\assert_true($complex_package_info['hash'] === '079acc5267e34016e3aa0b70cc1291edeb032d03', 'Complex package commit hash should match');
        
        // Verify both package directories exist
        $simple_package_dir = $temp_dir . '/Packages/php-repos/simple-package';
        Assertions\assert_true(is_dir($simple_package_dir), 'Simple package directory should exist');
        
        $complex_package_dir = $temp_dir . '/Packages/php-repos/complex-package';
        Assertions\assert_true(is_dir($complex_package_dir), 'Complex package directory should exist');
        
        // Check phpkg.config.json for both packages
        $simple_package_config = $simple_package_dir . '/phpkg.config.json';
        Assertions\assert_true(file_exists($simple_package_config), 'Simple package config file should exist');
        
        $simple_package_config_data = Files\read_json_as_array($simple_package_config);
        Assertions\assert_true(isset($simple_package_config_data['map']), 'Simple package config should have map section');
        Assertions\assert_true(isset($simple_package_config_data['map']['PhpRepos\\SimplePackage']), 'Simple package map should contain PhpRepos\\SimplePackage');
        Assertions\assert_true($simple_package_config_data['map']['PhpRepos\\SimplePackage'] === 'Source', 'Simple package map should point to Source directory');
        
        $complex_package_config = $complex_package_dir . '/phpkg.config.json';
        Assertions\assert_true(file_exists($complex_package_config), 'Complex package config file should exist');
        
        $complex_package_config_data = Files\read_json_as_array($complex_package_config);
        Assertions\assert_true(isset($complex_package_config_data['map']), 'Complex package config should have map section');
        Assertions\assert_true(isset($complex_package_config_data['map']['PhpRepos\\ComplexPackage']), 'Complex package map should contain PhpRepos\\ComplexPackage');
        Assertions\assert_true($complex_package_config_data['map']['PhpRepos\\ComplexPackage'] === 'src', 'Complex package map should point to src directory');
    },
    before: function () {
        // Create a temporary directory for the test
        $temp_dir = sys_get_temp_dir() . '/' . uniqid('phpkg_add_deps_success_test');
        Files\make_directory_recursively($temp_dir);
        
        // Initialize a new project
        $init_output = CliRunner\phpkg('init', ["--project=$temp_dir"]);
        Assertions\assert_true(str_contains($init_output, 'Project initialized successfully'), 'Project should be initialized. Output: ' . $init_output);
        
        // Add the simple-package (dependency) first
        $simple_package_url = 'https://github.com/php-repos/simple-package.git';
        $simple_version = 'development';
        
        $simple_output = CliRunner\phpkg('add', [$simple_package_url, "--version=$simple_version", "--project=$temp_dir"]);
        Assertions\assert_true(str_contains($simple_output, 'Package added successfully.'), 'Simple package should be added successfully in before hook. Output: ' . $simple_output);
        
        return $temp_dir;
    },
    after: function (string $temp_dir) {
        // Clean up: remove temp dir
        Files\force_delete_recursive($temp_dir);
    }
);

test(
    title: 'it should successfully add a package using its latest version when no version specified',
    case: function (string $temp_dir) {
        // Add the released-package without specifying a version (should use latest)
        $released_package_url = 'https://github.com/php-repos/released-package.git';
        
        $released_output = CliRunner\phpkg('add', [$released_package_url, "--project=$temp_dir"]);
        Assertions\assert_true(str_contains($released_output, 'Package added successfully.'), 'Released package should be added successfully. Output: ' . $released_output);
        
        // Verify the package is in the lock file with the correct version and hash
        $lock_file = $temp_dir . '/phpkg.config-lock.json';
        Assertions\assert_true(file_exists($lock_file), 'Lock file should exist');
        
        $lock_data = Files\read_json_as_array($lock_file);
        Assertions\assert_true(isset($lock_data['packages']), 'Lock file should have packages section');
        
        // Verify released-package is in lock file with latest version
        $released_package_key = $released_package_url;
        Assertions\assert_true(isset($lock_data['packages'][$released_package_key]), 'Released package should be found in lock file');
        
        $released_package_info = $lock_data['packages'][$released_package_key];
        Assertions\assert_true($released_package_info['version'] === 'v1.1.0', 'Released package version should be v1.1.0');
        Assertions\assert_true($released_package_info['hash'] === 'be24f45d8785c215901ba90b242f3b8a7d2bdbfb', 'Released package commit hash should match');
        
        // Verify the package directory exists
        $released_package_dir = $temp_dir . '/Packages/php-repos/released-package';
        Assertions\assert_true(is_dir($released_package_dir), 'Released package directory should exist');
        
        // Check the release-file.txt content
        $release_file = $released_package_dir . '/release-file.txt';
        Assertions\assert_true(file_exists($release_file), 'Release file should exist');
        
        $release_file_content = Files\file_content($release_file);
        $expected_content = "This is a specific file.\nv1.0.0\nv1.0.1\nv1.1.0\n";
        Assertions\assert_true($release_file_content === $expected_content, 'Release file content should match expected content. Actual: ' . $release_file_content);
        
        // Verify all three packages are now in the lock file
        $simple_package_url = 'https://github.com/php-repos/simple-package.git';
        $complex_package_url = 'https://github.com/php-repos/complex-package.git';
        
        Assertions\assert_true(isset($lock_data['packages'][$simple_package_url]), 'Simple package should still be in lock file');
        Assertions\assert_true(isset($lock_data['packages'][$complex_package_url]), 'Complex package should still be in lock file');
        Assertions\assert_true(isset($lock_data['packages'][$released_package_url]), 'Released package should be in lock file');
        
        // Verify the project config has all three packages
        $project_config = $temp_dir . '/phpkg.config.json';
        Assertions\assert_true(file_exists($project_config), 'Project config file should exist');
        
        $project_config_data = Files\read_json_as_array($project_config);
        Assertions\assert_true(isset($project_config_data['packages']), 'Project config should have packages section');
        Assertions\assert_true(isset($project_config_data['packages'][$simple_package_url]), 'Project config should contain simple-package');
        Assertions\assert_true(isset($project_config_data['packages'][$complex_package_url]), 'Project config should contain complex-package');
        Assertions\assert_true(isset($project_config_data['packages'][$released_package_url]), 'Project config should contain released-package');
    },
    before: function () {
        // Create a temporary directory for the test
        $temp_dir = sys_get_temp_dir() . '/' . uniqid('phpkg_add_latest_version_test');
        Files\make_directory_recursively($temp_dir);
        
        // Initialize a new project
        $init_output = CliRunner\phpkg('init', ["--project=$temp_dir"]);
        Assertions\assert_true(str_contains($init_output, 'Project initialized successfully'), 'Project should be initialized. Output: ' . $init_output);
        
        // Add the simple-package (dependency) first
        $simple_package_url = 'https://github.com/php-repos/simple-package.git';
        $simple_version = 'development';
        
        $simple_output = CliRunner\phpkg('add', [$simple_package_url, "--version=$simple_version", "--project=$temp_dir"]);
        Assertions\assert_true(str_contains($simple_output, 'Package added successfully.'), 'Simple package should be added successfully in before hook. Output: ' . $simple_output);
        
        // Add the complex-package (which depends on simple-package)
        $complex_package_url = 'https://github.com/php-repos/complex-package.git';
        $complex_version = 'development';
        
        $complex_output = CliRunner\phpkg('add', [$complex_package_url, "--version=$complex_version", "--project=$temp_dir"]);
        Assertions\assert_true(str_contains($complex_output, 'Package added successfully.'), 'Complex package should be added successfully in before hook. Output: ' . $complex_output);
        
        return $temp_dir;
    },
    after: function (string $temp_dir) {
        // Clean up: remove temp dir
        Files\force_delete_recursive($temp_dir);
    }
);

test(
    title: 'it should successfully add a package using an alias',
    case: function (string $temp_dir) {
        // First, create an alias for datatype
        $alias = 'datatype';
        $package_url = 'https://github.com/php-repos/datatype.git';
        
        $alias_output = CliRunner\phpkg('alias', [$alias, $package_url, "--project=$temp_dir"]);
        Assertions\assert_true(str_contains($alias_output, 'Alias registered successfully.'), 'Alias should be registered successfully. Output: ' . $alias_output);
        
        // Verify the alias is saved to the config file
        $config_file = $temp_dir . '/phpkg.config.json';
        $config_data = Files\read_json_as_array($config_file);
        Assertions\assert_true(isset($config_data['aliases'][$alias]), 'Alias should be found in config');
        Assertions\assert_true($config_data['aliases'][$alias] === $package_url, 'Alias should point to correct package URL');
        
        // Now add the package using the alias
        $add_output = CliRunner\phpkg('add', [$alias, "--project=$temp_dir"]);
        Assertions\assert_true(str_contains($add_output, 'Package added successfully.'), 'Package should be added successfully using alias. Output: ' . $add_output);
        
        // Verify the package is in the lock file using the actual URL (not the alias)
        $lock_file = $temp_dir . '/phpkg.config-lock.json';
        Assertions\assert_true(file_exists($lock_file), 'Lock file should exist');
        
        $lock_data = Files\read_json_as_array($lock_file);
        Assertions\assert_true(isset($lock_data['packages']), 'Lock file should have packages section');
        
        // The lock file should contain the actual URL, not the alias
        Assertions\assert_true(isset($lock_data['packages'][$package_url]), 'Package should be found in lock file using actual URL');
        
        // Verify the package directory exists
        $package_dir = $temp_dir . '/Packages/php-repos/datatype';
        Assertions\assert_true(is_dir($package_dir), 'Package directory should exist');
        
        // Verify the project config has the package
        $project_config = $temp_dir . '/phpkg.config.json';
        $project_config_data = Files\read_json_as_array($project_config);
        Assertions\assert_true(isset($project_config_data['packages'][$package_url]), 'Project config should contain the package using actual URL');
        
        // Verify the alias is still in the config
        Assertions\assert_true(isset($project_config_data['aliases'][$alias]), 'Alias should still be in config');
        Assertions\assert_true($project_config_data['aliases'][$alias] === $package_url, 'Alias should still point to correct package URL');
    },
    before: function () {
        // Create a temporary directory for the test
        $temp_dir = sys_get_temp_dir() . '/' . uniqid('phpkg_add_alias_test');
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
    title: 'it should successfully add a package at a specific version',
    case: function (string $temp_dir) {
        // Add the datatype package at a specific version
        $package_url = 'https://github.com/php-repos/datatype.git';
        $specific_version = 'v1.0.0';
        
        $add_output = CliRunner\phpkg('add', [$package_url, "--version=$specific_version", "--project=$temp_dir"]);
        Assertions\assert_true(str_contains($add_output, 'Package added successfully.'), 'Package should be added successfully at specific version. Output: ' . $add_output);
        
        // Verify the package is in the lock file with the correct version and hash
        $lock_file = $temp_dir . '/phpkg.config-lock.json';
        Assertions\assert_true(file_exists($lock_file), 'Lock file should exist');
        
        $lock_data = Files\read_json_as_array($lock_file);
        Assertions\assert_true(isset($lock_data['packages']), 'Lock file should have packages section');
        
        // Verify datatype package is in lock file with specific version
        Assertions\assert_true(isset($lock_data['packages'][$package_url]), 'Datatype package should be found in lock file');
        
        $package_info = $lock_data['packages'][$package_url];
        Assertions\assert_true($package_info['version'] === $specific_version, 'Package version should be v1.0.0');
        
        // Verify the commit hash matches the expected hash for v1.0.0
        $expected_hash = 'e802ba8c0cb2ffe2282de401bbf9e84a4ce1316a';
        Assertions\assert_true($package_info['hash'] === $expected_hash, "Package commit hash should match. Expected: $expected_hash, Actual: {$package_info['hash']}");
        
        // Verify the package directory exists
        $package_dir = $temp_dir . '/Packages/php-repos/datatype';
        Assertions\assert_true(is_dir($package_dir), 'Package directory should exist');
        
        // Verify the project config has the package
        $project_config = $temp_dir . '/phpkg.config.json';
        $project_config_data = Files\read_json_as_array($project_config);
        Assertions\assert_true(isset($project_config_data['packages']), 'Project config should have packages section');
        Assertions\assert_true(isset($project_config_data['packages'][$package_url]), 'Project config should contain datatype package');
        Assertions\assert_true($project_config_data['packages'][$package_url] === $specific_version, 'Project config should specify correct version');
        
        // Verify all expected packages are present with correct versions and hashes
        $expected_packages = [
            'https://github.com/php-repos/datatype.git' => [
                'owner' => 'php-repos',
                'repo' => 'datatype',
                'version' => 'v1.0.0',
                'hash' => 'e802ba8c0cb2ffe2282de401bbf9e84a4ce1316a'
            ],
            'git@github.com:php-repos/test-runner.git' => [
                'owner' => 'php-repos',
                'repo' => 'test-runner',
                'version' => 'v1.0.0',
                'hash' => '30f3ce06c760719c7a107532b6755f9882c57b83'
            ],
            'https://github.com/php-repos/cli.git' => [
                'owner' => 'php-repos',
                'repo' => 'cli',
                'version' => 'v1.0.0',
                'hash' => '9d8bd24f9d31b5bf18bc01e89053d311495f714d'
            ],
            'https://github.com/php-repos/file-manager.git' => [
                'owner' => 'php-repos',
                'repo' => 'file-manager',
                'version' => 'v1.0.0',
                'hash' => 'b120a464839922b0a208bc198fbc06b491f08ee0'
            ]
        ];
        
        // Verify each expected package
        foreach ($expected_packages as $package_url => $expected_info) {
            Assertions\assert_true(isset($lock_data['packages'][$package_url]), "Package $package_url should be found in lock file");
            
            $package_info = $lock_data['packages'][$package_url];
            Assertions\assert_true($package_info['owner'] === $expected_info['owner'], "Package $package_url should have correct owner. Expected: {$expected_info['owner']}, Actual: {$package_info['owner']}");
            Assertions\assert_true($package_info['repo'] === $expected_info['repo'], "Package $package_url should have correct repo. Expected: {$expected_info['repo']}, Actual: {$package_info['repo']}");
            Assertions\assert_true($package_info['version'] === $expected_info['version'], "Package $package_url should have correct version. Expected: {$expected_info['version']}, Actual: {$package_info['version']}");
            Assertions\assert_true($package_info['hash'] === $expected_info['hash'], "Package $package_url should have correct hash. Expected: {$expected_info['hash']}, Actual: {$package_info['hash']}");
        }
        
        // Verify the total number of packages
        $actual_package_count = count($lock_data['packages']);
        $expected_package_count = count($expected_packages);
        Assertions\assert_true($actual_package_count === $expected_package_count, "Lock file should contain exactly $expected_package_count packages. Actual: $actual_package_count");
    },
    before: function () {
        // Create a temporary directory for the test
        $temp_dir = sys_get_temp_dir() . '/' . uniqid('phpkg_add_specific_version_test');
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
    title: 'it should successfully add a composer package with dependencies',
    case: function (string $temp_dir) {
        // Add the Ramsey UUID composer package at the specific version
        $package_url = 'https://github.com/ramsey/uuid.git';
        $specific_version = 'v4.7.6'; // Requested version, should install 4.7.6
        
        $add_output = CliRunner\phpkg('add', [$package_url, "--version=$specific_version", "--project=$temp_dir"]);
        Assertions\assert_true(str_contains($add_output, 'Package added successfully.'), 'Composer package should be added successfully at specific version. Output: ' . $add_output);
        
        // Verify the package is in the lock file with the correct version and hash
        $lock_file = $temp_dir . '/phpkg.config-lock.json';
        Assertions\assert_true(file_exists($lock_file), 'Lock file should exist');
        
        $lock_data = Files\read_json_as_array($lock_file);
        Assertions\assert_true(isset($lock_data['packages']), 'Lock file should have packages section');
        
        // Verify Ramsey UUID package is in lock file with specific version
        Assertions\assert_true(isset($lock_data['packages'][$package_url]), 'Ramsey UUID package should be found in lock file');
        
        $package_info = $lock_data['packages'][$package_url];
        Assertions\assert_true($package_info['version'] === '4.7.6', 'Package version should be 4.7.6');
        Assertions\assert_true($package_info['hash'] === '91039bc1faa45ba123c4328958e620d382ec7088', 'Ramsey UUID package commit hash should match');
        
        // Verify the expected dependency packages are present
        $expected_dependencies = [
            'https://github.com/brick/math.git' => [
                'hash' => 'f05858549e5f9d7bb45875a75583240a38a281d0'
            ],
            'https://github.com/ramsey/collection.git' => [
                'hash' => '344572933ad0181accbf4ba763e85a0306a8c5e2'
            ]
        ];
        
        foreach ($expected_dependencies as $dep_url => $dep_info) {
            Assertions\assert_true(isset($lock_data['packages'][$dep_url]), "Dependency package $dep_url should be found in lock file");
            $dep_package_info = $lock_data['packages'][$dep_url];
            Assertions\assert_true($dep_package_info['hash'] === $dep_info['hash'], "Dependency package $dep_url should have correct hash. Expected: {$dep_info['hash']}, Actual: {$dep_package_info['hash']}");
        }
        
        // Verify the package directory exists
        $package_dir = $temp_dir . '/Packages/ramsey/uuid';
        Assertions\assert_true(is_dir($package_dir), 'Ramsey UUID package directory should exist');
        
        // Verify the project config has the package
        $project_config = $temp_dir . '/phpkg.config.json';
        $project_config_data = Files\read_json_as_array($project_config);
        Assertions\assert_true(isset($project_config_data['packages']), 'Project config should have packages section');
        Assertions\assert_true(isset($project_config_data['packages'][$package_url]), 'Project config should contain Ramsey UUID package');
        // The project config should contain the exact version that was installed
        $installed_version = '4.7.6';
        Assertions\assert_true($project_config_data['packages'][$package_url] === $installed_version, "Project config should specify installed version. Expected: $installed_version, Actual: {$project_config_data['packages'][$package_url]}");
    },
    before: function () {
        // Create a temporary directory for the test
        $temp_dir = sys_get_temp_dir() . '/' . uniqid('phpkg_add_composer_test');
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
    title: 'it should reject major version upgrade that conflicts with existing dependencies',
    case: function (string $temp_dir) {
        // Now try to add test-runner v2.0.0 which should fail due to version incompatibility
        $test_runner_url = 'https://github.com/php-repos/test-runner.git';
        $test_runner_version = 'v2.0.0';
        
        $add_test_runner_output = CliRunner\phpkg('add', [$test_runner_url, "--version=$test_runner_version", "--project=$temp_dir"]);
        
        // The command should fail with an error message about version incompatibility
        Assertions\assert_true(str_contains($add_test_runner_output, 'Failed to add package'), 'Adding test-runner v2.0.0 should fail with an error. Output: ' . $add_test_runner_output);
        Assertions\assert_true(str_contains($add_test_runner_output, 'version incompatibility'), 'Error should mention version incompatibility. Output: ' . $add_test_runner_output);
        Assertions\assert_true(str_contains($add_test_runner_output, 'You might force using --force option'), 'Error should suggest using --force option. Output: ' . $add_test_runner_output);
        
        // Verify that the lock file still contains the original packages (not changed)
        $lock_file = $temp_dir . '/phpkg.config-lock.json';
        $lock_data_after = Files\read_json_as_array($lock_file);

        // Verify that datatype package is still present and unchanged
        $datatype_url = 'https://github.com/php-repos/datatype.git';
        Assertions\assert_true(isset($lock_data_after['packages'][$datatype_url]), 'Datatype package should still be present');
        $datatype_info_after = $lock_data_after['packages'][$datatype_url];
        Assertions\assert_true($datatype_info_after['version'] === 'v1.0.0', 'Datatype package should remain at v1.0.0');
        
        // Verify that file-manager is still at v2.0.0
        $file_manager_url = 'https://github.com/php-repos/file-manager.git';
        Assertions\assert_true(isset($lock_data_after['packages'][$file_manager_url]), 'File-manager should still be present');
        $file_manager_info_after = $lock_data_after['packages'][$file_manager_url];
        Assertions\assert_true($file_manager_info_after['version'] === 'v2.0.0', 'File-manager should remain at v2.0.0');
        
        // Verify that test-runner was NOT added
        Assertions\assert_true(!isset($lock_data_after['packages'][$test_runner_url]), 'Test-runner should NOT be found in lock file');
    },
    before: function () {
        // Create a temporary directory for the test
        $temp_dir = sys_get_temp_dir() . '/' . uniqid('phpkg_add_version_conflict_test');
        Files\make_directory_recursively($temp_dir);
        
        // Initialize a new project
        $init_output = CliRunner\phpkg('init', ["--project=$temp_dir"]);
        Assertions\assert_true(str_contains($init_output, 'Project initialized successfully'), 'Project should be initialized. Output: ' . $init_output);
        
        // Add datatype v1.0.0
        $datatype_url = 'https://github.com/php-repos/datatype.git';
        $datatype_version = 'v1.0.0';
        
        $add_datatype_output = CliRunner\phpkg('add', [$datatype_url, "--version=$datatype_version", "--project=$temp_dir"]);
        Assertions\assert_true(str_contains($add_datatype_output, 'Package added successfully.'), 'Datatype package should be added successfully. Output: ' . $add_datatype_output);
        
        // Add file-manager v2.0.0
        $file_manager_url = 'https://github.com/php-repos/file-manager.git';
        $file_manager_version = 'v2.0.0';
        
        $add_file_manager_output = CliRunner\phpkg('add', [$file_manager_url, "--version=$file_manager_version", "--project=$temp_dir"]);
        Assertions\assert_true(str_contains($add_file_manager_output, 'Package added successfully.'), 'File-manager package should be added successfully. Output: ' . $add_file_manager_output);
        
        // Verify that file-manager v2.0.0 was added
        $lock_file = $temp_dir . '/phpkg.config-lock.json';
        $lock_data = Files\read_json_as_array($lock_file);
        
        Assertions\assert_true(isset($lock_data['packages'][$file_manager_url]), 'File-manager should be added');
        $file_manager_info = $lock_data['packages'][$file_manager_url];
        Assertions\assert_true($file_manager_info['version'] === 'v2.0.0', 'File-manager should be at version v2.0.0');
        
        return $temp_dir;
    },
    after: function (string $temp_dir) {
        // Clean up: remove temp dir
        Files\force_delete_recursive($temp_dir);
    }
);

test(
    title: 'it should successfully force major version upgrade using --force option while preserving compatible dependencies',
    case: function (string $temp_dir) {
        // Now try to add test-runner v2.0.0 with --force to add the package while preserving compatible dependencies
        $test_runner_url = 'https://github.com/php-repos/test-runner.git';
        $test_runner_version = 'v2.0.0';
        
        $add_test_runner_output = CliRunner\phpkg('add', [$test_runner_url, "--version=$test_runner_version", "--force", "--project=$temp_dir"]);
        
        // The command should succeed with the --force option
        Assertions\assert_true(str_contains($add_test_runner_output, 'Package added successfully.'), 'Adding test-runner v2.0.0 with --force should succeed. Output: ' . $add_test_runner_output);
        
        // Verify that the lock file now contains the expected packages
        $lock_file = $temp_dir . '/phpkg.config-lock.json';
        $lock_data_after = Files\read_json_as_array($lock_file);
        
        // Verify that test-runner v2.0.0 was added
        Assertions\assert_true(isset($lock_data_after['packages'][$test_runner_url]), 'Test-runner should be present in lock file after force add');
        $test_runner_info_after = $lock_data_after['packages'][$test_runner_url];
        Assertions\assert_true($test_runner_info_after['version'] === 'v2.0.0', 'Test-runner should be at v2.0.0');

        // Verify that datatype package remains at v1.0.0
        // Note: datatype might be listed under SSH URL format in the lock file
        $datatype_https_url = 'https://github.com/php-repos/datatype.git';
        $datatype_ssh_url = 'git@github.com:php-repos/datatype.git';
        
        // Check if datatype exists under either URL format
        $datatype_url = isset($lock_data_after['packages'][$datatype_https_url]) ? $datatype_https_url : $datatype_ssh_url;
        Assertions\assert_true(isset($lock_data_after['packages'][$datatype_url]), 'Datatype package should still be present after force add');
        $datatype_info_after = $lock_data_after['packages'][$datatype_url];
        Assertions\assert_true($datatype_info_after['version'] === 'v1.0.0', 'Datatype package should remain at v1.0.0');
        
        // Verify that file-manager is at v2.0.3 (as required by test-runner v2.0.0)
        $file_manager_url = 'https://github.com/php-repos/file-manager.git';
        Assertions\assert_true(isset($lock_data_after['packages'][$file_manager_url]), 'File-manager should be present in lock file');
        $file_manager_info_after = $lock_data_after['packages'][$file_manager_url];
        Assertions\assert_true($file_manager_info_after['version'] === 'v2.0.3', 'File-manager should be at v2.0.3 as required by test-runner v2.0.0');
        
        // Verify the project config reflects the correct versions
        $project_config = $temp_dir . '/phpkg.config.json';
        $project_config_data = Files\read_json_as_array($project_config);
        Assertions\assert_true(isset($project_config_data['packages'][$test_runner_url]), 'Project config should contain test-runner package');
        Assertions\assert_true($project_config_data['packages'][$test_runner_url] === 'v2.0.0', 'Project config should specify test-runner v2.0.0');
        Assertions\assert_true(isset($project_config_data['packages'][$datatype_url]), 'Project config should contain datatype package');
        Assertions\assert_true($project_config_data['packages'][$datatype_url] === 'v1.0.0', 'Project config should specify datatype v1.0.0');
        Assertions\assert_true(isset($project_config_data['packages'][$file_manager_url]), 'Project config should contain file-manager package');
        Assertions\assert_true($project_config_data['packages'][$file_manager_url] === 'v2.0.3', 'Project config should specify file-manager v2.0.3');
    },
    before: function () {
        // Create a temporary directory for the test
        $temp_dir = sys_get_temp_dir() . '/' . uniqid('phpkg_add_force_upgrade_test');
        Files\make_directory_recursively($temp_dir);
        
        // Initialize a new project
        $init_output = CliRunner\phpkg('init', ["--project=$temp_dir"]);
        Assertions\assert_true(str_contains($init_output, 'Project initialized successfully'), 'Project should be initialized. Output: ' . $init_output);
        
        // Add datatype v1.0.0
        $datatype_url = 'https://github.com/php-repos/datatype.git';
        $datatype_version = 'v1.0.0';
        
        $add_datatype_output = CliRunner\phpkg('add', [$datatype_url, "--version=$datatype_version", "--project=$temp_dir"]);
        Assertions\assert_true(str_contains($add_datatype_output, 'Package added successfully.'), 'Datatype package should be added successfully. Output: ' . $add_datatype_output);
        
        // Add file-manager v2.0.0
        $file_manager_url = 'https://github.com/php-repos/file-manager.git';
        $file_manager_version = 'v2.0.0';
        
        $add_file_manager_output = CliRunner\phpkg('add', [$file_manager_url, "--version=$file_manager_version", "--project=$temp_dir"]);
        Assertions\assert_true(str_contains($add_file_manager_output, 'Package added successfully.'), 'File-manager package should be added successfully. Output: ' . $add_file_manager_output);
        
        // Verify that file-manager v2.0.0 was added
        $lock_file = $temp_dir . '/phpkg.config-lock.json';
        $lock_data = Files\read_json_as_array($lock_file);
        
        Assertions\assert_true(isset($lock_data['packages'][$file_manager_url]), 'File-manager should be added');
        $file_manager_info = $lock_data['packages'][$file_manager_url];
        Assertions\assert_true($file_manager_info['version'] === 'v2.0.0', 'File-manager should be at version v2.0.0');
        
        return $temp_dir;
    },
    after: function (string $temp_dir) {
        // Clean up: remove temp dir
        Files\force_delete_recursive($temp_dir);
    }
);
