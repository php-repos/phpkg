<?php

namespace Phpkg\Commands\Flush;

use Phpkg\Classes\Build\Build;
use Phpkg\Classes\Environment\Environment;
use Phpkg\Classes\Project\Project;
use function PhpRepos\Cli\IO\Read\parameter;
use function PhpRepos\Cli\IO\Write\success;

function run(Environment $environment): void
{
    $project = new Project($environment->pwd->subdirectory(parameter('project', '')));

    $development_build = new Build($project, 'development');
    $production_build = new Build($project, 'production');

    $development_build->root()->renew_recursive();
    $production_build->root()->renew_recursive();

    success('Build directory has been flushed.');
}
