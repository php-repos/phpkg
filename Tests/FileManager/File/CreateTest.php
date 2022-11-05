<?php

namespace Tests\FileManager\File\CreateTest;

use Saeghe\Saeghe\FileManager\Address;
use function Saeghe\Saeghe\FileManager\File\content;
use function Saeghe\Saeghe\FileManager\File\create;
use function Saeghe\Saeghe\FileManager\File\delete;
use function Saeghe\Saeghe\FileManager\File\exists;

test(
    title: 'it should create file',
    case: function () {
        $file = Address::from_string(root() . 'Tests/PlayGround/sample.txt');
        assert_true(create($file->to_string(), 'content in file'));
        assert_true(exists($file->to_string()));
        assert_true('content in file' === content($file->to_string()));

        return $file;
    },
    after: function (Address $file) {
        delete($file->to_string());
    }
);
