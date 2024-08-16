<?php

namespace Tests\Git\Github\DownloadArchiveTest;

use PhpRepos\FileManager\Path;
use function Phpkg\Environments\github_token;
use function Phpkg\Git\GitHub\download_archive;
use function Phpkg\System\environment;
use PhpRepos\FileManager\Directory;
use PhpRepos\FileManager\File;
use function PhpRepos\TestRunner\Assertions\assert_true;
use function PhpRepos\TestRunner\Runner\test;

test(
    title: 'it should download the given tag in a given path',
    case: function (Path $destination) {
        download_archive($destination->string(), 'php-repos', 'cli', 'f4445518f45bc4161037532298b509bc7fb071bd', github_token(environment()));
        assert_true(Directory\exists($destination->append('Source')));
        assert_true(File\exists($destination->append('phpkg.config.json')));

        return $destination;
    },
    before: function () {
        $destination = Path::from_string(__DIR__ . '/destination');
        Directory\make($destination);
        return $destination;
    },
    after: function (Path $destination) {
        Directory\delete_recursive($destination);
    }
);
