<?php

namespace Tests\System\UpdateCommand\UpdateUsingSemanticVersionTest;

use Phpkg\Classes\Project;
use PhpRepos\FileManager\Path;
use PhpRepos\FileManager\JsonFile;
use function PhpRepos\Cli\Output\assert_error;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\TestRunner\Assertions\assert_true;
use function PhpRepos\TestRunner\Runner\test;
use function Tests\Helper\reset_dummy_project;
use function Tests\System\Assertions\assert_cli_1_0_0_installed;
use function Tests\System\Assertions\assert_cli_1_1_0_installed;
use function Tests\System\Assertions\assert_cli_1_2_1_installed;
use function Tests\System\Assertions\assert_cli_2_0_0_installed;
use function Tests\System\Assertions\assert_console_1_0_0_installed;
use function Tests\System\Assertions\assert_datatype_1_0_0_installed;
use function Tests\System\Assertions\assert_datatype_1_1_0_installed;
use function Tests\System\Assertions\assert_file_manager_1_0_0_installed;
use function Tests\System\Assertions\assert_file_manager_2_0_0_installed;
use function Tests\System\Assertions\assert_file_manager_2_0_3_installed;
use function Tests\System\Assertions\assert_test_runner_1_0_0_installed;
use function Tests\System\Assertions\assert_test_runner_1_1_0_installed;
use function Tests\System\Assertions\assert_test_runner_1_2_0_installed;

test(
    title: 'it should update considering semantic versioning',
    case: function () {
        shell_exec('php ' . root() . 'phpkg update https://github.com/php-repos/cli.git v1.1.0 --project=../../DummyProject');

        $project = Project::initialized(Path::from_string(root() . '../../DummyProject'));
        $config = JsonFile\to_array(Path::from_string(root() . '../../DummyProject/phpkg.config.json'));

        assert_true('v1.1.0' === $config['packages']['https://github.com/php-repos/cli.git'], 'Wrong version added to config for cli');
        assert_datatype_1_0_0_installed($project);
        assert_file_manager_1_0_0_installed($project);
        assert_test_runner_1_0_0_installed($project);
        assert_cli_1_1_0_installed($project);
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=../../DummyProject');
        shell_exec('php ' . root() . 'phpkg add https://github.com/php-repos/cli.git v1.0.0 --project=../../DummyProject');
    },
    after: function () {
        reset_dummy_project();
    }
);

test(
    title: 'it should update the given package and consider higher version for each package',
    case: function () {
        shell_exec('php ' . root() . 'phpkg update https://github.com/php-repos/test-runner.git v1.1.0 --force --project=../../DummyProject');

        $project = Project::initialized(Path::from_string(root() . '../../DummyProject'));

        $config = JsonFile\to_array(Path::from_string(root() . '../../DummyProject/phpkg.config.json'));

        assert_true('v1.0.0' === $config['packages']['https://github.com/php-repos/cli.git'], 'Wrong version added to config for cli');
        assert_true('v1.1.0' === $config['packages']['https://github.com/php-repos/test-runner.git'], 'Wrong version added to config for test-runner');
        assert_datatype_1_0_0_installed($project);
        assert_cli_1_0_0_installed($project);
        assert_file_manager_2_0_0_installed($project);
        assert_test_runner_1_1_0_installed($project);
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=../../DummyProject');
        shell_exec('php ' . root() . 'phpkg add https://github.com/php-repos/cli.git v1.0.0 --project=../../DummyProject');
        shell_exec('php ' . root() . 'phpkg add https://github.com/php-repos/test-runner.git v1.0.0 --project=../../DummyProject');
    },
    after: function () {
        reset_dummy_project();
    }
);

