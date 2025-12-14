<?php

namespace Phpkg\Infra\Exception;

use Exception;

/**
 * Exception thrown when an archive download fails.
 *
 * This exception is thrown when attempting to download an archive file
 * (such as a ZIP or TAR file) from a remote location and the download
 * operation fails for any reason (network issues, corrupted archive, etc.).
 *
 * @example
 * ```php
 * try {
 *     $archive_path = download('github.com', 'owner', 'repo', 'hash', 'token', '/tmp/repo.zip');
 * } catch (ArchiveDownloadException $e) {
 *     echo "Archive download failed: " . $e->getMessage();
 * }
 * ```
 */
class ArchiveDownloadException extends Exception
{

}
