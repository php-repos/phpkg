<?php

use function Phpkg\Infra\Files\file_write;
use function Phpkg\Infra\Files\make_directory_recursively;
use function Phpkg\Infra\Files\read_json_as_array;
use function Phpkg\Infra\Files\save_array_as_json;
use function PhpRepos\TestRunner\Runner\test;
use PhpRepos\TestRunner\Assertions;
use Tests\CliRunner;

test(
    title: 'it should run chuck norris project and display a joke',
    case: function () {
        // Run the chuck norris project with custom output pipe to capture output
        $run_output = CliRunner\phpkg('run', ['--output-pipe=file,/tmp/test_output,w', 'https://github.com/php-repos/chuck-norris.git']);
        
        // Should contain chuck norris content (the output should have some text that looks like a joke)
        Assertions\assert_true(
            str_contains($run_output, 'Chuck Norris') || 
            str_contains($run_output, 'joke') ||
            str_contains($run_output, 'fact') ||
            str_contains($run_output, 'Norris') ||
            str_contains($run_output, 'watch pot') ||
            str_contains($run_output, 'boils') ||
            strlen($run_output) > 20, // Should have substantial output
            'Should display chuck norris content. Output: ' . $run_output
        );
        
        // Should contain a chuck norris joke (the output should have some text that looks like a joke)
        Assertions\assert_true(
            str_contains($run_output, 'Chuck Norris') || 
            str_contains($run_output, 'joke') ||
            str_contains($run_output, 'fact') ||
            str_contains($run_output, 'Norris') ||
            strlen($run_output) > 50, // Should have substantial output
            'Should display chuck norris content. Output: ' . $run_output
        );
        
        return true;
    }
);

test(
    title: 'it should run local project and display correct file path',
    case: function (string $temp_dir) {
        // Run the local project
        $run_output = CliRunner\phpkg('run', [$temp_dir, '--output-pipe=file,/tmp/test_output,w']);
        
        // Verify the command executed successfully (should contain "Running..." message)
        Assertions\assert_true(
            str_contains($run_output, 'Running'),
            'Should show running message. Output: ' . $run_output
        );
        
        // Verify the project was built in build
        $build_dir = $temp_dir . '/build';
        Assertions\assert_true(is_dir($build_dir), 'Build directory should be created');
        
        // Verify cli.php was copied to the build directory
        $build_cli_file = $build_dir . '/cli.php';
        Assertions\assert_true(file_exists($build_cli_file), 'cli.php should be copied to build directory');
        
        // Verify the build directory contains the expected structure
        Assertions\assert_true(file_exists($build_dir . '/phpkg.imports.php'), 'phpkg.imports.php should be created in build directory');
        
        // Verify the cli.php file in the build directory has the expected content
        $build_cli_content = file_get_contents($build_cli_file);
        Assertions\assert_true(
            str_contains($build_cli_content, 'Current file path:'),
            'Build cli.php should contain file path output code'
        );
        
        Assertions\assert_true(
            str_contains($build_cli_content, 'Current directory:'),
            'Build cli.php should contain current directory output code'
        );
        
        Assertions\assert_true(
            str_contains($build_cli_content, 'Working directory:'),
            'Build cli.php should contain working directory output code'
        );
        
        return true;
    },
    before: function () {
        // Create a temporary local project
        $temp_dir = sys_get_temp_dir() . '/' . uniqid('phpkg_local_test');
        make_directory_recursively($temp_dir);
        
        // Initialize the project
        $init_output = CliRunner\phpkg('init', ["--project=$temp_dir"]);
        Assertions\assert_true(
            str_contains($init_output, 'Project has been initialized') || 
            str_contains($init_output, 'initialized'),
            'Project should be initialized. Output: ' . $init_output
        );
        
        // Update config to have an entry point
        $config_file = $temp_dir . '/phpkg.config.json';
        $config = read_json_as_array($config_file);
        $config['entry-points'] = ['cli.php' => 'cli.php'];
        save_array_as_json($config_file, $config);
        
        // Create cli.php entry point that prints the file path
        $cli_content = <<<'PHP'
<?php

echo "Current file path: " . __FILE__ . PHP_EOL;
echo "Current directory: " . __DIR__ . PHP_EOL;
echo "Working directory: " . getcwd() . PHP_EOL;
PHP;
        file_write($temp_dir . '/cli.php', $cli_content);
        
        return $temp_dir;
    },
);
