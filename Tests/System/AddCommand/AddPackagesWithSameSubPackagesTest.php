<?php

namespace Tests\System\AddCommand\AddPackagesWithSameSubPackagesTest;

use PhpRepos\FileManager\JsonFile;
use function PhpRepos\FileManager\Directory\clean;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\FileManager\Resolver\realpath;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_true;
use function PhpRepos\TestRunner\Runner\test;

test(
    title: 'it should not stuck if two packages using the same dependencies',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg add git@github.com:php-repos/cli.git --project=TestRequirements/Fixtures/EmptyProject');

        assert_output($output);
        assert_true(file_exists(root() . 'TestRequirements/Fixtures/EmptyProject/Packages/php-repos/cli'));
        assert_true(file_exists(root() . 'TestRequirements/Fixtures/EmptyProject/Packages/php-repos/test-runner'));
        $config = JsonFile\to_array(root() . 'TestRequirements/Fixtures/EmptyProject/phpkg.config.json');
        assert_true((
                isset($config['packages']['git@github.com:php-repos/test-runner.git'])
                && isset($config['packages']['git@github.com:php-repos/cli.git'])
            ),
            'Config file has not been created properly.'
        );
        $meta = JsonFile\to_array(root() . 'TestRequirements/Fixtures/EmptyProject/phpkg.config-lock.json');
        assert_true(4 === count($meta['packages']), 'Count of packages in meta file is not correct.');
        assert_true((
                array_key_exists('git@github.com:php-repos/test-runner.git', $meta['packages'])
                && array_key_exists('https://github.com/php-repos/cli.git', $meta['packages'])
            ),
            'Meta file has not been created properly.'
        );
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=TestRequirements/Fixtures/EmptyProject');
        shell_exec('php ' . root() . 'phpkg add git@github.com:php-repos/test-runner.git --project=TestRequirements/Fixtures/EmptyProject');
    },
    after: function () {
        clean(realpath(root() . 'TestRequirements/Fixtures/EmptyProject'));
    }
);

function assert_output($output)
{
    $expected = <<<EOD
\e[39mAdding package git@github.com:php-repos/cli.git latest version...
\e[39mSetting env credential...
\e[39mLoading configs...
\e[39mChecking installed packages...
\e[39mSetting package version...
\e[39mCreating package directory...
\e[39mDetecting version hash...
\e[39mDownloading the package...
\e[39mUpdating configs...
\e[39mCommitting configs...
\e[92mPackage git@github.com:php-repos/cli.git has been added successfully.\e[39m

EOD;

    assert_true($expected === $output, 'Output is not correct:' . PHP_EOL . $expected . PHP_EOL . $output);
}
