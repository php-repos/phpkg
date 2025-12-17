<?php

namespace Phpkg\Solution\Caches;

use Phpkg\Solution\Data\Commit;
use Phpkg\Solution\Data\Version;
use Phpkg\Infra\Cache;
use function Phpkg\Infra\Arrays\first;
use function Phpkg\Infra\Arrays\has;
use function Phpkg\Infra\Logs\log;

function remote_data_exists(Version $version): bool
{
    log('Checking if remote data exists for version', [
        'version' => $version->identifier(),
    ]);

    $cache = Cache::load();

    return has($cache->items(), fn (array $value, string $key) => $key === $version->identifier());
}

function get_remote_data(Version $version): ?array
{
    log('Getting remote data for version', [
        'version' => $version->identifier(),
    ]);

    $cache = Cache::load();

    return first($cache->items(), fn (array $value, string $key) => $key === $version->identifier());
}

function set_remote_data(Version $version, Commit $commit, array $config): Cache
{
    log('Setting remote data for version', [
        'version' => $version->identifier(),
        'commit' => $commit->identifier(),
        'config' => $config,
    ]);
    $cache = Cache::load();
    return $cache->set($version->identifier(), ['commit' => $commit, 'config' => $config]);
}

function update_remote_data(Version $version, array $packages): Cache
{
    log('Updating remote data for version', [
        'version' => $version->identifier(),
        'packages' => $packages,
    ]);
    $cache = Cache::load();
    $data = get_remote_data($version);
    $data['packages'] = $packages;
    return $cache->update($version->identifier(), $data);
}
