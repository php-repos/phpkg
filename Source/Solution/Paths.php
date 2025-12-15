<?php

namespace Phpkg\Solution\Paths;

use Phpkg\Solution\Data\Commit;
use Phpkg\Solution\Data\Package;
use Phpkg\Infra\Envs;
use Phpkg\Infra\Files;
use Phpkg\Infra\Strings;
use function Phpkg\Infra\Logs\debug;
use function Phpkg\Infra\Logs\log;
use Phpkg\Solution\Exceptions\NotWritableException;

function under(string $absolute, string ...$relatives): string
{
    log('Constructing path from absolute and relatives', [
        'absolute' => $absolute,
        'relatives' => $relatives,
    ]);

    return Files\realpath(Files\append($absolute, ...$relatives));
}

function under_current_working_directory(string ...$relatives): string
{
    log('Constructing path under current working directory', [
        'relatives' => $relatives,
    ]);
    return under(Files\root(), ...$relatives);
}

function phpkg_root(): string
{
    debug('Retrieving PHPKG root from environment variables');
    return Envs\get('PHPKG_ROOT');
}

function credentials(): string
{
    log('Retrieving credentials file path');
    return under(phpkg_root(), 'credentials.json');
}

function detect_project(string $project): string
{
    log('Detecting project path', ['project' => $project]);
    if ($project === '') {
        return Files\root();
    }

    $root = is_absolute_path($project)
        ? under($project)
        : under(Files\root(), $project);

    return Files\path_is_writable($root) ? $root : throw new NotWritableException($root);
}

function file_itself_exists(string $path): bool
{
    log('Checking if the file itself exists', ['path' => $path]);
    return Files\file_exists($path);
}

function temp_directory(string ...$relatives): string
{
    debug('Retrieving temporary directory', ['relatives' => $relatives]);
    return under(Envs\temp_dir(), ...$relatives);
}

function temp_installer_directory(Package $package): string
{
    log('Retrieving temporary installer directory for package', [
        'package' => $package->identifier(),
    ]);
    return temp_directory(
        'installer',
        $package->commit->version->repository->domain,
        $package->commit->version->repository->owner,
        $package->commit->version->repository->repo,
        $package->commit->version->tag,
        $package->commit->hash,
    );
}

function temp_runner_directory(Commit $commit): string
{
    log('Retrieving temporary runner directory for commit', [
        'commit' => $commit->identifier(),
    ]);
    return temp_directory(
        'runner',
        $commit->version->repository->domain,
        $commit->version->repository->owner,
        $commit->version->repository->repo,
        $commit->version->tag,
        $commit->hash,
    );
}

function is_absolute_path(string $path): bool
{
    log('Checking if the path is absolute', ['path' => $path]);
    // Unix-style absolute path
    if (Strings\starts_with($path, '/')) {
        return true;
    }
    // Windows-style absolute path (C:\ or C:/)
    if (preg_match('/^[A-Za-z]:[\/\\\\]/', $path)) {
        return true;
    }
    // Windows UNC path (\\server\share)
    if (Strings\starts_with($path, '\\\\')) {
        return true;
    }
    return false;
}

function has_path_identifier(string $str): bool
{
    log('Checking if string has path identifier', ['string' => $str]);
    // Unix-style paths
    if (Strings\starts_with($str, '/') 
      || Strings\starts_with($str, './') 
      || Strings\starts_with($str, '../')) {
        return true;
    }
    // Windows-style paths (C:\ or C:/)
    if (preg_match('/^[A-Za-z]:[\/\\\\]/', $str)) {
        return true;
    }
    // Windows UNC path (\\server\share)
    if (Strings\starts_with($str, '\\\\')) {
        return true;
    }
    // Windows relative paths with backslashes
    if (Strings\starts_with($str, '.\\') || Strings\starts_with($str, '..\\')) {
        return true;
    }
    return false;
}

function find(string $path): bool
{
    log('Checking if the directory exists', ['path' => $path]);
    return Files\directory_exists($path);
}

function make_recursively(string $path): bool
{
    log('Creating directory recursively', ['path' => $path]);
    
    $parent = Files\parent($path);
    // Check for root paths: Unix root '/' or Windows root (C:\ or C:/)
    $is_root = ($parent === '/' || $parent === '\\' || preg_match('/^[A-Za-z]:[\/\\\\]?$/', $parent));
    while (!$is_root && !Files\directory_exists($parent)) {
        $parent = Files\parent($parent);
        $is_root = ($parent === '/' || $parent === '\\' || preg_match('/^[A-Za-z]:[\/\\\\]?$/', $parent));
    }
    
    Files\path_is_writable($parent) || throw new NotWritableException($parent);
    
    return Files\make_directory_recursively($path);
}

