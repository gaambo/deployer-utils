# Deployer Utils

Shared, platform-neutral helpers for Deployer recipe packages. This package is
the common layer for recipe packages such as `deployer-wordpress`.

This is an early `0.x` release. The API may change before `1.0.0`.

## Table Of Contents

- [Installation](#installation)
- [Requirements](#requirements)
- [Configuration](#configuration)
  - [Hosts](#hosts)
  - [Runtimes](#runtimes)
  - [YAML inventory](#yaml-inventory)
- [Helpers](#helpers)
  - [Composer and npm](#composer-and-npm)
  - [Files](#files)
  - [Rsync](#rsync)
  - [Localhost](#localhost)
- [File Backup Tasks](#file-backup-tasks)
- [Shared Test Support](#shared-test-support)
- [Testing](#testing)
- [Changelog](#changelog)
- [Contributing](#contributing)
- [License](#license)

## Installation

```bash
composer require gaambo/deployer-utils
```

The package is a library. It does not register tasks automatically. Recipe
packages can opt into the task files they need.

## Requirements

- PHP 8.3 or newer
- Deployer 8
- A Unix-like host
- `rsync` for file transfers
- `zip` for file backups
- DDEV, when using `DdevRuntime`

## Configuration

### Hosts

Helpers use the current Deployer host. Configure the project root and current
path on each host that a recipe uses:

```php
use function Deployer\localhost;

localhost()
    ->set('deploy_path', __DIR__)
    ->set('current_path', '{{deploy_path}}/public');
```

The source host owns the transport and host-side paths. It can be a local host
or an SSH host.

### Runtimes

A runtime adds a command environment without changing the source host. The
runtime creates an internal execution host, inherits the source host config,
and applies runtime-specific paths and command wrappers.

```php
use Gaambo\DeployerUtils\Runtime\DdevRuntime;
use function Deployer\localhost;
use function Gaambo\DeployerUtils\runtime;

localhost()
    ->set('deploy_path', __DIR__)
    ->set('current_path', '{{deploy_path}}/public')
    ->set('runtime', runtime(DdevRuntime::class));
```

Run commands and read paths through the runtime when a runtime is configured:

```php
use Gaambo\DeployerUtils\Runtime\Runtime;

Runtime::run('composer install');
$path = Runtime::path('{{deploy_path}}/shared/database.sql');

Runtime::within(function (): void {
    // Runtime config and the execution-host context are active here.
});
```

Without a configured runtime, `Runtime::run()` uses the current host normally
and `Runtime::path()` returns the input path unchanged.

DDEV uses `/var/www/html` as its default project path. Set another container
path with the `ddev_deploy_path` option:

```php
localhost()->set(
    'runtime',
    runtime(DdevRuntime::class, ['ddev_deploy_path' => '/srv/app'])
);
```

Host paths below `deploy_path` map to the matching path below the DDEV project
path. Paths outside the project root are rejected.

### YAML inventory

Runtime definitions can also come from a Deployer YAML inventory:

```yaml
hosts:
  localhost:
    local: true
    runtime:
      type: ddev
      options:
        ddev_deploy_path: /var/www/html
```

The `runtime()` helper accepts the `ddev` alias or a fully qualified runtime
class name. Runtime objects serialize to plain definitions for Deployer worker
processes.

## Helpers

### Composer and npm

- `Composer::runDefault()` runs the default Composer command.
- `Composer::runCommand()` runs a Composer command with arguments.
- `Composer::runScript()` runs a Composer script.
- `Composer::install()` installs Composer when needed.
- `NPM::runCommand()` runs an npm action.
- `NPM::runScript()` runs an npm script.
- `NPM::runInstall()` installs npm dependencies.

All command helpers accept a project path and return the command output.

### Files

`Files` provides path-aware file transfers for the current host:

- `pushFiles()` and `pullFiles()` transfer directories.
- `pushFile()` and `pullFile()` transfer one file.
- `resolvePath()` resolves a path against a base path.
- `zipFiles()` creates a zip backup and returns its path.

Absolute paths stay absolute. Relative paths resolve against the base path
provided by the caller.

### Rsync

`Rsync` builds Deployer rsync option arrays from includes, excludes, filters,
and option configuration. Use it with the file helpers or direct Deployer
commands.

### Localhost

`Localhost` reads config and runs commands against the configured localhost:

- `Localhost::get()` returns the configured Deployer localhost.
- `Localhost::getConfig()` reads localhost-specific config.
- `Localhost::parse()` parses a value in localhost context.
- `Localhost::run()` uses the active runtime when one exists.
- `Localhost::runNative()` always runs outside a runtime.
- `Localhost::within()` runs a callback in localhost context.

`Utils` contains shell quoting, config parsing, and verbosity helpers.

## File Backup Tasks

Task registration is opt-in. Load the task file from a recipe:

```php
require 'vendor/gaambo/deployer-utils/tasks/files.php';
```

It registers:

- `files:backup:remote`: zip remote files and download the archive locally.
- `files:backup:local`: zip local files.

Configure `current_path`, `release_or_current_path`, and `backup_path` on the
hosts used by the tasks. The local host also needs `current_path` and
`backup_path`.

## Shared Test Support

Tests ship in distribution archives but are not part of production autoload.
Recipe packages can map the support classes in their development autoloader:

```json
{
  "autoload-dev": {
    "psr-4": {
      "Gaambo\\DeployerUtils\\Tests\\": "vendor/gaambo/deployer-utils/tests/"
    }
  }
}
```

## Testing

Install development dependencies and run the full local check suite:

```bash
composer install
composer precommit
```

The suite includes PHP syntax linting, PSR-12 checks, PHPStan, and unit,
integration, and functional tests. Individual commands are available as
`composer tests:unit`, `composer tests:integration`, and
`composer tests:functional`.

GitHub Actions runs the quality and test jobs on PHP 8.3, 8.4, and 8.5 with
Deployer 8. Composer dependency audits and lock-file diffs run on dependency
pull requests.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Contributing

Issues, feature requests, and pull requests are welcome on
[GitHub](https://github.com/gaambo/deployer-utils). Run `composer precommit`
before submitting a pull request.

## License

MIT. See [LICENSE](LICENSE).
