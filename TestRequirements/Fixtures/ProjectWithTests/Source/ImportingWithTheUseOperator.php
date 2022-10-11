<?php

namespace ProjectWithTests;

use ProjectWithTests\SubDirectory\SimpleClass;
use ProjectWithTests\SubDirectory\ClassUseAnotherClass as Another;
use ArrayObject;
use function ProjectWithTests\SampleFile\anImportantFunction;
use function ProjectWithTests\SubDirectory\Helper\helper1 as anotherFunction;
use const ProjectWithTests\SubDirectory\Constants\CONSTANT;
use const ProjectWithTests\SubDirectory\OtherConstants\RENAME as AnotherConstant;

function init()
{
    $another = new Another();
}

function action()
{
    new SimpleClass();
    if (anImportantFunction() || anotherFunction()) {
        $var = CONSTANT;
    } else {
        $var = AnotherConstant;
    }
}
