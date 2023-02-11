<?php

namespace Phpkg\Commands\Flush;

use Phpkg\Classes\Build\Build;
use Phpkg\Classes\Environment\Environment;
use Phpkg\Classes\Project\Project;
use function PhpRepos\Cli\IO\Read\parameter;
use function PhpRepos\Cli\IO\Write\success;
use function PhpRepos\FileManager\Directory\renew_recursive;

function run(Environment $environment): void
{
    $project = new Project($environment->pwd->append(parameter('project', '')));

    $development_build = new Build($project, 'development');
    $production_build = new Build($project, 'production');

    renew_recursive($development_build->root());
    renew_recursive($production_build->root());

    success('Build directory has been flushed.');
}
