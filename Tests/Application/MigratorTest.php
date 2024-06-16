<?php

namespace Tests\Application\MigratorTest;

use Exception;
use function Phpkg\Application\Migrator\composer;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_false;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_true;
use function PhpRepos\TestRunner\Runner\test;

test(
    title: 'it should convert composer requirements to packages',
    case: function () {
        $composer_config = [
            'name' => 'any package name',
            'description' => 'any package description',
            'type' => 'anything',
            'keywords' => ['any', 'defined', 'keywords'],
            'license' => 'any license',
            'require' => [
                'php' => '>=8.2',
                'ext-dom' => '*',
                'ext-json' => '*',
                'symfony/thanks' => 'v1.2.10',
                'this/does-not-exists' => '1.0.0',
            ]
        ];

        $result = composer($composer_config);

        $expected = [
            'packages' => [
                'https://github.com/symfony/thanks.git' => 'v1.2.10',
            ]
        ];

        assert_true($expected === $result, 'composer migrate did not convert packages properly.');
    }
);

test(
    title: 'it should ignore packages with dev version',
    case: function () {
        $composer_config = [
            'name' => 'any package name',
            'description' => 'any package description',
            'type' => 'anything',
            'keywords' => ['any', 'defined', 'keywords'],
            'license' => 'any license',
            'require' => [
                'symfony/thanks' => 'dev-main',
                'phpstan/phpstan' => '@dev',
                'psr/http-message' => '2.*-dev'
            ],
        ];

        $result = composer($composer_config);

        assert_true([] === $result, 'dev package not working as expected');
    }
);

test(
    title: 'it should throw exception when the version does not found',
    case: function () {
        $composer_config = [
            'name' => 'any package name',
            'description' => 'any package description',
            'type' => 'anything',
            'keywords' => ['any', 'defined', 'keywords'],
            'license' => 'any license',
            'require' => [
                'symfony/thanks' => 'v0.0.0',
            ]
        ];

        try {
            composer($composer_config);
            assert_false(true, 'There should be an exception which didn\'t happen');
        } catch (Exception $exception) {
            assert_true($exception->getMessage() === 'Not supported version number v0.0.0 defined for package symfony/thanks',  'The exception error is not what it should be!');
        }
    }
);

test(
    title: 'it should convert 2 digits version number',
    case: function () {
        $composer_config = [
            'name' => 'any package name',
            'description' => 'any package description',
            'type' => 'anything',
            'keywords' => ['any', 'defined', 'keywords'],
            'license' => 'any license',
            'require' => [
                'psr/http-message' => '2.0',
            ]
        ];

        $result = composer($composer_config);

        $expected = [
            'packages' => [
                'https://github.com/php-fig/http-message.git' => '2.0',
            ]
        ];

        assert_true($expected === $result, '2 digits convert is not working properly!');
    }
);

test(
    title: 'it should work with or without v',
    case: function () {
        $composer_config = [
            'name' => 'any package name',
            'description' => 'any package description',
            'type' => 'anything',
            'keywords' => ['any', 'defined', 'keywords'],
            'license' => 'any license',
            'require' => [
                'psr/http-message' => 'v2.0',
                'symfony/thanks' => '1.0.0',
            ]
        ];

        $result = composer($composer_config);

        $expected = [
            'packages' => [
                'https://github.com/php-fig/http-message.git' => '2.0',
                'https://github.com/symfony/thanks.git' => 'v1.0.0'
            ]
        ];

        assert_true($expected === $result, 'It is not working with or without v');
    }
);

test(
    title: 'it should convert caret version numbers',
    case: function () {
        $composer_config = [
            'name' => 'any package name',
            'description' => 'any package description',
            'type' => 'anything',
            'keywords' => ['any', 'defined', 'keywords'],
            'license' => 'any license',
            'require' => [
                'myclabs/deep-copy' => '^1.10.1',
            ]
        ];

        $result = composer($composer_config);

        $expected = [
            'packages' => [
                'https://github.com/myclabs/DeepCopy.git' => '1.12.0',
            ]
        ];

        assert_true($expected === $result, 'Converting caret version numbers is not working properly.');
    }
);

test(
    title: 'it should convert tilda version numbers',
    case: function () {
        $composer_config = [
            'name' => 'any package name',
            'description' => 'any package description',
            'type' => 'anything',
            'keywords' => ['any', 'defined', 'keywords'],
            'license' => 'any license',
            'require' => [
                'symfony/thanks' => '~v1.0.0',
            ]
        ];

        $result = composer($composer_config);

        $expected = [
            'packages' => [
                'https://github.com/symfony/thanks.git' => 'v1.3.0',
            ]
        ];

        assert_true($expected === $result, 'Converting tilda version numbers is not working properly.');
    }
);

