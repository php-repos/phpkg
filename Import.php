<?php

spl_autoload_register(function ($class) {
    $class_map = [
        'Phpkg\Classes\BuildMode' => realpath(__DIR__ . '/Source/Classes/BuildMode.php'),
        'Phpkg\Classes\Config' => realpath(__DIR__ . '/Source/Classes/Config.php'),
        'Phpkg\Classes\Credential' => realpath(__DIR__ . '/Source/Classes/Credential.php'),
        'Phpkg\Classes\Credentials' => realpath(__DIR__ . '/Source/Classes/Credentials.php'),
        'Phpkg\Classes\Dependency' => realpath(__DIR__ . '/Source/Classes/Dependency.php'),
        'Phpkg\Classes\Environment' => realpath(__DIR__ . '/Source/Classes/Environment.php'),
        'Phpkg\Classes\LinkPair' => realpath(__DIR__ . '/Source/Classes/LinkPair.php'),
        'Phpkg\Classes\Meta' => realpath(__DIR__ . '/Source/Classes/Meta.php'),
        'Phpkg\Classes\NamespaceFilePair' => realpath(__DIR__ . '/Source/Classes/NamespaceFilePair.php'),
        'Phpkg\Classes\NamespacePathPair' => realpath(__DIR__ . '/Source/Classes/NamespacePathPair.php'),
        'Phpkg\Datatypes\Digraph' => realpath(__DIR__ . '/Source/Datatypes/Digraph.php'),
        'Phpkg\DependencyGraph' => realpath(__DIR__ . '/Source/DependencyGraph.php'),
        'Phpkg\Datatypes\Node' => realpath(__DIR__ . '/Source/Datatypes/Node.php'),
        'Phpkg\Datatypes\Edge' => realpath(__DIR__ . '/Source/Datatypes/Edge.php'),
        'Phpkg\Classes\Package' => realpath(__DIR__ . '/Source/Classes/Package.php'),
        'Phpkg\Classes\PackageAlias' => realpath(__DIR__ . '/Source/Classes/PackageAlias.php'),
        'Phpkg\Classes\Project' => realpath(__DIR__ . '/Source/Classes/Project.php'),
        'Phpkg\Exception\CanNotDetectComposerPackageVersionException' => realpath(__DIR__ . '/Source/Exception/CanNotDetectComposerPackageVersionException.php'),
        'Phpkg\Exception\CredentialCanNotBeSetException' => realpath(__DIR__ . '/Source/Exception/CredentialCanNotBeSetException.php'),
        'Phpkg\Exception\PreRequirementsFailedException' => realpath(__DIR__ . '/Source/Exception/PreRequirementsFailedException.php'),
        'Phpkg\Git\Repository' => realpath(__DIR__ . '/Source/Git/Repository.php'),
        'Phpkg\Git\Exception\GithubApiRequestException' => realpath(__DIR__ . '/Source/Git/Exception/GithubApiRequestException.php'),
        'Phpkg\Git\Exception\InvalidTokenException' => realpath(__DIR__ . '/Source/Git/Exception/InvalidTokenException.php'),
        'Phpkg\Git\Exception\NotFoundException' => realpath(__DIR__ . '/Source/Git/Exception/NotFoundException.php'),
        'Phpkg\Git\Exception\NotSupportedVersionControlException' => realpath(__DIR__ . '/Source/Git/Exception/NotSupportedVersionControlException.php'),
        'Phpkg\Git\Exception\RateLimitedException' => realpath(__DIR__ . '/Source/Git/Exception/RateLimitedException.php'),
        'Phpkg\Git\Exception\UnauthenticatedRateLimitedException' => realpath(__DIR__ . '/Source/Git/Exception/UnauthenticatedRateLimitedException.php'),
        'Phpkg\Http\Request\Header' => realpath(__DIR__ . '/Source/Http/Request/Header.php'),
        'Phpkg\Http\Request\Message' => realpath(__DIR__ . '/Source/Http/Request/Message.php'),
        'Phpkg\Http\Request\Method' => realpath(__DIR__ . '/Source/Http/Request/Method.php'),
        'Phpkg\Http\Request\Url' => realpath(__DIR__ . '/Source/Http/Request/Url.php'),
        'Phpkg\Http\Response\Body' => realpath(__DIR__ . '/Source/Http/Response/Body.php'),
        'Phpkg\Http\Response\Header' => realpath(__DIR__ . '/Source/Http/Response/Header.php'),
        'Phpkg\Http\Response\Message' => realpath(__DIR__ . '/Source/Http/Response/Message.php'),
        'Phpkg\Http\Response\Status' => realpath(__DIR__ . '/Source/Http/Response/Status.php'),
        'Phpkg\Http\Conversation' => realpath(__DIR__ . '/Source/Http/Conversation.php'),
        'Phpkg\PhpFile' => realpath(__DIR__ . '/Source/PhpFile.php'),
        // Datatype classes
        'PhpRepos\Datatype\Collection' => realpath(__DIR__ . '/Packages/php-repos/datatype/Source/Collection.php'),
        'PhpRepos\Datatype\Map' => realpath(__DIR__ . '/Packages/php-repos/datatype/Source/Map.php'),
        'PhpRepos\Datatype\Pair' => realpath(__DIR__ . '/Packages/php-repos/datatype/Source/Pair.php'),
        'PhpRepos\Datatype\Text' => realpath(__DIR__ . '/Packages/php-repos/datatype/Source/Text.php'),
        'PhpRepos\Datatype\Tree' => realpath(__DIR__ . '/Packages/php-repos/datatype/Source/Tree.php'),
        // File manager classes
        'PhpRepos\FileManager\Filename' => realpath(__DIR__ . '/Packages/php-repos/file-manager/Source/Filename.php'),
        'PhpRepos\FileManager\FilesystemCollection' => realpath(__DIR__ . '/Packages/php-repos/file-manager/Source/FilesystemCollection.php'),
        'PhpRepos\FileManager\FilesystemTree' => realpath(__DIR__ . '/Packages/php-repos/file-manager/Source/FilesystemTree.php'),
        'PhpRepos\FileManager\Path' => realpath(__DIR__ . '/Packages/php-repos/file-manager/Source/Path.php'),
        // Console classes
        'PhpRepos\Console\Arguments' => realpath(__DIR__ . '/Packages/php-repos/console/Source/Arguments.php'),
        'PhpRepos\Console\Attributes\Argument' => realpath(__DIR__ . '/Packages/php-repos/console/Source/Attributes/Argument.php'),
        'PhpRepos\Console\Attributes\Description' => realpath(__DIR__ . '/Packages/php-repos/console/Source/Attributes/Description.php'),
        'PhpRepos\Console\Attributes\ExcessiveArguments' => realpath(__DIR__ . '/Packages/php-repos/console/Source/Attributes/ExcessiveArguments.php'),
        'PhpRepos\Console\Attributes\LongOption' => realpath(__DIR__ . '/Packages/php-repos/console/Source/Attributes/LongOption.php'),
        'PhpRepos\Console\Attributes\ShortOption' => realpath(__DIR__ . '/Packages/php-repos/console/Source/Attributes/ShortOption.php'),
        'PhpRepos\Console\CommandParameter' => realpath(__DIR__ . '/Packages/php-repos/console/Source/CommandParameter.php'),
        'PhpRepos\Console\Environment' => realpath(__DIR__ . '/Packages/php-repos/console/Source/Environment.php'),
        'PhpRepos\Console\Config' => realpath(__DIR__ . '/Packages/php-repos/console/Source/Config.php'),
        'PhpRepos\Console\Exceptions\InvalidCommandDefinitionException' => realpath(__DIR__ . '/Packages/php-repos/console/Source/Exceptions/InvalidCommandDefinitionException.php'),
        'PhpRepos\Console\Exceptions\InvalidCommandPromptException' => realpath(__DIR__ . '/Packages/php-repos/console/Source/Exceptions/InvalidCommandPromptException.php'),
        'PhpRepos\Console\ParamCollection' => realpath(__DIR__ . '/Packages/php-repos/console/Source/ParamCollection.php'),
    ];

    require_once $class_map[$class];
});

