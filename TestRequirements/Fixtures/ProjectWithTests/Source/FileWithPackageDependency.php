<?php

namespace ProjectWithTests\SampleFile;

use PhpRepos\SimplePackage\AUsefulClass;

class FileWithPackageDependency
{
    public function __construct()
    {
        $this->dependency = new AUsefulClass();
    }
}
