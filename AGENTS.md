# AGENTS.md — AI Agent Guide for phpBB

This file provides guidance for AI coding agents (GitHub Copilot, Claude,
Cursor, etc.) working on this repository.

---

## Project Overview

phpBB is an open-source forum software written in PHP. The repository
structure is:

```
phpBB/          — main application (PHP source, templates, assets)
  phpbb/        — namespaced OOP core (PSR-4, namespace phpbb\)
  includes/     — legacy procedural code
  config/       — Symfony DI container configuration (YAML)
  vendor/       — Composer dependencies (do NOT edit)
tests/          — PHPUnit test suite (mirrors phpBB/ structure)
build/          — build tools, code sniffer rulesets, Psalm config
git-tools/      — git hooks and utilities
```

### Technology Stack

- **PHP**: `^8.2` (minimum)
- **Symfony**: `^7.4` (Config, Console, DependencyInjection, EventDispatcher,
  HttpFoundation, HttpKernel, Mailer, Routing, Twig Bridge, Yaml)
- **Twig**: `^3.14`
- **Doctrine DBAL**: `^3.9`
- **Testing**: PHPUnit `^10.0`
- **Static analysis**: Psalm `^6.13` (error level 5)
- **Code style**: PHP_CodeSniffer `^4.0` with custom phpBB ruleset

---

## Commands

All commands must be run from the **repository root** unless stated otherwise.

### Install Dependencies

```bash
cd phpBB && php ../composer.phar install
```

### Run Tests

```bash
# Unit tests (excludes functional and slow tests)
phpBB/vendor/bin/phpunit

# Run a specific test file
phpBB/vendor/bin/phpunit tests/controller/controller_test.php

# Run a specific test method
phpBB/vendor/bin/phpunit --filter test_controller_resolver tests/controller/controller_test.php

# Run slow tests (excluded by default)
phpBB/vendor/bin/phpunit --group slow
```

Tests require the bootstrap at `tests/bootstrap.php`. The working directory
must be the repository root when running PHPUnit.

### Code Style (PHP_CodeSniffer)

```bash
# Check phpbb/ namespace (strict ruleset — the main one for OOP code)
phpBB/vendor/bin/phpcs -s -p --extensions=php \
  --standard=build/code_sniffer/ruleset-php-strict-core.xml \
  phpBB/phpbb

# Check includes/ and other legacy code (legacy ruleset)
phpBB/vendor/bin/phpcs -s -p --extensions=php \
  --standard=build/code_sniffer/ruleset-php-legacy.xml \
  phpBB/includes

# Auto-fix fixable violations
phpBB/vendor/bin/phpcbf -s -p --extensions=php \
  --standard=build/code_sniffer/ruleset-php-strict-core.xml \
  phpBB/phpbb
```

### Static Analysis (Psalm)

```bash
phpBB/vendor/bin/psalm
```

Psalm is configured in `psalm.xml` at error level 5, targeting `phpBB/phpbb/`.

### Using Phing (build system)

```bash
# Run all checks (test + sniff)
php build/build.xml all

# Just sniff
vendor/bin/phing -f build/build.xml sniff

# Just test
vendor/bin/phing -f build/build.xml test
```

---

## Code Style Rules

### General (enforced by phpcs ruleset)

- **Indentation**: tabs (not spaces)
- **Line length**: recommended ≤80 chars, hard limit 120 chars
- **PHP constants**: `true`, `false`, `null` must be **lowercase**
- **PHP keywords**: must be **lowercase**
- **Opening braces**: on the **next line** (Allman style) — applies to
  classes, functions, control structures
- **Blank line after last `use`**: required (PSR2)
- **Exactly 1 blank line at end of file** (PSR2)

### phpBB-Specific Rules (custom sniffs in `build/code_sniffer/phpbb/`)

#### Nullable Types — **IMPORTANT**
- **Never use `?Type` shorthand nullable syntax**
- Always use the union type form: `Type|null`
- Correct: `string|null $foo = null`
- Wrong:   `?string $foo = null`

#### Union Types
- `null` must always be the **last** element: `int|string|null` ✓
- Non-null elements must be in **alphabetical order**: `int|string|null` ✓

#### Namespaces
- Classes in `phpBB/phpbb/` must be namespaced (`phpbb\...`)
- Classes in `phpBB/includes/` use legacy global namespace

### Symfony Integration Patterns

- **DI Container Extensions** must extend
  `Symfony\Component\DependencyInjection\Extension\Extension`
  (NOT `HttpKernel\DependencyInjection\Extension` — marked `@internal` in 7.1)
- **`load()` method** on extensions must have `: void` return type
- **`CompilerPassInterface::process()`** must have `: void` return type
- **`EventSubscriberInterface::getSubscribedEvents()`** must have `: array`
  return type
- **`ArgumentResolverInterface`** — use `phpbb\controller\argument_resolver`
  for controller argument resolution (NOT `ControllerResolverInterface::
  getArguments()` which was removed in Symfony 5.0)
- **Services YAML** — DI services are in `phpBB/config/*/container/`

### PHP Version Compatibility

