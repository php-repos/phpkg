<?php

namespace Tests\GitTest\GitHubTest;

use PhpRepos\FileManager\Path;
use PhpRepos\FileManager\JsonFile;
use function file_exists;
use function Phpkg\Git\GitHub\download_archive;
use function Phpkg\Git\GitHub\extract_owner;
use function Phpkg\Git\GitHub\extract_repo;
use function Phpkg\Git\GitHub\find_latest_commit_hash;
use function Phpkg\Git\GitHub\find_latest_version;
use function Phpkg\Git\GitHub\find_version_hash;
use function Phpkg\Git\GitHub\has_any_tag;
use function Phpkg\Git\GitHub\is_ssh;
use function Phpkg\System\random_temp_directory;
use function PhpRepos\FileManager\Directory\make_recursive;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\FileManager\Resolver\realpath;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_true;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_false;
use function PhpRepos\TestRunner\Runner\test;
use const Phpkg\Git\GitHub\GITHUB_DOMAIN;

function token(): string
{
    $credentials = JsonFile\to_array(realpath(root() . 'credentials.json'));
    return $credentials[GITHUB_DOMAIN]['token'];
}

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
    title: 'it should detect if repository has any tag',
    case: function () {
        assert_true(has_any_tag('php-repos', 'released-package', token()));
        assert_false(has_any_tag('php-repos', 'simple-package', token()));
    }
);

test(
    title: 'it should find latest version for repository',
    case: function () {
        assert_true('v1.1.0' === find_latest_version('php-repos', 'released-package', token()));
    }
);

test(
    title: 'it should find version hash for released repository',
    case: function () {
        assert_true('875b7ecebe6d781bec4b670a77b00471ffaa3422' === find_version_hash('php-repos', 'released-package', 'v1.0.0', token()));
        assert_true('34c23761155364826342a79766b6d662aa0ae7fb' === find_version_hash('php-repos', 'released-package', 'v1.0.1', token()));
        assert_true('be24f45d8785c215901ba90b242f3b8a7d2bdbfb' === find_version_hash('php-repos', 'released-package', 'v1.1.0', token()));
    }
);

test(
    title: 'it should find latest commit hash for repository',
    case: function () {
        assert_true('1022f2004a8543326a92c0ba606439db530a23c9' === find_latest_commit_hash('php-repos', 'simple-package', token()));
        assert_true('be24f45d8785c215901ba90b242f3b8a7d2bdbfb' === find_latest_commit_hash('php-repos', 'released-package', token()));
    }
);

test(
    title: 'it should download given repository to the given destination',
    case: function (Path $destination) {
        assert_true(download_archive($destination, 'saeghe', 'released-package', '5885e5f3ed26c2289ceb2eeea1f108f7fbc10c01', token()), 'download failed');
        // Assert latest changes on the commit
        assert_true(true ===
            str_contains(
                file_get_contents($destination->append('saeghe.config-lock.json')),
                '080478442a9ef1d19f5966edc9bf3c1eccca4848'
            ),
            'config file does not found'
        );
        assert_false(file_exists(realpath(sys_get_temp_dir(). '/saeghe/installer/cache/saeghe/released-package.zip/')), 'zip file is not deleted');

        return $destination;
    },
    before: function () {
        $destination = Path::from_string(random_temp_directory());
        make_recursive($destination);

        return $destination;
    }
);
