<?php

namespace Tests\System\AddCommand\AddCommandTest;

use PhpRepos\FileManager\JsonFile;
use function Phpkg\Providers\GitHub\github_token;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\FileManager\Resolver\realpath;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_true;
use function PhpRepos\TestRunner\Runner\test;
use function Tests\Helper\reset_empty_project;
use const Phpkg\Providers\GitHub\GITHUB_DOMAIN;

test(
    title: 'it should show error message when project is not initialized',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg add git@github.com:php-repos/simple-package.git --project=TestRequirements/Fixtures/EmptyProject');
        $expected = <<<EOD
\e[39mAdding package git@github.com:php-repos/simple-package.git latest version...
\e[91mProject is not initialized. Please try to initialize using the init command.\e[39m

EOD;

        assert_true($expected === $output, 'Output is not correct:' . PHP_EOL . $expected . PHP_EOL . $output);
    }
);

test(
    title: 'it should show error message when there is no credential files and there is no GITHUB_TOKEN',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg add git@github.com:php-repos/simple-package.git --project=TestRequirements/Fixtures/EmptyProject');

        $expected = <<<EOD
\e[39mAdding package git@github.com:php-repos/simple-package.git latest version...
\e[39mSetting env credential...
\e[91mThere is no credential file. Please use the `credential` command to add your token.\e[39m

EOD;

        assert_true($expected === $output, 'Output is not correct:' . PHP_EOL . $expected . PHP_EOL . $output);
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=TestRequirements/Fixtures/EmptyProject');
        rename(root() . 'credentials.json', root() . 'credentials.json.back');
        github_token('');
    },
    after: function () {
        reset_empty_project();
        rename(root() . 'credentials.json.back', root() . 'credentials.json');
    }
);

test(
    title: 'it should not show error message when there is no credential files but GITHUB_TOKEN is set',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg add git@github.com:php-repos/simple-package.git --project=TestRequirements/Fixtures/EmptyProject');

        $expected = <<<EOD
\e[39mAdding package git@github.com:php-repos/simple-package.git latest version...
\e[39mSetting env credential...
\e[39mLoading configs...
\e[39mChecking installed packages...
\e[39mSetting package version...
\e[39mCreating package directory...
\e[39mDetecting version hash...
\e[39mDownloading the package...
\e[39mUpdating configs...
\e[39mCommitting configs...
\e[92mPackage git@github.com:php-repos/simple-package.git has been added successfully.\e[39m

EOD;

        assert_true($expected === $output, 'Output is not correct:' . PHP_EOL . $expected . PHP_EOL . $output);
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=TestRequirements/Fixtures/EmptyProject');
        $credential = JsonFile\to_array(root() . 'credentials.json');
        github_token($credential[GITHUB_DOMAIN]['token']);
        rename(root() . 'credentials.json', root() . 'credentials.json.back');
    },
    after: function () {
        reset_empty_project();
        rename(root() . 'credentials.json.back', root() . 'credentials.json');
    }
);

test(
    title: 'it should show error message when token is invalid',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg add git@github.com:php-repos/simple-package.git --project=TestRequirements/Fixtures/EmptyProject');

        $expected = <<<EOD
\e[39mAdding package git@github.com:php-repos/simple-package.git latest version...
\e[39mSetting env credential...
\e[39mLoading configs...
\e[39mChecking installed packages...
\e[39mSetting package version...
\e[91mThe GitHub token is not valid. Either, you didn't set one yet, or it is not valid. Please use the `credential` command to set a valid token.\e[39m

EOD;

        assert_true($expected === $output, 'Output is not correct:' . PHP_EOL . $expected . PHP_EOL . $output);
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=TestRequirements/Fixtures/EmptyProject');
        rename(root() . 'credentials.json', root() . 'credentials.json.back');
        shell_exec('php ' . root() . 'phpkg credential github.com not-valid');
        github_token('');
    },
    after: function () {
        reset_empty_project();
        rename(root() . 'credentials.json.back', root() . 'credentials.json');
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

        $expected = <<<EOD
\e[39mAdding package https://github.com/php-repos/simple-package.git latest version...
\e[39mSetting env credential...
\e[39mLoading configs...
\e[39mChecking installed packages...
\e[91mPackage https://github.com/php-repos/simple-package.git is already exists.\e[39m

EOD;

        assert_true($expected === $output, 'Output is not correct:' . PHP_EOL . $expected . PHP_EOL . $output);

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
    $expected = <<<EOD
\e[39mAdding package git@github.com:php-repos/simple-package.git latest version...
\e[39mSetting env credential...
\e[39mLoading configs...
\e[39mChecking installed packages...
\e[39mSetting package version...
\e[39mCreating package directory...
\e[39mDetecting version hash...
\e[39mDownloading the package...
\e[39mUpdating configs...
\e[39mCommitting configs...
\e[92mPackage git@github.com:php-repos/simple-package.git has been added successfully.\e[39m

EOD;

    assert_true($expected === $output, 'Output is not correct:' . PHP_EOL . $expected . PHP_EOL . $output);
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
