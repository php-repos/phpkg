<?php

namespace Tests\System\UpdateCommand\UpdateCommandTest;

use PhpRepos\FileManager\JsonFile;
use function PhpRepos\FileManager\Directory\clean;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\FileManager\Resolver\realpath;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_true;
use function PhpRepos\TestRunner\Runner\test;
use function Tests\Helper\reset_empty_project;

test(
    title: 'it should show error message when the project is not initialized',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg update git@github.com:php-repos/simple-package.git --project=TestRequirements/Fixtures/EmptyProject');

        $expected = <<<EOD
\e[39mUpdating package git@github.com:php-repos/simple-package.git to latest version...
\e[91mProject is not initialized. Please try to initialize using the init command.\e[39m

EOD;

        assert_true($expected === $output, 'Output is not correct:' . PHP_EOL . $expected . PHP_EOL . $output);
    }
);

test(
    title: 'it should show error message when project is not installed',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg update git@github.com:php-repos/released-package.git --project=TestRequirements/Fixtures/EmptyProject');

        $expected = <<<EOD
\e[39mUpdating package git@github.com:php-repos/released-package.git to latest version...
\e[39mSetting env credential...
\e[39mLoading configs...
\e[39mFinding package in configs...
\e[39mSetting package version...
\e[39mLoading package's config...
\e[91mIt seems you didn't run the install command. Please make sure you installed your required packages.\e[39m

EOD;

        assert_true($expected === $output, 'Output is not correct:' . PHP_EOL . $expected . PHP_EOL . $output);
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=TestRequirements/Fixtures/EmptyProject');
        shell_exec('php ' . root() . 'phpkg add git@github.com:php-repos/released-package.git --version=v1.0.1 --project=TestRequirements/Fixtures/EmptyProject');
        clean(realpath(root() . 'TestRequirements/Fixtures/EmptyProject/Packages'));
    },
    after: function () {
        reset_empty_project();
    }
);

test(
    title: 'it should update to the latest version',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg update git@github.com:php-repos/released-package.git --project=TestRequirements/Fixtures/EmptyProject');

        $expected = <<<EOD
\e[39mUpdating package git@github.com:php-repos/released-package.git to latest version...
\e[39mSetting env credential...
\e[39mLoading configs...
\e[39mFinding package in configs...
\e[39mSetting package version...
\e[39mLoading package's config...
\e[39mDeleting package's files...
\e[39mDetecting version hash...
\e[39mDownloading the package with new version...
\e[39mUpdating configs...
\e[39mCommitting new configs...
\e[92mPackage git@github.com:php-repos/released-package.git has been updated.\e[39m

EOD;

        assert_true($expected === $output, 'Output is not correct:' . PHP_EOL . $expected . PHP_EOL . $output);
        assert_version_upgraded_in_config_file($output);
        assert_meta_updated($output);
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=TestRequirements/Fixtures/EmptyProject');
        shell_exec('php ' . root() . 'phpkg add git@github.com:php-repos/released-package.git --version=v1.0.1 --project=TestRequirements/Fixtures/EmptyProject');
    },
    after: function () {
        reset_empty_project();
    }
);

function assert_version_upgraded_in_config_file($message)
{
    $config = JsonFile\to_array(realpath(root() . 'TestRequirements/Fixtures/EmptyProject/phpkg.config.json'));

    assert_true((
            isset($config['packages']['git@github.com:php-repos/released-package.git'])
            && 'v1.1.0' === $config['packages']['git@github.com:php-repos/released-package.git']
        ),
        $message
    );
}

function assert_meta_updated($message)
{
    $meta = JsonFile\to_array(realpath(root() . 'TestRequirements/Fixtures/EmptyProject/phpkg.config-lock.json'));

    assert_true((
            isset($meta['packages']['git@github.com:php-repos/released-package.git'])
            && 'v1.1.0' === $meta['packages']['git@github.com:php-repos/released-package.git']['version']
            && 'php-repos' === $meta['packages']['git@github.com:php-repos/released-package.git']['owner']
            && 'released-package' === $meta['packages']['git@github.com:php-repos/released-package.git']['repo']
            && 'be24f45d8785c215901ba90b242f3b8a7d2bdbfb' === $meta['packages']['git@github.com:php-repos/released-package.git']['hash']
        ),
        $message
    );
}
