<?php

namespace Tests\System\AddCommand\SupportSemanticVersioningTest;

use Phpkg\Classes\Environment;
use Phpkg\Classes\Project;
use PhpRepos\FileManager\Path;
use PhpRepos\FileManager\JsonFile;
use function PhpRepos\Cli\Output\assert_error;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_true;
use function PhpRepos\TestRunner\Runner\test;
use function Tests\Helper\reset_empty_project;
use function Tests\System\Assertions\assert_cli_1_0_0_installed;
use function Tests\System\Assertions\assert_cli_1_2_1_installed;
use function Tests\System\Assertions\assert_cli_2_0_0_installed;
use function Tests\System\Assertions\assert_console_1_0_0_installed;
use function Tests\System\Assertions\assert_datatype_1_0_0_installed;
use function Tests\System\Assertions\assert_datatype_1_1_0_installed;
use function Tests\System\Assertions\assert_file_manager_1_0_0_installed;
use function Tests\System\Assertions\assert_file_manager_2_0_0_installed;
use function Tests\System\Assertions\assert_file_manager_2_0_3_installed;
use function Tests\System\Assertions\assert_test_runner_1_0_0_installed;
use function Tests\System\Assertions\assert_test_runner_1_0_2_installed;
use function Tests\System\Assertions\assert_test_runner_1_1_0_installed;
use function Tests\System\Assertions\assert_test_runner_1_2_0_installed;

test(
    title: 'it should add the newest version when there is two version for a package',
    case: function () {
        shell_exec('php ' . root() . 'phpkg add git@github.com:php-repos/cli.git v1.2.1 --project=TestRequirements/Fixtures/EmptyProject');

        $project = Project::installed(Environment::setup(), Path::from_string(root() . 'TestRequirements/Fixtures/EmptyProject'));

        assert_true('v1.2.1' === JsonFile\to_array(root() . 'TestRequirements/Fixtures/EmptyProject/phpkg.config.json')['packages']['git@github.com:php-repos/cli.git'], 'Wrong version added to config');
        assert_true('v1.0.2' === JsonFile\to_array(root() . 'TestRequirements/Fixtures/EmptyProject/phpkg.config.json')['packages']['https://github.com/php-repos/test-runner.git'], 'Wrong version added for test runner');
        assert_cli_1_2_1_installed($project);
        assert_test_runner_1_0_2_installed($project);
        assert_datatype_1_0_0_installed($project);
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=TestRequirements/Fixtures/EmptyProject');
        shell_exec('php ' . root() . 'phpkg add https://github.com/php-repos/test-runner.git v1.0.2 --project=TestRequirements/Fixtures/EmptyProject');
    },
    after: function () {
        reset_empty_project();
    }
);

test(
    title: 'it should add the newest version for all sub packages when there is two version for a package',
    case: function () {
        shell_exec('php ' . root() . 'phpkg add git@github.com:php-repos/console.git v1.0.0 --project=TestRequirements/Fixtures/EmptyProject');

        $project = Project::installed(Environment::setup(), Path::from_string(root() . 'TestRequirements/Fixtures/EmptyProject'));

        assert_true([
               'https://github.com/php-repos/test-runner.git' => 'v1.1.0',
               'git@github.com:php-repos/console.git' => 'v1.0.0',
            ] === JsonFile\to_array(root() . 'TestRequirements/Fixtures/EmptyProject/phpkg.config.json')['packages'],
            'Config is wrong'
        );
        assert_cli_1_2_1_installed($project);
        assert_datatype_1_1_0_installed($project);
        assert_file_manager_2_0_3_installed($project);
        assert_test_runner_1_1_0_installed($project);
        assert_console_1_0_0_installed($project);
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=TestRequirements/Fixtures/EmptyProject');
        shell_exec('php ' . root() . 'phpkg add https://github.com/php-repos/test-runner.git v1.1.0 --project=TestRequirements/Fixtures/EmptyProject');
    },
    after: function () {
        reset_empty_project();
    }
);

