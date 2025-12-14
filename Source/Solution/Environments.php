<?php

namespace Phpkg\Solution\Environments;

use Phpkg\Infra\Envs;
use function Phpkg\Infra\Logs\log;

function get_github_token(): ?string
{
    log('Retrieving GitHub token from environment variables');
    return Envs\get('GITHUB_TOKEN');
}
