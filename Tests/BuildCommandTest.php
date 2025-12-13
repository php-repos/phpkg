<?php

use function PhpRepos\TestRunner\Runner\test;
use PhpRepos\Datatype\Str;
use PhpRepos\TestRunner\Assertions;
use Phpkg\InfrastructureStructure\Files;
use function Phpkg\InfrastructureStructure\Logs\notice;
use Tests\CliRunner;

/**
 * Normalizes path strings in PHP code by converting backslashes to forward slashes.
 * This ensures cross-platform compatibility since PHP accepts forward slashes on all platforms.
 * Normalizes paths in patterns like __DIR__ . '/path/to/file.php' and require_once statements.
 */
function normalize_paths_in_code(string $code): string
{
    // Replace backslashes with forward slashes in path-like strings
    // This handles:
    // - __DIR__ . '/path/to/file.php' or __DIR__ . "\path\to\file.php"
    // - require_once __DIR__ . '/path/to/file.php';
    // - 'Application\Model' => __DIR__ . '/Source/Model.php'
    return preg_replace_callback(
        "/(__DIR__\s*\.\s*['\"])([^'\"]+)(['\"])/",
        function ($matches) {
            $normalized_path = str_replace('\\', '/', $matches[2]);
            return $matches[1] . $normalized_path . $matches[3];
        },
        $code
    );
}

test(
    title: 'it should show error when building uninitialized project',
    case: function (string $temp_dir) {
        // Try to build without initializing the project
        $build_output = CliRunner\phpkg('build', ["--project=$temp_dir"]);
        
        // Should show error message about failed to build or could not read project config
        Assertions\assert_true(
            str_contains($build_output, 'Failed to build') ||
            str_contains($build_output, 'Could not read the project config') ||
            str_contains($build_output, 'Config file not found'),
            'Should show error message about failed to build or could not read project config. Output: ' . $build_output
        );
    },
    before: function () {
        // Create a temporary directory that is NOT initialized as a phpkg project
        $temp_dir = sys_get_temp_dir() . '/' . uniqid('phpkg_build_uninitialized_test');
        Files\make_directory_recursively($temp_dir);
        
        return $temp_dir;
    },
    after: function (string $temp_dir) {
        // Clean up: remove temp dir
        Files\force_delete_recursive($temp_dir);
    }
);

test(
    title: 'it should build successfully on initialized project',
    case: function (string $temp_dir) {
        // Build the initialized project
        $build_output = CliRunner\phpkg('build', ["--project=$temp_dir"]);
        
        // Should show success message
        Assertions\assert_true(
            str_contains($build_output, 'Build finished successfully') || 
            str_contains($build_output, 'successfully') ||
            str_contains($build_output, 'success'),
            'Should show success message. Output: ' . $build_output
        );
        
        // Verify build directory was created
        $build_dir = $temp_dir . '/build';
        Assertions\assert_true(is_dir($build_dir), 'Build directory should be created');
        
        // Verify phpkg.config.json was copied with same content
        $config_file = $build_dir . '/phpkg.config.json';
        Assertions\assert_true(file_exists($config_file), 'phpkg.config.json should be copied to build directory');
        
        $config_content = file_get_contents($config_file);
        $original_config = file_get_contents($temp_dir . '/phpkg.config.json');
        Assertions\assert_true($config_content === $original_config, 'Config content should match');
        
        // Verify phpkg.config-lock.json was copied with same content
        $lock_file = $build_dir . '/phpkg.config-lock.json';
        Assertions\assert_true(file_exists($lock_file), 'phpkg.config-lock.json should be copied to build directory');
        
        $lock_content = file_get_contents($lock_file);
        $original_lock = file_get_contents($temp_dir . '/phpkg.config-lock.json');
        Assertions\assert_true($lock_content === $original_lock, 'Lock content should match');
        
        // Verify phpkg.imports.php was created with correct content
        $import_file = $build_dir . '/phpkg.imports.php';
        Assertions\assert_true(file_exists($import_file), 'phpkg.imports.php file should be created');
        
        $import_content = file_get_contents($import_file);
        $expected_content = <<<'EOD'
<?php

spl_autoload_register(function ($class) {
    $classes = [
    ];

    if (array_key_exists($class, $classes)) {
        require $classes[$class];
    }

}, true, true);

spl_autoload_register(function ($class) {
    $namespaces = [
    ];

    $realpath = null;

    foreach ($namespaces as $namespace => $path) {
        if (str_starts_with($class, $namespace)) {
            $pos = strpos($class, $namespace);
            if ($pos !== false) {
                $realpath = substr_replace($class, $path, $pos, strlen($namespace));
            }
            $realpath = str_replace("\\", DIRECTORY_SEPARATOR, $realpath) . '.php';
            if (file_exists($realpath)) {
                require $realpath;
            }

            return ;
        }
    }
});

EOD;
        
        Str\assert_equal($import_content, $expected_content);
    },
    before: function () {
        // Create a temporary directory and initialize it as a phpkg project
        $temp_dir = sys_get_temp_dir() . '/' . uniqid('phpkg_build_initialized_test');
        Files\make_directory_recursively($temp_dir);
        
        // Initialize the project
        $init_output = CliRunner\phpkg('init', ["--project=$temp_dir"]);
        Assertions\assert_true(
            str_contains($init_output, 'Project has been initialized') || 
            str_contains($init_output, 'initialized'),
            'Project should be initialized. Output: ' . $init_output
        );
        
        return $temp_dir;
    },
    after: function (string $temp_dir) {
        // Clean up: remove temp dir
        Files\force_delete_recursive($temp_dir);
    }
);

test(
    title: 'it should respect custom import file configuration',
    case: function (string $temp_dir) {
        // Build the project with custom import file configuration
        $build_output = CliRunner\phpkg('build', ["--project=$temp_dir"]);
        
        // Should show success message
        Assertions\assert_true(
            str_contains($build_output, 'Build finished successfully') || 
            str_contains($build_output, 'successfully') ||
            str_contains($build_output, 'success'),
            'Should show success message. Output: ' . $build_output
        );
        
        // Verify build directory was created
        $build_dir = $temp_dir . '/build';
        Assertions\assert_true(is_dir($build_dir), 'Build directory should be created');
        
        // Verify phpkg.imports.php was NOT created (should use custom import file name)
        $default_import_file = $build_dir . '/phpkg.imports.php';
        Assertions\assert_true(!file_exists($default_import_file), 'Default phpkg.imports.php should not be created');
        
        // Verify vendor directory was created
        $vendor_dir = $build_dir . '/vendor';
        Assertions\assert_true(is_dir($vendor_dir), 'Vendor directory should be created');
        
        // Verify custom autoload.php was created with correct content
        $custom_import_file = $vendor_dir . '/autoload.php';
        Assertions\assert_true(file_exists($custom_import_file), 'Custom autoload.php file should be created');
        
        $import_content = file_get_contents($custom_import_file);
        $expected_content = <<<'EOD'
<?php

spl_autoload_register(function ($class) {
    $classes = [
    ];

    if (array_key_exists($class, $classes)) {
        require $classes[$class];
    }

}, true, true);

spl_autoload_register(function ($class) {
    $namespaces = [
    ];

    $realpath = null;

    foreach ($namespaces as $namespace => $path) {
        if (str_starts_with($class, $namespace)) {
            $pos = strpos($class, $namespace);
            if ($pos !== false) {
                $realpath = substr_replace($class, $path, $pos, strlen($namespace));
            }
            $realpath = str_replace("\\", DIRECTORY_SEPARATOR, $realpath) . '.php';
            if (file_exists($realpath)) {
                require $realpath;
            }

            return ;
        }
    }
});

EOD;
        
        Str\assert_equal($import_content, $expected_content);
    },
    before: function () {
        // Create a temporary directory and initialize it as a phpkg project
        $temp_dir = sys_get_temp_dir() . '/' . uniqid('phpkg_build_custom_import_test');
        Files\make_directory_recursively($temp_dir);
        
        // Initialize the project
        $init_output = CliRunner\phpkg('init', ["--project=$temp_dir"]);
        Assertions\assert_true(
            str_contains($init_output, 'Project has been initialized') || 
            str_contains($init_output, 'initialized'),
            'Project should be initialized. Output: ' . $init_output
        );
        
        // Update config to have custom import file configuration
        $config_file = $temp_dir . '/phpkg.config.json';
        $config = Files\read_json_as_array($config_file);
        $config['import-file'] = 'vendor/autoload.php';
        Files\save_array_as_json($config_file, $config);
        
        return $temp_dir;
    },
    after: function (string $temp_dir) {
        // Clean up: remove temp dir
        Files\force_delete_recursive($temp_dir);
    }
);

test(
    title: 'it should handle custom namespace mapping correctly',
    case: function (string $temp_dir) {
        // Build the project with custom namespace mapping
        $build_output = CliRunner\phpkg('build', ["--project=$temp_dir"]);
        
        // Should show success message
        Assertions\assert_true(
            str_contains($build_output, 'Build finished successfully') || 
            str_contains($build_output, 'successfully') ||
            str_contains($build_output, 'success'),
            'Should show success message. Output: ' . $build_output
        );
        
        // Verify build directory was created
        $build_dir = $temp_dir . '/build';
        Assertions\assert_true(is_dir($build_dir), 'Build directory should be created');
        
        // Verify the Source directory structure is preserved
        $source_dir = $build_dir . '/Source';
        Assertions\assert_true(is_dir($source_dir), 'Source directory should be preserved');
        
        // Verify Model.php was copied with same content
        $model_file = $source_dir . '/Model.php';
        Assertions\assert_true(file_exists($model_file), 'Model.php should be copied to Source directory');
        
        $model_content = file_get_contents($model_file);
        $original_model = file_get_contents($temp_dir . '/Source/Model.php');
        Str\assert_equal($model_content, $original_model);
        
        // Verify Service.php was copied with same content
        $service_file = $source_dir . '/Service.php';
        Assertions\assert_true(file_exists($service_file), 'Service.php should be copied to Source directory');
        
        $service_content = file_get_contents($service_file);
        $original_service = file_get_contents($temp_dir . '/Source/Service.php');
        Str\assert_equal($service_content, $original_service);
        
        // Verify phpkg.imports.php was created with correct namespace mapping
        $import_file = $build_dir . '/phpkg.imports.php';
        Assertions\assert_true(file_exists($import_file), 'phpkg.imports.php file should be created');
        
        $import_content = file_get_contents($import_file);
        $expected_content = <<<'EOD'
<?php

spl_autoload_register(function ($class) {
    $classes = [
    ];

    if (array_key_exists($class, $classes)) {
        require $classes[$class];
    }

}, true, true);

spl_autoload_register(function ($class) {
    $namespaces = [
        'Application' => __DIR__ . '/Source',
    ];

    $realpath = null;

    foreach ($namespaces as $namespace => $path) {
        if (str_starts_with($class, $namespace)) {
            $pos = strpos($class, $namespace);
            if ($pos !== false) {
                $realpath = substr_replace($class, $path, $pos, strlen($namespace));
            }
            $realpath = str_replace("\\", DIRECTORY_SEPARATOR, $realpath) . '.php';
            if (file_exists($realpath)) {
                require $realpath;
            }

            return ;
        }
    }
});

EOD;
        
        Str\assert_equal($import_content, $expected_content);
    },
    before: function () {
        // Create a temporary directory and initialize it as a phpkg project
        $temp_dir = sys_get_temp_dir() . '/' . uniqid('phpkg_build_namespace_mapping_test');
        Files\make_directory_recursively($temp_dir);
        
        // Initialize the project
        $init_output = CliRunner\phpkg('init', ["--project=$temp_dir"]);
        Assertions\assert_true(
            str_contains($init_output, 'Project has been initialized') || 
            str_contains($init_output, 'initialized'),
            'Project should be initialized. Output: ' . $init_output
        );
        
        // Update config to have custom namespace mapping
        $config_file = $temp_dir . '/phpkg.config.json';
        $config = Files\read_json_as_array($config_file);
        $config['map'] = ['Application' => 'Source'];
        Files\save_array_as_json($config_file, $config);
        
        // Create Source directory with Model.php
        $source_dir = $temp_dir . '/Source';
        Files\make_directory_recursively($source_dir);
        
        $model_content = <<<'PHP'
<?php

namespace Application;

class Model {
    // Model implementation
}
PHP;
        Files\file_write($source_dir . '/Model.php', $model_content);
        
        // Create Service.php
        $service_content = <<<'PHP'
<?php

namespace Application\Service;

function run(): void {
    // Service implementation
}
PHP;
        Files\file_write($source_dir . '/Service.php', $service_content);
        
        return $temp_dir;
    },
    after: function (string $temp_dir) {
        // Clean up: remove temp dir
        Files\force_delete_recursive($temp_dir);
    }
);

