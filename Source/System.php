<?php

namespace Phpkg\System;

function is_windows(): bool
{
    return PHP_OS === 'WINNT';
}
