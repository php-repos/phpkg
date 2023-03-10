<?php

namespace Phpkg\Commands\Version;

use PhpRepos\Cli\IO\Write;

function run(): void
{
    Write\success('phpkg version 1.1.0');
}