test(
    title: 'it should inject require_once for function dependencies',
    case: function (string $temp_dir) {
        // Build the project with function dependencies
        $build_output = CliRunner\phpkg('build', ["--project=$temp_dir"]);
        
        // Should show success message
        Assertions\assert_true(
            str_contains($build_output, 'Build finished successfully') || 
            str_contains($build_output, 'successfully') ||
            str_contains($build_output, 'success'),
            'Should show success message. Output: ' . $build_output
        );
        
        // Verify build directory was created
        $build_dir = $temp_dir . '/build';
        Assertions\assert_true(is_dir($build_dir), 'Build directory should be created');
        
        // Verify the Source directory structure is preserved
        $source_dir = $build_dir . '/Source';
        Assertions\assert_true(is_dir($source_dir), 'Source directory should be preserved');
        
        // Verify Model.php was copied and modified with require_once injection
        $model_file = $source_dir . '/Model.php';
        Assertions\assert_true(file_exists($model_file), 'Model.php should be copied to Source directory');
        
        $model_content = file_get_contents($model_file);
        $expected_content = <<<'EOD'
<?php

namespace Application;require_once __DIR__ . '/Service.php';

use function Application\Service\run;

class Model
{
    public function use_service()
    {
        run();
    }
}
EOD;

        Str\assert_equal($model_content, $expected_content);
        
        // Verify Service.php was copied with same content
        $service_file = $source_dir . '/Service.php';
        Assertions\assert_true(file_exists($service_file), 'Service.php should be copied to Source directory');
        
        $service_content = file_get_contents($service_file);
        $original_service = file_get_contents($temp_dir . '/Source/Service.php');
        Str\assert_equal($service_content, $original_service);
        
        // Verify phpkg.imports.php was created with correct namespace mapping
        $import_file = $build_dir . '/phpkg.imports.php';
        Assertions\assert_true(file_exists($import_file), 'phpkg.imports.php file should be created');
        
        $import_content = file_get_contents($import_file);
        $expected_import_content = <<<'EOD'
<?php

spl_autoload_register(function ($class) {
    $classes = [
    ];

    if (array_key_exists($class, $classes)) {
        require $classes[$class];
    }

}, true, true);

spl_autoload_register(function ($class) {
    $namespaces = [
        'Application' => __DIR__ . '/Source',
    ];

    $realpath = null;

    foreach ($namespaces as $namespace => $path) {
        if (str_starts_with($class, $namespace)) {
            $pos = strpos($class, $namespace);
            if ($pos !== false) {
                $realpath = substr_replace($class, $path, $pos, strlen($namespace));
            }
            $realpath = str_replace("\\", DIRECTORY_SEPARATOR, $realpath) . '.php';
            if (file_exists($realpath)) {
                require $realpath;
            }

            return ;
        }
    }
});

EOD;
        
        Str\assert_equal(normalize_paths_in_code($import_content), normalize_paths_in_code($expected_import_content));
    },
    before: function () {
        // Create a temporary directory and initialize it as a phpkg project
        $temp_dir = sys_get_temp_dir() . '/' . uniqid('phpkg_build_function_dependency_test');
        Files\make_directory_recursively($temp_dir);
        
        // Initialize the project
        $init_output = CliRunner\phpkg('init', ["--project=$temp_dir"]);
        Assertions\assert_true(
            str_contains($init_output, 'Project has been initialized') || 
            str_contains($init_output, 'initialized'),
            'Project should be initialized. Output: ' . $init_output
        );
        
        // Update config to have custom namespace mapping
        $config_file = $temp_dir . '/phpkg.config.json';
        $config = Files\read_json_as_array($config_file);
        $config['map'] = ['Application' => 'Source'];
        Files\save_array_as_json($config_file, $config);
        
        // Create Source directory with Model.php that has function dependency
        $source_dir = $temp_dir . '/Source';
        Files\make_directory_recursively($source_dir);
        
        $model_content = <<<'PHP'
<?php

namespace Application;

use function Application\Service\run;

class Model
{
    public function use_service()
    {
        run();
    }
}
PHP;
        Files\file_write($source_dir . '/Model.php', $model_content);
        
        // Create Service.php
        $service_content = <<<'PHP'
<?php

namespace Application\Service;

function run(): void {
    // Service implementation
}
PHP;
        Files\file_write($source_dir . '/Service.php', $service_content);
        
        return $temp_dir;
    },
    after: function (string $temp_dir) {
        // Clean up: remove temp dir
        Files\force_delete_recursive($temp_dir);
    }
);

test(
    title: 'it should handle class dependencies correctly',
    case: function (string $temp_dir) {
        // Build the project with class dependencies
        $build_output = CliRunner\phpkg('build', ["--project=$temp_dir"]);
        
        // Should show success message
        Assertions\assert_true(
            str_contains($build_output, 'Build finished successfully') || 
            str_contains($build_output, 'successfully') ||
            str_contains($build_output, 'success'),
            'Should show success message. Output: ' . $build_output
        );
        
        // Verify build directory was created
        $build_dir = $temp_dir . '/build';
        Assertions\assert_true(is_dir($build_dir), 'Build directory should be created');
        
        // Verify the Source directory structure is preserved
        $source_dir = $build_dir . '/Source';
        Assertions\assert_true(is_dir($source_dir), 'Source directory should be preserved');
        
        // Verify ModelDto.php was copied with same content
        $model_dto_file = $source_dir . '/DTO/ModelDto.php';
        Assertions\assert_true(file_exists($model_dto_file), 'ModelDto.php should be copied to Source/DTO directory');
        
        $model_dto_content = file_get_contents($model_dto_file);
        $original_model_dto = file_get_contents($temp_dir . '/Source/DTO/ModelDto.php');
        Str\assert_equal($model_dto_content, $original_model_dto);
        
        // Verify Model.php was copied with same content
        $model_file = $source_dir . '/Model.php';
        Assertions\assert_true(file_exists($model_file), 'Model.php should be copied to Source directory');
        
        $model_content = file_get_contents($model_file);
        $original_model = file_get_contents($temp_dir . '/Source/Model.php');
        Str\assert_equal($model_content, $original_model);
        
        // Verify phpkg.imports.php was created with correct class and namespace mappings
        $import_file = $build_dir . '/phpkg.imports.php';
        Assertions\assert_true(file_exists($import_file), 'phpkg.imports.php file should be created');
        
        $import_content = file_get_contents($import_file);
        $expected_import_content = <<<'EOD'
<?php

spl_autoload_register(function ($class) {
    $classes = [
        'Application\Model' => __DIR__ . '/Source/Model.php',
    ];

    if (array_key_exists($class, $classes)) {
        require $classes[$class];
    }

}, true, true);

spl_autoload_register(function ($class) {
    $namespaces = [
        'Application' => __DIR__ . '/Source',
    ];

    $realpath = null;

    foreach ($namespaces as $namespace => $path) {
        if (str_starts_with($class, $namespace)) {
            $pos = strpos($class, $namespace);
            if ($pos !== false) {
                $realpath = substr_replace($class, $path, $pos, strlen($namespace));
            }
            $realpath = str_replace("\\", DIRECTORY_SEPARATOR, $realpath) . '.php';
            if (file_exists($realpath)) {
                require $realpath;
            }

            return ;
        }
    }
});

EOD;
        
        Str\assert_equal(normalize_paths_in_code($import_content), normalize_paths_in_code($expected_import_content));
    },
    before: function () {
        // Create a temporary directory and initialize it as a phpkg project
        $temp_dir = sys_get_temp_dir() . '/' . uniqid('phpkg_build_class_dependency_test');
        Files\make_directory_recursively($temp_dir);
        
        // Initialize the project
        $init_output = CliRunner\phpkg('init', ["--project=$temp_dir"]);
        Assertions\assert_true(
            str_contains($init_output, 'Project has been initialized') || 
            str_contains($init_output, 'initialized'),
            'Project should be initialized. Output: ' . $init_output
        );
        
        // Update config to have custom namespace mapping
        $config_file = $temp_dir . '/phpkg.config.json';
        $config = Files\read_json_as_array($config_file);
        $config['map'] = ['Application' => 'Source'];
        Files\save_array_as_json($config_file, $config);
        
        // Create Source directory with Model.php
        $source_dir = $temp_dir . '/Source';
        Files\make_directory_recursively($source_dir);
        
        $model_content = <<<'PHP'
<?php

namespace Application;

class Model
{
    // Model implementation
}
PHP;
        Files\file_write($source_dir . '/Model.php', $model_content);
        
        // Create DTO directory with ModelDto.php that has class dependency
        $dto_dir = $source_dir . '/DTO';
        Files\make_directory_recursively($dto_dir);
        
        $model_dto_content = <<<'PHP'
<?php

namespace Application\DTO;

use Application\Model;

class ModelDto
{
    public function __construct(public readonly Model $model)
    {
    }
}
PHP;
        Files\file_write($dto_dir . '/ModelDto.php', $model_dto_content);
        
        return $temp_dir;
    },
    after: function (string $temp_dir) {
        // Clean up: remove temp dir
        Files\force_delete_recursive($temp_dir);
    }
);