function delete_recursively(string $path): bool
{
    log('Deleting directory recursively', ['path' => $path]);
    return Files\force_delete_recursive($path);
}

function phpkg_config_exists(string $root): bool
{
    log('Checking if PHPKG config exists', ['root' => $root]);
    return Files\directory_exists(phpkg_config_path($root));
}

function composer_vendor_path(string $root): string
{
    log('Retrieving Composer vendor path', ['root' => $root]);
    return under($root, 'vendor');
}

function composer_config_path(string $root): string
{
    log('Retrieving Composer config path', ['root' => $root]);
    return under($root, 'composer.json');
}

function composer_lock_path(string $root): string
{
    log('Retrieving Composer lock file path', ['root' => $root]);
    return under($root, 'composer.lock');
}

function to_array(string $json): array
{
    log('Reading JSON file as array', ['json' => $json]);
    return Files\read_json_as_array($json);
}

function phpkg_config_path(string $root): string
{
    log('Retrieving PHPKG config path', ['root' => $root]);
    return under($root, 'phpkg.config.json');
}

function save_as_json(string $path, array $data): bool
{
    log('Saving data as JSON', ['path' => $path, 'data' => $data]);
    return Files\save_array_as_json($path, $data);
}

function phpkg_meta_path(string $root): string
{
    log('Retrieving PHPKG meta path', ['root' => $root]);
    return under($root, 'phpkg.config-lock.json');
}

function write(string $root, string $content, ?int $permission = 0664): bool
{
    log('Writing content to file', [
        'root' => $root,
        'permission' => $permission,
    ]);
    if (!Files\directory_exists(Files\parent($root))) {
        log('Parent directory does not exist, creating it', ['parent' => Files\parent($root)]);
        Files\make_directory_recursively(Files\parent($root));
    }
    return Files\file_write($root, $content, $permission);
}

function permission(string $path): int
{
    log('Retrieving file permission', ['path' => $path]);
    return Files\file_permission($path);
}

function read(string $root): string
{
    log('Reading file content', ['root' => $root]);
    return Files\file_content($root);
}

function symlink(string $source, string $link): bool
{
    log('Creating symlink', [
        'source' => $source,
        'link' => $link,
    ]);
    if (!Files\directory_exists(Files\parent($source))) {
        log('Parent directory of source does not exist, creating it', ['parent' => Files\parent($source)]);
        Files\make_directory_recursively(Files\parent($source));
    }

    return Files\make_symlink($source, $link);
}

function ensure_directory_exists(string $path): bool
{
    log('Ensuring directory exists', ['path' => $path]);
    if (Files\directory_exists($path)) {
        return true;
    }
    return Files\make_directory_recursively($path);
}

function file_is_symlink(string $path): bool
{
    log('Checking if the file is a symlink', ['path' => $path]);

    return Files\file_exists($path) && Files\is_symlink($path);
}

function symlink_destination(string $path): string
{
    log('Retrieving symlink destination', ['path' => $path]);
    return under(Files\symlink_link($path));
}

function preserve_copy(string $source, string $destination): bool
{
    debug('Preserving copy of file', [
        'source' => $source,
        'destination' => $destination,
    ]);
    if (!Files\directory_exists(Files\parent($destination))) {
        debug('Parent directory of destination does not exist, creating it', ['parent' => Files\parent($destination)]);
        Files\make_directory_recursively(Files\parent($destination));
    }

    return Files\preserve_copy_file($source, $destination);
}

function delete_file(string $path): bool
{
    debug('Deleting file', ['path' => $path]);
    return unlink($path);
}

function preserve_copy_recursive(string $source, string $destination): bool
{
    log('Preserving recursive copy of directory', [
        'source' => $source,
        'destination' => $destination,
    ]);
    return Files\preserve_copy_recursively($source, $destination);
}

function preserve_copy_directory_content(string $source, string $destination): bool
{
    log('Preserving copy of directory contents', [
        'source' => $source,
        'destination' => $destination,
    ]);

    if (!Files\is_directory($source)) {
        return false;
    }

    if (!Files\directory_exists($destination)) {
        Files\make_directory_recursively($destination);
    }

    $items = Files\ls_all($source);
    $success = true;

    foreach ($items as $item) {
        $item_name = basename($item);
        $dest_path = under($destination, $item_name);

        if (Files\is_directory($item)) {
            if (!Files\directory_exists($dest_path)) {
                Files\make_directory_recursively($dest_path);
            }
            $success = preserve_copy_directory_content($item, $dest_path) && $success;
        } else {
            $success = preserve_copy($item, $dest_path) && $success;
        }
    }

    return $success;
}

