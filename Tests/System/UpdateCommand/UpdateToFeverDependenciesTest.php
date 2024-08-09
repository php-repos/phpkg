<?php

namespace Tests\System\UpdateCommand\UpdateToFeverDependenciesTest;

use Phpkg\Classes\Project;
use PhpRepos\FileManager\Path;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_true;
use function PhpRepos\TestRunner\Runner\test;
use function Tests\Helper\reset_empty_project;
use function Tests\System\Assertions\assert_datatype_2_0_0_installed;

test(
    title: 'it should remove unused dependencies',
    case: function () {
        shell_exec('php ' . root() . 'phpkg update git@github.com:php-repos/datatype.git --version=v2 --force --project=TestRequirements/Fixtures/EmptyProject');

        $project = Project::initialized(Path::from_string(root() . 'TestRequirements/Fixtures/EmptyProject'));

        assert_datatype_2_0_0_installed($project);
        assert_true($project->config->packages->count() === 1);
        assert_true($project->meta->packages->count() === 1);
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=TestRequirements/Fixtures/EmptyProject');
        shell_exec('php ' . root() . 'phpkg add git@github.com:php-repos/datatype.git --version=v1.2 --project=TestRequirements/Fixtures/EmptyProject');
    },
    after: function () {
        reset_empty_project();
    }
);