- Minimum PHP: **8.2**
- **Deprecated in PHP 8.2**: `utf8_encode()` / `utf8_decode()` — use
  `mb_convert_encoding($str, 'UTF-8', 'ISO-8859-1')` instead
- **Deprecated in PHP 8.4**: implicit nullable type hints
  (`function foo(Type $x = null)`) — use `Type|null $x = null` instead
- **PHP 8.4 Features**: Do NOT use PHP 8.4 specific features (e.g. property
  hooks, asymmetric visibility) as the codebase must support PHP 8.2.
- Dynamic properties (without `#[\AllowDynamicProperties]`) are deprecated
  in PHP 8.2 — some phpBB classes suppress this via Psalm config

---

## Git Workflow

### Branch Naming

```
ticket/NNNNN    — bug fixes or improvements for a tracker ticket
feature/name    — new feature branches
task/name       — maintenance/refactoring tasks
```

### Commit Message Format

phpBB enforces a strict commit message format, validated by
`git-tools/hooks/commit-msg`. Install the hook with:

```bash
ln -s ../../git-tools/hooks/commit-msg .git/hooks/commit-msg
```

**Format:**

```
[ticket/17672] Short imperative description, max 80 chars

Optional body paragraph(s) explaining the why, each line ≤80 chars.
Multiple paragraphs separated by blank lines are fine.

PHPBB-17672
```

**Rules:**
- Line 1 (header): `[ticket/NNNNN]`, `[feature/name]`, or `[task/name]`
  followed by a space and a description (max 80 chars total)
- Line 2: **must be blank**
- Body: free-form text, each line ≤80 chars
- Footer: `PHPBB-NNNNN` on its own line, after a blank line (required for
  `ticket/` branches; references the phpBB tracker issue)
- **No line may exceed 80 characters**

Validate a range of commits before submitting a PR:

```bash
git-tools/commit-msg-hook-range.sh upstream/master..HEAD
```

### Rebasing

Use `git rebase -i upstream/master` before submitting. The upstream remote
is `upstream` (not `origin`).

---

## Testing Conventions

- Test files live in `tests/` mirroring the `phpBB/phpbb/` structure
- Test class names follow `phpbb_<module>_<name>_test` convention
- Test classes extend `phpbb_test_case` (in `tests/test_framework/`)
- Database tests use `misantron/dbunit` and extend `phpbb_database_test_case`
- **Functional tests** are in `tests/functional/` and require a running
  phpBB installation — they are excluded from the default test suite

---

## Common Patterns

### Registering a Service

Add to the appropriate YAML file in `phpBB/config/default/container/`:

```yaml
my.service:
    class: phpbb\my\service
    arguments:
        - '@dependency.service'
        - '%some.parameter%'
```

### Creating a Compiler Pass

```php
namespace phpbb\di\pass;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

class my_pass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // ...
    }
}
```

### Creating an Event Subscriber

```php
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class my_subscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::TERMINATE => 'on_terminate',
        ];
    }
}
```

### Creating a DI Extension (for phpBB extensions)

```php
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

class my_extension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        // ...
    }

    public function getAlias(): string
    {
        return 'my_extension';
    }
}
```

---

## Known Suppressions / Workarounds

- **Psalm `UndefinedThisPropertyAssignment` / `UndefinedThisPropertyFetch`**:
  Suppressed globally in `psalm.xml` because some large legacy classes
  (`session.php`, `user.php`) use dynamic properties.
- **phpcs sniff exclusions**: `phpBB/includes/utf/data/`, migration data
  under `v30x`, and `phpBB/install/` are excluded from strict sniffing.
- **`restrictDeprecations="true"`** in `phpunit.xml.dist`: PHPUnit will fail
  on PHP/Symfony deprecation notices emitted during tests.

---

## Additional Developer Documentation

For topics not covered in this guide, refer to the following official resources:

*   **Test Suite Configuration**: Detailed instructions on setting up database
    and functional tests are available in [RUNNING_TESTS.md].
*   **Extension Development**: Guidelines and architecture for third-party
    extensions are in the [phpBB Extension Development Documentation][Extension Documentation].
*   **Database Migrations**: Instructions on creating, running, and reverting
    migrations are in the [phpBB Migration Documentation][Migration Documentation].
*   **Template System**: Guide to styles, templates, events, and syntax in the
    [phpBB Style Documentation][Style Documentation].
*   **CI/CD Pipeline**: GitHub Actions workflows are defined under
    [.github/workflows/tests.yml][tests.yml].

[RUNNING_TESTS.md]: file:///home/rubencm/Desktop/repos/phpbb/tests/RUNNING_TESTS.md
[Extension Documentation]: https://area51.phpbb.com/docs/dev/3.3.x/extensions/index.html
[Migration Documentation]: https://area51.phpbb.com/docs/dev/3.3.x/migrations/index.html
[Style Documentation]: https://area51.phpbb.com/docs/dev/3.3.x/styles/index.html
[tests.yml]: file:///home/rubencm/Desktop/repos/phpbb/.github/workflows/tests.yml

