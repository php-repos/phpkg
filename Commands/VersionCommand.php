<?php

use function PhpRepos\Cli\Output\line;
use function PhpRepos\Cli\Output\success;
use function PhpRepos\Cli\Output\write;
use function Phpkg\Infra\Envs\phpkg_version;

/**
 * Show the current running version of phpkg
 */
return function () {
    $logo = "
\e[38;5;57m██████╗ \e[38;5;57m██╗  ██╗\e[38;5;57m██████╗ \e[38;5;57m██╗  ██╗\e[38;5;57m ██████╗ 
\e[38;5;57m██╔══██╗\e[38;5;57m██║  ██║\e[38;5;57m██╔══██╗\e[38;5;57m██║ ██╔╝\e[38;5;57m██╔════╝ 
\e[38;5;57m██████╔╝\e[38;5;57m███████║\e[38;5;57m██████╔╝\e[38;5;57m█████╔╝ \e[38;5;57m██║  ███╗
\e[38;5;57m██╔═══╝ \e[38;5;57m██╔══██║\e[38;5;57m██╔═══╝ \e[38;5;57m██╔═██╗ \e[38;5;57m██║   ██║
\e[38;5;57m██║     \e[38;5;57m██║  ██║\e[38;5;57m██║     \e[38;5;57m██║  ██╗\e[38;5;57m╚██████╔╝
\e[38;5;57m╚═╝     \e[38;5;57m╚═╝  ╚═╝\e[38;5;57m╚═╝     \e[38;5;57m╚═╝  ╚═╝\e[38;5;57m ╚═════╝ 
\e[0m
";
    write($logo);
    success('phpkg version ' . phpkg_version());
    line('Copyright (c) 2022-' . date('Y') . ' PHPKG');
    return 0;
};