test(
    title: 'it should handle multiple namespace mappings and complex dependencies',
    case: function (string $temp_dir) {
        // Build the project with multiple namespace mappings and complex dependencies
        $build_output = CliRunner\phpkg('build', ["--project=$temp_dir"]);
        
        // Should show success message
        Assertions\assert_true(
            str_contains($build_output, 'Build finished successfully') || 
            str_contains($build_output, 'successfully') ||
            str_contains($build_output, 'success'),
            'Should show success message. Output: ' . $build_output
        );
        
        // Verify build directory was created
        $build_dir = $temp_dir . '/build';
        Assertions\assert_true(is_dir($build_dir), 'Build directory should be created');
        
        // Verify the Source directory structure is preserved
        $source_dir = $build_dir . '/Source';
        Assertions\assert_true(is_dir($source_dir), 'Source directory should be preserved');
        
        // Verify the External/ThirdParties directory structure is preserved
        $third_party_dir = $build_dir . '/External/ThirdParties';
        Assertions\assert_true(is_dir($third_party_dir), 'External/ThirdParties directory should be preserved');
        
        // Verify Model.php was copied and modified with require_once injection
        $model_file = $source_dir . '/Model.php';
        Assertions\assert_true(file_exists($model_file), 'Model.php should be copied to Source directory');
        
        $model_content = file_get_contents($model_file);
        $expected_model_content = <<<'EOD'
<?php

namespace Application;require_once __DIR__ . '/Service.php';require_once __DIR__ . '/../External/ThirdParties/Helper.php';

use function Application\Service\run;
use function ThirdParty\Helper\run_api;

class Model
{
    public function use_service()
    {
        run();
    }
    
    public function convert()
    {
        run_api();
    }
}
EOD;
        
        Str\assert_equal(normalize_paths_in_code($model_content), normalize_paths_in_code($expected_model_content));
        
        // Verify ModelDto.php was copied and modified with require_once injection
        $model_dto_file = $source_dir . '/DTO/ModelDto.php';
        Assertions\assert_true(file_exists($model_dto_file), 'ModelDto.php should be copied to Source/DTO directory');
        
        $model_dto_content = file_get_contents($model_dto_file);
        $expected_model_dto_content = <<<'EOD'
<?php

namespace Application\DTO;require_once __DIR__ . '/../../External/ThirdParties/Helper.php';

use Application\Model;
use ThirdParty\Helper;

class ModelDto
{
    public function __construct(public readonly Model $model)
    {
    }
    
    public function get_third_party()
    {
        Helper\run_api();
    }
}
EOD;
        
        Str\assert_equal(normalize_paths_in_code($model_dto_content), normalize_paths_in_code($expected_model_dto_content));
        
        // Verify Service.php was copied with same content
        $service_file = $source_dir . '/Service.php';
        Assertions\assert_true(file_exists($service_file), 'Service.php should be copied to Source directory');
        
        $service_content = file_get_contents($service_file);
        $original_service = file_get_contents($temp_dir . '/Source/Service.php');
        Str\assert_equal($service_content, $original_service);
        
        // Verify Response.php was copied with same content
        $response_file = $third_party_dir . '/Response.php';
        Assertions\assert_true(file_exists($response_file), 'Response.php should be copied to External/ThirdParties directory');
        
        $response_content = file_get_contents($response_file);
        $original_response = file_get_contents($temp_dir . '/External/ThirdParties/Response.php');
        Str\assert_equal($response_content, $original_response);
        
        // Verify Helper.php was copied with same content
        $helper_file = $third_party_dir . '/Helper.php';
        Assertions\assert_true(file_exists($helper_file), 'Helper.php should be copied to External/ThirdParties directory');
        
        $helper_content = file_get_contents($helper_file);
        $original_helper = file_get_contents($temp_dir . '/External/ThirdParties/Helper.php');
        Str\assert_equal($helper_content, $original_helper);
        
        // Verify phpkg.imports.php was created with correct class and namespace mappings
        $import_file = $build_dir . '/phpkg.imports.php';
        Assertions\assert_true(file_exists($import_file), 'phpkg.imports.php file should be created');
        
        $import_content = file_get_contents($import_file);
        $expected_import_content = <<<'EOD'
<?php

spl_autoload_register(function ($class) {
    $classes = [
        'Application\Model' => __DIR__ . '/Source/Model.php',
    ];

    if (array_key_exists($class, $classes)) {
        require $classes[$class];
    }

}, true, true);

spl_autoload_register(function ($class) {
    $namespaces = [
        'Application' => __DIR__ . '/Source',
        'ThirdParty' => __DIR__ . '/External/ThirdParties',
    ];

    $realpath = null;

    foreach ($namespaces as $namespace => $path) {
        if (str_starts_with($class, $namespace)) {
            $pos = strpos($class, $namespace);
            if ($pos !== false) {
                $realpath = substr_replace($class, $path, $pos, strlen($namespace));
            }
            $realpath = str_replace("\\", DIRECTORY_SEPARATOR, $realpath) . '.php';
            if (file_exists($realpath)) {
                require $realpath;
            }

            return ;
        }
    }
});

EOD;
        
        Str\assert_equal(normalize_paths_in_code($import_content), normalize_paths_in_code($expected_import_content));
    },
    before: function () {
        // Create a temporary directory and initialize it as a phpkg project
        $temp_dir = sys_get_temp_dir() . '/' . uniqid('phpkg_build_multiple_mappings_test');
        Files\make_directory_recursively($temp_dir);
        
        // Initialize the project
        $init_output = CliRunner\phpkg('init', ["--project=$temp_dir"]);
        Assertions\assert_true(
            str_contains($init_output, 'Project has been initialized') || 
            str_contains($init_output, 'initialized'),
            'Project should be initialized. Output: ' . $init_output
        );
        
        // Update config to have multiple namespace mappings
        $config_file = $temp_dir . '/phpkg.config.json';
        $config = Files\read_json_as_array($config_file);
        $config['map'] = [
            'Application' => 'Source',
            'ThirdParty' => 'External/ThirdParties'
        ];
        Files\save_array_as_json($config_file, $config);
        
        // Create Source directory with Model.php that has function dependencies
        $source_dir = $temp_dir . '/Source';
        Files\make_directory_recursively($source_dir);
        
        $model_content = <<<'PHP'
<?php

namespace Application;

use function Application\Service\run;
use function ThirdParty\Helper\run_api;

class Model
{
    public function use_service()
    {
        run();
    }
    
    public function convert()
    {
        run_api();
    }
}
PHP;
        Files\file_write($source_dir . '/Model.php', $model_content);
        
        // Create Service.php
        $service_content = <<<'PHP'
<?php

namespace Application\Service;

function run(): void {
    // Service implementation
}
PHP;
        Files\file_write($source_dir . '/Service.php', $service_content);
        
        // Create DTO directory with ModelDto.php that has class and function dependencies
        $dto_dir = $source_dir . '/DTO';
        Files\make_directory_recursively($dto_dir);
        
        $model_dto_content = <<<'PHP'
<?php

namespace Application\DTO;

use Application\Model;
use ThirdParty\Helper;

class ModelDto
{
    public function __construct(public readonly Model $model)
    {
    }
    
    public function get_third_party()
    {
        Helper\run_api();
    }
}
PHP;
        Files\file_write($dto_dir . '/ModelDto.php', $model_dto_content);
        
        // Create External/ThirdParties directory with Response.php and Helper.php
        $external_dir = $temp_dir . '/External';
        $third_parties_dir = $external_dir . '/ThirdParties';
        Files\make_directory_recursively($third_parties_dir);
        
        $response_content = <<<'PHP'
<?php

namespace ThirdParty;

class Response
{

}
PHP;
        Files\file_write($third_parties_dir . '/Response.php', $response_content);
        
        $helper_content = <<<'PHP'
<?php

namespace ThirdParty\Helper;

function run_api()
{
    
}
PHP;
        Files\file_write($third_parties_dir . '/Helper.php', $helper_content);
        
        return $temp_dir;
    },
    after: function (string $temp_dir) {
        // Clean up: remove temp dir
        Files\force_delete_recursive($temp_dir);
    }
);

test(
    title: 'it should handle entry points correctly',
    case: function (string $temp_dir) {
        // Build the project with entry point configuration
        $build_output = CliRunner\phpkg('build', ["--project=$temp_dir"]);
        
        // Should show success message
        Assertions\assert_true(
            str_contains($build_output, 'Build finished successfully') || 
            str_contains($build_output, 'successfully') ||
            str_contains($build_output, 'success'),
            'Should show success message. Output: ' . $build_output
        );
        
        // Verify build directory was created
        $build_dir = $temp_dir . '/build';
        Assertions\assert_true(is_dir($build_dir), 'Build directory should be created');
        
        // Verify the public directory structure is preserved
        $public_dir = $build_dir . '/public';
        Assertions\assert_true(is_dir($public_dir), 'Public directory should be preserved');
        
        // Verify index.php was copied and modified with require_once injection for the import file
        $index_file = $public_dir . '/index.php';
        Assertions\assert_true(file_exists($index_file), 'index.php should be copied to public directory');
        
        $index_content = file_get_contents($index_file);
        $expected_index_content = <<<'EOD'
<?php
require_once __DIR__ . '/../phpkg.imports.php';
echo "Hello World!";
EOD;
        
        Str\assert_equal(normalize_paths_in_code($index_content), normalize_paths_in_code($expected_index_content));
        
        // Verify phpkg.imports.php was created with correct content (no entry point import needed)
        $import_file = $build_dir . '/phpkg.imports.php';
        Assertions\assert_true(file_exists($import_file), 'phpkg.imports.php file should be created');
        
        $import_content = file_get_contents($import_file);
        $expected_import_content = <<<'EOD'
<?php

spl_autoload_register(function ($class) {
    $classes = [
    ];

    if (array_key_exists($class, $classes)) {
        require $classes[$class];
    }

}, true, true);

spl_autoload_register(function ($class) {
    $namespaces = [
    ];

    $realpath = null;

    foreach ($namespaces as $namespace => $path) {
        if (str_starts_with($class, $namespace)) {
            $pos = strpos($class, $namespace);
            if ($pos !== false) {
                $realpath = substr_replace($class, $path, $pos, strlen($namespace));
            }
            $realpath = str_replace("\\", DIRECTORY_SEPARATOR, $realpath) . '.php';
            if (file_exists($realpath)) {
                require $realpath;
            }

            return ;
        }
    }
});

EOD;
        
        Str\assert_equal(normalize_paths_in_code($import_content), normalize_paths_in_code($expected_import_content));
    },
    before: function () {
        // Create a temporary directory and initialize it as a phpkg project
        $temp_dir = sys_get_temp_dir() . '/' . uniqid('phpkg_build_entry_point_test');
        Files\make_directory_recursively($temp_dir);
        
        // Initialize the project
        $init_output = CliRunner\phpkg('init', ["--project=$temp_dir"]);
        Assertions\assert_true(
            str_contains($init_output, 'Project has been initialized') || 
            str_contains($init_output, 'initialized'),
            'Project should be initialized. Output: ' . $init_output
        );
        
        // Update config to have entry point configuration
        $config_file = $temp_dir . '/phpkg.config.json';
        $config = Files\read_json_as_array($config_file);
        $config['entry-points'] = ['public/index.php'];
        Files\save_array_as_json($config_file, $config);
        
        // Create public directory with index.php
        $public_dir = $temp_dir . '/public';
        Files\make_directory_recursively($public_dir);
        
        $index_content = <<<'PHP'
<?php

echo "Hello World!";
PHP;
        Files\file_write($public_dir . '/index.php', $index_content);
        
        return $temp_dir;
    },
    after: function (string $temp_dir) {
        // Clean up: remove temp dir
        Files\force_delete_recursive($temp_dir);
    }
);

