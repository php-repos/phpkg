<?php

namespace Phpkg\BusinessSpecifications\Config;

use Phpkg\SoftwareSolutions\Commits;
use Phpkg\SoftwareSolutions\Composers;
use Phpkg\SoftwareSolutions\Paths;
use Phpkg\SoftwareSolutions\PHPKGs;
use Phpkg\BusinessSpecifications\Credential;
use Phpkg\BusinessSpecifications\Outcome;
use function PhpRepos\Observer\Observer\propose;
use function PhpRepos\Observer\Observer\broadcast;
use PhpRepos\Observer\Signals\Plan;
use PhpRepos\Observer\Signals\Event;

function load(string $url, string $version, string $hash): Outcome
{
    propose(Plan::create('I try to load a config from a git host using the given commit data.', [
        'url' => $url,
        'version' => $version,
        'hash' => $hash,
    ]));

    $outcome = Credential\read();
    if (!$outcome->success) {
        broadcast(Event::create('I could not find any credentials!', [
            'url' => $url,
            'version' => $version ?: 'latest',
            'hash' => $hash,
        ]));
        return new Outcome(false, '🔑 No credentials found.');
    }
    $credentials = $outcome->data['credentials'];
    $commit = Commits\prepare($url, $version, $hash, $credentials);

    if (Commits\remote_phpkg_exists($commit)) {
        $config = Commits\get_remote_phpkg($commit);
    } else if (Commits\remote_composer_exists($commit)) {
        $config = Composers\config(Commits\get_remote_composer($commit), $credentials);
    } else {
        broadcast(Event::create('It seems the given commit is neither a phpkg nor a composer project!', [
            'commit' => $commit,
        ]));
        return new Outcome(false, '❌ Invalid package.');
    }
    
    $config = PHPKGs\config($config);

    broadcast(Event::create('I loaded the config for the given commit.', [
        'commit' => $commit,
        'config' => $config,
    ]));
    return new Outcome(true, '✅ Config loaded successfully.', ['config' => $config]);
}

function read(string $root): Outcome
{
    propose(Plan::create('I try to read a config from the given root.', ['root' => $root]));

    $path = Paths\phpkg_config_path($root);

    if (!Paths\file_itself_exists($path)) {
        broadcast(Event::create('It seems the config file does not exist!', [
            'root' => $root,
            'path' => $path,
        ]));
        return new Outcome(false, '🔍 Config file not found.');
    }

    $config = PHPKGs\config(Paths\to_array($path));

    broadcast(Event::create('I read the config from a config file on the given root.', [
        'root' => $root,
        'path' => $path,
        'config' => $config,
    ]));
    return new Outcome(true, '✅ Read config successfully.', ['config' => $config]);
}

function save(string $root, array $config): Outcome
{
    propose(Plan::create('I try to save a given config on the given root.', ['root' => $root, 'config' => $config]));

    $path = Paths\phpkg_config_path($root);

    $config = PHPKGs\config_to_array($config);

    if (! Paths\save_as_json($path, $config)) {
        broadcast(Event::create('It seems file has not been saved!', [
            'root' => $root,
            'path' => $path,
        ]));
        return new Outcome(false, '💾 Cannot save config file.');
    }

    broadcast(Event::create('I saved the given config in a config file on the given root.', [
        'root' => $root,
        'path' => $path,
        'config' => $config,
    ]));
    return new Outcome(true, '💾 Config file saved.');
}