function is_php_file(string $path): bool
{
    log('Checking if the path is a PHP file', ['path' => $path]);
    return Strings\ends_with($path, '.php');
}

function exists(string $path): bool
{
    log('Checking if file or directory exists', ['path' => $path]);
    return Files\file_exists($path) || Files\directory_exists($path);
}

function is_empty_directory(string $path): bool
{
    log('Checking if directory is empty', ['path' => $path]);

    return Files\is_empty_directory($path);
}

function delete_directory(string $path): bool
{
    log('Deleting empty directory', ['path' => $path]);

    return Files\force_delete_recursive($path);
}

/**
 * Calculates a checksum for a path (file or directory).
 * For files, returns the SHA256 hash of the file content.
 * For directories, recursively hashes all files using Directories\ls.
 *
 * @param Path $path The path to checksum (file or directory)
 * @return string The SHA256 checksum
 */
function checksum(string $path): string
{
    log('Calculating checksum for path', ['path' => $path]);
    
    if (!Files\is_directory($path)) {
        return Files\hash($path);
    }

    $directory_hash = '';
    $files_and_directories = Files\ls_all($path);
    \sort($files_and_directories);
    foreach ($files_and_directories as $item) {
        $directory_hash .= checksum($item);
    }
    
    return Strings\hash($directory_hash);
}

function verify_checksum(string $path, string $checksum): bool
{
    log('Verifying checksum', [
        'path' => $path,
        'checksum' => $checksum,
    ]);
    return checksum($path) === $checksum;
}

function packages_directory(string $root, array $config): string
{
    log('Retrieving packages directory', [
        'root' => $root,
        'config' => $config,
    ]);
    return under($root, $config['packages-directory']);
}

function package_root(string $vendor, string $owner, string $repo): string
{
    log('Retrieving package root path', [
        'vendor' => $vendor,
        'owner' => $owner,
        'repo' => $repo,
    ]);
    return under($vendor, $owner, $repo);
}

function delete_owner_when_empty(string $repo_path): bool
{
    log('Deleting owner directory if empty', ['repo_path' => $repo_path]);

    if (!is_empty_directory(Files\parent($repo_path))) {
        return true;
    }

    return Files\delete_directory(Files\parent($repo_path));
}

/**
 * Normalizes a path to Linux-style format (forward slashes).
 * Converts Windows backslashes to forward slashes for cross-platform compatibility.
 * This ensures consistent path representation regardless of the operating system.
 *
 * @param string $path The path to normalize
 * @return string The normalized path with forward slashes
 *
 * @example
 * ```php
 * $normalized = normalize('Source\\Test*.php');
 * // Returns: 'Source/Test*.php'
 *
 * $normalized = normalize('C:\\project\\Source\\File.php');
 * // Returns: 'C:/project/Source/File.php'
 * ```
 */
function normalize(string $path): string
{
    log('Normalizing path to Linux-style format', ['path' => $path]);
    return str_replace('\\', '/', $path);
}

/**
 * Checks if a path matches any exclude pattern in the given excludes array.
 *
 * The excludes array can contain both exact paths and glob patterns.
 * Patterns are checked using glob matching, while exact paths are checked directly.
 *
 * @param array $excludes Array of exclude patterns/paths (can be absolute paths or glob patterns)
 * @param string $path The absolute path to check
 * @return bool True if the path matches any exclude pattern, false otherwise
 *
 * @example
 * ```php
 * $excludes = ['/path/to/project/Source/Test*.php', '/path/to/project/Source/ExcludedFile.php'];
 * $is_excluded = is_excluded($excludes, '/path/to/project/Source/Test1.php');
 * // Returns: true
 * ```
 */
function is_excluded(array $excludes, string $path): bool
{
    log('Checking if path is excluded', [
        'path' => $path,
        'excludes' => $excludes,
    ]);

    foreach ($excludes as $exclude) {
        if ($path === $exclude) {
            return true;
        }
        // Check if it's a glob pattern using Strings\is_pattern
        if (Strings\is_pattern($exclude)) {
            // Use glob pattern matching
            if (Files\path_matches_pattern($exclude, $path)) {
                return true;
            }
        }
        return Strings\starts_with($path, $exclude);
    }

    return false;
}
