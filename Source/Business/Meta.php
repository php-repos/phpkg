<?php

namespace Phpkg\Business\Meta;

use Phpkg\Solution\Dependencies;
use Phpkg\Solution\Paths;
use Phpkg\Solution\PHPKGs;
use Phpkg\Business\Config;
use Phpkg\Business\Outcome;
use function PhpRepos\Observer\Observer\propose;
use function PhpRepos\Observer\Observer\broadcast;
use PhpRepos\Observer\Signals\Plan;
use PhpRepos\Observer\Signals\Event;

function read(string $root, string $vendor): Outcome
{
    propose(Plan::create('I try to read meta from the given root.', [
        'root' => $root,
        'vendor' => $vendor,
    ]));

    $meta_path = Paths\phpkg_meta_path($root);

    if (!Paths\file_itself_exists($meta_path)) {
        broadcast(Event::create('It seems meta file does not exist!', [
            'root' => $root,
            'vendor' => $vendor,
            'meta_path' => $meta_path,
        ]));
        return new Outcome(false, '🔍 Meta file does not exist.');
    }

    $meta = Paths\to_array($meta_path);

    if (!isset($meta['version'])) {
        $meta['checksum'] = PHPKGs\lock_checksum($meta['packages']);
    }

    if (!PHPKGs\verify_lock($meta['checksum'], $meta['packages'])) {
        broadcast(Event::create('Meta lock verification failed!', [
            'root' => $root,
            'vendor' => $vendor,
            'meta_path' => $meta_path,
            'meta' => $meta,
        ]));
        return new Outcome(false, '🔒 Meta lock verification failed. Please run install command to regenerate the meta file.');
    }

    if (!empty($meta['packages']) && !Paths\find($vendor)) {
        broadcast(Event::create('There are some packages but the packages directory does not exist!', [
            'root' => $root,
            'vendor' => $vendor,
            'meta_path' => $meta_path,
            'meta' => $meta,
        ]));
        return new Outcome(false, '📁 Packages directory does not exist.');
    }

    if (empty($meta['packages']) && Paths\find($vendor) && !Paths\is_empty_directory($vendor)) {
        broadcast(Event::create('There are no packages but the packages directory is not empty!', [
            'root' => $root,
            'vendor' => $vendor,
            'meta_path' => $meta_path,
            'meta' => $meta,
        ]));
        return new Outcome(false, '⚠️ No package added for the project, but packages directory is not empty.');
    }

    $meta_packages = $meta['packages'] ?? [];

    $failed = false;
    $packages = [];

    foreach ($meta_packages as $package_url => $package_meta) {
        $package_root = Paths\under($vendor, $package_meta['owner'], $package_meta['repo']);
        $outcome = Config\read($package_root);
        if (!$outcome->success) {
            broadcast(Event::create('I could not find a config file for a package!', [
                'root' => $root,
                'vendor' => $vendor,
                'meta_path' => $meta_path,
                'package_url' => $package_url,
                'package_meta' => $package_meta,
            ]));
            $failed = true;
            break;
        }

        $package_config = $outcome->data['config'];
        $packages[] = Dependencies\from_meta($package_url, $package_meta, $package_config, $package_root);
    }

    if ($failed) {
        broadcast(Event::create('There was a problem getting packages information.', [
            'root' => $root,
            'vendor' => $vendor,
            'meta_path' => $meta_path,
            'meta' => $meta,
        ]));
        return new Outcome(false, '❌ There was a problem getting packages information.');
    }

    broadcast(Event::create('I read meta from the given root.', [
        'root' => $root,
        'vendor' => $vendor,
        'meta_path' => $meta_path,
        'meta' => $meta,
        'packages' => $packages,
    ]));
    return new Outcome(true, '✅ Read Meta successfully.', ['packages' => $packages]);
}

function save(string $root, array $packages): Outcome
{
    propose(Plan::create('I try to save a meta using given information on the given root.', [
        'root' => $root,
        'packages' => $packages,
    ]));

    $meta = ['version' => 2, 'checksum' => null, 'packages' => []];

    foreach ($packages as $package) {
        $meta['packages'][$package->commit->version->repository->url] = [
            'owner' => $package->commit->version->repository->owner,
            'repo' => $package->commit->version->repository->repo,
            'version' => $package->commit->version->tag,
            'hash' => $package->commit->hash,
        ];
        if (isset($package->checksum)) {
            $meta['packages'][$package->commit->version->repository->url]['checksum'] = $package->checksum;
        }
    }

    $meta['checksum'] = PHPKGs\lock_checksum($meta['packages']);

    $meta_path = Paths\phpkg_meta_path($root);

    if (! Paths\save_as_json($meta_path, $meta)) {
        broadcast(Event::create('I could not save the lock file!', [
            'root' => $root,
            'meta_path' => $meta_path,
            'meta' => $meta,
        ]));
        return new Outcome(false, '💾 Could not save the meta file.');
    }

    broadcast(Event::create('I saved a meta to the given root.', [
        'root' => $root,
        'meta_path' => $meta_path,
        'meta' => $meta,
    ]));
    return new Outcome(true, '💾 Meta saved successfully.');
}
