<?php

namespace Tests\System\UpdateCommand\UpdateToSpecificVersionTest;

use PhpRepos\FileManager\JsonFile;
use function PhpRepos\FileManager\Directory\clean;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\FileManager\Resolver\realpath;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_true;
use function PhpRepos\TestRunner\Runner\test;

test(
    title: 'it should update to the given version',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg update git@github.com:php-repos/released-package.git --version=v1.0.1 --project=TestRequirements/Fixtures/EmptyProject');

        $expected = <<<EOD
\e[39mUpdating package git@github.com:php-repos/released-package.git to version v1.0.1...
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
        assert_given_version_added('Package did not updated to given package version. ' . $output);
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=TestRequirements/Fixtures/EmptyProject');
        shell_exec('php ' . root() . 'phpkg add git@github.com:php-repos/released-package.git --version=v1.0.1 --project=TestRequirements/Fixtures/EmptyProject');
    },
    after: function () {
        clean(realpath(root() . 'TestRequirements/Fixtures/EmptyProject'));
    }
);

function assert_given_version_added($message)
{
    $config = JsonFile\to_array(realpath(root() . 'TestRequirements/Fixtures/EmptyProject/phpkg.config.json'));
    $meta = JsonFile\to_array(realpath(root() . 'TestRequirements/Fixtures/EmptyProject/phpkg.config-lock.json'));

    assert_true(
        isset($config['packages']['git@github.com:php-repos/released-package.git'])
        && 'v1.0.1' === $config['packages']['git@github.com:php-repos/released-package.git']
        && isset($meta['packages']['git@github.com:php-repos/released-package.git'])
        && 'v1.0.1' === $meta['packages']['git@github.com:php-repos/released-package.git']['version']
        && 'php-repos' === $meta['packages']['git@github.com:php-repos/released-package.git']['owner']
        && 'released-package' === $meta['packages']['git@github.com:php-repos/released-package.git']['repo']
        && '34c23761155364826342a79766b6d662aa0ae7fb' === $meta['packages']['git@github.com:php-repos/released-package.git']['hash'],
        $message
    );
}
