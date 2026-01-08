<?php

namespace Phpkg\Infra\Files;

use DirectoryIterator;
use Exception;
use FilesystemIterator;
use JsonException;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;
use Phpkg\Infra\Strings;
use function file_exists;
use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;

/**
 * Changes the permissions of a file.
 *
 * @param string $path The path to the file.
 * @param int $permission The permission value (e.g., 0644).
 * @return bool True on success, false on failure.
 */
function chmod(string $path, int $permission): bool
{
    $old_umask = umask(0);
    $return = \chmod($path, $permission);
    umask($old_umask);

    return $return;
}

function exists(string $path): bool
{
    return file_exists($path);
}

function root(): string
{
    return getcwd() . DIRECTORY_SEPARATOR;
}

function realpath(string $path): string
{
    $path_string = rtrim(ltrim($path));
    if ($path_string === '/') {
        return $path_string;
    }
    $needle = DIRECTORY_SEPARATOR === '/' ? '\\' : '/';
    $path_string = str_replace($needle, DIRECTORY_SEPARATOR, $path_string);

    while (str_contains($path_string, DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR)) {
        $path_string = str_replace(DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR, $path_string);
    }

    $path_string = str_replace(DIRECTORY_SEPARATOR . '.' . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR, $path_string);
    $path_string = Strings\last_character($path_string) === DIRECTORY_SEPARATOR ? Strings\remove_last_character($path_string) : $path_string;

    $parts = explode(DIRECTORY_SEPARATOR, $path_string);

    while (in_array('..', $parts)) {
        foreach ($parts as $key => $part) {
            if ($part === '..') {
                unset($parts[$key - 1]);
                unset($parts[$key]);
                $parts = array_values($parts);
                break;
            }
        }
    }

    return implode(DIRECTORY_SEPARATOR, $parts);
}

/**
 * Appends one or more relative paths to an absolute base path.
 * This is a simple path concatenation function that does not resolve paths,
 * making it suitable for glob patterns that cannot be resolved.
 * For exact paths that should be resolved, use Paths\under instead.
 *
 * @param string $absolute The absolute base path
 * @param string ...$relatives The relative paths to append
 * @return string The concatenated path with directory separators
 *
 * @example
 * ```php
 * $path = append('/path/to/project', 'Source', 'Test*.php');
 * // Returns: '/path/to/project/Source/Test*.php' (not resolved)
 * ```
 */
function append(string $absolute, string ...$relatives): string
{
    $result = rtrim($absolute, '/\\');
    foreach ($relatives as $relative) {
        $relative = ltrim($relative, '/\\');
        if ($relative !== '') {
            $result .= DIRECTORY_SEPARATOR . $relative;
        }
    }
    return $result;
}

function parent(string $path): string
{
    $path = realpath($path);

    if ($path === DIRECTORY_SEPARATOR) {
        return DIRECTORY_SEPARATOR;
    }

    $parent = realpath($path . '/..');

    return $parent === '' ? DIRECTORY_SEPARATOR : $parent;
}

/**
 * Writes content to a file with specified permissions.
 *
 * Creates a new file or overwrites an existing one with the given content.
 * The file permissions can be customized, defaulting to 0664 (rw-rw-r--).
 *
 * @param string $path The file path where content will be written
 * @param string $content The content to write to the file
 * @param int|null $permission Optional file permissions (default: 0664)
 * @return bool True if the file was successfully written, false otherwise
 *
 * @example
 * ```php
 * $success = file_write('/path/to/file.txt', 'Hello World', 0644);
 * if ($success) {
 *     echo "File written successfully";
 * }
 * ```
 */
function file_write(string $path, string $content, ?int $permission = 0664): bool
{
    $file = fopen($path, "w");
    fwrite($file, $content);
    $created = fclose($file);
    chmod($path, $permission);

    return $created;
}

