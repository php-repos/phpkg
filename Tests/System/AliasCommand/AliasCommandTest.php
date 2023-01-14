<?php

namespace Tests\System\AliasCommand\AliasCommandTest;

use function PhpRepos\FileManager\Directory\clean;
use function PhpRepos\FileManager\FileType\Json\to_array;
use function PhpRepos\FileManager\Resolver\realpath;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_false;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_true;
use function PhpRepos\TestRunner\Runner\test;

test(
    title: 'it should add the given alias to the config file',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg alias test-runner git@github.com:php-repos/test-runner.git --project=TestRequirements/Fixtures/EmptyProject');

        $expected = <<<EOD
\e[39mRegistering alias test-runner for git@github.com:php-repos/test-runner.git...
\e[92mAlias test-runner has been registered for git@github.com:php-repos/test-runner.git.\e[39m

EOD;
        assert_true($expected === $output, 'Output is not correct:' . PHP_EOL . $expected . PHP_EOL . $output);

        $config = to_array(root() . 'TestRequirements/Fixtures/EmptyProject/phpkg.config.json');

        assert_true(isset($config['aliases']['test-runner']) && $config['aliases']['test-runner'] === 'git@github.com:php-repos/test-runner.git');
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=TestRequirements/Fixtures/EmptyProject');
    },
    after: function () {
        clean(realpath(root() . 'TestRequirements/Fixtures/EmptyProject'));
    }
);

test(
    title: 'it should show error message when the project is not initialized',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg alias test-runner git@github.com:php-repos/test-runner.git --project=TestRequirements/Fixtures/EmptyProject');
        $expected = <<<EOD
\e[39mRegistering alias test-runner for git@github.com:php-repos/test-runner.git...
\e[91mProject is not initialized. Please try to initialize using the init command.\e[39m

EOD;
        assert_true($expected === $output, 'Output is not correct:' . PHP_EOL . $expected . PHP_EOL . $output);

        assert_false(file_exists(root() . 'TestRequirements/Fixtures/EmptyProject/phpkg.config.json'));
    }
);

test(
    title: 'it should show error message when alias is registered',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg alias test-runner git@github.com:php-repos/test-runner.git --project=TestRequirements/Fixtures/EmptyProject');

        $expected = <<<EOD
\e[39mRegistering alias test-runner for git@github.com:php-repos/test-runner.git...
\e[91mThe alias is already registered for git@github.com:php-repos/test-runner.git.\e[39m

EOD;
        assert_true($expected === $output, 'Output is not correct:' . PHP_EOL . $expected . PHP_EOL . $output);

        $config = to_array(root() . 'TestRequirements/Fixtures/EmptyProject/phpkg.config.json');

        assert_true(isset($config['aliases']['test-runner']) && $config['aliases']['test-runner'] === 'git@github.com:php-repos/test-runner.git');
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=TestRequirements/Fixtures/EmptyProject');
        shell_exec('php ' . root() . 'phpkg alias test-runner git@github.com:php-repos/test-runner.git --project=TestRequirements/Fixtures/EmptyProject');
    },
    after: function () {
        clean(realpath(root() . 'TestRequirements/Fixtures/EmptyProject'));
    }
);

test(
    title: 'it should show error message when alias is registered for other package',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg alias test-runner git@github.com:php-repos/test-runner.git --project=TestRequirements/Fixtures/EmptyProject');

        $expected = <<<EOD
\e[39mRegistering alias test-runner for git@github.com:php-repos/test-runner.git...
\e[91mThe alias is already registered for git@github.com:php-repos/cli.git.\e[39m

EOD;
        assert_true($expected === $output, 'Output is not correct:' . PHP_EOL . $expected . PHP_EOL . $output);

        $config = to_array(root() . 'TestRequirements/Fixtures/EmptyProject/phpkg.config.json');

        assert_true($config['aliases']['test-runner'] === 'git@github.com:php-repos/cli.git');
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=TestRequirements/Fixtures/EmptyProject');
        shell_exec('php ' . root() . 'phpkg alias test-runner git@github.com:php-repos/cli.git --project=TestRequirements/Fixtures/EmptyProject');
    },
    after: function () {
        clean(realpath(root() . 'TestRequirements/Fixtures/EmptyProject'));
    }
);

test(
    title: 'it should show error message when alias is registered with different url',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg alias test-runner https://github.com/php-repos/test-runner.git --project=TestRequirements/Fixtures/EmptyProject');

        $expected = <<<EOD
\e[39mRegistering alias test-runner for https://github.com/php-repos/test-runner.git...
\e[91mThe alias is already registered for git@github.com:php-repos/test-runner.git.\e[39m

EOD;
        assert_true($expected === $output, 'Output is not correct:' . PHP_EOL . $expected . PHP_EOL . $output);

        $config = to_array(root() . 'TestRequirements/Fixtures/EmptyProject/phpkg.config.json');

        assert_true(isset($config['aliases']['test-runner']) && $config['aliases']['test-runner'] === 'git@github.com:php-repos/test-runner.git');
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=TestRequirements/Fixtures/EmptyProject');
        shell_exec('php ' . root() . 'phpkg alias test-runner git@github.com:php-repos/test-runner.git --project=TestRequirements/Fixtures/EmptyProject');
    },
    after: function () {
        clean(realpath(root() . 'TestRequirements/Fixtures/EmptyProject'));
    }
);