test(
    title: 'it should update package when major version number is higher',
    case: function () {
        shell_exec('php ' . root() . 'phpkg update https://github.com/php-repos/cli.git v2.0.0 --force --project=../../DummyProject');

        $project = Project::initialized(Path::from_string(root() . '../../DummyProject'));

        $config = JsonFile\to_array(Path::from_string(root() . '../../DummyProject/phpkg.config.json'));

        assert_true('v2.0.0' === $config['packages']['https://github.com/php-repos/cli.git'], 'Wrong version added to config for cli');
        assert_datatype_1_0_0_installed($project);
        assert_cli_2_0_0_installed($project);
        assert_file_manager_2_0_0_installed($project);
        assert_test_runner_1_1_0_installed($project);
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=../../DummyProject');
        shell_exec('php ' . root() . 'phpkg add https://github.com/php-repos/cli.git v1.1.0 --project=../../DummyProject');
    },
    after: function () {
        reset_dummy_project();
    }
);

test(
    title: 'it should update package when a portion of the version passed',
    case: function () {
        shell_exec('php ' . root() . 'phpkg update https://github.com/php-repos/cli.git v1.2 --project=../../DummyProject');

        $project = Project::initialized(Path::from_string(root() . '../../DummyProject'));

        $config = JsonFile\to_array(Path::from_string(root() . '../../DummyProject/phpkg.config.json'));

        assert_true('v1.2.1' === $config['packages']['https://github.com/php-repos/cli.git'], 'Wrong version added to config for cli');
        assert_datatype_1_0_0_installed($project);
        assert_cli_1_2_1_installed($project);
        assert_file_manager_1_0_0_installed($project);
        assert_test_runner_1_0_0_installed($project);
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=../../DummyProject');
        shell_exec('php ' . root() . 'phpkg add https://github.com/php-repos/cli.git v1.1.0 --project=../../DummyProject');
    },
    after: function () {
        reset_dummy_project();
    }
);

test(
    title: 'it should show error message when the major version is different and force option not passed to update',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg update https://github.com/php-repos/cli.git v2.0.0 --project=../../DummyProject');

        $project = Project::initialized(Path::from_string(root() . '../../DummyProject'));

        $config = JsonFile\to_array(Path::from_string(root() . '../../DummyProject/phpkg.config.json'));

        $lines = explode("\n", trim($output));

        assert_error('There is a major upgrade in the version number. Make sure it is a compatible change and if it is, try updating by --force.', end($lines) . PHP_EOL);

        assert_true('v1.1.0' === $config['packages']['https://github.com/php-repos/cli.git'], 'Wrong version added to config for cli');

        assert_datatype_1_0_0_installed($project);
        assert_cli_1_1_0_installed($project);
        assert_file_manager_1_0_0_installed($project);
        assert_test_runner_1_0_0_installed($project);
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=../../DummyProject');
        shell_exec('php ' . root() . 'phpkg add https://github.com/php-repos/cli.git v1.1.0 --project=../../DummyProject');
        shell_exec('php ' . root() . 'phpkg add https://github.com/php-repos/test-runner.git v1.0.0 --project=../../DummyProject');
    },
    after: function () {
        reset_dummy_project();
    }
);

test(
    title: 'it should update the package with major version difference when the force option passed',
    case: function () {
        shell_exec('php ' . root() . 'phpkg update https://github.com/php-repos/cli.git v2.0.0 --force --project=../../DummyProject');

        $project = Project::initialized(Path::from_string(root() . '../../DummyProject'));

        $config = JsonFile\to_array(Path::from_string(root() . '../../DummyProject/phpkg.config.json'));

        assert_true('v2.0.0' === $config['packages']['https://github.com/php-repos/cli.git'], 'Wrong version added to config for cli');

        assert_datatype_1_0_0_installed($project);
        assert_cli_2_0_0_installed($project);
        assert_file_manager_2_0_0_installed($project);
        assert_test_runner_1_1_0_installed($project);
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=../../DummyProject');
        shell_exec('php ' . root() . 'phpkg add https://github.com/php-repos/cli.git v1.1.0 --project=../../DummyProject');
        shell_exec('php ' . root() . 'phpkg add https://github.com/php-repos/test-runner.git v1.0.0 --project=../../DummyProject');
    },
    after: function () {
        reset_dummy_project();
    }
);