test(
    title: 'it should convert >= version numbers',
    case: function () {
        $composer_config = [
            'name' => 'any package name',
            'description' => 'any package description',
            'type' => 'anything',
            'keywords' => ['any', 'defined', 'keywords'],
            'license' => 'any license',
            'require' => [
                'symfony/thanks' => '>=v1.2.9',
                'myclabs/deep-copy' => '>=1.11.0',
            ]
        ];

        $result = composer($composer_config);

        $expected = [
            'packages' => [
                'https://github.com/symfony/thanks.git' => 'v1.3.0',
                'https://github.com/myclabs/DeepCopy.git' => '1.12.0',
            ]
        ];

        assert_true($expected === $result, 'Converting >= version numbers is not working properly.');
    }
);

test(
    title: 'it should convert > version numbers',
    case: function () {
        $composer_config = [
            'name' => 'any package name',
            'description' => 'any package description',
            'type' => 'anything',
            'keywords' => ['any', 'defined', 'keywords'],
            'license' => 'any license',
            'require' => [
                'symfony/thanks' => '>v0.0.0',
            ]
        ];

        $result = composer($composer_config);

        $expected = [
            'packages' => [
                'https://github.com/symfony/thanks.git' => 'v1.3.0',
            ]
        ];

        assert_true($expected === $result, 'Converting > version numbers is not working properly.');
    }
);

test(
    title: 'it should convert versions separated with |',
    case: function () {
        $composer_config = [
            'name' => 'any package name',
            'description' => 'any package description',
            'type' => 'anything',
            'keywords' => ['any', 'defined', 'keywords'],
            'license' => 'any license',
            'require' => [
                'brick/math' => '^0.9.3|^0.10.2',
            ]
        ];

        $result = composer($composer_config);

        $expected = [
            'packages' => [
                'https://github.com/brick/math.git' => '0.12.1',
            ]
        ];

        assert_true($expected === $result, 'multiple version number separated with | is not working properly.');
    }
);

test(
    title: 'it should convert versions separated with ||',
    case: function () {
        $composer_config = [
            'name' => 'any package name',
            'description' => 'any package description',
            'type' => 'anything',
            'keywords' => ['any', 'defined', 'keywords'],
            'license' => 'any license',
            'require' => [
                'brick/math' => '^0.9.3 || ^0.10.2',
            ]
        ];

        $result = composer($composer_config);

        $expected = [
            'packages' => [
                'https://github.com/brick/math.git' => '0.12.1',
            ]
        ];

        assert_true($expected === $result, 'multiple version number separated with double pipe is not working properly.');
    }
);


test(
    title: 'it should trim versions',
    case: function () {
        $composer_config = [
            'name' => 'any package name',
            'description' => 'any package description',
            'type' => 'anything',
            'keywords' => ['any', 'defined', 'keywords'],
            'license' => 'any license',
            'require' => [
                'brick/math' => '^0.9.3 | ^0.10.2',
            ]
        ];

        $result = composer($composer_config);

        $expected = [
            'packages' => [
                'https://github.com/brick/math.git' => '0.12.1',
            ]
        ];

        assert_true($expected === $result, 'Trimming version numbers is not working!');
    }
);

test(
    title: 'it should convert <= version numbers',
    case: function () {
        $composer_config = [
            'name' => 'any package name',
            'description' => 'any package description',
            'type' => 'anything',
            'keywords' => ['any', 'defined', 'keywords'],
            'license' => 'any license',
            'require' => [
                'symfony/thanks' => '<=v1.2.9',
                'myclabs/deep-copy' => '<=1.11.2',
            ]
        ];

        $result = composer($composer_config);

        $expected = [
            'packages' => [
                'https://github.com/symfony/thanks.git' => 'v1.2.9',
                'https://github.com/myclabs/DeepCopy.git' => '1.11.1',
            ]
        ];

        assert_true($expected === $result, 'Converting <= version numbers is not working properly.');
    }
);

test(
    title: 'it should convert < version numbers',
    case: function () {
        $composer_config = [
            'name' => 'any package name',
            'description' => 'any package description',
            'type' => 'anything',
            'keywords' => ['any', 'defined', 'keywords'],
            'license' => 'any license',
            'require' => [
                'symfony/thanks' => '<v1.2.10',
                'phpunit/phpunit' => '<9'
            ]
        ];

        $result = composer($composer_config);

        $expected = [
            'packages' => [
                'https://github.com/symfony/thanks.git' => 'v1.2.9',
                'https://github.com/sebastianbergmann/phpunit.git' => '8.5.38',
            ]
        ];

        assert_true($expected === $result, 'Converting <= version numbers is not working properly.');
    }
);