/**
 * Gets the permission mode of a file.
 *
 * Returns the file permissions as an integer (e.g., 0644, 0755).
 *
 * @param string $path The path to the file
 * @return int The file permissions as an integer
 *
 * @example
 * ```php
 * $perms = file_permission('/path/to/script.sh');
 * if (($perms & 0x0040) && ($perms & 0x0008)) {
 *     echo "File is executable by owner and others";
 * }
 * ```
 */
function file_permission(string $path): int
{
    clearstatcache();
    return fileperms($path) & 0x0FFF;
}

/**
 * Checks if a path is writable by the current process.
 *
 * @param string $path The path to check for writability
 * @return bool True if the path is writable, false otherwise
 *
 * @example
 * ```php
 * if (path_is_writable('/tmp')) {
 *     file_write('/tmp/test.txt', 'Hello');
 * }
 * ```
 */
function path_is_writable(string $path): bool
{
    return is_writable($path);
}

/**
 * Saves an array as JSON to a file.
 *
 * Converts the array to JSON format and writes it to the specified file path.
 *
 * @param string $path The file path where JSON will be written
 * @param array $content The array to convert to JSON and save
 * @return bool True if the JSON was successfully written, false otherwise
 *
 * @example
 * ```php
 * $data = ['name' => 'John', 'age' => 30];
 * $success = save_array_as_json(\'/path/to/data.json\', $data);
 * ```
 */
function save_array_as_json(string $path, array $content): bool
{
    return file_put_contents($path, json_encode($content, JSON_PRETTY_PRINT) . PHP_EOL) !== false;
}

/**
 * Reads a JSON file and converts it to an array.
 *
 * @param string $path The path to the JSON file
 * @return array The decoded JSON content as an array
 *
 * @throws JsonException
 * @example
 * ```php
 * $config = read_json_as_array('/path/to/config.json');
 * $db_host = $config['database']['host'];
 * ```
 */
function read_json_as_array(string $path): array
{
    return json_decode(json: file_get_contents($path), associative: true, flags: JSON_THROW_ON_ERROR);
}

/**
 * Deletes an empty directory.
 *
 * @param string $path The path to the directory.
 * @return bool True on success, false on failure.
 */
function delete_directory(string $path): bool
{
    return rmdir($path);
}

/**
 * Deletes a file.
 *
 * @param string $path The path to the file.
 * @return bool True on success, false on failure.
 */
function delete_file(string $path): bool
{
    return unlink($path);
}

/**
 * Lists all directory contents recursively in reverse order (children first) with an optional filter.
 *
 * @param string $directory The path to the directory.
 * @param callable|null $filter An optional callback to filter paths.
 * @return RecursiveIteratorIterator An iterator over the directory contents, processing children before parents.
 */
function ls_all_backward(string $directory, ?callable $filter = null): RecursiveIteratorIterator
{
    return ls_all_recursively($directory, $filter, RecursiveIteratorIterator::CHILD_FIRST);
}

/**
 * Removes all contents from a directory.
 *
 * @param string $path The path to the directory.
 * @return void
 */
function clean(string $path): void
{
    $iterator = ls_all_backward($path);
    foreach ($iterator as $item) {
        is_dir($item) ? delete_directory($item) : delete_file($item);
    }
}

/**
 * Recursively deletes a directory and all its contents.
 *
 * Removes the specified directory and all files and subdirectories within it.
 *
 * @param string $path The path to the directory to delete
 * @return bool True if the directory was successfully deleted, false otherwise
 *
 * @example
 * ```php
 * $success = force_delete_recursive('/path/to/temp_directory');
 * if ($success) {
 *     echo "Directory and contents deleted";
 * }
 * ```
 */
function force_delete_recursive(string $path): bool
{
    clean($path);

    return delete_directory($path);
}

/**
 * Creates a directory and any necessary parent directories.
 *
 * Creates the specified directory path, creating parent directories as needed.
 *
 * @param string $path The directory path to create
 * @return bool True if the directory was successfully created, false otherwise
 *
 * @example
 * ```php
 * $success = make_directory_recursively('/path/to/nested/directories');
 * if ($success) {
 *     echo "Directory structure created";
 * }
 * ```
 */