test(
    title: 'it should show error message when the major version is different and force option not passed to add',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg add https://github.com/php-repos/cli.git v2.0.0 --project=TestRequirements/Fixtures/EmptyProject');

        $project = Project::installed(Environment::setup(), Path::from_string(root() . 'TestRequirements/Fixtures/EmptyProject'));
        $lines = explode("\n", trim($output));

        assert_error('There is a major upgrade in the version number. Make sure it is a compatible change and if it is, try updating by --force.', end($lines) . PHP_EOL);

        assert_true(! isset(JsonFile\to_array(root() . 'TestRequirements/Fixtures/EmptyProject/phpkg.config.json')['packages']['https://github.com/php-repos/cli.git']), 'Cli added wrongly to the repositories!');

        assert_datatype_1_0_0_installed($project);
        assert_cli_1_0_0_installed($project);
        assert_file_manager_1_0_0_installed($project);
        assert_test_runner_1_0_0_installed($project);
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=TestRequirements/Fixtures/EmptyProject');
        shell_exec('php ' . root() . 'phpkg add https://github.com/php-repos/test-runner.git v1.0.0 --project=TestRequirements/Fixtures/EmptyProject');
    },
    after: function () {
        reset_empty_project();
    }
);

test(
    title: 'it should add the package with major version difference when the force option passed',
    case: function () {
        shell_exec('php ' . root() . 'phpkg add https://github.com/php-repos/cli.git v2.0.0 --force --project=TestRequirements/Fixtures/EmptyProject');

        $project = Project::installed(Environment::setup(), Path::from_string(root() . 'TestRequirements/Fixtures/EmptyProject'));

        assert_true('v2.0.0' === JsonFile\to_array(root() . 'TestRequirements/Fixtures/EmptyProject/phpkg.config.json')['packages']['https://github.com/php-repos/cli.git'], 'Wrong version added to config for cli');

        assert_datatype_1_0_0_installed($project);
        assert_cli_2_0_0_installed($project);
        assert_file_manager_2_0_0_installed($project);
        assert_test_runner_1_1_0_installed($project);
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=TestRequirements/Fixtures/EmptyProject');
        shell_exec('php ' . root() . 'phpkg add https://github.com/php-repos/test-runner.git v1.0.0 --project=TestRequirements/Fixtures/EmptyProject');
    },
    after: function () {
        reset_empty_project();
    }
);

test(
    title: 'it should show error message instead of adding when package does not have major difference in version number but its dependencies does',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg add https://github.com/php-repos/test-runner.git v1.2.0 --project=TestRequirements/Fixtures/EmptyProject');

        $lines = explode("\n", trim($output));
        assert_error('There is a major upgrade in the version number. Make sure it is a compatible change and if it is, try updating by --force.', end($lines) . PHP_EOL);

        $project = Project::installed(Environment::setup(), Path::from_string(root() . 'TestRequirements/Fixtures/EmptyProject'));
        assert_datatype_1_0_0_installed($project);
        assert_cli_1_2_1_installed($project);
        assert_file_manager_1_0_0_installed($project);
        assert_test_runner_1_0_0_installed($project);
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=TestRequirements/Fixtures/EmptyProject');
        shell_exec('php ' . root() . 'phpkg alias test-runner https://github.com/php-repos/test-runner.git --project=TestRequirements/Fixtures/EmptyProject');
        shell_exec('php ' . root() . 'phpkg alias cli https://github.com/php-repos/cli.git --project=TestRequirements/Fixtures/EmptyProject');
        shell_exec('php ' . root() . 'phpkg add cli v1.2.1 --project=TestRequirements/Fixtures/EmptyProject');
    },
    after: function () {
        reset_empty_project();
    }
);

test(
    title: 'it should add when sub dependency has major version number difference and the force option is passed',
    case: function () {
        shell_exec('php ' . root() . 'phpkg add https://github.com/php-repos/test-runner.git v1.2.0 --force --project=TestRequirements/Fixtures/EmptyProject');

        $project = Project::installed(Environment::setup(), Path::from_string(root() . 'TestRequirements/Fixtures/EmptyProject'));

        assert_datatype_1_0_0_installed($project);
        assert_cli_2_0_0_installed($project);
        assert_file_manager_2_0_3_installed($project);
        assert_test_runner_1_2_0_installed($project);
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=TestRequirements/Fixtures/EmptyProject');
        shell_exec('php ' . root() . 'phpkg alias test-runner https://github.com/php-repos/test-runner.git --project=TestRequirements/Fixtures/EmptyProject');
        shell_exec('php ' . root() . 'phpkg alias cli https://github.com/php-repos/cli.git --project=TestRequirements/Fixtures/EmptyProject');
        shell_exec('php ' . root() . 'phpkg add cli v1.2.1 --project=TestRequirements/Fixtures/EmptyProject');
    },
    after: function () {
        reset_empty_project();
    }
);