test(
    title: 'it should show error message instead of updating when package does not have major difference in version number but its dependencies does',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg update https://github.com/php-repos/test-runner.git v1.2.0 --project=../../DummyProject');

        $lines = explode("\n", trim($output));

        assert_error('There is a major upgrade in the version number. Make sure it is a compatible change and if it is, try updating by --force.', end($lines) . PHP_EOL);
        $project = Project::initialized(Path::from_string(root() . '../../DummyProject'));

        assert_datatype_1_0_0_installed($project);
        assert_cli_1_2_1_installed($project);
        assert_file_manager_2_0_0_installed($project);
        assert_test_runner_1_1_0_installed($project);
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=../../DummyProject');
        shell_exec('php ' . root() . 'phpkg alias test-runner https://github.com/php-repos/test-runner.git --project=../../DummyProject');
        shell_exec('php ' . root() . 'phpkg alias cli https://github.com/php-repos/cli.git --project=../../DummyProject');
        shell_exec('php ' . root() . 'phpkg add cli v1.2.1 --project=../../DummyProject');
        shell_exec('php ' . root() . 'phpkg add test-runner v1.1.0 --force --project=../../DummyProject');
    },
    after: function () {
        reset_dummy_project();
    }
);

test(
    title: 'it should update when sub dependency has major version number difference and the force option is passed',
    case: function () {
        shell_exec('php ' . root() . 'phpkg update https://github.com/php-repos/test-runner.git v1.2.0 --force --project=../../DummyProject');

        $project = Project::initialized(Path::from_string(root() . '../../DummyProject'));

        assert_datatype_1_0_0_installed($project);
        assert_cli_2_0_0_installed($project);
        assert_file_manager_2_0_3_installed($project);
        assert_test_runner_1_2_0_installed($project);
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=../../DummyProject');
        shell_exec('php ' . root() . 'phpkg alias test-runner https://github.com/php-repos/test-runner.git --project=../../DummyProject');
        shell_exec('php ' . root() . 'phpkg alias cli https://github.com/php-repos/cli.git --project=../../DummyProject');
        shell_exec('php ' . root() . 'phpkg add cli v1.2.1 --project=../../DummyProject');
        shell_exec('php ' . root() . 'phpkg add test-runner v1.1.0 --force --project=../../DummyProject');
    },
    after: function () {
        reset_dummy_project();
    }
);

test(
    title: 'it should replace the exists version in the lock file with the new given version',
    case: function () {
        shell_exec('php ' . root() . 'phpkg update https://github.com/php-repos/test-runner.git v1.2.0 --force --project=../../DummyProject');

        $project = Project::initialized(Path::from_string(root() . '../../DummyProject'));

        $meta = JsonFile\to_array(Path::from_string(root() . '../../DummyProject/phpkg.config-lock.json'));

        assert_console_1_0_0_installed($project);
        assert_datatype_1_1_0_installed($project);
        assert_cli_2_0_0_installed($project);
        assert_file_manager_2_0_3_installed($project);
        assert_test_runner_1_2_0_installed($project);
        assert_true(5 === count($meta['packages']), 'There is extra package in dependencies!');
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=../../DummyProject');
        shell_exec('php ' . root() . 'phpkg alias test-runner https://github.com/php-repos/test-runner.git --project=../../DummyProject');
        shell_exec('php ' . root() . 'phpkg alias console https://github.com/php-repos/console.git --project=../../DummyProject');
        shell_exec('php ' . root() . 'phpkg add console v1.0.0 --force --project=../../DummyProject');
        shell_exec('php ' . root() . 'phpkg add test-runner v1.1.0 --project=../../DummyProject');
    },
    after: function () {
        reset_dummy_project();
    }
);