function make_directory_recursively(string $path, ?int $permission = 0775): bool
{
    $old_umask = umask(0);
    $created = mkdir(directory: $path, permissions: $permission, recursive: true);
    umask($old_umask);

    return $created;
}

/**
 * Extracts a ZIP archive to a destination directory.
 *
 * Opens a ZIP file, extracts its contents to a temporary location,
 * then copies them to the final destination while preserving file attributes.
 *
 * @param string $zip_file The path to the ZIP file to extract
 * @param string $destination The destination directory where files will be extracted
 * @return bool True if extraction was successful, false otherwise
 *
 * @example
 * ```php
 * $success = unpack('/path/to/package.zip', '/path/to/extract/to');
 * if ($success) {
 *     echo "ZIP file extracted successfully";
 * }
 * ```
 *
 * @throws Exception When ZIP extraction fails or the archive is corrupted
 */
function unpack(string $zip_file, string $destination): bool
{
    $zip = new ZipArchive;
    $res = $zip->open($zip_file);

    if ($res === TRUE) {
        $zip->extractTo($destination);
        $zip->close();
        return true;
    } else {
        throw new Exception('Failed to extract the archive zip file!');
    }
}

/**
 * Creates a symbolic link.
 *
 * Creates a symbolic link from the source path to the link path.
 *
 * @param string $source The target path that the link will point to
 * @param string $link The path where the symbolic link will be created
 * @return bool True if the symbolic link was successfully created, false otherwise
 *
 * @example
 * ```php
 * $success = make_symlink('/path/to/original/file', '/path/to/link');
 * if ($success) {
 *     echo "Symbolic link created";
 * }
 * ```
 */
function make_symlink(string $source, string $link): bool
{
    return symlink($source, $link);
}

/**
 * Checks if a path is a symbolic link.
 *
 * @param string $path The path to check
 * @return bool True if the path is a symbolic link, false otherwise
 *
 * @example
 * ```php
 * if (is_symlink('/path/to/link')) {
 *     echo "This is a symbolic link";
 * }
 * ```
 */
function is_symlink(string $path): bool
{
    return is_link($path);
}

/**
 * Checks if a path is an empty directory.
 *
 * Determines whether the specified path exists and is an empty directory
 * (contains no files or subdirectories).
 *
 * @param string $path The directory path to check
 * @return bool True if the path is an empty directory, false otherwise
 *
 * @example
 * ```php
 * if (is_empty_directory('/path/to/vendor')) {
 *     echo "Vendor directory is empty";
 * }
 * ```
 */
function is_empty_directory(string $path): bool
{
    $iterator = new DirectoryIterator($path);
    foreach ($iterator as $item) {
        if (!$item->isDot()) {
            return false;
        }
    }
    return true;
}

/**
 * Checks if a path is a directory.
 *
 * Determines whether the specified path exists and is a directory.
 * This function checks if the path points to a directory rather than
 * a file, symbolic link, or other filesystem object.
 *
 * @param string $path The path to check
 * @return bool True if the path is a directory, false otherwise
 *
 * @example
 * ```php
 * if (is_directory('/path/to/project')) {
 *     echo "This is a project directory";
 * }
 * ```
 */
function is_directory(string $path): bool
{
    return is_dir($path);
}

/**
 * Gets the target path of a symbolic link.
 *
 * @param string $path The path to the symbolic link
 * @return string The target path that the symlink points to
 *
 * @example
 * ```php
 * $target = symlink_link('/path/to/symlink');
 * echo "Symlink points to:" . $target;
 * ```
 */
function symlink_target(string $path): string
{
    return readlink($path);
}

/**
 * Retrieves the permission bits of a file.
 *
 * @param string $path The path to the file.
 * @return int The permission bits (e.g., 0644), or false on failure.
 */
function permission(string $path): int
{
    clearstatcache();
    return fileperms($path) & 0x0FFF;
}

