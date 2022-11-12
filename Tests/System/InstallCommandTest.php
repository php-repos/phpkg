<?php

namespace Tests\System\InstallCommandTest;

use Saeghe\Saeghe\FileManager\FileType\Json;
use function Saeghe\Cli\IO\Write\assert_error;
use function Saeghe\Cli\IO\Write\assert_success;
use function Saeghe\Saeghe\FileManager\Directory\clean;
use function Saeghe\Saeghe\FileManager\Resolver\realpath;
use function Saeghe\Saeghe\Providers\GitHub\github_token;
use function Saeghe\TestRunner\Assertions\Boolean\assert_true;
use function Saeghe\TestRunner\Assertions\Boolean\assert_false;
use const Saeghe\Saeghe\Providers\GitHub\GITHUB_DOMAIN;

test(
    title: 'it should show error message when the credential file is not exists and there is no GITHUB_TOKEN',
    case: function () {
        $output = shell_exec('php ' . root() . 'saeghe install --project=TestRequirements/Fixtures/EmptyProject');

        assert_error('There is no credential file. Please use the `credential` command to add your token.', $output);
    },
    before: function () {
        shell_exec('php ' . root() . 'saeghe init --project=TestRequirements/Fixtures/EmptyProject');
        shell_exec('php ' . root() . 'saeghe add git@github.com:saeghe/released-package.git --version=v1.0.3 --project=TestRequirements/Fixtures/EmptyProject');
        clean(realpath(root() . 'TestRequirements/Fixtures/EmptyProject/Packages'));
        rename(root() . 'credentials.json', root() . 'credentials.json.back');
        github_token('');
    },
    after: function () {
        clean(realpath(root() . 'TestRequirements/Fixtures/EmptyProject'));
        rename(root() . 'credentials.json.back', root() . 'credentials.json');
    },
);

test(
    title: 'it should not show error message when the credential file is not exists and GITHUB_TOKEN is set',
    case: function () {
        $output = shell_exec('php ' . root() . 'saeghe install --project=TestRequirements/Fixtures/EmptyProject');

        assert_error('There is no credential file. Please use the `credential` command to add your token.', $output);
    },
    before: function () {
        shell_exec('php ' . root() . 'saeghe init --project=TestRequirements/Fixtures/EmptyProject');
        shell_exec('php ' . root() . 'saeghe add git@github.com:saeghe/released-package.git --version=v1.0.3 --project=TestRequirements/Fixtures/EmptyProject');
        clean(realpath(root() . 'TestRequirements/Fixtures/EmptyProject/Packages'));
        rename(root() . 'credentials.json', root() . 'credentials.json.back');
        github_token('');
    },
    after: function () {
        clean(realpath(root() . 'TestRequirements/Fixtures/EmptyProject'));
        rename(root() . 'credentials.json.back', root() . 'credentials.json');
    },
);

test(
    title: 'it should show error message when the credential file is not exists and there is no GITHUB_TOKEN',
    case: function () {
        $output = shell_exec('php ' . root() . 'saeghe install --project=TestRequirements/Fixtures/EmptyProject');

        assert_success('Packages has been installed successfully.', $output);
    },
    before: function () {
        shell_exec('php ' . root() . 'saeghe init --project=TestRequirements/Fixtures/EmptyProject');
        shell_exec('php ' . root() . 'saeghe add git@github.com:saeghe/released-package.git --version=v1.0.3 --project=TestRequirements/Fixtures/EmptyProject');
        clean(realpath(root() . 'TestRequirements/Fixtures/EmptyProject/Packages'));
        $credential = Json\to_array(root() . 'credentials.json');
        github_token($credential[GITHUB_DOMAIN]['token']);
        rename(root() . 'credentials.json', root() . 'credentials.json.back');
    },
    after: function () {
        clean(realpath(root() . 'TestRequirements/Fixtures/EmptyProject'));
        rename(root() . 'credentials.json.back', root() . 'credentials.json');
    },
);

test(
    title: 'it should install packages from lock file',
    case: function () {
        $output = shell_exec('php ' . root() . 'saeghe install --project=TestRequirements/Fixtures/EmptyProject');

        assert_success('Packages has been installed successfully.', $output);
        assert_config_file_content_not_changed('Config file has been changed!' . $output);
        assert_meta_file_content_not_changed('Released Package metadata does not added to meta file properly! ' . $output);
        assert_package_exists_in_packages_directory('Package does not exist in the packages\' directory.' . $output);
        assert_zip_file_deleted('Zip file has not been deleted.' . $output);
    },
    before: function () {
        shell_exec('php ' . root() . 'saeghe init --project=TestRequirements/Fixtures/EmptyProject');
        shell_exec('php ' . root() . 'saeghe add git@github.com:saeghe/released-package.git --version=v1.0.3 --project=TestRequirements/Fixtures/EmptyProject');
        clean(realpath(root() . 'TestRequirements/Fixtures/EmptyProject/Packages'));
    },
    after: function () {
        clean(realpath(root() . 'TestRequirements/Fixtures/EmptyProject'));
    },
);

function assert_config_file_content_not_changed($message)
{
    $config = Json\to_array(realpath(root() . 'TestRequirements/Fixtures/EmptyProject/saeghe.config.json'));

    assert_true((
            isset($config['packages']['git@github.com:saeghe/released-package.git'])
            && 'v1.0.3' === $config['packages']['git@github.com:saeghe/released-package.git']
        ),
        $message
    );
}

function assert_meta_file_content_not_changed($message)
{
    $meta = Json\to_array(realpath(root() . 'TestRequirements/Fixtures/EmptyProject/saeghe.config-lock.json'));

    assert_true((
            isset($meta['packages']['git@github.com:saeghe/released-package.git'])
            && 'v1.0.3' === $meta['packages']['git@github.com:saeghe/released-package.git']['version']
            && 'saeghe' === $meta['packages']['git@github.com:saeghe/released-package.git']['owner']
            && 'released-package' === $meta['packages']['git@github.com:saeghe/released-package.git']['repo']
            && '9e9b796915596f7c5e0b91d2f9fa5f916a9b5cc8' === $meta['packages']['git@github.com:saeghe/released-package.git']['hash']
        ),
        $message
    );
}

function assert_package_exists_in_packages_directory($message)
{
    assert_true((
            file_exists(realpath(root() . 'TestRequirements/Fixtures/EmptyProject/Packages/saeghe/released-package'))
            && file_exists(realpath(root() . 'TestRequirements/Fixtures/EmptyProject/Packages/saeghe/released-package/saeghe.config.json'))
            && file_exists(realpath(root() . 'TestRequirements/Fixtures/EmptyProject/Packages/saeghe/released-package/saeghe.config-lock.json'))
            && ! file_exists(realpath(root() . 'TestRequirements/Fixtures/EmptyProject/Packages/saeghe/released-package/Tests'))
        ),
        $message
    );
}

function assert_zip_file_deleted($message)
{
    assert_false(file_exists(realpath(root() . 'TestRequirements/Fixtures/EmptyProject/Packages/saeghe/released-package.zip')),
        $message
    );
}