test(
    title: 'it should exclude excludes from config',
    case: function (string $temp_dir) {
        // Build the project with excludes configuration
        $build_output = CliRunner\phpkg('build', ["--project=$temp_dir"]);
        
        // Should show success message
        Assertions\assert_true(
            str_contains($build_output, 'Build finished successfully') || 
            str_contains($build_output, 'successfully') ||
            str_contains($build_output, 'success'),
            'Should show success message. Output: ' . $build_output
        );
        
        // Verify build directory was created
        $build_dir = $temp_dir . '/build';
        Assertions\assert_true(is_dir($build_dir), 'Build directory should be created');
        
        // Verify the Source directory structure is preserved
        $source_dir = $build_dir . '/Source';
        Assertions\assert_true(is_dir($source_dir), 'Source directory should be preserved');
        
        // Verify ExcludedFile.php was NOT copied (completely excluded from build)
        $excluded_file = $source_dir . '/ExcludedFile.php';
        Assertions\assert_true(!file_exists($excluded_file), 'ExcludedFile.php should NOT be copied to Source directory when excluded');
        
        // Verify Service.php was copied with same content
        $service_file = $source_dir . '/Service.php';
        Assertions\assert_true(file_exists($service_file), 'Service.php should be copied to Source directory');
        
        $service_content = file_get_contents($service_file);
        $original_service = file_get_contents($temp_dir . '/Source/Service.php');
        Str\assert_equal($service_content, $original_service);
        
        // Verify phpkg.imports.php was created with correct content
        $import_file = $build_dir . '/phpkg.imports.php';
        Assertions\assert_true(file_exists($import_file), 'phpkg.imports.php file should be created');
        
        $import_content = file_get_contents($import_file);
        $expected_import_content = <<<'EOD'
<?php

spl_autoload_register(function ($class) {
    $classes = [
    ];

    if (array_key_exists($class, $classes)) {
        require $classes[$class];
    }

}, true, true);

spl_autoload_register(function ($class) {
    $namespaces = [
        'Application' => __DIR__ . '/Source',
    ];

    $realpath = null;

    foreach ($namespaces as $namespace => $path) {
        if (str_starts_with($class, $namespace)) {
            $pos = strpos($class, $namespace);
            if ($pos !== false) {
                $realpath = substr_replace($class, $path, $pos, strlen($namespace));
            }
            $realpath = str_replace("\\", DIRECTORY_SEPARATOR, $realpath) . '.php';
            if (file_exists($realpath)) {
                require $realpath;
            }

            return ;
        }
    }
});

EOD;
        
        Str\assert_equal(normalize_paths_in_code($import_content), normalize_paths_in_code($expected_import_content));
    },
    before: function () {
        // Create a temporary directory and initialize it as a phpkg project
        $temp_dir = sys_get_temp_dir() . '/' . uniqid('phpkg_build_excludes_test');
        Files\make_directory_recursively($temp_dir);
        
        // Initialize the project
        $init_output = CliRunner\phpkg('init', ["--project=$temp_dir"]);
        Assertions\assert_true(
            str_contains($init_output, 'Project has been initialized') || 
            str_contains($init_output, 'initialized'),
            'Project should be initialized. Output: ' . $init_output
        );
        
        // Update config to have custom namespace mapping and excludes
        $config_file = $temp_dir . '/phpkg.config.json';
        $config = Files\read_json_as_array($config_file);
        $config['map'] = ['Application' => 'Source'];
        $config['excludes'] = ['Source/ExcludedFile.php'];
        Files\save_array_as_json($config_file, $config);
        
        // Create Source directory with ExcludedFile.php that has function dependency
        $source_dir = $temp_dir . '/Source';
        Files\make_directory_recursively($source_dir);
        
        $excluded_content = <<<'PHP'
<?php

namespace Application;

use function Application\Service\run;

class ExcludedFile
{
    public function do_something()
    {
        run();
    }
}
PHP;
        Files\file_write($source_dir . '/ExcludedFile.php', $excluded_content);
        
        // Create Service.php
        $service_content = <<<'PHP'
<?php

namespace Application\Service;

function run(): void {
    // Service implementation
}
PHP;
        Files\file_write($source_dir . '/Service.php', $service_content);
        
        return $temp_dir;
    },
    after: function (string $temp_dir) {
        // Clean up: remove temp dir
        Files\force_delete_recursive($temp_dir);
    }
);

test(
    title: 'it should handle package dependencies correctly',
    case: function (string $temp_dir) {
        // Build the project with package dependencies
        $build_output = CliRunner\phpkg('build', ["--project=$temp_dir"]);
        
        // Should show success message
        Assertions\assert_true(
            str_contains($build_output, 'Build finished successfully') || 
            str_contains($build_output, 'successfully') ||
            str_contains($build_output, 'success'),
            'Should show success message. Output: ' . $build_output
        );
        
        // Verify build directory was created
        $build_dir = $temp_dir . '/build';
        Assertions\assert_true(is_dir($build_dir), 'Build directory should be created');
        
        // Verify the Source directory structure is preserved
        $source_dir = $build_dir . '/Source';
        Assertions\assert_true(is_dir($source_dir), 'Source directory should be created');
        
        // Verify the Packages directory structure is preserved
        $packages_dir = $build_dir . '/Packages';
        Assertions\assert_true(is_dir($packages_dir), 'Packages directory should be created');
        
        $test_package_dir = $packages_dir . '/test-owner/test-repo';
        Assertions\assert_true(is_dir($test_package_dir), 'Test package directory should be created');
        
        // Verify package Source directory is preserved
        $package_source_dir = $test_package_dir . '/Source';
        Assertions\assert_true(is_dir($package_source_dir), 'Package Source directory should be created');
        
        // Verify package files were copied
        $package_class_file = $package_source_dir . '/TestClass.php';
        Assertions\assert_true(file_exists($package_class_file), 'Package TestClass.php should be copied');
        
        $package_function_file = $package_source_dir . '/TestHelpers.php';
        Assertions\assert_true(file_exists($package_function_file), 'Package TestHelpers.php should be copied');
        
        // Verify package TestClass.php was copied and modified with require_once injection
        $package_class_content = file_get_contents($package_class_file);
        $expected_package_class_content = <<<'EOD'
<?php

namespace Repo;require_once __DIR__ . '/TestHelpers.php';

use function Repo\TestHelpers\test_function;

class TestClass
{
    public function __construct()
    {
        // Test class implementation
    }
    
    public function use_helper()
    {
        test_function();
    }
}
EOD;
        
        Str\assert_equal(normalize_paths_in_code($package_class_content), normalize_paths_in_code($expected_package_class_content));
        
        // Verify main project Model.php was copied and modified with require_once injection
        $model_file = $source_dir . '/Model.php';
        Assertions\assert_true(file_exists($model_file), 'Model.php should be copied to Source directory');
        
        $model_content = file_get_contents($model_file);
        $expected_model_content = <<<'EOD'
<?php

namespace Application;require_once __DIR__ . '/../Packages/test-owner/test-repo/Source/TestHelpers.php';

use Repo\TestClass;
use function Repo\TestHelpers\test_function;

class Model
{
    public function use_package()
    {
        $test = new TestClass();
        test_function();
    }
}
EOD;
        
        Str\assert_equal(normalize_paths_in_code($model_content), normalize_paths_in_code($expected_model_content));
        
        // Verify phpkg.imports.php was created with correct package namespace and class mappings
        $import_file = $build_dir . '/phpkg.imports.php';
        Assertions\assert_true(file_exists($import_file), 'phpkg.imports.php file should be created');
        
        $import_content = file_get_contents($import_file);
        $expected_import_content = <<<'EOD'
<?php

spl_autoload_register(function ($class) {
    $classes = [
        'Repo\TestClass' => __DIR__ . '/Packages/test-owner/test-repo/Source/TestClass.php',
    ];

    if (array_key_exists($class, $classes)) {
        require $classes[$class];
    }

}, true, true);

spl_autoload_register(function ($class) {
    $namespaces = [
        'Repo' => __DIR__ . '/Packages/test-owner/test-repo/Source',
        'Application' => __DIR__ . '/Source',
    ];

    $realpath = null;

    foreach ($namespaces as $namespace => $path) {
        if (str_starts_with($class, $namespace)) {
            $pos = strpos($class, $namespace);
            if ($pos !== false) {
                $realpath = substr_replace($class, $path, $pos, strlen($namespace));
            }
            $realpath = str_replace("\\", DIRECTORY_SEPARATOR, $realpath) . '.php';
            if (file_exists($realpath)) {
                require $realpath;
            }

            return ;
        }
    }
});

EOD;
        
        Str\assert_equal(normalize_paths_in_code($import_content), normalize_paths_in_code($expected_import_content));
    },
    before: function () {
        // Create a temporary directory and initialize it as a phpkg project
        $temp_dir = sys_get_temp_dir() . '/' . uniqid('phpkg_build_package_test');
        Files\make_directory_recursively($temp_dir);
        
        // Initialize the project
        $init_output = CliRunner\phpkg('init', ["--project=$temp_dir"]);
        Assertions\assert_true(
            str_contains($init_output, 'Project has been initialized') || 
            str_contains($init_output, 'initialized'),
            'Project should be initialized. Output: ' . $init_output
        );
        
        // Update config to have custom namespace mapping and package dependency
        $config_file = $temp_dir . '/phpkg.config.json';
        $config = Files\read_json_as_array($config_file);
        $config['map'] = ['Application' => 'Source'];
        $config['packages'] = ['https://github.com/test-owner/test-repo.git' => 'v1.0.0'];
        Files\save_array_as_json($config_file, $config);
        
        // Update lock file to include package information
        $lock_file = $temp_dir . '/phpkg.config-lock.json';
        $lock_data = Files\read_json_as_array($lock_file);
        $lock_data['packages'] = [
            'https://github.com/test-owner/test-repo.git' => [
                'owner' => 'test-owner',
                'repo' => 'test-repo',
                'version' => 'v1.0.0',
                'hash' => 'test-hash-12345'
            ]
        ];
        Files\save_array_as_json($lock_file, $lock_data);
        
        // Create Packages directory structure
        $packages_dir = $temp_dir . '/Packages';
        $test_package_dir = $packages_dir . '/test-owner/test-repo';
        Files\make_directory_recursively($test_package_dir);
        
        // Create package phpkg.config.json
        $package_config = [
            'map' => ['Repo' => 'Source'],
            'entry-points' => [],
            'excludes' => [],
            'executables' => [],
            'packages-directory' => 'Packages',
            'packages' => [],
            'aliases' => [],
            'autoloads' => [],
            'import-file' => 'phpkg.imports.php'
        ];
        Files\save_array_as_json($test_package_dir . '/phpkg.config.json', $package_config);
        
        // Create package Source directory with TestClass.php
        $package_source_dir = $test_package_dir . '/Source';
        Files\make_directory_recursively($package_source_dir);
        
        $test_class_content = <<<'PHP'
<?php

namespace Repo;

use function Repo\TestHelpers\test_function;

class TestClass
{
    public function __construct()
    {
        // Test class implementation
    }
    
    public function use_helper()
    {
        test_function();
    }
}
PHP;
        Files\file_write($package_source_dir . '/TestClass.php', $test_class_content);
        
        // Create package function file
        $test_function_content = <<<'PHP'
<?php

namespace Repo\TestHelpers;

function test_function(): void
{
    // Test function implementation
}
PHP;
        Files\file_write($package_source_dir . '/TestHelpers.php', $test_function_content);
        
        // Create main project Source directory with Model.php that uses package
        $source_dir = $temp_dir . '/Source';
        Files\make_directory_recursively($source_dir);
        
        $model_content = <<<'PHP'
<?php

namespace Application;

use Repo\TestClass;
use function Repo\TestHelpers\test_function;

class Model
{
    public function use_package()
    {
        $test = new TestClass();
        test_function();
    }
}
PHP;
        Files\file_write($source_dir . '/Model.php', $model_content);
        
        return $temp_dir;
    },
    after: function (string $temp_dir) {
        // Clean up: remove temp dir
        Files\force_delete_recursive($temp_dir);
    }
);

