<?php

namespace Tests\AddingComplexProjectTest;

test(
    title: 'it should add a complex project',
    case: function () {
        $output = shell_exec($_SERVER['PWD'] . "/saeghe --command=add --project=TestRequirements/Fixtures/ProjectWithTests --package=git@github.com:saeghe/complex-package.git");

        assert_pacakges_added_to_packages_directory('Packages does not added to the packages directory!' . $output);
        assert_build_file_has_desired_data('Build file for adding complex package does not have desired data!' . $output);
        assert_meta_file_has_desired_data('Meta data for adding complex package does not have desired data!' . $output);
    },
    before: function () {
        delete_build_json();
        delete_meta_file();
        delete_packages_directory();
        copy($_SERVER['PWD'] . '/TestRequirements/Stubs/ProjectWithTests/build.json', $_SERVER['PWD'] . '/TestRequirements/Fixtures/ProjectWithTests/build.json');
    },
    after: function () {
        delete_build_json();
        delete_meta_file();
        delete_packages_directory();
    }
);

test(
    title: 'it should add a complex project with http path',
    case: function () {
        $output = shell_exec($_SERVER['PWD'] . "/saeghe --command=add --project=TestRequirements/Fixtures/ProjectWithTests --package=https://github.com/saeghe/complex-package.git");

        assert_pacakges_added_to_packages_directory('Packages does not added to the packages directory!' . $output);
    },
    before: function () {
        delete_build_json();
        delete_meta_file();
        delete_packages_directory();
        copy($_SERVER['PWD'] . '/TestRequirements/Stubs/ProjectWithTests/build.json', $_SERVER['PWD'] . '/TestRequirements/Fixtures/ProjectWithTests/build.json');
    },
    after: function () {
        delete_build_json();
        delete_meta_file();
        delete_packages_directory();
    }
);

function delete_build_json()
{
    shell_exec('rm -f ' . $_SERVER['PWD'] . '/TestRequirements/Fixtures/ProjectWithTests/build.json');
}

function delete_meta_file()
{
    shell_exec('rm -f ' . $_SERVER['PWD'] . '/TestRequirements/Fixtures/ProjectWithTests/build-lock.json');
}

function delete_packages_directory()
{
    shell_exec('rm -Rf ' . $_SERVER['PWD'] . '/TestRequirements/Fixtures/ProjectWithTests/Packages');
}

function assert_pacakges_added_to_packages_directory($message)
{
    assert(
        file_exists($_SERVER['PWD'] . '/TestRequirements/Fixtures/ProjectWithTests/Packages/Saeghe/simple-package')
        && file_exists($_SERVER['PWD'] . '/TestRequirements/Fixtures/ProjectWithTests/Packages/Saeghe/simple-package/build.json')
        && file_exists($_SERVER['PWD'] . '/TestRequirements/Fixtures/ProjectWithTests/Packages/Saeghe/simple-package/README.md')
        && file_exists($_SERVER['PWD'] . '/TestRequirements/Fixtures/ProjectWithTests/Packages/Saeghe/complex-package')
        && file_exists($_SERVER['PWD'] . '/TestRequirements/Fixtures/ProjectWithTests/Packages/Saeghe/complex-package/build.json')
        && file_exists($_SERVER['PWD'] . '/TestRequirements/Fixtures/ProjectWithTests/Packages/Saeghe/complex-package/build-lock.json'),
        $message
    );
}

function assert_build_file_has_desired_data($message)
{
    $config = json_decode(file_get_contents($_SERVER['PWD'] . '/TestRequirements/Fixtures/ProjectWithTests/build.json'), true, JSON_THROW_ON_ERROR);

    assert(
        assert(! isset($config['packages']['git@github.com:saeghe/simple-package.git']))

        && assert(isset($config['packages']['git@github.com:saeghe/complex-package.git']))
        && assert('development' === $config['packages']['git@github.com:saeghe/complex-package.git']),
        $message
    );
}

function assert_meta_file_has_desired_data($message)
{
    $meta = json_decode(file_get_contents($_SERVER['PWD'] . '/TestRequirements/Fixtures/ProjectWithTests/build-lock.json'), true, JSON_THROW_ON_ERROR);

    assert(
        isset($meta['packages']['git@github.com:saeghe/simple-package.git'])
        &&'development' === $meta['packages']['git@github.com:saeghe/simple-package.git']['version']
        &&'saeghe' === $meta['packages']['git@github.com:saeghe/simple-package.git']['owner']
        &&'simple-package' === $meta['packages']['git@github.com:saeghe/simple-package.git']['repo']
        && '3db611bcd9fe6732e011f61bd36ca60ff42f4946' === $meta['packages']['git@github.com:saeghe/simple-package.git']['hash']

        && isset($meta['packages']['git@github.com:saeghe/complex-package.git'])
        &&'development' === $meta['packages']['git@github.com:saeghe/complex-package.git']['version']
        && 'saeghe' === $meta['packages']['git@github.com:saeghe/complex-package.git']['owner']
        && 'complex-package' === $meta['packages']['git@github.com:saeghe/complex-package.git']['repo']
        && '5e60733132ddf50df675b2491e35b7bb01674c3e' === $meta['packages']['git@github.com:saeghe/complex-package.git']['hash'],
        $message
    );
}
