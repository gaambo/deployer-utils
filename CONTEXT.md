# Deployer Utilities

Shared Deployer support for deployment recipe packages. It owns behavior that is not tied to one application platform.

## Language

**Shared package**:
`deployer-utils`, the package that owns reusable Deployer helpers and their tests.
_Avoid_: common library, Laravel library

**WordPress package**:
A platform-specific Deployer package `deployer-wordpress` that depends on the shared package.
_Avoid_: application package

**Local runtime**:
The environment that runs local application commands, independent from the Deployer localhost host.
_Avoid_: local host, container host

**Compatibility wrapper**:
A deprecated recipe-package class that forwards its old public helper API to the shared package until the next major release.

**Shared test support**:
Test-only helpers shipped in `deployer-utils/tests/` and loaded by a recipe package's development autoloader.

**Task file**:
An opt-in Deployer task registration file in `tasks/`, explicitly loaded by a recipe package.
_Avoid_: auto-loaded task
