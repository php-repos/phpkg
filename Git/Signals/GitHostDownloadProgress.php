<?php

namespace PhpRepos\Git\Signals;

use PhpRepos\Observer\Signals\Message;

/**
 * Message signal for Git hosts download progress.
 * Contains all information available from cURL XFERINFO callback.
 */
class GitHostDownloadProgress extends Message
{
    /**
     * Create a GitHostDownloadProgress event from GitHub downloader using cURL XFERINFO callback.
     *
     * @param string $domain Git host domain (e.g., 'github.com')
     * @param string $owner Repository owner
     * @param string $repo Repository name
     * @param string $hash Commit hash being downloaded
     * @param int|float $download_size Total bytes to download (may be -1 if unknown)
     * @param int|float $downloaded Bytes downloaded so far
     * @param int|float $upload_size Total bytes to upload
     * @param int|float $uploaded Bytes uploaded so far
     * @param float $time Elapsed time in seconds
     * @param string|null $destination Destination file path
     * @return static
     */
    public static function from_github_downloader(
        string $domain,
        string $owner,
        string $repo,
        string $hash,
        int|float $download_size,
        int|float $downloaded,
        int|float $upload_size,
        int|float $uploaded,
        float $time,
        ?string $destination = null
    ): static {
        return parent::create(
            'Git hosts download progress',
            [
                'domain' => $domain,
                'owner' => $owner,
                'repo' => $repo,
                'hash' => $hash,
                'download_size' => $download_size,
                'downloaded' => $downloaded,
                'upload_size' => $upload_size,
                'uploaded' => $uploaded,
                'time' => $time,
                'destination' => $destination,
            ]
        );
    }
}

