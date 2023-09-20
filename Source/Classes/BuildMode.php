<?php

namespace Phpkg\Classes;

enum BuildMode: string
{
    case Production = 'production';
    case Development = 'development';
}