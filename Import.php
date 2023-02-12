<?php

spl_autoload_register(function ($class) {
    $class_map = [
        'Phpkg\Classes\Build\Build' => realpath(__DIR__ . '/Source/Classes/Build/Build.php'),
        'Phpkg\Classes\Config\Config' => realpath(__DIR__ . '/Source/Classes/Config/Config.php'),
        'Phpkg\Classes\Config\Library' => realpath(__DIR__ . '/Source/Classes/Config/Library.php'),
        'Phpkg\Classes\Config\LinkPair' => realpath(__DIR__ . '/Source/Classes/Config/LinkPair.php'),
        'Phpkg\Classes\Config\NamespaceFilePair' => realpath(__DIR__ . '/Source/Classes/Config/NamespaceFilePair.php'),
        'Phpkg\Classes\Config\NamespacePathPair' => realpath(__DIR__ . '/Source/Classes/Config/NamespacePathPair.php'),
        'Phpkg\Classes\Config\PackageAlias' => realpath(__DIR__ . '/Source/Classes/Config/PackageAlias.php'),
        'Phpkg\Classes\Credential\Credential' => realpath(__DIR__ . '/Source/Classes/Credential/Credential.php'),
        'Phpkg\Classes\Credential\Credentials' => realpath(__DIR__ . '/Source/Classes/Credential/Credentials.php'),
        'Phpkg\Classes\Environment\Environment' => realpath(__DIR__ . '/Source/Classes/Environment/Environment.php'),
        'Phpkg\Classes\Meta\Dependency' => realpath(__DIR__ . '/Source/Classes/Meta/Dependency.php'),
        'Phpkg\Classes\Meta\Meta' => realpath(__DIR__ . '/Source/Classes/Meta/Meta.php'),
        'Phpkg\Classes\Package\Package' => realpath(__DIR__ . '/Source/Classes/Package/Package.php'),
        'Phpkg\Classes\Project\Project' => realpath(__DIR__ . '/Source/Classes/Project/Project.php'),
        'Phpkg\Exception\CredentialCanNotBeSetException' => realpath(__DIR__ . '/Source/Exception/CredentialCanNotBeSetException.php'),
        'Phpkg\Exception\PreRequirementsFailedException' => realpath(__DIR__ . '/Source/Exception/PreRequirementsFailedException.php'),
        'Phpkg\Git\Repository' => realpath(__DIR__ . '/Source/Git/Repository.php'),
        'Phpkg\Git\Exception\InvalidTokenException' => realpath(__DIR__ . '/Source/Git/Exception/InvalidTokenException.php'),
        'Phpkg\PhpFile' => realpath(__DIR__ . '/Source/PhpFile.php'),
        'PhpRepos\Datatype\Collection' => realpath(__DIR__ . '/Packages/php-repos/datatype/Source/Collection.php'),
        'PhpRepos\Datatype\Map' => realpath(__DIR__ . '/Packages/php-repos/datatype/Source/Map.php'),
        'PhpRepos\Datatype\Pair' => realpath(__DIR__ . '/Packages/php-repos/datatype/Source/Pair.php'),
        'PhpRepos\Datatype\Text' => realpath(__DIR__ . '/Packages/php-repos/datatype/Source/Text.php'),
        'PhpRepos\Datatype\Tree' => realpath(__DIR__ . '/Packages/php-repos/datatype/Source/Tree.php'),
        'PhpRepos\FileManager\Filename' => realpath(__DIR__ . '/Packages/php-repos/file-manager/Source/Filename.php'),
        'PhpRepos\FileManager\FilesystemCollection' => realpath(__DIR__ . '/Packages/php-repos/file-manager/Source/FilesystemCollection.php'),
        'PhpRepos\FileManager\FilesystemTree' => realpath(__DIR__ . '/Packages/php-repos/file-manager/Source/FilesystemTree.php'),
        'PhpRepos\FileManager\Path' => realpath(__DIR__ . '/Packages/php-repos/file-manager/Source/Path.php'),
    ];

    require_once $class_map[$class];
});

require realpath(__DIR__ . '/Packages/php-repos/cli/Source/IO/Read.php');
require realpath(__DIR__ . '/Packages/php-repos/cli/Source/IO/Write.php');
require realpath(__DIR__ . '/Packages/php-repos/datatype/Source/Arr.php');
require realpath(__DIR__ . '/Packages/php-repos/datatype/Source/Str.php');
require realpath(__DIR__ . '/Packages/php-repos/file-manager/Source/Resolver.php');
require realpath(__DIR__ . '/Packages/php-repos/file-manager/Source/File.php');
require realpath(__DIR__ . '/Packages/php-repos/file-manager/Source/Symlink.php');
require realpath(__DIR__ . '/Packages/php-repos/file-manager/Source/Directory.php');
require realpath(__DIR__ . '/Packages/php-repos/file-manager/Source/JsonFile.php');
require realpath(__DIR__ . '/Source/Exception/Handler.php');
require realpath(__DIR__ . '/Source/Git/GitHub.php');
require realpath(__DIR__ . '/Source/PackageManager.php');
