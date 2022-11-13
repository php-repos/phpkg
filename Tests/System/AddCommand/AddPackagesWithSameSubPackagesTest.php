<?php

namespace Tests\System\AddCommand\AddPackagesWithSameSubPackagesTest;

use Saeghe\FileManager\FileType\Json;
use function Saeghe\Cli\IO\Write\assert_success;
use function Saeghe\FileManager\Directory\clean;
use function Saeghe\FileManager\Resolver\root;
use function Saeghe\FileManager\Resolver\realpath;
use function Saeghe\TestRunner\Assertions\Boolean\assert_true;

test(
    title: 'it should not stuck if two packages using the same dependencies',
    case: function () {
        $output = shell_exec('php ' . root() . 'saeghe add git@github.com:saeghe/cli.git --project=TestRequirements/Fixtures/EmptyProject');

        assert_success('Package git@github.com:saeghe/cli.git has been added successfully.', $output);
        assert_true(file_exists(root() . 'TestRequirements/Fixtures/EmptyProject/Packages/saeghe/cli'));
        assert_true(file_exists(root() . 'TestRequirements/Fixtures/EmptyProject/Packages/saeghe/test-runner'));
        $config = Json\to_array(root() . 'TestRequirements/Fixtures/EmptyProject/saeghe.config.json');
        assert_true((
                isset($config['packages']['git@github.com:saeghe/test-runner.git'])
                && isset($config['packages']['git@github.com:saeghe/cli.git'])
            ),
            'Config file has not been created properly.'
        );
        $meta = Json\to_array(root() . 'TestRequirements/Fixtures/EmptyProject/saeghe.config-lock.json');
        assert_true(2 === count($meta['packages']), 'Count of packages in meta file is not correct.');
        assert_true((
                $meta['packages'][array_key_first($meta['packages'])]['repo'] === 'test-runner'
                && $meta['packages'][array_key_last($meta['packages'])]['repo'] === 'cli'
            ),
            'Meta file has not been created properly.'
        );
    },
    before: function () {
        shell_exec('php ' . root() . 'saeghe init --project=TestRequirements/Fixtures/EmptyProject');
        shell_exec('php ' . root() . 'saeghe add git@github.com:saeghe/test-runner.git --project=TestRequirements/Fixtures/EmptyProject');
    },
    after: function () {
        clean(realpath(root() . 'TestRequirements/Fixtures/EmptyProject'));
    }
);
