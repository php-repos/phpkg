<?php

namespace Tests\System\ManCommandTest;

use function Saeghe\FileManager\Resolver\root;
use function Saeghe\TestRunner\Assertions\Boolean\assert_true;

$man_content = <<<EOD
usage: saeghe [-v | --version] [-h | --help] [--man]
           <command> [<args>]

These are common Saeghe commands used in various situations:

start a working area
    init {--packages-directory=}
                    Initializes the project. This command adds required files and directories. You can pass a
                    `packages-directory` as an option. If passed, then your packages will be added under the given
                    directory instead of the default `Packages` directory.
    migrate  
                    Migrates from a Composer project to a Saeghe project.

work with packages
    credential <provider> <token>
                    Add given `token` for the given `provider` in credential file.
    alias <alias> <package>
                    Defines the given alias as an alias for the given package. After defining an alias, you can use the
                    alias in other commands where a package URL is required.

    add <package|alias> {--version=}
                    Adds the given package to your project. This command needs a required `package` argument that should
                    be a valid git URL (SSH or HTTPS) or a registered alias using the `alias` command.
                    You can pass an optional `version` option, then Saeghe will add the given version of the given package, 
                    otherwise, it adds the latest released version on the package. The package’s source code will be 
                    added under your package’s directory, the package's path and installed version will be added to your
                     `saeghe.config.json` file and its metadata will be added to the `saeghe.config-lock.json` file.
    remove <package|alias>
                    Removes the given package from your project. This command needs a required `package` argument that 
                    should be a valid git URL (SSH or HTTPS) or a registered alias using the `alias` command. It deleted
                    given package's source files from your packages directory and also removes the package from
                    `saeghe.config.json` and its metadata from `saeghe.config-lock.json`.
    update <package|alias> {--version=}
                    If you need to get the latest version of an added package, you can run the update command. This 
                    command needs a required `package` argument that should be a valid git URL (SSH or HTTPS) or a 
                    registered alias using the `alias` command. You can also path an optional `version` option, if 
                    passed, then Saeghe will download the exact version number, if not passed, it downloads the latest 
                    available version.
    install
                    When you clone your project, you don't have your packages source code (unless you didn't add your
                    packages directory to the .gitignore file, which is not recommended) After the clone, by running 
                    install command, Saeghe will download added packages to your packages directory.
work on an existing project
    build [{dev}|production]
                    Builds project and adds built files to environment's build directory under the `build` directory. By
                    default environment will be `development`. You can pass the environment argument as `production` 
                    when you want to build the production environment.
    watch
                    By running this command, Saeghe builds your file while you are doing your development. This command
                    always builds files under the `development` environment.
    flush
                    If you need to delete any built files, running this command will give you a fresh `builds` directory.

global access
    run <package>
                    Downloads, builds and runs the given package on the fly. You need to pass a valid git URL (SSH or HTTPS)
                    to this command.
    version
                    Prints current version number.
EOD;


test(
    title: 'it should return desired man output',
    case: function () use ($man_content) {
        $output = shell_exec('php ' . root() . 'saeghe --man');

        assert_true(str_contains($output, $man_content), 'Manual output is not what we want!' . $output);
    }
);
