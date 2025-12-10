<?php

namespace Phpkg\SoftwareSolutions\Environments;

use Phpkg\InfrastructureStructure\Envs;
use function Phpkg\InfrastructureStructure\Logs\log;

function get_github_token(): ?string
{
    log('Retrieving GitHub token from environment variables');
    return Envs\get('GITHUB_TOKEN');
}
