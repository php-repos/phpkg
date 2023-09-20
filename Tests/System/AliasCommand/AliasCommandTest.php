<?php

namespace Tests\System\AliasCommand\AliasCommandTest;

use function PhpRepos\Cli\Output\assert_error;
use function PhpRepos\Cli\Output\assert_line;
use function PhpRepos\Cli\Output\assert_success;
use function PhpRepos\FileManager\JsonFile\to_array;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_false;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_true;
use function PhpRepos\TestRunner\Runner\test;
use function Tests\Helper\reset_empty_project;

test(
    title: 'it should add the given alias to the config file',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg alias test-runner git@github.com:php-repos/test-runner.git --project=TestRequirements/Fixtures/EmptyProject');

        $lines = explode("\n", trim($output));

        assert_true(2 === count($lines), 'Number of output lines do not match' . $output);
        assert_line("Registering alias test-runner for git@github.com:php-repos/test-runner.git...", $lines[0] . PHP_EOL);
        assert_success("Alias test-runner has been registered for git@github.com:php-repos/test-runner.git.", $lines[1] . PHP_EOL);

        $config = to_array(root() . 'TestRequirements/Fixtures/EmptyProject/phpkg.config.json');

        assert_true(isset($config['aliases']['test-runner']) && $config['aliases']['test-runner'] === 'git@github.com:php-repos/test-runner.git');
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=TestRequirements/Fixtures/EmptyProject');
    },
    after: function () {
        reset_empty_project();
    }
);

test(
    title: 'it should show error message when the project is not initialized',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg alias test-runner git@github.com:php-repos/test-runner.git --project=TestRequirements/Fixtures/EmptyProject');

        $lines = explode("\n", trim($output));

        assert_true(2 === count($lines), 'Number of output lines do not match' . $output);
        assert_line("Registering alias test-runner for git@github.com:php-repos/test-runner.git...", $lines[0] . PHP_EOL);
        assert_error("Project is not initialized. Please try to initialize using the init command.", $lines[1] . PHP_EOL);
        assert_false(file_exists(root() . 'TestRequirements/Fixtures/EmptyProject/phpkg.config.json'));
    }
);

test(
    title: 'it should show error message when alias is registered',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg alias test-runner git@github.com:php-repos/test-runner.git --project=TestRequirements/Fixtures/EmptyProject');

        $lines = explode("\n", trim($output));

        assert_true(2 === count($lines), 'Number of output lines do not match' . $output);
        assert_line("Registering alias test-runner for git@github.com:php-repos/test-runner.git...", $lines[0] . PHP_EOL);
        assert_error("The alias is already registered for git@github.com:php-repos/test-runner.git.", $lines[1] . PHP_EOL);

        $config = to_array(root() . 'TestRequirements/Fixtures/EmptyProject/phpkg.config.json');

        assert_true(isset($config['aliases']['test-runner']) && $config['aliases']['test-runner'] === 'git@github.com:php-repos/test-runner.git');
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=TestRequirements/Fixtures/EmptyProject');
        shell_exec('php ' . root() . 'phpkg alias test-runner git@github.com:php-repos/test-runner.git --project=TestRequirements/Fixtures/EmptyProject');
    },
    after: function () {
        reset_empty_project();
    }
);

test(
    title: 'it should show error message when alias is registered for other package',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg alias test-runner git@github.com:php-repos/test-runner.git --project=TestRequirements/Fixtures/EmptyProject');

        $lines = explode("\n", trim($output));

        assert_true(2 === count($lines), 'Number of output lines do not match' . $output);
        assert_line("Registering alias test-runner for git@github.com:php-repos/test-runner.git...", $lines[0] . PHP_EOL);
        assert_error("The alias is already registered for git@github.com:php-repos/cli.git.", $lines[1] . PHP_EOL);

        $config = to_array(root() . 'TestRequirements/Fixtures/EmptyProject/phpkg.config.json');

        assert_true($config['aliases']['test-runner'] === 'git@github.com:php-repos/cli.git');
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=TestRequirements/Fixtures/EmptyProject');
        shell_exec('php ' . root() . 'phpkg alias test-runner git@github.com:php-repos/cli.git --project=TestRequirements/Fixtures/EmptyProject');
    },
    after: function () {
        reset_empty_project();
    }
);

test(
    title: 'it should show error message when alias is registered with different url',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg alias test-runner https://github.com/php-repos/test-runner.git --project=TestRequirements/Fixtures/EmptyProject');

        $lines = explode("\n", trim($output));

        assert_true(2 === count($lines), 'Number of output lines do not match' . $output);
        assert_line("Registering alias test-runner for https://github.com/php-repos/test-runner.git...", $lines[0] . PHP_EOL);
        assert_error("The alias is already registered for git@github.com:php-repos/test-runner.git.", $lines[1] . PHP_EOL);

        $config = to_array(root() . 'TestRequirements/Fixtures/EmptyProject/phpkg.config.json');

        assert_true(isset($config['aliases']['test-runner']) && $config['aliases']['test-runner'] === 'git@github.com:php-repos/test-runner.git');
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=TestRequirements/Fixtures/EmptyProject');
        shell_exec('php ' . root() . 'phpkg alias test-runner git@github.com:php-repos/test-runner.git --project=TestRequirements/Fixtures/EmptyProject');
    },
    after: function () {
        reset_empty_project();
    }
);
