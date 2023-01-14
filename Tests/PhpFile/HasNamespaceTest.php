<?php

namespace Tests\PhpFile\HasNamespaceTest;

use Phpkg\PhpFile;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_false;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_true;
use function PhpRepos\TestRunner\Runner\test;

test(
    title: 'it should detect namespace in files with env',
    case: function () {
        $content = <<<'EOD'
#!/usr/bin/env php
<?php

namespace Application\Service;

use Application\Application\Application;
use Application\ReturnTypeDeclaration;

#[Pure]
class ClassUseNamespaceTwice extends Application\ExtendClass
{
}
EOD;

        assert_true(PhpFile::from_content($content)->has_namespace());
    }
);

test(
    title: 'it should detect namespace in classes',
    case: function () {
        $content = <<<'EOD'
<?php

namespace Application\Service;

use Application\Application\Application;
use Application\ReturnTypeDeclaration;

#[Pure]
class ClassUseNamespaceTwice extends Application\ExtendClass
{
}
EOD;

        assert_true(PhpFile::from_content($content)->has_namespace());
    }
);

test(
    title: 'it should detect namespace in files without classes',
    case: function () {
        $content = <<<'EOD'
<?php

namespace Application\Service;

function something()
{

}
EOD;

        assert_true(PhpFile::from_content($content)->has_namespace());
    }
);

test(
    title: 'it should detect when there is no namespace',
    case: function () {
        $content = <<<'EOD'
<?php

// namespace in comment

function namespace_detector() {

}

EOD;

        assert_false(PhpFile::from_content($content)->has_namespace());
    }
);
