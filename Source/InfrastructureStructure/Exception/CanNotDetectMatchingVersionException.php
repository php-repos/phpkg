<?php

namespace Phpkg\InfrastructureStructure\Exception;

use Exception;

/**
 * Exception thrown when a matching version cannot be detected.
 *
 * This exception is thrown when attempting to find a version that matches
 * a given constraint (such as "^1.0", ">=2.0") but no suitable version
 * can be found in the available versions. This typically occurs when the
 * version constraint is too restrictive or when the repository has no
 * versions that satisfy the requirements.
 *
 * @example
 * ```php
 * try {
 *     $version = match_highest_version('github.com', 'owner', 'repo', '^5.0', 'token');
 * } catch (CanNotDetectMatchingVersionException $e) {
 *     echo "No version matching ^5.0 found: " . $e->getMessage();
 * }
 * ```
 */
class CanNotDetectMatchingVersionException extends Exception
{

}