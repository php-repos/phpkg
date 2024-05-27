<?php

namespace Tests\System\MigrateCommandTest\MigrateFromComposerTest;

use Phpkg\Classes\Project;
use PhpRepos\FileManager\Path;
use function PhpRepos\Cli\Output\assert_error;
use function PhpRepos\Cli\Output\assert_success;
use function PhpRepos\FileManager\Directory\make_recursive;
use function PhpRepos\FileManager\File\create;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_true;
use function PhpRepos\TestRunner\Runner\test;
use function Tests\Helper\reset_empty_project;

test(
    title: 'it should show error message when there is no composer file',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg migrate --project=TestRequirements/Fixtures/EmptyProject');

        $lines = explode("\n", trim($output));
        assert_true(1 === count($lines), 'Number of output lines do not match' . $output);
        assert_error('There is no composer.json file.', $lines[0] . PHP_EOL);
    }
);

test(
    title: 'it should migrate a composer package',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg migrate --project=TestRequirements/Fixtures/EmptyProject');

        $lines = explode("\n", trim($output));
        assert_success('Composer package has been successfully migrated to a phpkg project. Enjoy.', $lines[0] . PHP_EOL);

        $project = Project::initialized(Path::from_string(root() . 'TestRequirements/Fixtures/EmptyProject'));

        assert_config_file_generated_properly($project);
        assert_config_lock_file_generated_properly($project);
    },
    before: function () {
        $composer_json_content = <<<'EOD'
{
    "name": "phpkg/composer-project",
    "type": "project",
    "require": {
        "nikic/php-parser": "v5.0.2"
    },
    "license": "MIT",
    "autoload": {
        "psr-4": {
            "Phpkg\\ComposerProject\\": "src/"
        }
    },
    "authors": [
        {
            "name": "Morteza Poussaneh",
            "email": "morteza@protonmail.com"
        }
    ]
}

EOD;

        create(root() . 'TestRequirements/Fixtures/EmptyProject/composer.json', $composer_json_content);
        make_recursive(root() . 'TestRequirements/Fixtures/EmptyProject/vendor/bin');
    },
    after: function () {
        reset_empty_project();
    }
);

function assert_config_file_generated_properly(Project $project)
{
    assert_true($project->packages_directory->string() ===  Path::from_string(root() . 'TestRequirements/Fixtures/EmptyProject/vendor')->string());
    assert_true($project->config->packages->count() === 1);
    assert_true($project->config->packages->first()->value->version === 'v5.0.2', 'Wrong version defined!');
}

function assert_config_lock_file_generated_properly(Project $project)
{
    assert_true($project->meta->packages->first()->value->hash === '139676794dc1e9231bf7bcd123cfc0c99182cb13', 'Wrong hash added!');
}