test(
    title: 'it should handle package executables with symlinks and dependencies',
    case: function (string $temp_dir) {
        // Skip this test on Windows as symlinks require special permissions and behave differently
        if (PHP_OS_FAMILY === 'Windows') {
            notice('Skipping symlink test on Windows - symlinks require administrator privileges or developer mode');
            return;
        }
        
        // Build the project with package executables
        $build_output = CliRunner\phpkg('build', ["--project=$temp_dir"]);
        // Should show success message
        Assertions\assert_true(
            str_contains($build_output, 'Build finished successfully') || 
            str_contains($build_output, 'successfully') ||
            str_contains($build_output, 'success'),
            'Should show success message. Output: ' . $build_output
        );
        
        // Verify build directory was created
        $build_dir = $temp_dir . '/build';
        Assertions\assert_true(is_dir($build_dir), 'Build directory should be created');
        
        // Verify the Source directory structure is preserved
        $source_dir = $build_dir . '/Source';
        Assertions\assert_true(is_dir($source_dir), 'Source directory should be created');
        
        // Verify the Packages directory structure is preserved
        $packages_dir = $build_dir . '/Packages';
        Assertions\assert_true(is_dir($packages_dir), 'Packages directory should be created');
        
        $repo_package_dir = $packages_dir . '/repo-owner/repo-package';
        Assertions\assert_true(is_dir($repo_package_dir), 'Repo package directory should be created');
        
        $utils_package_dir = $packages_dir . '/utils-owner/utils-package';
        Assertions\assert_true(is_dir($utils_package_dir), 'Utils package directory should be created');
        
        // Verify package Source directories are preserved
        $repo_source_dir = $repo_package_dir . '/Source';
        Assertions\assert_true(is_dir($repo_source_dir), 'Repo package Source directory should be created');
        
        $utils_source_dir = $utils_package_dir . '/Source';
        Assertions\assert_true(is_dir($utils_source_dir), 'Utils package Source directory should be created');
        
        // Verify package files were copied
        $repo_cli_file = $repo_package_dir . '/console/cli.php';
        Assertions\assert_true(file_exists($repo_cli_file), 'Repo package cli.php should be copied');
        
        $repo_class_file = $repo_source_dir . '/RepoClass.php';
        Assertions\assert_true(file_exists($repo_class_file), 'Repo package RepoClass.php should be copied');
        
        $utils_helper_file = $utils_source_dir . '/UtilsHelper.php';
        Assertions\assert_true(file_exists($utils_helper_file), 'Utils package UtilsHelper.php should be copied');
        
        // Verify repo package cli.php was copied and modified with require_once injection
        $repo_cli_content = file_get_contents($repo_cli_file);
        $expected_repo_cli_content = <<<'EOD'
<?php
require_once __DIR__ . '/../../../../phpkg.imports.php';require_once __DIR__ . '/../../../utils-owner/utils-package/Source/UtilsHelper.php';
#!/usr/bin/env php

use Repo\RepoClass;
use function Utils\UtilsHelper\helper_function;

$repo = new RepoClass();
helper_function();

echo "CLI executed successfully.\n";
EOD;
        
        // Replace placeholder with relative path (empty string for current directory)
        $expected_repo_cli_content = str_replace('@BUILDS_DIRECTORY', '', $expected_repo_cli_content);
        
        Str\assert_equal(normalize_paths_in_code($repo_cli_content), normalize_paths_in_code($expected_repo_cli_content));
        
        // Verify repo package RepoClass.php was copied and modified with require_once injection
        $repo_class_content = file_get_contents($repo_class_file);
        $expected_repo_class_content = <<<'EOD'
<?php

namespace Repo;require_once __DIR__ . '/../../../utils-owner/utils-package/Source/UtilsHelper.php';

use function Utils\UtilsHelper\helper_function;

class RepoClass
{
    public function __construct()
    {
        // Repo class implementation
    }
    
    public function do_something()
    {
        helper_function();
    }
}
EOD;
        
        Str\assert_equal(normalize_paths_in_code($repo_class_content), normalize_paths_in_code($expected_repo_class_content));
        
        // Verify utils package UtilsHelper.php was copied with same content
        $utils_helper_content = file_get_contents($utils_helper_file);
        $original_utils_helper = file_get_contents($temp_dir . '/Packages/utils-owner/utils-package/Source/UtilsHelper.php');
        Str\assert_equal($utils_helper_content, $original_utils_helper);
        
        // Verify executable symlink was created
        $executable_symlink = $build_dir . '/repo-cli';
        Assertions\assert_true(is_link($executable_symlink), 'Executable symlink should be created');
        
        // Verify symlink points to the correct file
        $symlink_target = readlink($executable_symlink);
        $expected_target = $repo_package_dir . '/console/cli.php';
        // Normalize paths for comparison (symlinks may use different separators on Windows)
        // Try to resolve paths, but fall back to original if realpath fails
        $resolved_target = @Files\realpath($symlink_target);
        $resolved_expected = @Files\realpath($expected_target);
        $normalized_target = str_replace('\\', '/', $resolved_target ?: $symlink_target);
        $normalized_expected = str_replace('\\', '/', $resolved_expected ?: $expected_target);
        Assertions\assert_true($normalized_target === $normalized_expected, 'Symlink should point to cli.php');
        
        // Verify symlink has executable permissions
        $symlink_permissions = fileperms($executable_symlink);
        Assertions\assert_true(($symlink_permissions & 0x0040) !== 0, 'Symlink should have executable permissions');
        
        // Verify phpkg.imports.php was created with correct package namespace and class mappings
        $import_file = $build_dir . '/phpkg.imports.php';
        Assertions\assert_true(file_exists($import_file), 'phpkg.imports.php file should be created');
        
        $import_content = file_get_contents($import_file);
        $expected_import_content = <<<'EOD'
<?php

spl_autoload_register(function ($class) {
    $classes = [
        'Repo\RepoClass' => __DIR__ . '/Packages/repo-owner/repo-package/Source/RepoClass.php',
    ];

    if (array_key_exists($class, $classes)) {
        require $classes[$class];
    }

}, true, true);

spl_autoload_register(function ($class) {
    $namespaces = [
        'Repo' => __DIR__ . '/Packages/repo-owner/repo-package/Source',
        'Utils' => __DIR__ . '/Packages/utils-owner/utils-package/Source',
        'Application' => __DIR__ . '/Source',
    ];

    $realpath = null;

    foreach ($namespaces as $namespace => $path) {
        if (str_starts_with($class, $namespace)) {
            $pos = strpos($class, $namespace);
            if ($pos !== false) {
                $realpath = substr_replace($class, $path, $pos, strlen($namespace));
            }
            $realpath = str_replace("\\", DIRECTORY_SEPARATOR, $realpath) . '.php';
            if (file_exists($realpath)) {
                require $realpath;
            }

            return ;
        }
    }
});

EOD;
        
        Str\assert_equal(normalize_paths_in_code($import_content), normalize_paths_in_code($expected_import_content));
    },
    before: function () {
        // Create a temporary directory and initialize it as a phpkg project
        $temp_dir = sys_get_temp_dir() . '/' . uniqid('phpkg_build_executable_test');
        Files\make_directory_recursively($temp_dir);
        
        // Initialize the project
        $init_output = CliRunner\phpkg('init', ["--project=$temp_dir"]);
        Assertions\assert_true(
            str_contains($init_output, 'Project has been initialized') || 
            str_contains($init_output, 'initialized'),
            'Project should be initialized. Output: ' . $init_output
        );
        
        // Update config to have custom namespace mapping and package dependencies
        $config_file = $temp_dir . '/phpkg.config.json';
        $config = Files\read_json_as_array($config_file);
        $config['map'] = ['Application' => 'Source'];
        $config['packages'] = [
            'https://github.com/repo-owner/repo-package.git' => 'v1.0.0',
            'https://github.com/utils-owner/utils-package.git' => 'v1.0.0'
        ];
        Files\save_array_as_json($config_file, $config);
        
        // Update lock file to include package information
        $lock_file = $temp_dir . '/phpkg.config-lock.json';
        $lock_data = Files\read_json_as_array($lock_file);
        $lock_data['packages'] = [
            'https://github.com/repo-owner/repo-package.git' => [
                'owner' => 'repo-owner',
                'repo' => 'repo-package',
                'version' => 'v1.0.0',
                'hash' => 'repo-hash-12345'
            ],
            'https://github.com/utils-owner/utils-package.git' => [
                'owner' => 'utils-owner',
                'repo' => 'utils-package',
                'version' => 'v1.0.0',
                'hash' => 'utils-hash-67890'
            ]
        ];
        Files\save_array_as_json($lock_file, $lock_data);
        
        // Create Packages directory structure for repo package
        $packages_dir = $temp_dir . '/Packages';
        $repo_package_dir = $packages_dir . '/repo-owner/repo-package';
        Files\make_directory_recursively($repo_package_dir);
        
        // Create repo package phpkg.config.json with executable
        $repo_package_config = [
            'map' => ['Repo' => 'Source'],
            'entry-points' => [],
            'excludes' => [],
            'executables' => ['repo-cli' => 'console/cli.php'],
            'packages-directory' => 'Packages',
            'packages' => ['https://github.com/utils-owner/utils-package.git' => 'v1.0.0'],
            'aliases' => [],
            'autoloads' => [],
            'import-file' => 'phpkg.imports.php'
        ];
        Files\save_array_as_json($repo_package_dir . '/phpkg.config.json', $repo_package_config);
        
        // Create repo package console directory with cli.php
        $repo_console_dir = $repo_package_dir . '/console';
        Files\make_directory_recursively($repo_console_dir);
        
        $repo_cli_content = <<<'PHP'
<?php

#!/usr/bin/env php

use Repo\RepoClass;
use function Utils\UtilsHelper\helper_function;

$repo = new RepoClass();
helper_function();

echo "CLI executed successfully.\n";
PHP;
        Files\file_write($repo_console_dir . '/cli.php', $repo_cli_content);
        
        // Create repo package Source directory with RepoClass.php
        $repo_source_dir = $repo_package_dir . '/Source';
        Files\make_directory_recursively($repo_source_dir);
        
        $repo_class_content = <<<'PHP'
<?php

namespace Repo;

use function Utils\UtilsHelper\helper_function;

class RepoClass
{
    public function __construct()
    {
        // Repo class implementation
    }
    
    public function do_something()
    {
        helper_function();
    }
}
PHP;
        Files\file_write($repo_source_dir . '/RepoClass.php', $repo_class_content);
        
        // Create Packages directory structure for utils package
        $utils_package_dir = $packages_dir . '/utils-owner/utils-package';
        Files\make_directory_recursively($utils_package_dir);
        
        // Create utils package phpkg.config.json
        $utils_package_config = [
            'map' => ['Utils' => 'Source'],
            'entry-points' => [],
            'excludes' => [],
            'executables' => [],
            'packages-directory' => 'Packages',
            'packages' => [],
            'aliases' => [],
            'import-file' => 'phpkg.imports.php'
        ];
        Files\save_array_as_json($utils_package_dir . '/phpkg.config.json', $utils_package_config);
        
        // Create utils package Source directory with UtilsHelper.php
        $utils_source_dir = $utils_package_dir . '/Source';
        Files\make_directory_recursively($utils_source_dir);
        
        $utils_helper_content = <<<'PHP'
<?php

namespace Utils\UtilsHelper;

function helper_function(): void
{
    // Utils helper function implementation
}
PHP;
        Files\file_write($utils_source_dir . '/UtilsHelper.php', $utils_helper_content);
        
        // Create main project Source directory with Model.php
        $source_dir = $temp_dir . '/Source';
        Files\make_directory_recursively($source_dir);
        
        $model_content = <<<'PHP'
<?php

namespace Application;

class Model
{
    // Model implementation
}
PHP;
        Files\file_write($source_dir . '/Model.php', $model_content);
        
        return $temp_dir;
    },
    after: function (string $temp_dir) {
        // Clean up: remove temp dir
        Files\force_delete_recursive($temp_dir);
    }
);

