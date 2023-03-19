<?php

namespace Phpkg\Commands\Version;

use PhpRepos\Cli\IO\Write;

return function (): void
{
    Write\success('phpkg version 1.2.0');
};
