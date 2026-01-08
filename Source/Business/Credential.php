<?php

namespace Phpkg\Business\Credential;

use Phpkg\Solution\Environments;
use Phpkg\Solution\Paths;
use Phpkg\Business\Outcome;
use PhpRepos\Observer\API\Bus;
use PhpRepos\Observer\API\Event;
use PhpRepos\Observer\API\Plan;

function read(): Outcome
{
    Bus\propose(Plan::create('I try to read credentials from the credentials file or environment variables.'));

    $path = Paths\credentials();
    $file_content = Paths\file_itself_exists($path) ? Paths\to_array($path) : [];

    $credentials = [];
    foreach ($file_content as $key => $value) {
        $credentials[$key] = $value['token'];
    }

    $github_token = Environments\get_github_token();
    if ($github_token) {
        $credentials['github.com'] = $github_token;
    }

    Bus\broadcast(Event::create('I loaded credentials.', [
        'credentials' => $credentials,
    ]));

    return new Outcome(true, '🔑 Credentials loaded.', ['credentials' => $credentials]);
}

function add(string $provider, string $token, bool $force = false): Outcome
{
    Bus\propose(Plan::create('I try to add the given token for the given provider to credentials.', [
        'provider' => $provider,
    ]));

    if (empty($provider)) {
        Bus\broadcast(Event::create('It seems the given provider is empty!', [
            'provider' => $provider,
        ]));
        return new Outcome(false, '❌ Failed to add credential: provider is empty.');
    }

    if (empty($token)) {
        Bus\broadcast(Event::create('It seems the given token is empty!', [
            'provider' => $provider,
        ]));
        return new Outcome(false, '❌ Failed to add credential: token is empty.');
    }

    $path = Paths\credentials();
    $file_content = Paths\file_itself_exists($path) ? Paths\to_array($path) : [];
    
    if (!$force) {
        foreach ($file_content as $registered_provider => $setting) {
            if ($registered_provider === $provider) {
                if (isset($setting['token']) && strlen($setting['token'] > 0)) {
                    Bus\broadcast(Event::create('It seems there is already a token for the given provider!', [
                       'provider' => $provider,
                       'path' => $path,
                    ]));
                    return new Outcome(false, '⚠️ There is a token for the given provider.');
                }
            }
        }
    }

    $file_content[$provider]['token'] = $token;

    if (!Paths\save_as_json($path, $file_content)) {
        Bus\broadcast(Event::create('It seems file has not been saved!', [
            'provider' => $provider,
            'path' => $path,
        ]));
        return new Outcome(false, '💾 Cannot save credentials file.');
    }

    Bus\broadcast(Event::create('I saved the given token for the given provider to the credentials file.', [
        'provider' => $provider,
        'token' => $token,
        'path' => $path,
    ]));
    return new Outcome(true, '💾 Credentials file saved.');
}
