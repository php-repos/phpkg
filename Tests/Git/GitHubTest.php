<?php

namespace Tests\GitTest\GitHubTest;

use PhpRepos\FileManager\Path;
use PhpRepos\FileManager\JsonFile;
use function file_exists;
use function Phpkg\Providers\GitHub\clone_to;
use function Phpkg\Providers\GitHub\download;
use function Phpkg\Providers\GitHub\extract_owner;
use function Phpkg\Providers\GitHub\extract_repo;
use function Phpkg\Providers\GitHub\find_latest_commit_hash;
use function Phpkg\Providers\GitHub\find_latest_version;
use function Phpkg\Providers\GitHub\find_version_hash;
use function Phpkg\Providers\GitHub\github_token;
use function Phpkg\Providers\GitHub\has_release;
use function Phpkg\Providers\GitHub\is_ssh;
use function Phpkg\System\random_temp_directory;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\FileManager\Resolver\realpath;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_true;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_false;
use function PhpRepos\TestRunner\Runner\test;
use const Phpkg\Providers\GitHub\GITHUB_DOMAIN;

test(
    title: 'it should detect if url is ssh',
    case: function () {
        assert_true(is_ssh('git@github.com:owner/repo'));
        assert_false(is_ssh('https://github.com/owner/repo'));
    }
);

test(
    title: 'it should extract owner from url',
    case: function () {
        assert_true('php-repos' === extract_owner('git@github.com:php-repos/repo'));
        assert_true('php-repos' === extract_owner('git@github.com:php-repos/repo.git'));
        assert_true('php-repos' === extract_owner('https://github.com/php-repos/repo'));
    }
);

test(
    title: 'it should extract repo from url',
    case: function () {
        assert_true('cli' === extract_repo('git@github.com:php-repos/cli'));
        assert_true('cli' === extract_repo('git@github.com:php-repos/cli.git'));
        assert_true('test-runner' === extract_repo('https://github.com/php-repos/test-runner'));
    }
);

test(
    title: 'it should get and set github token',
    case: function () {
        putenv("GITHUB_TOKEN=FIRST_TOKEN");
        assert_true('FIRST_TOKEN' === github_token());

        github_token('set new token');
        assert_true(getenv('GITHUB_TOKEN', true) === 'set new token');

        $token = 'set another token';
        assert_true(github_token($token) === $token);
        assert_true(github_token() === $token);
    }
);

test(
    title: 'it should detect if repository has release',
    case: function () {
        assert_true(has_release('php-repos', 'released-package'));
        assert_false(has_release('php-repos', 'simple-package'));
    },
    before: function () {
        $credentials = JsonFile\to_array(realpath(root() . 'credentials.json'));
        github_token($credentials[GITHUB_DOMAIN]['token']);
    }
);

test(
    title: 'it should find latest version for released repository',
    case: function () {
        assert_true('v1.1.0' === find_latest_version('php-repos', 'released-package'));
    },
    before: function () {
        $credentials = JsonFile\to_array(realpath(root() . 'credentials.json'));
        github_token($credentials[GITHUB_DOMAIN]['token']);
    }
);

test(
    title: 'it should find version hash for released repository',
    case: function () {
        assert_true('875b7ecebe6d781bec4b670a77b00471ffaa3422' === find_version_hash('php-repos', 'released-package', 'v1.0.0'));
        assert_true('34c23761155364826342a79766b6d662aa0ae7fb' === find_version_hash('php-repos', 'released-package', 'v1.0.1'));
        assert_true('be24f45d8785c215901ba90b242f3b8a7d2bdbfb' === find_version_hash('php-repos', 'released-package', 'v1.1.0'));
    },
    before: function () {
        $credentials = JsonFile\to_array(realpath(root() . 'credentials.json'));
        github_token($credentials[GITHUB_DOMAIN]['token']);
    }
);

test(
    title: 'it should find latest commit hash for repository',
    case: function () {
        assert_true('1022f2004a8543326a92c0ba606439db530a23c9' === find_latest_commit_hash('php-repos', 'simple-package'));
        assert_true('be24f45d8785c215901ba90b242f3b8a7d2bdbfb' === find_latest_commit_hash('php-repos', 'released-package'));
    },
    before: function () {
        $credentials = JsonFile\to_array(realpath(root() . 'credentials.json'));
        github_token($credentials[GITHUB_DOMAIN]['token']);
    }
);

test(
    title: 'it should download given repository',
    case: function (Path $packages_directory) {
        assert_true(download($packages_directory, 'saeghe', 'released-package', 'v1.0.5'), 'download failed');
        // Assert latest changes on the latest commit
        assert_true(true ===
            str_contains(
                file_get_contents($packages_directory->append('saeghe.config-lock.json')),
                '080478442a9ef1d19f5966edc9bf3c1eccca4848'
            ),
            'config file does not found'
        );
        assert_false(file_exists(realpath(sys_get_temp_dir(). '/saeghe/installer/cache/saeghe/released-package.zip/')), 'zip file is not deleted');

        return $packages_directory;
    },
    before: function () {
        $credentials = JsonFile\to_array(realpath(root() . 'credentials.json'));
        github_token($credentials[GITHUB_DOMAIN]['token']);

        return Path::from_string(random_temp_directory());
    }
);

test(
    title: 'it should clone given repository',
    case: function (Path $packages_directory) {
        clone_to($packages_directory, 'php-repos', 'simple-package');

        // Assert latest changes on the latest commit
        assert_true(true ===
            str_contains(
                file_get_contents($packages_directory->append('entry-point')),
                'new ImaginaryClass();'
            )
        );

        return $packages_directory;
    },
    before: function () {
        $packages_directory = Path::from_string(random_temp_directory());
        mkdir($packages_directory, 0777, true);

        return $packages_directory;
    }
);
