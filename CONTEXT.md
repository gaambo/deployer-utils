# Deployer Utilities

Shared Deployer support for deployment recipe packages. It owns behavior that is not tied to one application platform.

## Language

**Shared package**:
`deployer-utils`, the package that owns reusable Deployer helpers and their tests.
_Avoid_: common library, Laravel library

**WordPress package**:
A platform-specific Deployer package `deployer-wordpress` that depends on the shared package.
_Avoid_: application package

**Runtime**:
An optional command environment attached to a Deployer host. It maps host paths and wraps commands without owning the
host's local or SSH transport.
_Avoid_: runtime host, container host

**Source host**:
The real configured Deployer host that owns a runtime. It retains host-side paths and owns local or SSH transport.
_Avoid_: runtime host

**Execution host**:
An internal Deployer host created by a runtime. It preserves the source host's transport type, inherits its config, and
holds runtime-only path and command-wrapper overrides. Its alias combines source and runtime names, such as
`production:ddev`. It is never a user-configured deployment target.
_Avoid_: source host, runtime host

**Compatibility wrapper**:
A deprecated recipe-package class that forwards its old public helper API to the shared package until the next major release.

**Shared test support**:
Test-only helpers shipped in `deployer-utils/tests/` and loaded by a recipe package's development autoloader.

**Task file**:
An opt-in Deployer task registration file in `tasks/`, explicitly loaded by a recipe package.
_Avoid_: auto-loaded task
