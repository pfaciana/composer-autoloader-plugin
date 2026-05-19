# Composer Autoloader Plugin

Generate Composer bootstrap files from PHP attributes or directory patterns.

This package is a Composer plugin. It runs during `composer dump-autoload`, writes generated bootstrap files into `vendor/composer` by default, and registers those files with Composer's `autoload.files` list.

## Install

```bash
composer require pfaciana/composer-autoloader-plugin
composer config allow-plugins.pfaciana/composer-autoloader-plugin true
```

After changing config, adding or removing autoload attributes, or changing matched files, regenerate Composer autoload files:

```bash
composer dump-autoload
```

## Understanding Patterns

A major feature of this plugin is that it can scan files by patterns. These patterns mirror the patterns used by .gitignore files. If you already know how to use a .gitignore file, then you know how to use patterns here. The only difference is this plugins uses "include" patterns instead of "ignore" patterns. So your patterns will likely be inverted from your .gitignore file.

This done using the `pfaciana/includefile` package, which is an inverted wrapper around `automattic/ignorefile`.

Both `autoload-by-attr.patterns` and `autoload-by-dir.patterns` use `pfaciana/includefile`.

Example patterns:

```text
**/*.php
!vendor
!node_modules
!src/Excluded
```

---

## Attribute Autoloading

Attribute autoloading scans PHP files for functions or public static methods marked with an attribute, then generates a bootstrap file that calls them.

By default, it looks for `#[AutoRun]`.

```json
{
	"extra": {
		"autoload-by-attr": {
			"patterns": [
				"src"
			]
		}
	}
}
```

```php
<?php

namespace App;

use Render\Autoloader\Attributes\AutoRun;

#[AutoRun(priority: 10)]
function bootstrap(): void
{
    // Runs when vendor/autoload.php is loaded.
}
```

```php
<?php

namespace App;

use Render\Autoloader\Attributes\AutoRun;

class Plugin
{
    #[AutoRun(priority: 20)]
    public static function boot(): void
    {
        // Static methods are supported.
    }
}
```

Default output autoload file location:

```text
vendor/composer/autoload_bootstrap.php
```

### Inputs

| Key         | Default                    | Notes                                                                     |
|-------------|----------------------------|---------------------------------------------------------------------------|
| `attribute` | `"AutoRun"`                | Attribute name to scan for. Can include default args.                     |
| `patterns`  | `["src"]`                  | Files/directories to scan from `cwd`.                                     |
| `output`    | `"autoload_bootstrap.php"` | Relative paths write to `vendor/composer`; absolute paths are used as-is. |
| `cwd`       | project root               | Base directory for `patterns`.                                            |
| `maxDepth`  | `25`                       | Maximum directory depth.                                                  |

### `#[AutoRun]` Arguments

| Argument   | Default        | Notes                                                                     |
|------------|----------------|---------------------------------------------------------------------------|
| `priority` | `10`           | Lower numbers run earlier.                                                |
| `import`   | `require_once` | How to load the PHP file that defines an attributed function. See below.  |
| `check`    | `false`        | Whether to guard function-file imports with `function_exists`. See below. |

Supported `Import` type values:

```text
none 
require_once
include_once
require
include
```

### Import argument

`import` controls how the generated bootstrap loads PHP files.

This matters because PHP functions are not class-autoloaded. If an attributed function lives in `src/functions.php`, the generated bootstrap must load that file before it can call the function.

```php
<?php

use Render\Autoloader\Attributes\AutoRun;

#[AutoRun(import: 'include')]
function bootstrap(): void
{
}
```

Generated output includes the file import before the function call:

```php
include '/path/to/project/src/functions.php';

\bootstrap();
```

Static methods are different. Composer can autoload the class, so static method entries usually do not need a file import:

```php
<?php

use Render\Autoloader\Attributes\AutoRun;

class Plugin
{
    #[AutoRun]
    public static function boot(): void
    {
    }
}
```

Use `none` when the function or file is already loaded somewhere else.

Directory autoloading also uses `import`; there it controls how each matched file is included.

### Check argument

`check` is only useful for attributed functions.

When `check: true`, the generated bootstrap wraps the file import in `function_exists` checks. If the function already exists, the file is not imported again.

```php
<?php

use Render\Autoloader\Attributes\AutoRun;

#[AutoRun(check: true)]
function bootstrap(): void
{
}

#[AutoRun(check: true)]
function register_helpers(): void
{
}
```

Generated output:

```php
if (array_filter(['bootstrap', 'register_helpers'], fn($f) => !function_exists($f))) {
    require_once '/path/to/project/src/functions.php';
}

\bootstrap();
\register_helpers();
```

Use this when a function file might already be loaded before Composer's generated bootstrap runs. It helps avoid fatal "Cannot redeclare function" errors.

