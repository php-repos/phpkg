<?php

namespace Tests\System\InstallCommand\InstallCommandTest;

use PhpRepos\FileManager\JsonFile;
use function PhpRepos\FileManager\Directory\clean;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\FileManager\Resolver\realpath;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_true;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_false;
use function PhpRepos\TestRunner\Runner\test;

test(
    title: 'it should show error message when the project is not initialized',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg install --project=TestRequirements/Fixtures/EmptyProject');

        $expected = <<<EOD
\e[39mInstalling packages...
\e[91mProject is not initialized. Please try to initialize using the init command.\e[39m

EOD;

        assert_true($expected === $output, 'Output is not correct:' . PHP_EOL . $expected . PHP_EOL . $output);
    }
);

test(
    title: 'it should install packages from lock file',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg install --project=TestRequirements/Fixtures/EmptyProject');

        assert_output($output);
        assert_config_file_content_not_changed('Config file has been changed!' . $output);
        assert_meta_file_content_not_changed('Released Package metadata does not added to meta file properly! ' . $output);
        assert_package_exists_in_packages_directory('Package does not exist in the packages\' directory.' . $output);
        assert_zip_file_deleted('Zip file has not been deleted.' . $output);
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=TestRequirements/Fixtures/EmptyProject');
        shell_exec('php ' . root() . 'phpkg add git@github.com:php-repos/released-package.git --version=v1.0.1 --project=TestRequirements/Fixtures/EmptyProject');
        clean(realpath(root() . 'TestRequirements/Fixtures/EmptyProject/Packages'));
    },
    after: function () {
        clean(realpath(root() . 'TestRequirements/Fixtures/EmptyProject'));
    },
);

function assert_output($output)
{
    $packages = root() . 'TestRequirements/Fixtures/EmptyProject/Packages/';
    $expected = <<<EOD
\e[39mInstalling packages...
\e[39mSetting env credential...
\e[39mLoading configs...
\e[39mDownloading packages...
\e[39mDownloading package git@github.com:php-repos/released-package.git to {$packages}php-repos/released-package
\e[39mDownloading package git@github.com:php-repos/complex-package to {$packages}php-repos/complex-package
\e[39mDownloading package git@github.com:php-repos/simple-package.git to {$packages}php-repos/simple-package
\e[92mPackages has been installed successfully.\e[39m

EOD;

    assert_true($expected === $output, 'Output is not correct:' . PHP_EOL . $expected . PHP_EOL . $output);
}

function assert_config_file_content_not_changed($message)
{
    $config = JsonFile\to_array(realpath(root() . 'TestRequirements/Fixtures/EmptyProject/phpkg.config.json'));

    assert_true((
            isset($config['packages']['git@github.com:php-repos/released-package.git'])
            && 'v1.0.1' === $config['packages']['git@github.com:php-repos/released-package.git']
        ),
        $message
    );
}

function assert_meta_file_content_not_changed($message)
{
    $meta = JsonFile\to_array(realpath(root() . 'TestRequirements/Fixtures/EmptyProject/phpkg.config-lock.json'));

    assert_true((
            isset($meta['packages']['git@github.com:php-repos/released-package.git'])
            && 'v1.0.1' === $meta['packages']['git@github.com:php-repos/released-package.git']['version']
            && 'php-repos' === $meta['packages']['git@github.com:php-repos/released-package.git']['owner']
            && 'released-package' === $meta['packages']['git@github.com:php-repos/released-package.git']['repo']
            && '34c23761155364826342a79766b6d662aa0ae7fb' === $meta['packages']['git@github.com:php-repos/released-package.git']['hash']
        ),
        $message
    );
}

function assert_package_exists_in_packages_directory($message)
{
    assert_true((
            file_exists(realpath(root() . 'TestRequirements/Fixtures/EmptyProject/Packages/php-repos/released-package'))
            && file_exists(realpath(root() . 'TestRequirements/Fixtures/EmptyProject/Packages/php-repos/released-package/phpkg.config.json'))
            && file_exists(realpath(root() . 'TestRequirements/Fixtures/EmptyProject/Packages/php-repos/released-package/phpkg.config-lock.json'))
            && ! file_exists(realpath(root() . 'TestRequirements/Fixtures/EmptyProject/Packages/php-repos/released-package/Tests'))
        ),
        $message
    );
}

function assert_zip_file_deleted($message)
{
    assert_false(
        file_exists(realpath(root() . 'TestRequirements/Fixtures/EmptyProject/Packages/php-repos/released-package.zip')),
        $message
    );
}
