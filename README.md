# Deployer Utils

Shared, platform-neutral helpers for Deployer recipe packages.

## Requirements

- PHP 8.3 or newer
- Deployer 8
- A Unix-like host with `rsync`; file backup tasks also need `zip`

## Installation

```bash
composer require gaambo/deployer-utils
```

## Localhost And Runtimes

`Localhost` reads and parses values against the configured `localhost` host. `Localhost::run()` runs natively unless it
is called inside `Runtime::within()`; `Runtime::run()` always routes application commands through the configured runtime.

```php
use Gaambo\DeployerUtils\Runtime\DdevRuntimeHost;

use function Deployer\localhost;
use function Gaambo\DeployerUtils\runtime;

localhost()
    ->set('deploy_path', __DIR__)
    ->set('current_path', '{{deploy_path}}/public')
    ->set('runtime', runtime(DdevRuntimeHost::class));
```

DDEV maps host paths below localhost's `deploy_path` to `/var/www/html`. Configure another container root on the runtime:

```php
localhost()->set(
    'runtime',
    runtime(DdevRuntimeHost::class)->set('ddev_deploy_path', '/srv/app')
);
```

Runtime hosts inherit localhost config. Lazy binary values such as `bin/composer`, `bin/npm`, and `bin/php` resolve in
the runtime and cache there. Use `Runtime::path()` when passing an explicit host path to a runtime command.

## Helpers

- `Composer`: run Composer commands and scripts, or install Composer.
- `NPM`: run npm commands, installs, and scripts.
- `Files`: push, pull, resolve, and zip paths.
- `Rsync`: build Deployer rsync option arrays.
- `Utils`: verbosity, config parsing, and shell quoting helpers.

## File Backup Tasks

Task registration is opt-in. Load the task file from a recipe:

```php
require 'vendor/gaambo/deployer-utils/tasks/files.php';
```

It registers `files:backup:remote` and `files:backup:local`. Configure `current_path`, `release_or_current_path`,
`backup_path`, and `zip_options` on the relevant hosts.

## Shared Test Support

Tests ship in distribution archives but are not part of production autoload. Recipe packages can map the support classes:

```json
{
  "autoload-dev": {
    "psr-4": {
      "Gaambo\\DeployerUtils\\Tests\\": "vendor/gaambo/deployer-utils/tests/"
    }
  }
}
```

## Contributing

Run `composer precommit` before submitting a pull request.
