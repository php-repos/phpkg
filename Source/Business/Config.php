<?php

namespace Phpkg\Business\Config;

use Phpkg\Solution\Exceptions\CanNotDetectComposerPackageVersionException;
use Phpkg\Solution\Exceptions\ComposerConfigFileNotFound;
use Phpkg\Solution\Exceptions\RemoteConfigNotFound;
use PhpRepos\Git\Exception\ApiRequestException;
use PhpRepos\Git\Exception\InvalidTokenException;
use PhpRepos\Git\Exception\NotFoundException;
use PhpRepos\Git\Exception\RateLimitedException;
use Phpkg\Solution\Commits;
use Phpkg\Solution\Composers;
use Phpkg\Solution\Paths;
use Phpkg\Solution\PHPKGs;
use Phpkg\Business\Credential;
use Phpkg\Business\Outcome;
use PhpRepos\Observer\Signals\Plan;
use PhpRepos\Observer\Signals\Event;
use function PhpRepos\Observer\Observer\propose;
use function PhpRepos\Observer\Observer\broadcast;

function load(string $url, string $version, string $hash): Outcome
{
    try {
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
    } catch (ApiRequestException $e) {
        broadcast(Event::create('An error occurred while trying to load the config from the git host!', [
            'url' => $url,
            'version' => $version,
            'hash' => $hash,
            'exception' => $e,
        ]));
        return new Outcome(false, '⚠️ API request error: ' . $e->getMessage());
    } catch (InvalidTokenException $e) {
        broadcast(Event::create('The provided token is invalid for accessing the remote repository!', [
            'url' => $url,
            'version' => $version,
            'hash' => $hash,
            'exception' => $e,
        ]));
        return new Outcome(false, '🔐 Invalid token: ' . $e->getMessage());
    } catch (RateLimitedException $e) {
        broadcast(Event::create('Rate limit exceeded while accessing the remote repository!', [
            'url' => $url,
            'version' => $version,
            'hash' => $hash,
            'exception' => $e,
        ]));
        return new Outcome(false, '🐢 Rate limit exceeded: ' . $e->getMessage());
    } catch (RemoteConfigNotFound $e) {
        broadcast(Event::create('The remote config could not be found!', [
            'url' => $url,
            'version' => $version,
            'hash' => $hash,
            'exception' => $e,
        ]));
        return new Outcome(false, '🔍 Remote config not found.');
    } catch (NotFoundException $e) {
        broadcast(Event::create('The specified commit was not found in the remote repository!', [
            'url' => $url,
            'version' => $version,
            'hash' => $hash,
            'exception' => $e,
        ]));
        return new Outcome(false, '❓ Commit not found: ' . $e->getMessage());
    } catch (CanNotDetectComposerPackageVersionException $e) {
        broadcast(Event::create('Could not detect the composer package version!', [
            'url' => $url,
            'version' => $version,
            'hash' => $hash,
            'exception' => $e,
        ]));
        return new Outcome(false, '❗ Cannot detect composer package version: ' . $e->getMessage());
    } catch (ComposerConfigFileNotFound $e) {
        broadcast(Event::create('The composer config file could not be found in the remote repository!', [
            'url' => $url,
            'version' => $version,
            'hash' => $hash,
            'exception' => $e,
        ]));
        return new Outcome(false, '🔍 Composer config file not found.');
    }
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
