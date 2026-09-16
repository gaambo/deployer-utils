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

## Hosts And Runtimes

`Localhost` reads and parses values against the configured `localhost` host. `Localhost::run()` runs natively unless it
is called inside `Runtime::within()`. `Runtime::run()` routes application commands through the current host's configured
runtime, while the host still determines whether execution uses a local process or SSH.

Runtime execution uses two distinct Deployer hosts:

- The **source host** is the real configured host. It owns the local or SSH transport and paths as seen outside the
  runtime, such as `/srv/site`.
- The **execution host** is an internal host created by the runtime. It has the same transport type, inherits source-host
  config, and carries runtime-only overrides such as DDEV's `/var/www/html` project path and command wrapper.

The separate execution host prevents runtime config from changing normal commands on the source host. While
`Runtime::within()` runs, Deployer uses the execution host as its current context. This also keeps nested
`Localhost::run()` calls and lazy config such as `bin/php` inside the same runtime. Runtime objects serialize back to
plain definitions when Deployer passes host config between worker processes.

```php
use Gaambo\DeployerUtils\Runtime\DdevRuntime;

use function Deployer\localhost;
use function Gaambo\DeployerUtils\runtime;

localhost()
    ->set('deploy_path', __DIR__)
    ->set('current_path', '{{deploy_path}}/public')
    ->set('runtime', runtime(DdevRuntime::class));
```

Runtime configuration can also come from a Deployer YAML inventory:

```yaml
hosts:
  localhost:
    local: true
    runtime:
      type: ddev
      options:
        ddev_deploy_path: /srv/app
```

The `ddev` alias and runtime options are resolved by the PHP `runtime()` helper. It returns a serializable runtime object.
Each source host binds its own clone, so a runtime configured globally can safely serve multiple hosts.

DDEV maps host paths below the source host's `deploy_path` to `/var/www/html`. Configure another container root on the
runtime:

```php
localhost()->set(
    'runtime',
    runtime(DdevRuntime::class, ['ddev_deploy_path' => '/srv/app'])
);
```

Runtimes inherit their host's config. Lazy binary values such as `bin/composer`, `bin/npm`, and `bin/php` resolve in the
runtime and cache there. Use `Runtime::path()` when passing an explicit host path to a runtime command.

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
