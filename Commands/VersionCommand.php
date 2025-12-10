<?php

use function PhpRepos\Cli\Output\line;
use function PhpRepos\Cli\Output\success;
use function PhpRepos\Cli\Output\write;

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
    success('phpkg version 3.0.0');
    line('Copyright (c) PHPKG');
};