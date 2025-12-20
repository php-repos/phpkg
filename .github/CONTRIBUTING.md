# Contribution Guide

## Bug Reports

To encourage active collaboration, phpkg strongly encourages pull requests, not just bug reports. 
Pull requests will only be reviewed when marked as "ready for review" (not in the "draft" state) and all tests for new features are passing. 
Lingering, non-active pull requests left in the "draft" state will be closed after a few days.

However, if you file a bug report, your issue should contain a title and a clear description of the issue. 
You should also include as much relevant information as possible and a code sample that demonstrates the issue. 
The goal of a bug report is to make it easy for yourself - and others - to replicate the bug and develop a fix.

Remember, bug reports are created in the hope that others with the same problem will be able to collaborate with you on solving it. 
Do not expect that the bug report will automatically see any activity or that others will jump to fix it. 
Creating a bug report serves to help yourself and others start on the path of fixing the problem. 
If you want to chip in, you can help out by fixing [any bugs listed in our issue trackers](https://github.com/php-repos/phpkg/labels/bug). 
You must be authenticated with GitHub to view all of phpkg issues.

## Support Questions

phpkg GitHub issue trackers are not intended to provide phpkg help or support. Instead, use the [GitHub Discussions](https://github.com/php-repos/phpkg/discussions) channel.

## Core Development Discussion

You may propose new features or improvements of existing phpkg behavior in the phpkg repository's [GitHub discussion board](https://github.com/php-repos/phpkg/discussions). 
If you propose a new feature, please be willing to implement at least some of the code that would be needed to complete the feature.

## Security Vulnerabilities

If you discover a security vulnerability within php-repos, please email [Morteza Poussaneh](mailto:morteza@protonmail.com?subject=[GitHub]%20Security%20Vulnerabilities%20Report). 
All security vulnerabilities will be promptly addressed.

## Code of Conduct

In order to ensure that the phpkg community is welcoming to all, please review and abide by the [Code of Conduct](https://github.com/php-repos/phpkg/blob/master/.github/CODE_OF_CONDUCT.md).

## Installation for Contributors

In order to install `phpkg` for contributing, follow these steps:

### Install phpkg on your machine

Follow instruction [here](https://phpkg.com/documentations/installation).

### Clone the fork

Create a fork from the `phpkg` repository and then clone it.

### Code Style

Please use snake_case syntax for variables and functions to follow PHP's code style.

## Architecture

This project follows a specific layered architecture pattern. Understanding this architecture is crucial for contributing effectively. For a detailed explanation, see the [Natural Architecture for Software](https://medium.com/@MortezaPoussane/natural-architecture-for-software-30455bea1ed7) article.

### Layer Structure

The project is organized into three main layers:

1. **Business Layer** (`Source/Business/`) - Contains business specifications and use cases
2. **Solution Layer** (`Source/Solution/`) - Contains reusable solution functions
3. **Infra Layer** (`Source/Infra/`) - Contains infrastructure utilities and low-level operations

### Development Flow

When implementing a new feature, follow this flow:

1. **Start with Business Layer**: Implement the business specification first
2. **Create Solution Functions**: Extract reusable logic into Solution layer functions
3. **Use Infra Layer**: Solution layer should use Infra layer for low-level operations

**Important**: Business layer functions should call Solution layer functions, and Solution layer functions should use Infra layer functions. Never skip layers or call functions across layers in the wrong direction.

### Business Layer Requirements

Every business specification function must:

1. **Return an Outcome**: All business functions must return an `Outcome` object indicating success or failure
2. **Handle Exceptions**: Catch and handle any exceptions thrown by Solution layer functions
3. **Send a Plan**: Use `propose(Plan::create(...))` at the beginning of the function to announce the intent
4. **Send an Event**: Use `broadcast(Event::create(...))` at the end (both for success and failure cases) to announce completion

Example:
```php
function my_feature(string $project): Outcome
{
    try {
        $root = Paths\detect_project($project);
        
        propose(Plan::create('I try to perform my feature operation.', [
            'root' => $root,
        ]));
        
        // ... implementation ...
        
        broadcast(Event::create('I completed the feature operation.', [
            'root' => $root,
        ]));
        return new Outcome(true, '✅ Feature completed successfully.');
    } catch (NotWritableException $e) {
        broadcast(Event::create('The path is not writable!', [
            'project' => $project,
            'error' => $e->getMessage(),
        ]));
        return new Outcome(false, '🔒 Path is not writable: ' . $e->getMessage());
    }
}
```

### Solution Layer Logging Requirements

Every Solution layer function must have logging. The log level depends on how the function is used:

1. **Info Level (`log()`)**: Use when the function is called by Business layer specifications
2. **Debug Level (`debug()`)**: Use when the function is only called by other Solution layer functions

Example:
```php
// This function is called by Business layer, so use log()
function detect_project(string $project): string
{
    log('Detecting project path', ['project' => $project]);
    // ... implementation ...
}

// This function is only used internally by Solution layer, so use debug()
function phpkg_root(): string
{
    debug('Retrieving PHPKG root from environment variables');
    return Envs\phpkg_root();
}
```

## Testing

### Running Tests

To run all tests, use:

```shell
phpkg run test-runner
```

### Running Specific Tests

To filter tests for a specific test file, use the `--filter` option:

```shell
phpkg run test-runner run --filter=TestFileName
```

For example:
```shell
phpkg run test-runner run --filter=AddCommandTest
```

### Test Requirements

For each new feature, you must provide tests that cover:

1. **Main Feature**: Test the primary functionality and happy path
2. **Main Errors**: Test the main error cases and edge cases

Tests should be comprehensive and ensure the feature works as expected in various scenarios.

## Platform Support

### Windows Compatibility

**Windows is officially supported as far as possible**, all new features must work on Windows as well, unless there is a specific need for `pcntl` functionality or similar requirements that is not supported on Windows.

When implementing features:

- Use cross-platform compatible code
- Test on Windows if possible
- Avoid Windows-specific assumptions
- Only use `pcntl` when absolutely necessary and document why it's needed

If a feature requires `pcntl` and cannot work on Windows, this should be clearly documented in the code and tests.
