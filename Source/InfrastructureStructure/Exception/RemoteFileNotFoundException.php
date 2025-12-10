<?php

namespace Phpkg\InfrastructureStructure\Exception;

use Exception;

/**
 * Exception thrown when a remote file cannot be found.
 *
 * This exception is thrown when attempting to access or download a file
 * from a remote location (such as a Git repository) and the file does not exist
 * at the specified path or commit.
 *
 * @example
 * ```php
 * try {
 *     $content = file_content_as_array('github.com', 'owner', 'repo', 'hash', 'token', 'nonexistent.json');
 * } catch (RemoteFileNotFoundException $e) {
 *     echo "File not found: " . $e->getMessage();
 * }
 * ```
 */
class RemoteFileNotFoundException extends Exception
{

}