/**
 * Copies a file while preserving its attributes.
 *
 * Copies a file from source to destination, maintaining file permissions,
 * timestamps, and other attributes.
 *
 * @param string $source The source file path
 * @param string $destination The destination file path
 * @return bool True if the file was successfully copied, false otherwise
 *
 * @example
 * ```php
 * $success = preserve_copy_file('/source/file.txt', '/destination/file.txt');
 * if ($success) {
 *     echo "File copied with attributes preserved";
 * }
 * ```
 */
function preserve_copy_file(string $source, string $destination): bool
{
    $copied = copy($source, $destination);
    chmod($destination, permission($source));
    return $copied;
}

function hash(string $path, string $algorithm = 'sha256'): string
{
    return hash_file($algorithm, $path);
}

/**
 * Lists all directory contents (non-recursive) with an optional filter.
 *
 * @param string $directory The path to the directory.
 * @param callable|null $filter An optional callback to filter paths.
 * @return array An array of file and directory paths.
 */
function ls_all(string $directory, ?callable $filter = null): array
{
    $items = [];
    $iterator = new DirectoryIterator($directory);

    foreach ($iterator as $item) {
        if (!$item->isDot()) {
            $pathname = $item->getPathname();
            if (is_callable($filter)) {
                if ($filter($pathname)) {
                    $items[] = $pathname;
                }
            } else {
                $items[] = $pathname;
            }
        }
    }

    return $items;
}

/**
 * Gets the root directory name from a ZIP archive.
 *
 * Opens a ZIP file and returns the name of the root directory (first entry).
 * This is useful for extracting archives where the root directory name may vary
 * (e.g., GitHub uses owner-repo-hash format).
 *
 * @param string $zip_file The path to the ZIP file
 * @return string The root directory name without trailing slashes
 * @throws Exception When the ZIP file cannot be opened
 *
 * @example
 * ```php
 * $root = zip_root('/path/to/archive.zip');
 * // Returns: 'php-repos-simple-package-1022f20'
 * ```
 */
function zip_root(string $zip_file): string
{
    $zip = new ZipArchive;
    $res = $zip->open($zip_file);

    if ($res !== TRUE) {
        throw new Exception('Failed to open the archive zip file!');
    }

    $root_dir = rtrim($zip->getNameIndex(0), DIRECTORY_SEPARATOR);
    $zip->close();

    return $root_dir;
}

/**
 * Checks if a path matches a glob pattern.
 *
 * @param string $pattern The glob pattern to match against (e.g., 'Source/Test*.php')
 * @param string $path The path to check against the pattern
 * @return bool True if the path matches the pattern, false otherwise
 *
 * @example
 * ```php
 * $matches = path_matches_pattern('Source/Test*.php', 'Source/Test1.php');
 * // Returns: true
 *
 * $matches = path_matches_pattern('Source/Test*.php', 'Source/Service.php');
 * // Returns: false
 * ```
 */
function path_matches_pattern(string $pattern, string $path): bool
{
    return fnmatch($pattern, $path, FNM_PATHNAME | FNM_PERIOD);
}

function content(string $path): string
{
    return file_get_contents($path);
}

/**
 * Lists all directory contents recursively with an optional filter.
 *
 * @param string $directory The path to the directory.
 * @param callable|null $filter An optional callback to filter paths.
 * @param int|null $mode The iteration mode (e.g., RecursiveIteratorIterator::SELF_FIRST).
 * @return RecursiveIteratorIterator An iterator over the directory contents.
 */
function ls_all_recursively(string $directory, ?callable $filter = null, ?int $mode = null): RecursiveIteratorIterator
{
    $mode = $mode ?: RecursiveIteratorIterator::SELF_FIRST;
    $iterator = new RecursiveDirectoryIterator(
        $directory,
        FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_PATHNAME
    );

    $iterator = is_callable($filter) ? new RecursiveCallbackFilterIterator($iterator, $filter) : $iterator;

    return new RecursiveIteratorIterator($iterator, $mode);
}