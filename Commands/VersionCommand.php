<?php

use function Phpkg\Infra\CLI\line;
use function Phpkg\Infra\CLI\success;
use function Phpkg\Infra\CLI\write;
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
