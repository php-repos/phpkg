<?php

namespace Phpkg\Classes\Environment;

use PhpRepos\FileManager\Filesystem\Directory;
use PhpRepos\FileManager\Filesystem\File;
use function PhpRepos\FileManager\Resolver\root;

class Environment
{
    public Directory $pwd;
    public File $credential_file;

    public function __construct(
        public Directory $root,
    ) {
        $this->pwd = Directory::from_string(root());
        $this->credential_file = $this->root->file('credentials.json');
    }
}