test(
    title: 'it should handle autoloads from project and packages correctly',
    case: function () {
        // Create a temporary directory and initialize it as a phpkg project
        $temp_dir = sys_get_temp_dir() . '/' . uniqid('phpkg_build_autoloads_test');
        Files\make_directory_recursively($temp_dir);
        
        // Initialize the project
        $init_output = CliRunner\phpkg('init', ["--project=$temp_dir"]);
        Assertions\assert_true(
            str_contains($init_output, 'Project has been initialized') || 
            str_contains($init_output, 'initialized'),
            'Project should be initialized. Output: ' . $init_output
        );
        
        // Update config to have autoloads and package dependencies
        $config_file = $temp_dir . '/phpkg.config.json';
        $config = Files\read_json_as_array($config_file);
        $config['autoloads'] = [
            'Source/ProjectHelper.php',
            'Source/ProjectUtils.php'
        ];
        $config['packages'] = [
            'https://github.com/autoload-owner/autoload-package.git' => 'v1.0.0'
        ];
        Files\save_array_as_json($config_file, $config);
        
        // Update lock file to include package information
        $lock_file = $temp_dir . '/phpkg.config-lock.json';
        $lock_data = Files\read_json_as_array($lock_file);
        $lock_data['packages'] = [
            'https://github.com/autoload-owner/autoload-package.git' => [
                'owner' => 'autoload-owner',
                'repo' => 'autoload-package',
                'version' => 'v1.0.0',
                'hash' => 'autoload-hash-12345'
            ]
        ];
        Files\save_array_as_json($lock_file, $lock_data);
        
        // Create project source files for autoloads
        $source_dir = $temp_dir . '/Source';
        Files\make_directory_recursively($source_dir);
        
        $project_helper_content = <<<'PHP'
<?php

namespace Project;

function project_helper(): void
{
    // Project helper function implementation
}
PHP;
        Files\file_write($source_dir . '/ProjectHelper.php', $project_helper_content);
        
        $project_utils_content = <<<'PHP'
<?php

namespace Project;

function project_utils(): void
{
    // Project utils function implementation
}
PHP;
        Files\file_write($source_dir . '/ProjectUtils.php', $project_utils_content);
        
        // Create Packages directory structure for autoload package
        $packages_dir = $temp_dir . '/Packages';
        $autoload_package_dir = $packages_dir . '/autoload-owner/autoload-package';
        Files\make_directory_recursively($autoload_package_dir);
        
        // Create autoload package phpkg.config.json with autoloads
        $autoload_package_config = [
            'map' => ['Autoload' => 'Source'],
            'entry-points' => [],
            'excludes' => [],
            'executables' => [],
            'autoloads' => [
                'Source/PackageHelper.php',
                'Source/PackageUtils.php'
            ],
            'packages-directory' => 'Packages',
            'packages' => [],
            'aliases' => [],
            'import-file' => 'phpkg.imports.php'
        ];
        Files\save_array_as_json($autoload_package_dir . '/phpkg.config.json', $autoload_package_config);
        
        // Create autoload package source files
        $autoload_source_dir = $autoload_package_dir . '/Source';
        Files\make_directory_recursively($autoload_source_dir);
        
        $package_helper_content = <<<'PHP'
<?php

namespace Autoload;

function package_helper(): void
{
    // Package helper function implementation
}
PHP;
        Files\file_write($autoload_source_dir . '/PackageHelper.php', $package_helper_content);
        
        $package_utils_content = <<<'PHP'
<?php

namespace Autoload;

function package_utils(): void
{
    // Package utils function implementation
}
PHP;
        Files\file_write($autoload_source_dir . '/PackageUtils.php', $package_utils_content);
        
            // Build the project
    $build_output = CliRunner\phpkg('build', ["--project=$temp_dir"]);
    Assertions\assert_true(
        str_contains($build_output, 'Build finished successfully') || str_contains($build_output, 'Project built successfully'),
        'Build should complete successfully. Output: ' . $build_output
    );
        
        // Verify the build directory was created
        $build_dir = $temp_dir . '/build';
        Assertions\assert_true(file_exists($build_dir), 'Build directory should be created');
        
        // Verify all autoload files were copied
        Assertions\assert_true(file_exists($build_dir . '/Source/ProjectHelper.php'), 'ProjectHelper.php should be copied');
        Assertions\assert_true(file_exists($build_dir . '/Source/ProjectUtils.php'), 'ProjectUtils.php should be copied');
        Assertions\assert_true(file_exists($build_dir . '/Packages/autoload-owner/autoload-package/Source/PackageHelper.php'), 'PackageHelper.php should be copied');
        Assertions\assert_true(file_exists($build_dir . '/Packages/autoload-owner/autoload-package/Source/PackageUtils.php'), 'PackageUtils.php should be copied');
        
        // Verify phpkg.imports.php was created with all autoloads
        $import_file = $build_dir . '/phpkg.imports.php';
        Assertions\assert_true(file_exists($import_file), 'phpkg.imports.php file should be created');
        
        $import_content = file_get_contents($import_file);
        $expected_import_content = <<<'EOD'
<?php

spl_autoload_register(function ($class) {
    $classes = [
    ];

    if (array_key_exists($class, $classes)) {
        require $classes[$class];
    }

}, true, true);

spl_autoload_register(function ($class) {
    $namespaces = [
        'Autoload' => __DIR__ . '/Packages/autoload-owner/autoload-package/Source',
    ];

    $realpath = null;

    foreach ($namespaces as $namespace => $path) {
        if (str_starts_with($class, $namespace)) {
            $pos = strpos($class, $namespace);
            if ($pos !== false) {
                $realpath = substr_replace($class, $path, $pos, strlen($namespace));
            }
            $realpath = str_replace("\\", DIRECTORY_SEPARATOR, $realpath) . '.php';
            if (file_exists($realpath)) {
                require $realpath;
            }

            return ;
        }
    }
});

require_once __DIR__ . '/Packages/autoload-owner/autoload-package/Source/PackageHelper.php';
require_once __DIR__ . '/Packages/autoload-owner/autoload-package/Source/PackageUtils.php';
require_once __DIR__ . '/Source/ProjectHelper.php';
require_once __DIR__ . '/Source/ProjectUtils.php';

EOD;

        Str\assert_equal(normalize_paths_in_code($import_content), normalize_paths_in_code($expected_import_content));

        return $temp_dir;
    },
    before: function () {
        // Create a temporary directory and initialize it as a phpkg project
        $temp_dir = sys_get_temp_dir() . '/' . uniqid('phpkg_build_autoloads_test');
        Files\make_directory_recursively($temp_dir);
        
        // Initialize the project
        $init_output = CliRunner\phpkg('init', ["--project=$temp_dir"]);
        Assertions\assert_true(
            str_contains($init_output, 'Project has been initialized') || 
            str_contains($init_output, 'initialized'),
            'Project should be initialized. Output: ' . $init_output
        );
        
        // Update config to have autoloads and package dependencies
        $config_file = $temp_dir . '/phpkg.config.json';
        $config = Files\read_json_as_array($config_file);
        $config['autoloads'] = [
            'Source/ProjectHelper.php',
            'Source/ProjectUtils.php'
        ];
        $config['packages'] = [
            'https://github.com/autoload-owner/autoload-package.git' => 'v1.0.0'
        ];
        Files\save_array_as_json($config_file, $config);
        
        // Update lock file to include package information
        $lock_file = $temp_dir . '/phpkg.config-lock.json';
        $lock_data = Files\read_json_as_array($lock_file);
        $lock_data['packages'] = [
            'https://github.com/autoload-owner/autoload-package.git' => [
                'owner' => 'autoload-owner',
                'repo' => 'autoload-package',
                'version' => 'v1.0.0',
                'hash' => 'autoload-hash-12345'
            ]
        ];
        Files\save_array_as_json($lock_file, $lock_data);
        
        // Create project source files for autoloads
        $source_dir = $temp_dir . '/Source';
        Files\make_directory_recursively($source_dir);
        
        $project_helper_content = <<<'PHP'
<?php

namespace Project;

function project_helper(): void
{
    // Project helper function implementation
}
PHP;
        Files\file_write($source_dir . '/ProjectHelper.php', $project_helper_content);
        
        $project_utils_content = <<<'PHP'
<?php

namespace Project;

function project_utils(): void
{
    // Project utils function implementation
}
PHP;
        Files\file_write($source_dir . '/ProjectUtils.php', $project_utils_content);
        
        // Create Packages directory structure for autoload package
        $packages_dir = $temp_dir . '/Packages';
        $autoload_package_dir = $packages_dir . '/autoload-owner/autoload-package';
        Files\make_directory_recursively($autoload_package_dir);
        
        // Create autoload package phpkg.config.json with autoloads
        $autoload_package_config = [
            'map' => ['Autoload' => 'Source'],
            'entry-points' => [],
            'excludes' => [],
            'executables' => [],
            'autoloads' => [
                'Source/PackageHelper.php',
                'Source/PackageUtils.php'
            ],
            'packages-directory' => 'Packages',
            'packages' => [],
            'aliases' => [],
            'import-file' => 'phpkg.imports.php'
        ];
        Files\save_array_as_json($autoload_package_dir . '/phpkg.config.json', $autoload_package_config);
        
        // Create autoload package source files
        $autoload_source_dir = $autoload_package_dir . '/Source';
        Files\make_directory_recursively($autoload_source_dir);
        
        $package_helper_content = <<<'PHP'
<?php

namespace Autoload;

function package_helper(): void
{
    // Package helper function implementation
}
PHP;
        Files\file_write($autoload_source_dir . '/PackageHelper.php', $package_helper_content);
        
        $package_utils_content = <<<'PHP'
<?php

namespace Autoload;

function package_utils(): void
{
    // Package utils function implementation
}
PHP;
        Files\file_write($autoload_source_dir . '/PackageUtils.php', $package_utils_content);
        
        return $temp_dir;
    },
    after: function (string $temp_dir) {
        // Clean up: remove temp dir
        Files\force_delete_recursive($temp_dir);
    }
);