require realpath(__DIR__ . '/Packages/php-repos/cli/Source/Output.php');
require realpath(__DIR__ . '/Packages/php-repos/control-flow/Source/Conditional.php');
require realpath(__DIR__ . '/Packages/php-repos/control-flow/Source/Transformation.php');
require realpath(__DIR__ . '/Packages/php-repos/datatype/Source/Arr.php');
require realpath(__DIR__ . '/Packages/php-repos/datatype/Source/Str.php');
require realpath(__DIR__ . '/Packages/php-repos/file-manager/Source/Resolver.php');
require realpath(__DIR__ . '/Packages/php-repos/file-manager/Source/File.php');
require realpath(__DIR__ . '/Packages/php-repos/file-manager/Source/Symlink.php');
require realpath(__DIR__ . '/Packages/php-repos/file-manager/Source/Directory.php');
require realpath(__DIR__ . '/Packages/php-repos/file-manager/Source/JsonFile.php');
require realpath(__DIR__ . '/Packages/php-repos/console/Source/Reflection.php');
require realpath(__DIR__ . '/Packages/php-repos/console/Source/Runner.php');
require realpath(__DIR__ . '/Source/Comparison.php');
require realpath(__DIR__ . '/Source/Environments.php');
require realpath(__DIR__ . '/Source/Datatypes/Digraphs.php');
require realpath(__DIR__ . '/Source/Repository.php');
require realpath(__DIR__ . '/Source/DependencyGraphs.php');
require realpath(__DIR__ . '/Source/System.php');
require realpath(__DIR__ . '/Source/Exception/Handler.php');
require realpath(__DIR__ . '/Source/Packagist.php');
require realpath(__DIR__ . '/Source/Http/Request/Headers.php');
require realpath(__DIR__ . '/Source/Http/Request/Requests.php');
require realpath(__DIR__ . '/Source/Http/Response/Responses.php');
require realpath(__DIR__ . '/Source/Git/GitHub.php');
require realpath(__DIR__ . '/Source/Git/Repositories.php');
require realpath(__DIR__ . '/Source/Git/Version.php');
require realpath(__DIR__ . '/Source/Application/Builder.php');
require realpath(__DIR__ . '/Source/Application/PackageManager.php');
require realpath(__DIR__ . '/Source/Application/Migrator.php');
