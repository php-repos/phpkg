<?php

namespace Tests\System\AddCommand\AddCommandTest;

use PhpRepos\FileManager\JsonFile;
use function PhpRepos\Cli\Output\assert_error;
use function PhpRepos\Cli\Output\assert_line;
use function PhpRepos\Cli\Output\assert_success;
use function PhpRepos\Datatype\Arr\last;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\FileManager\Resolver\realpath;
use function PhpRepos\TestRunner\Assertions\assert_true;
use function PhpRepos\TestRunner\Runner\test;
use function Tests\Helper\force_delete;
use function Tests\Helper\reset_empty_project;

test(
    title: 'it should show error message when project is not initialized',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg add git@github.com:php-repos/simple-package.git --project=TestRequirements/Fixtures/EmptyProject');

        $lines = explode("\n", trim($output));

        assert_true(2 === count($lines), 'Number of output lines do not match: ' . $output);
        assert_line("Adding package git@github.com:php-repos/simple-package.git latest version...", $lines[0] . PHP_EOL);
        assert_error("Project is not initialized. Please try to initialize using the init command.", $lines[1] . PHP_EOL);
    }
);

test(
    title: 'it should not add the package and show error message when project is not installed',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg add git@github.com:php-repos/simple-package.git --project=TestRequirements/Fixtures/EmptyProject');

        $lines = explode("\n", trim($output));

        assert_line("Adding package git@github.com:php-repos/simple-package.git latest version...", $lines[0] . PHP_EOL);
        assert_error("It seems you didn't run the install command. Please make sure you installed your required packages.", $lines[1] . PHP_EOL);
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=TestRequirements/Fixtures/EmptyProject');
        shell_exec('php ' . root() . 'phpkg add git@github.com:php-repos/released-package.git --version=v1.0.1 --project=TestRequirements/Fixtures/EmptyProject');
        force_delete(realpath(root() . 'TestRequirements/Fixtures/EmptyProject/Packages'));
    },
    after: function () {
        reset_empty_project();
    }
);

test(
    title: 'it should show error message when project is not a supported package',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg add git@github.com:php-repos/phpkg-installation.git --project=TestRequirements/Fixtures/EmptyProject');

        $lines = explode("\n", trim($output));

        assert_error('The package you provided is neither a valid `phpkg` package nor a `composer` package. Please ensure that you are using a supported package type.', last($lines) . PHP_EOL);
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=TestRequirements/Fixtures/EmptyProject');
    },
    after: function () {
        reset_empty_project();
    }
);

test(
    title: 'it should add package to the project',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg add git@github.com:php-repos/simple-package.git --project=TestRequirements/Fixtures/EmptyProject');

        assert_output($output);
        assert_config_file_created_for_simple_project('Config file is not created!' . $output);
        assert_simple_package_added_to_config('Simple Package does not added to config file properly! ' . $output);
        assert_packages_directory_created_for_empty_project('Package directory does not created.' . $output);
        assert_simple_package_cloned('Simple package does not cloned!' . $output);
        assert_meta_has_desired_data('Meta data is not what we want.' . $output);
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=TestRequirements/Fixtures/EmptyProject');
    },
    after: function () {
        reset_empty_project();
    }
);

test(
    title: 'it should add package to the project without trailing .git',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg add git@github.com:php-repos/simple-package --project=TestRequirements/Fixtures/EmptyProject');

        assert_simple_package_cloned('Simple package does not cloned!' . $output);
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=TestRequirements/Fixtures/EmptyProject');
    },
    after: function () {
        reset_empty_project();
    }
);

test(
    title: 'it should use same repo with git and https urls',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg add https://github.com/php-repos/simple-package.git --project=TestRequirements/Fixtures/EmptyProject');

        $lines = explode("\n", trim($output));

        assert_true(3 === count($lines), 'Number of output lines do not match' . $output);
        assert_line("Adding package https://github.com/php-repos/simple-package.git latest version...", $lines[0] . PHP_EOL);
        assert_line("Checking installed packages...", $lines[1] . PHP_EOL);
        assert_error("Package https://github.com/php-repos/simple-package.git is already exists.", $lines[2] . PHP_EOL);

        $config = JsonFile\to_array(root() . 'TestRequirements/Fixtures/EmptyProject/phpkg.config.json');
        $meta = JsonFile\to_array(root() . 'TestRequirements/Fixtures/EmptyProject/phpkg.config-lock.json');
        assert_true(count($config['packages']) === 1);
        assert_true(count($meta['packages']) === 1);
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=TestRequirements/Fixtures/EmptyProject');
        shell_exec('php ' . root() . 'phpkg add git@github.com:php-repos/simple-package.git --project=TestRequirements/Fixtures/EmptyProject');
    },
    after: function () {
        reset_empty_project();
    }
);

function assert_output($output)
{
    $lines = explode("\n", trim($output));

    assert_true(7 === count($lines), 'Number of output lines do not match' . $output);
    assert_line("Adding package git@github.com:php-repos/simple-package.git latest version...", $lines[0] . PHP_EOL);
    assert_line("Checking installed packages...", $lines[1] . PHP_EOL);
    assert_line("Setting package version...", $lines[2] . PHP_EOL);
    assert_line("Adding the package...", $lines[3] . PHP_EOL);
    assert_line("Updating configs...", $lines[4] . PHP_EOL);
    assert_line("Committing configs...", $lines[5] . PHP_EOL);
    assert_success("Package git@github.com:php-repos/simple-package.git has been added successfully.", $lines[6] . PHP_EOL);
}

function assert_config_file_created_for_simple_project($message)
{
    assert_true(file_exists(realpath(root() . 'TestRequirements/Fixtures/EmptyProject/phpkg.config.json')), $message);
}

function assert_packages_directory_created_for_empty_project($message)
{
    assert_true(file_exists(realpath(root() . 'TestRequirements/Fixtures/EmptyProject/Packages')), $message);
}

function assert_simple_package_cloned($message)
{
    assert_true(
        file_exists(realpath(root() . 'TestRequirements/Fixtures/EmptyProject/Packages/php-repos/simple-package'))
            && file_exists(realpath(root() . 'TestRequirements/Fixtures/EmptyProject/Packages/php-repos/simple-package/phpkg.config.json'))
            && file_exists(realpath(root() . 'TestRequirements/Fixtures/EmptyProject/Packages/php-repos/simple-package/README.md'))
        ,
        $message
    );
}

function assert_simple_package_added_to_config($message)
{
    $config = JsonFile\to_array(realpath(root() . 'TestRequirements/Fixtures/EmptyProject/phpkg.config.json'));

    assert_true((
            isset($config['packages']['git@github.com:php-repos/simple-package.git'])
            && 'development' === $config['packages']['git@github.com:php-repos/simple-package.git']
        ),
        $message
    );
}

function assert_meta_has_desired_data($message)
{
    $meta = JsonFile\to_array(realpath(root() . 'TestRequirements/Fixtures/EmptyProject/phpkg.config-lock.json'));

    assert_true((
            isset($meta['packages']['git@github.com:php-repos/simple-package.git'])
            && 'development' === $meta['packages']['git@github.com:php-repos/simple-package.git']['version']
            && 'php-repos' === $meta['packages']['git@github.com:php-repos/simple-package.git']['owner']
            && 'simple-package' === $meta['packages']['git@github.com:php-repos/simple-package.git']['repo']
            && '1022f2004a8543326a92c0ba606439db530a23c9' === $meta['packages']['git@github.com:php-repos/simple-package.git']['hash']
        ),
        $message
    );
}
