<?php

namespace Phpkg\Infra\Files;

use Exception;
use JsonException;
use PhpRepos\FileManager\Files;
use PhpRepos\FileManager\Directories;
use PhpRepos\FileManager\JsonFiles;
use PhpRepos\FileManager\Paths;
use PhpRepos\FileManager\Symlinks;
use ZipArchive;

function root(): string
{
    return Paths\root();
}

function realpath(string $path): string
{
    return Paths\realpath($path);
}

function append(string $absolute, string ...$relatives): string
{
    return Paths\append($absolute, ...$relatives);
}

function parent(string $path): string
{
    return Paths\parent($path);
}

function preserve_copy_recursively(string $source, string $destination): bool
{
    return Directories\preserve_copy_recursively($source, $destination);
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
    return Files\create($path, $content, $permission);
}

/**
 * Reads the content of a file.
 *
 * Retrieves the entire content of a file as a string.
 *
 * @param string $path The path to the file to read
 * @return string The content of the file
 *
 * @example
 * ```php
 * $content = file_content('/path/to/config.json');
 * $config = json_decode($content, true);
 * ```
 */
function file_content(string $path): string
{
    return Files\content($path);
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
    return Files\permission($path);
}

/**
 * Checks if a file exists at the specified path.
 *
 * @param string $path The path to check
 * @return bool True if the file exists, false otherwise
 *
 * @example
 * ```php
 * if (file_exists('/path/to/config.php')) {
 *     include '/path/to/config.php';
 * }
 * ```
 */
function file_exists(string $path): bool
{
    return Files\exists($path);
}

/**
 * Checks if a directory exists at the specified path.
 *
 * @param string $path The directory path to check
 * @return bool True if the directory exists, false otherwise
 *
 * @example
 * ```php
 * if (directory_exists('/path/to/vendor')) {
 *     echo "Vendor directory exists";
 * }
 * ```
 */
function directory_exists(string $path): bool
{
    return Directories\exists($path);
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
 * $success = save_array_as_json('/path/to/data.json', $data);
 * ```
 */
function save_array_as_json(string $path, array $content): bool
{
    return JsonFiles\write($path, $content);
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
    return JsonFiles\to_array($path);
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
    return Directories\delete_recursive($path);
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
function make_directory_recursively(string $path): bool
{
    return Directories\make_recursive($path);
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
    return Symlinks\link($source, $link);
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
    return Symlinks\exists($path);
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
    return Directories\is_empty($path);
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
 * echo "Symlink points to: " . $target;
 * ```
 */
function symlink_link(string $path): string
{
    return Symlinks\target($path);
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
    return Files\preserve_copy($source, $destination);
}

function hash(string $path, string $algorithm = 'sha256'): string
{
    return hash_file($algorithm, $path);
}

function ls_all(string $path): array
{
    return Directories\ls_all($path);
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

function delete_directory(string $path): bool
{
    return Directories\delete($path);
}