test(
    title: 'it should use pattern matching when there is *',
    case: function () {
        $composer_config = [
            'name' => 'any package name',
            'description' => 'any package description',
            'type' => 'anything',
            'keywords' => ['any', 'defined', 'keywords'],
            'license' => 'any license',
            'require' => [
                'phpunit/phpunit' => '8.*',
            ]
        ];

        $result = composer($composer_config);

        $expected = [
            'packages' => [
                'https://github.com/sebastianbergmann/phpunit.git' => '8.5.38',
            ]
        ];

        assert_true($expected === $result, 'Matching * is not working properly.');
    }
);

test(
    title: 'it should use pattern matching when there is x',
    case: function () {
        $composer_config = [
            'name' => 'any package name',
            'description' => 'any package description',
            'type' => 'anything',
            'keywords' => ['any', 'defined', 'keywords'],
            'license' => 'any license',
            'require' => [
                'phpunit/phpunit' => '8.x',
            ]
        ];

        $result = composer($composer_config);

        $expected = [
            'packages' => [
                'https://github.com/sebastianbergmann/phpunit.git' => '8.5.38',
            ]
        ];

        assert_true($expected === $result, 'Matching x is not working properly.');
    }
);

test(
    title: 'it should get next page when there is no matching in the first page',
    case: function () {
        $composer_config = [
            'name' => 'any package name',
            'description' => 'any package description',
            'type' => 'anything',
            'keywords' => ['any', 'defined', 'keywords'],
            'license' => 'any license',
            'require' => [
                'phpstan/phpstan' => '1.1.1',
            ]
        ];

        $result = composer($composer_config);

        $expected = [
            'packages' => [
                'https://github.com/phpstan/phpstan.git' => '1.1.1',
            ]
        ];

        assert_true($expected === $result, 'Converting <= version numbers is not working properly.');
    }
);

test(
    title: 'it should go for the next highest version when the biggest does not have any match',
    case: function () {
        $composer_config = [
            'name' => 'any package name',
            'description' => 'any package description',
            'type' => 'anything',
            'keywords' => ['any', 'defined', 'keywords'],
            'license' => 'any license',
            'require' => [
                'symfony/thanks' => '^1.0.0 | ^2.0.0 | ^10.0.0',
            ]
        ];

        $result = composer($composer_config);

        $expected = [
            'packages' => [
                'https://github.com/symfony/thanks.git' => 'v1.3.0',
            ]
        ];

        assert_true($expected === $result, 'Converting <= version numbers is not working properly.');
    }
);

test(
    title: 'it should sort version numbers where tags are not sorted',
    case: function () {
        $composer_config = [
            'name' => 'any package name',
            'description' => 'any package description',
            'type' => 'anything',
            'keywords' => ['any', 'defined', 'keywords'],
            'license' => 'any license',
            'require' => [
                'doctrine/common' => "^2.13.3 || ^3.2.2",
            ]
        ];

        $result = composer($composer_config);

        $expected = [
            'packages' => [
                'https://github.com/doctrine/common.git' => '3.4.4',
            ]
        ];

        assert_true($expected === $result, 'Double pipe is not working properly');
    }
);

test(
    title: 'it should find the most stable version when the version is @stable',
    case: function () {
        $composer_config = [
            'name' => 'any package name',
            'description' => 'any package description',
            'type' => 'anything',
            'keywords' => ['any', 'defined', 'keywords'],
            'license' => 'any license',
            'require' => [
                'nikic/PHP-Parser' => "@stable",
            ]
        ];

        $result = composer($composer_config);

        $expected = [
            'packages' => [
                'https://github.com/nikic/PHP-Parser.git' => 'v5.0.2',
            ]
        ];

        assert_true($expected === $result, '@stable version is not working as expected!');
    }
);

test(
    title: 'it should ignore package when there is no tag',
    case: function () {
        $composer_config = [
            'name' => 'any package name',
            'description' => 'any package description',
            'type' => 'anything',
            'keywords' => ['any', 'defined', 'keywords'],
            'license' => 'any license',
            'require' => [
                'json-schema/json-schema-test-suite' => "1.2.0",
            ]
        ];

        $result = composer($composer_config);

        $expected = [
        ];

        assert_true($expected === $result, 'It is not ignoring the package when there is no tag');
    }
);
