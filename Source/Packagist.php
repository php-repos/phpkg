<?php

namespace Phpkg\Packagist;

function git_url($package): ?string
{
    $url = "https://packagist.org/packages/$package.json";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    $response = curl_exec($ch);
    curl_close($ch);

    if ($response) {
        $data = json_decode($response, true);

        return isset($data['package']['repository']) ? $data['package']['repository'] . '.git' : null;
    } else {
        return null;
    }
}