### Custom Attribute

`AutoRun` is only the default. You can scan for a different attribute name:

```json
{
	"extra": {
		"autoload-by-attr": {
			"attribute": "Bootstrap",
			"patterns": [
				"src"
			]
		}
	}
}
```

```php
<?php

namespace App;

#[Bootstrap(priority: 10)]
function bootstrap(): void
{
}
```

The configured attribute can also set default argument values:

```json
{
	"extra": {
		"autoload-by-attr": {
			"attribute": "Bootstrap(priority: 50, import: 'require_once', check: true)"
		}
	}
}
```

### Runtime

`AutoRunPlugin::runtime()` is a runtime version of the Plugin. It operates outside the context of Composer. It scans source code only. It does not load scanned files during discovery, so runtime attribute usage must be simple and statically readable.

Runtime-only rules:

- Do not use attribute aliases such as `use AutoRun as Run; #[Run]`.
- Use literal attribute arguments only: numbers, strings, booleans, or `null`.
- Do not use constants, class constants, enum values, expressions, or function calls in runtime attribute arguments.

Avoid in runtime mode:

```php
use Render\Autoloader\Attributes\AutoRun as Run;

#[Run]
function bootstrap(): void
{
}

class Plugin
{
    #[AutoRun(priority: self::BOOT_PRIORITY)]
    public static function boot(): void
    {
    }
}
```

Use Composer/plugin generation when you need PHP reflection to resolve aliases, constants, or more complex attribute arguments.

---

## Directory Autoloading

Directory autoloading scans files by pattern and generates a bootstrap file that imports each matched PHP file.

```json
{
	"extra": {
		"autoload-by-dir": {
			"patterns": [
				"src",
				"!vendor"
			],
			"import": "require_once"
		}
	}
}
```

```php
<?php

// src/functions.php

function app_helper(): string
{
    return 'ready';
}
```

Default output:

```text
vendor/composer/autoload_directory_files.php
```

## Pattern Examples

These examples use Git-style include/exclude patterns through `pfaciana/includefile`.

```json
{
	"extra": {
		"autoload-by-dir": {
			"patterns": [
				"src",
				"inc/*.php",
				"lib",
				"!src/Excluded"
			]
		}
	}
}
```

String patterns can also be newline-separated:

```json
{
	"extra": {
		"autoload-by-dir": {
			"patterns": "src\ninc\nlib"
		}
	}
}
```

### Inputs

| Key        | Default                          | Notes                                                                     |
|------------|----------------------------------|---------------------------------------------------------------------------|
| `patterns` | `["*"]`                          | Files/directories to import from `cwd`.                                   |
| `import`   | `require_once`                   | Import strategy for each matched file.                                    |
| `output`   | `"autoload_directory_files.php"` | Relative paths write to `vendor/composer`; absolute paths are used as-is. |
| `cwd`      | project root                     | Base directory for `patterns`.                                            |
| `maxDepth` | `25`                             | Maximum directory depth.                                                  |

### Runtime

`AutoloadPlugin::runtime()` is a runtime version of the Plugin. It operates outside the context of Composer.

---

## Performance

`cwd` is the scan root. The plugin starts file discovery from `cwd`, then applies `patterns`.

This matters for performance. If `cwd` is the project root, the plugin must walk files and directories from the project root before patterns can include or exclude them.

Negated patterns like `!vendor` and `!node_modules` help, but they still require the scanner to start at the root and decide what to skip.

If you only need files under one directory, set `cwd` to that directory.

Less efficient:

```json
{
	"extra": {
		"autoload-by-attr": {
			"cwd": ".",
			"patterns": [
				"src",
				"!vendor",
				"!node_modules",
				"!tests"
			]
		}
	}
}
```

More efficient:

```json
{
	"extra": {
		"autoload-by-attr": {
			"cwd": "src",
			"patterns": [
				"*"
			]
		}
	}
}
```

Use project root as `cwd` only when you need to scan across multiple root-level directories.

---

## Notes

- Generated files live in `vendor/composer` by default. Do not edit them by hand.
- Attribute mode sorts calls by `priority`, lowest first.
- Function entries need their source file imported before the function can be called.
- Static method entries rely on Composer class autoloading.
- Files with only declarations are safely loaded for reflection. Files with top-level executable code use token fallback instead.
- Token fallback avoids running app code during `composer dump-autoload`, but it cannot verify every runtime detail the same way reflection can.
- Multiple separate attribute groups are supported:

```php
#[AutoRun(priority: 10)]
#[Other]
function bootstrap(): void
{
}
```

- Multiple attributes in the same group are not currently supported by the token scanner:

```php
#[Other, AutoRun(priority: 10)]
function bootstrap(): void
{
}
```

Use separate attribute groups when combining `AutoRun` with other attributes.