test(
    title: 'it should handle build with package interdependencies and relative paths correctly',
    case: function (string $temp_dir) {
        // Build the project
        $build_output = CliRunner\phpkg('build', ["--project=$temp_dir"]);
        // Should show success message
        Assertions\assert_true(
            str_contains($build_output, 'Build finished successfully') || 
            str_contains($build_output, 'Project built successfully'),
            'Build should complete successfully. Output: ' . $build_output
        );
        
        // Verify build directory was created
        $build_dir = $temp_dir . '/build';
        Assertions\assert_true(is_dir($build_dir), 'Build directory should be created');
        
        // Verify the Source directory structure is preserved
        $source_dir = $build_dir . '/Source';
        Assertions\assert_true(is_dir($source_dir), 'Source directory should be created');
        
        // Verify the Packages directory structure is preserved
        $packages_dir = $build_dir . '/Packages';
        Assertions\assert_true(is_dir($packages_dir), 'Packages directory should be created');
        
        // Verify both packages were copied
        $utils_package_dir = $packages_dir . '/utils-owner/utils-package';
        $logger_package_dir = $packages_dir . '/logger-owner/logger-package';
        Assertions\assert_true(is_dir($utils_package_dir), 'Utils package directory should be created');
        Assertions\assert_true(is_dir($logger_package_dir), 'Logger package directory should be created');
        
        // Verify package source files were copied
        $utils_helper_file = $utils_package_dir . '/Source/UtilsHelper.php';
        $logger_class_file = $logger_package_dir . '/Source/Logger.php';
        Assertions\assert_true(file_exists($utils_helper_file), 'UtilsHelper.php should be copied');
        Assertions\assert_true(file_exists($logger_class_file), 'Logger.php should be copied');
        
        // Verify main project files were copied
        $app_file = $source_dir . '/App.php';
        Assertions\assert_true(file_exists($app_file), 'App.php should be copied to Source directory');
        
        // Verify App.php was modified with require_once injection for both packages
        $app_content = file_get_contents($app_file);
        $expected_app_content = <<<'EOD'
<?php

namespace Application;require_once __DIR__ . '/../Packages/utils-owner/utils-package/Source/Services.php';require_once __DIR__ . '/../Packages/logger-owner/logger-package/Source/Services.php';

use Utils\UtilsHelper;
use Logger\Logger;
use function Utils\Services\call_service_a;
use function Utils\Services\call_service_b;
use function Utils\Services\validate_email;
use function Logger\Services\log_info;
use function Logger\Services\log_error;
use function Logger\Services\log_debug;

class App
{
    public function run()
    {
        $logger = new Logger();
        $logger->log('Application started');
        
        $result = UtilsHelper::format('Hello World');
        $logger->log($result);
        
        // Use package functions (these should trigger require_once injection)
        $service_a_result = call_service_a('test input');
        log_info($service_a_result);
        
        $service_b_result = call_service_b(42);
        log_debug("Service B result: $service_b_result");
        
        $is_valid = validate_email('test@example.com');
        if ($is_valid) {
            log_info('Email is valid');
        } else {
            log_error('Email is invalid');
        }
        
        return $result;
    }
}
EOD;
        
        Str\assert_equal(normalize_paths_in_code($app_content), normalize_paths_in_code($expected_app_content));
        
        // Verify entry points were copied and have correct import file paths
        $cli_file = $build_dir . '/cli.php';
        $index_file = $build_dir . '/public/index.php';
        
        Assertions\assert_true(file_exists($cli_file), 'cli.php should be copied to build directory');
        Assertions\assert_true(file_exists($index_file), 'public/index.php should be copied to build directory');
        
        // Verify cli.php content with injected require statement
        $cli_content = file_get_contents($cli_file);
        $expected_cli_content = <<<'EOD'
<?php
require_once __DIR__ . '/phpkg.imports.php';
use Application\App;

$app = new App();
$result = $app->run();
echo "CLI Result: $result\n";
EOD;
        Str\assert_equal(normalize_paths_in_code($cli_content), normalize_paths_in_code($expected_cli_content));
        
        // Verify index.php content with injected require statement
        $index_content = file_get_contents($index_file);
        $expected_index_content = <<<'EOD'
<?php
require_once __DIR__ . '/../phpkg.imports.php';
use Application\App;

$app = new App();
$result = $app->run();
echo "Web Result: $result\n";
EOD;
        Str\assert_equal(normalize_paths_in_code($index_content), normalize_paths_in_code($expected_index_content));
        
        // Verify Logger.php was modified with require_once injection for UtilsHelper
        $logger_content = file_get_contents($logger_class_file);
        $expected_logger_content = <<<'EOD'
<?php

namespace Logger;

use Utils\UtilsHelper;

class Logger
{
    public function log(string $message): void
    {
        $formatted = UtilsHelper::format($message);
        echo "[LOG] $formatted\n";
    }
}
EOD;
        
        Str\assert_equal($logger_content, $expected_logger_content);
        
                // Verify UtilsHelper.php was copied without modification (no dependencies)
        $utils_content = file_get_contents($utils_helper_file);
        $expected_utils_content = <<<'EOD'
<?php

namespace Utils;

class UtilsHelper
{
    public static function format(string $text): string
    {
        return strtoupper(trim($text));
    }
}
EOD;
        
        Str\assert_equal($utils_content, $expected_utils_content);
        
        // Verify package autoload files were copied correctly
        $utils_package_helper_file = $utils_package_dir . '/Source/PackageHelper.php';
        $utils_package_utils_file = $utils_package_dir . '/Source/PackageUtils.php';
        
        Assertions\assert_true(file_exists($utils_package_helper_file), 'PackageHelper.php should be copied');
        Assertions\assert_true(file_exists($utils_package_utils_file), 'PackageUtils.php should be copied');
        
        // Verify PackageHelper.php content
        $package_helper_content = file_get_contents($utils_package_helper_file);
        $expected_package_helper_content = <<<'EOD'
<?php

namespace Utils;

function package_helper_function(): string
{
    return "Package helper function called";
}
EOD;
        Str\assert_equal($package_helper_content, $expected_package_helper_content);
        
        // Verify PackageUtils.php content
        $package_utils_content = file_get_contents($utils_package_utils_file);
        $expected_package_utils_content = <<<'EOD'
<?php

namespace Utils;

function package_utils_function(): string
{
    return "Package utils function called";
}
EOD;
        Str\assert_equal($package_utils_content, $expected_package_utils_content);
        
        // Verify phpkg.imports.php was created with correct content
        $import_file = $build_dir . '/phpkg.imports.php';
        Assertions\assert_true(file_exists($import_file), 'phpkg.imports.php file should be created');
        
        $import_content = file_get_contents($import_file);
        

        
        // Verify the complete import file content with correct paths (no extra ../)
        $expected_import_content = <<<'EOD'
<?php

spl_autoload_register(function ($class) {
    $classes = [
        'Logger\Logger' => __DIR__ . '/Packages/logger-owner/logger-package/Source/Logger.php',
        'Utils\UtilsHelper' => __DIR__ . '/Packages/utils-owner/utils-package/Source/UtilsHelper.php',
    ];

    if (array_key_exists($class, $classes)) {
        require $classes[$class];
    }

}, true, true);

spl_autoload_register(function ($class) {
    $namespaces = [
        'Utils' => __DIR__ . '/Packages/utils-owner/utils-package/Source',
        'Logger' => __DIR__ . '/Packages/logger-owner/logger-package/Source',
    ];

    $realpath = null;

    foreach ($namespaces as $namespace => $path) {
        if (str_starts_with($class, $namespace)) {
            $pos = strpos($class, $namespace);
            if ($pos !== false) {
                $realpath = substr_replace($class, $path, $pos, strlen($namespace));
            }
            $realpath = str_replace("\\", DIRECTORY_SEPARATOR, $realpath) . '.php';
            if (file_exists($realpath)) {
                require $realpath;
            }

            return ;
        }
    }
});

require_once __DIR__ . '/Source/ProjectHelper.php';
require_once __DIR__ . '/Source/ProjectUtils.php';

EOD;
        
        Str\assert_equal(normalize_paths_in_code($import_content), normalize_paths_in_code($expected_import_content));
        
        // Verify project autoload files were copied correctly
        $project_helper_file = $build_dir . '/Source/ProjectHelper.php';
        $project_utils_file = $build_dir . '/Source/ProjectUtils.php';
        
        Assertions\assert_true(file_exists($project_helper_file), 'ProjectHelper.php should be copied');
        Assertions\assert_true(file_exists($project_utils_file), 'ProjectUtils.php should be copied');
        
        // Verify ProjectHelper.php content
        $project_helper_content = file_get_contents($project_helper_file);
        $expected_project_helper_content = <<<'EOD'
<?php

namespace Application;

function project_helper_function(): string
{
    return "Project helper function called";
}
EOD;
        Str\assert_equal($project_helper_content, $expected_project_helper_content);
        
        // Verify ProjectUtils.php content
        $project_utils_content = file_get_contents($project_utils_file);
        $expected_project_utils_content = <<<'EOD'
<?php

namespace Application;

function project_utils_function(): string
{
    return "Project utils function called";
}
EOD;
        Str\assert_equal($project_utils_content, $expected_project_utils_content);
        
        return $temp_dir;
    },
    before: function () {
        // Create a temporary directory and initialize it as a phpkg project
        $temp_dir = sys_get_temp_dir() . '/' . uniqid('phpkg_build_test');
        Files\make_directory_recursively($temp_dir);
        
        // Initialize the project
        $init_output = CliRunner\phpkg('init', ["--project=$temp_dir"]);
        Assertions\assert_true(
            str_contains($init_output, 'Project has been initialized') || 
            str_contains($init_output, 'initialized'),
            'Project should be initialized. Output: ' . $init_output
        );
        
        // Update config to have both packages, project autoloads, and entry points
        $config_file = $temp_dir . '/phpkg.config.json';
        $config = Files\read_json_as_array($config_file);
        $config['packages'] = [
            'https://github.com/utils-owner/utils-package.git' => 'v1.0.0',
            'https://github.com/logger-owner/logger-package.git' => 'v1.0.0'
        ];
        $config['autoloads'] = [
            'Source/ProjectHelper.php',
            'Source/ProjectUtils.php'
        ];
        $config['entry-points'] = [
            'cli.php' => 'cli.php',
            'public/index.php' => 'public/index.php'
        ];
        Files\save_array_as_json($config_file, $config);
        
        // Update lock file to include package information
        $lock_file = $temp_dir . '/phpkg.config-lock.json';
        $lock_data = Files\read_json_as_array($lock_file);
        $lock_data['packages'] = [
            'https://github.com/utils-owner/utils-package.git' => [
                'owner' => 'utils-owner',
                'repo' => 'utils-package',
                'version' => 'v1.0.0',
                'hash' => 'utils-hash-12345'
            ],
            'https://github.com/logger-owner/logger-package.git' => [
                'owner' => 'logger-owner',
                'repo' => 'logger-package',
                'version' => 'v1.0.0',
                'hash' => 'logger-hash-12345'
            ]
        ];
        Files\save_array_as_json($lock_file, $lock_data);
        
        // Create project source files
        $source_dir = $temp_dir . '/Source';
        Files\make_directory_recursively($source_dir);
        
        $app_content = <<<'PHP'
<?php

namespace Application;

use Utils\UtilsHelper;
use Logger\Logger;
use function Utils\Services\call_service_a;
use function Utils\Services\call_service_b;
use function Utils\Services\validate_email;
use function Logger\Services\log_info;
use function Logger\Services\log_error;
use function Logger\Services\log_debug;

class App
{
    public function run()
    {
        $logger = new Logger();
        $logger->log('Application started');
        
        $result = UtilsHelper::format('Hello World');
        $logger->log($result);
        
        // Use package functions (these should trigger require_once injection)
        $service_a_result = call_service_a('test input');
        log_info($service_a_result);
        
        $service_b_result = call_service_b(42);
        log_debug("Service B result: $service_b_result");
        
        $is_valid = validate_email('test@example.com');
        if ($is_valid) {
            log_info('Email is valid');
        } else {
            log_error('Email is invalid');
        }
        
        return $result;
    }
}
PHP;
        Files\file_write($source_dir . '/App.php', $app_content);
        
        // Create entry point files at root level (no require statements - phpkg will inject them)
        $cli_content = <<<'PHP'
<?php

use Application\App;

$app = new App();
$result = $app->run();
echo "CLI Result: $result\n";
PHP;
        Files\file_write($temp_dir . '/cli.php', $cli_content);
        
        // Create public directory and index.php at root level
        $public_dir = $temp_dir . '/public';
        Files\make_directory_recursively($public_dir);
        
        $index_content = <<<'PHP'
<?php

use Application\App;

$app = new App();
$result = $app->run();
echo "Web Result: $result\n";
PHP;
        Files\file_write($public_dir . '/index.php', $index_content);
        
        // Create project autoload files
        $project_helper_content = <<<'PHP'
<?php

namespace Application;

function project_helper_function(): string
{
    return "Project helper function called";
}
PHP;
        Files\file_write($source_dir . '/ProjectHelper.php', $project_helper_content);
        
        $project_utils_content = <<<'PHP'
<?php

namespace Application;

function project_utils_function(): string
{
    return "Project utils function called";
}
PHP;
        Files\file_write($source_dir . '/ProjectUtils.php', $project_utils_content);
        
        // Create Packages directory structure for utils package
        $packages_dir = $temp_dir . '/Packages';
        $utils_package_dir = $packages_dir . '/utils-owner/utils-package';
        Files\make_directory_recursively($utils_package_dir);
        
        // Create utils package phpkg.config.json
        $utils_package_config = [
            'map' => ['Utils' => 'Source'],
            'entry-points' => [],
            'excludes' => [],
            'executables' => [],
            'packages-directory' => 'Packages',
            'packages' => [],
            'aliases' => [],
            'import-file' => 'phpkg.imports.php'
        ];
        Files\save_array_as_json($utils_package_dir . '/phpkg.config.json', $utils_package_config);
        
        // Create utils package source directory first
        $utils_source_dir = $utils_package_dir . '/Source';
        Files\make_directory_recursively($utils_source_dir);
        
        // Create utils package autoload files
        $utils_package_helper_content = <<<'PHP'
<?php

namespace Utils;

function package_helper_function(): string
{
    return "Package helper function called";
}
PHP;
        Files\file_write($utils_source_dir . '/PackageHelper.php', $utils_package_helper_content);
        
        $utils_package_utils_content = <<<'PHP'
<?php

namespace Utils;

function package_utils_function(): string
{
    return "Package utils function called";
}
PHP;
        Files\file_write($utils_source_dir . '/PackageUtils.php', $utils_package_utils_content);
        
        // Create utils package source files
        
        $utils_helper_content = <<<'PHP'
<?php

namespace Utils;

class UtilsHelper
{
    public static function format(string $text): string
    {
        return strtoupper(trim($text));
    }
}
PHP;
        Files\file_write($utils_source_dir . '/UtilsHelper.php', $utils_helper_content);
        
        // Create Services file with functions
        $utils_services_content = <<<'PHP'
<?php

namespace Utils\Services;

function call_service_a(string $input): string
{
    return "Service A processed: " . strtoupper($input);
}

function call_service_b(int $number): int
{
    return $number * 2;
}

function validate_email(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}
PHP;
        Files\file_write($utils_source_dir . '/Services.php', $utils_services_content);
        
        // Create Packages directory structure for logger package
        $logger_package_dir = $packages_dir . '/logger-owner/logger-package';
        Files\make_directory_recursively($logger_package_dir);
        
        // Create logger package phpkg.config.json
        $logger_package_config = [
            'map' => ['Logger' => 'Source'],
            'entry-points' => [],
            'excludes' => [],
            'executables' => [],
            'packages-directory' => 'Packages',
            'packages' => [],
            'aliases' => [],
            'import-file' => 'phpkg.imports.php'
        ];
        Files\save_array_as_json($logger_package_dir . '/phpkg.config.json', $logger_package_config);
        
        // Create logger package source files
        $logger_source_dir = $logger_package_dir . '/Source';
        Files\make_directory_recursively($logger_source_dir);
        
        $logger_class_content = <<<'PHP'
<?php

namespace Logger;

use Utils\UtilsHelper;

class Logger
{
    public function log(string $message): void
    {
        $formatted = UtilsHelper::format($message);
        echo "[LOG] $formatted\n";
    }
}
PHP;
        Files\file_write($logger_source_dir . '/Logger.php', $logger_class_content);
        
        // Create Logger Services file with functions
        $logger_services_content = <<<'PHP'
<?php

namespace Logger\Services;

function log_info(string $message): void
{
    $logger = new \Logger\Logger();
    $logger->log("[INFO] $message");
}

function log_error(string $message): void
{
    $logger = new \Logger\Logger();
    $logger->log("[ERROR] $message");
}

function log_debug(string $message): void
{
    $logger = new \Logger\Logger();
    $logger->log("[DEBUG] $message");
}
PHP;
        Files\file_write($logger_source_dir . '/Services.php', $logger_services_content);
        
        return $temp_dir;
    },
    after: function (string $temp_dir) {
        // Clean up: remove temp dir
        Files\force_delete_recursive($temp_dir);
    }
);

