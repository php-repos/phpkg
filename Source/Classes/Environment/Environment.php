<?php

namespace Phpkg\Classes\Environment;

use PhpRepos\FileManager\Path;
use function PhpRepos\FileManager\Resolver\root;

class Environment
{
    public Path $pwd;
    public Path $credential_file;

    public function __construct(
        public Path $root,
    ) {
        $this->pwd = Path::from_string(root());
        $this->credential_file = $this->root->append('credentials.json');
    }
}
