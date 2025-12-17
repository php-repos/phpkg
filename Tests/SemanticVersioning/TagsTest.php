<?php

namespace Tests\SemanticVersioning\TagsTest;

use PhpRepos\TestRunner\Assertions;
use function PhpRepos\TestRunner\Runner\test;
use function PhpRepos\SemanticVersioning\Tags\compare;
use function PhpRepos\SemanticVersioning\Tags\has_major_change;
use function PhpRepos\SemanticVersioning\Tags\major;
use function PhpRepos\SemanticVersioning\Tags\is_valid_semantic;
use function PhpRepos\SemanticVersioning\Tags\is_stable;

test(
    title: 'it should compare two version strings correctly',
    case: function () {
        // Test basic version comparison
        Assertions\assert_true(compare('1.0.0', '1.0.0') === 0, 'Equal versions should return 0');
        Assertions\assert_true(compare('1.0.0', '2.0.0') === -1, '1.0.0 should be less than 2.0.0');
        Assertions\assert_true(compare('2.0.0', '1.0.0') === 1, '2.0.0 should be greater than 1.0.0');
        
        // Test with v prefix (case insensitive)
        Assertions\assert_true(compare('v1.0.0', '1.0.0') === 0, 'v1.0.0 should equal 1.0.0');
        Assertions\assert_true(compare('V1.0.0', '1.0.0') === 0, 'V1.0.0 should equal 1.0.0');
        Assertions\assert_true(compare('v1.0.0', 'V1.0.0') === 0, 'v1.0.0 should equal V1.0.0');
        Assertions\assert_true(compare('1.0.0', 'v1.0.0') === 0, '1.0.0 should equal v1.0.0');
        
        // Test with pre-release and build metadata
        Assertions\assert_true(compare('1.0.0-alpha', '1.0.0') === -1, '1.0.0-alpha should be less than 1.0.0');
        Assertions\assert_true(compare('1.0.0', '1.0.0-alpha') === 1, '1.0.0 should be greater than 1.0.0-alpha');
    }
);


test(
    title: 'it should handle v/V prefix normalization correctly',
    case: function () {
        // Test that v/V prefixes are normalized and don't affect comparison
        $versions_with_prefix = ['v1.0.0', 'V1.0.0', 'v2.1.3', 'V2.1.3'];
        $versions_without_prefix = ['1.0.0', '1.0.0', '2.1.3', '2.1.3'];
        
        for ($i = 0; $i < count($versions_with_prefix); $i++) {
            $with_prefix = $versions_with_prefix[$i];
            $without_prefix = $versions_without_prefix[$i];
            
            Assertions\assert_true(compare($with_prefix, $without_prefix) === 0, "$with_prefix should equal $without_prefix");
            Assertions\assert_true(compare($without_prefix, $with_prefix) === 0, "$without_prefix should equal $with_prefix");
        }
        
        // Test that different versions with same prefix are still different
        Assertions\assert_true(compare('v1.0.0', 'v2.0.0') === -1, 'v1.0.0 should be less than v2.0.0');
        Assertions\assert_true(compare('V1.0.0', 'V2.0.0') === -1, 'V1.0.0 should be less than V2.0.0');
    }
);

test(
    title: 'it should detect major version changes correctly',
    case: function () {
        // Test major version changes
        Assertions\assert_true(has_major_change('1.0.0', '2.0.0'), '1.0.0 to 2.0.0 should have major change');
        Assertions\assert_true(has_major_change('2.0.0', '1.0.0'), '2.0.0 to 1.0.0 should have major change');
        Assertions\assert_true(has_major_change('10.0.0', '11.0.0'), '10.0.0 to 11.0.0 should have major change');
        
        // Test no major version changes
        Assertions\assert_true(!has_major_change('1.0.0', '1.1.0'), '1.0.0 to 1.1.0 should not have major change');
        Assertions\assert_true(!has_major_change('1.0.0', '1.0.1'), '1.0.0 to 1.0.1 should not have major change');
        Assertions\assert_true(!has_major_change('1.0.0', '1.0.0'), '1.0.0 to 1.0.0 should not have major change');
    }
);


test(
    title: 'it should extract major version part correctly',
    case: function () {
        // Test basic major version extraction
        Assertions\assert_true(major('1.0.0') === '1', 'Major version of 1.0.0 should be 1');
        Assertions\assert_true(major('2.1.3') === '2', 'Major version of 2.1.3 should be 2');
        Assertions\assert_true(major('10.20.30') === '10', 'Major version of 10.20.30 should be 10');
        
        // Test with v prefix
        Assertions\assert_true(major('v1.0.0') === '1', 'Major version of v1.0.0 should be 1');
        Assertions\assert_true(major('V2.1.3') === '2', 'Major version of V2.1.3 should be 2');
        
        // Test with pre-release
        Assertions\assert_true(major('1.0.0-alpha') === '1', 'Major version of 1.0.0-alpha should be 1');
        Assertions\assert_true(major('2.1.3-beta.1') === '2', 'Major version of 2.1.3-beta.1 should be 2');
        
        // Test with build metadata
        Assertions\assert_true(major('1.0.0+build.1') === '1', 'Major version of 1.0.0+build.1 should be 1');
        Assertions\assert_true(major('3.0.0+20130313144700') === '3', 'Major version of 3.0.0+20130313144700 should be 3');
        
        // Test with both pre-release and build metadata
        Assertions\assert_true(major('1.0.0-alpha+build.1') === '1', 'Major version of 1.0.0-alpha+build.1 should be 1');
    }
);


test(
    title: 'it should validate semantic version format correctly',
    case: function () {
        // Test valid semantic versions
        $valid_versions = [
            '1.0.0', '2.1.3', '10.20.30',
            'v1.0.0', 'V2.1.3',
            '1.0.0-alpha', '2.1.3-beta.1', '10.20.30-rc.2',
            '1.0.0+build.1', '2.1.3+20130313144700',
            '1.0.0-alpha+build.1', '2.1.3-beta.1+20130313144700'
        ];
        
        foreach ($valid_versions as $version) {
            Assertions\assert_true(is_valid_semantic($version), "Version should be valid: $version");
        }
        
        // Test invalid semantic versions
        $invalid_versions = [
            '', 'invalid', '1.0.0.', '.1.0.0', '1.0.0.0',
            'a.b.c', '1.0.0-', '1.0.0+', '1.0.0-alpha.', '1.0.0+.build'
        ];
        
        foreach ($invalid_versions as $version) {
            Assertions\assert_true(!is_valid_semantic($version), "Version should be invalid: $version");
        }
    }
);

test(
    title: 'it should identify stable versions correctly',
    case: function () {
        // Test stable versions
        $stable_versions = ['1.0.0', '2.1.3', '10.20.30', 'v1.0.0', 'V2.1.3'];
        
        foreach ($stable_versions as $version) {
            Assertions\assert_true(is_stable($version), "Version should be stable: $version");
        }
        
        // Test unstable versions
        $unstable_versions = [
            '1.0.0-alpha', '2.1.3-beta.1', '10.20.30-rc.2',
            '1.0.0+build.1', '2.1.3+20130313144700',
            '1.0.0-alpha+build.1', '2.1.3-beta.1+20130313144700',
            '1.0', '1.0.0.0', '1.0.0.1'
        ];
        
        foreach ($unstable_versions as $version) {
            Assertions\assert_true(!is_stable($version), "Version should not be stable: $version");
        }
    }
);