test(
    title: 'it should handle specific file mappings in namespace configuration',
    case: function (string $temp_dir) {
        // Build the project
        $build_output = CliRunner\phpkg('build', ["--project=$temp_dir"]);
        
        // Should show success message
        Assertions\assert_true(
            str_contains($build_output, 'Build finished successfully') || 
            str_contains($build_output, 'Project built successfully'),
            'Build should complete successfully. Output: ' . $build_output
        );
        
        // Verify build directory was created
        $build_dir = $temp_dir . '/build';
        Assertions\assert_true(is_dir($build_dir), 'Build directory should be created');
        
        // Verify the Source directory structure is preserved
        $source_dir = $build_dir . '/Source';
        Assertions\assert_true(is_dir($source_dir), 'Source directory should be created');
        
        // Verify Model.php was copied
        $model_file = $source_dir . '/Interfaces/Model.php';
        Assertions\assert_true(file_exists($model_file), 'Model.php should be copied to Source/Interfaces directory');
        
        // Verify Model.php content
        $model_content = file_get_contents($model_file);
        $expected_model_content = <<<'EOD'
<?php

namespace Application;

interface Model
{
    public function getId(): int;
    public function getName(): string;
}
EOD;
        Str\assert_equal(normalize_paths_in_code($model_content), normalize_paths_in_code($expected_model_content));
        
        // Verify phpkg.imports.php was created with correct namespace and file mappings
        $import_file = $build_dir . '/phpkg.imports.php';
        Assertions\assert_true(file_exists($import_file), 'phpkg.imports.php file should be created');
        
        $import_content = file_get_contents($import_file);
        
        // Expected import file content with namespace mapping and specific file mapping
        // phpkg now uses relative paths for consistency
        $expected_import_content = <<<'EOD'
<?php

spl_autoload_register(function ($class) {
    $classes = [
        'Application\Model' => __DIR__ . '/Source/Interfaces/Model.php',
    ];

    if (array_key_exists($class, $classes)) {
        require $classes[$class];
    }

}, true, true);

spl_autoload_register(function ($class) {
    $namespaces = [
        'Application' => __DIR__ . '/Source',
    ];

    $realpath = null;

    foreach ($namespaces as $namespace => $path) {
        if (str_starts_with($class, $namespace)) {
            $pos = strpos($class, $namespace);
            if ($pos !== false) {
                $realpath = substr_replace($class, $path, $pos, strlen($namespace));
            }
            $realpath = str_replace("\\", DIRECTORY_SEPARATOR, $realpath) . '.php';
            if (file_exists($realpath)) {
                require $realpath;
            }

            return ;
        }
    }
});

EOD;
        
        // Normalize paths in both actual and expected content for cross-platform compatibility
        $normalized_actual = normalize_paths_in_code($import_content);
        $normalized_expected = normalize_paths_in_code($expected_import_content);
        Str\assert_equal($normalized_actual, $normalized_expected);
        
        return $temp_dir;
    },
    before: function () {
        // Create a temporary directory and initialize it as a phpkg project
        $temp_dir = sys_get_temp_dir() . '/' . uniqid('phpkg_mapping_test');
        Files\make_directory_recursively($temp_dir);
        
        // Initialize the project
        $init_output = CliRunner\phpkg('init', ["--project=$temp_dir"]);
        Assertions\assert_true(
            str_contains($init_output, 'Project has been initialized') || 
            str_contains($init_output, 'initialized'),
            'Project should be initialized. Output: ' . $init_output
        );
        
        // Update config to have namespace mapping with specific file mapping
        $config_file = $temp_dir . '/phpkg.config.json';
        $config = Files\read_json_as_array($config_file);
        $config['map'] = [
            'Application' => 'Source',
            'Application\Model' => 'Source/Interfaces/Model.php'
        ];
        Files\save_array_as_json($config_file, $config);
        
        // Create Source directory structure
        $source_dir = $temp_dir . '/Source';
        Files\make_directory_recursively($source_dir);
        
        // Create Interfaces subdirectory
        $interfaces_dir = $source_dir . '/Interfaces';
        Files\make_directory_recursively($interfaces_dir);
        
        // Create Model.php interface
        $model_content = <<<'PHP'
<?php

namespace Application;

interface Model
{
    public function getId(): int;
    public function getName(): string;
}
PHP;
        Files\file_write($interfaces_dir . '/Model.php', $model_content);
        
        return $temp_dir;
    },
    after: function (string $temp_dir) {
        // Clean up: remove temp dir
        Files\force_delete_recursive($temp_dir);
    }
);

test(
    title: 'it should compile entry point with hashbang and add import file',
    case: function (string $temp_dir) {
        // Build the project
        $build_output = CliRunner\phpkg('build', ["--project=$temp_dir"]);
        // Should show success message
        Assertions\assert_true(
            str_contains($build_output, 'Project built successfully'), 
            'Should show success message. Output: ' . $build_output
        );
        
        // Verify build directory was created
        $build_dir = $temp_dir . '/build';
        Assertions\assert_true(is_dir($build_dir), 'Build directory should be created');
        
        // Verify entry point file was compiled (with hashbang, no .php extension)
        $entry_point_build = $build_dir . '/cli';
        Assertions\assert_true(file_exists($entry_point_build), 'Entry point file should be compiled');
        
        // Read the compiled entry point
        $entry_point_content = file_get_contents($entry_point_build);
        
        // Verify hashbang is preserved
        Assertions\assert_true(
            str_starts_with(trim($entry_point_content), '#!/usr/bin/env php'),
            'Hashbang should be preserved in compiled entry point'
        );
        
        // Normalize paths in entry point content for comparison
        $normalized_entry_point_content = normalize_paths_in_code($entry_point_content);
        
        // Verify import file is added to entry point
        // Entry point is at: build/cli
        // Import file is at: build/phpkg.imports.php
        // They are in the same directory, so relative path is just the filename
        Assertions\assert_true(
            str_contains($normalized_entry_point_content, "require_once __DIR__ . '/phpkg.imports.php';"),
            'Import file should be added to entry point. Content: ' . $entry_point_content
        );
        
        // Verify function import is added (for the function used from project)
        // Entry point is at: build/cli
        // Helper is at: build/Source/Helper.php
        // Relative path from cli to Helper.php: Source/Helper.php
        Assertions\assert_true(
            str_contains($normalized_entry_point_content, "require_once __DIR__ . '/Source/Helper.php';"),
            'Function import should be added to entry point. Content: ' . $entry_point_content
        );
        
        // Verify the entry point content structure
        // Should have: hashbang, function import (from compilation), import file (from entry point processing), then the original code
        $lines = explode("\n", $normalized_entry_point_content);
        $hashbang_line = trim($lines[0]);
        Assertions\assert_true(
            $hashbang_line === '#!/usr/bin/env php',
            'First line should be hashbang'
        );
        
        // Verify both imports are present (order may vary, but both should be there)
        $import_file_line_found = false;
        $helper_import_line_found = false;
        foreach ($lines as $line) {
            if (str_contains($line, "require_once __DIR__ . '/phpkg.imports.php';")) {
                $import_file_line_found = true;
            }
            if (str_contains($line, "require_once __DIR__ . '/Source/Helper.php';")) {
                $helper_import_line_found = true;
            }
        }
        
        Assertions\assert_true($import_file_line_found, 'Import file line should be present');
        Assertions\assert_true($helper_import_line_found, 'Helper function import line should be present');
        
        // Verify original code is preserved
        Assertions\assert_true(
            str_contains($entry_point_content, 'use function Application\Helper\greet;'),
            'Original use statement should be preserved'
        );
        Assertions\assert_true(
            str_contains($entry_point_content, 'greet("World");'),
            'Original function call should be preserved'
        );
    },
    before: function () {
        // Create a temporary directory and initialize it as a phpkg project
        $temp_dir = sys_get_temp_dir() . '/' . uniqid('phpkg_build_hashbang_entry_point_test');
        Files\make_directory_recursively($temp_dir);
        
        // Initialize the project
        $init_output = CliRunner\phpkg('init', ["--project=$temp_dir"]);
        Assertions\assert_true(
            str_contains($init_output, 'Project has been initialized') || 
            str_contains($init_output, 'initialized'),
            'Project should be initialized. Output: ' . $init_output
        );
        
        // Update config to have entry point and namespace mapping
        $config_file = $temp_dir . '/phpkg.config.json';
        $config = Files\read_json_as_array($config_file);
        $config['entry-points'] = ['cli'];
        $config['map'] = ['Application' => 'Source'];
        Files\save_array_as_json($config_file, $config);
        
        // Create Source directory with Helper.php that has a function
        $source_dir = $temp_dir . '/Source';
        Files\make_directory_recursively($source_dir);
        
        $helper_content = <<<'PHP'
<?php

namespace Application\Helper;

function greet(string $name): void {
    echo "Hello, $name!\n";
}
PHP;
        Files\file_write($source_dir . '/Helper.php', $helper_content);
        
        // Create entry point file with hashbang (no .php extension)
        $entry_point_content = <<<'PHP'
#!/usr/bin/env php
<?php

use function Application\Helper\greet;

greet("World");
PHP;
        Files\file_write($temp_dir . '/cli', $entry_point_content);
        
        return $temp_dir;
    },
    after: function (string $temp_dir) {
        // Clean up: remove temp dir
        Files\force_delete_recursive($temp_dir);
    }
);
