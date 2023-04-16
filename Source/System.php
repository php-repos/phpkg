<?php

namespace Phpkg\System;

function is_windows(): bool
{
    return PHP_OS === 'WINNT';
}

function random_temp_directory(): string
{
    return sys_get_temp_dir() . '/' . uniqid();
}
