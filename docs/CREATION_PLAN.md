# File Search Replace Command Creation Plan

## Goal

Create a WP-CLI command that performs serialization-aware string replacement against WordPress SQL dump files.

The command will adapt the existing PHP port documented in `docs/references/php-search-replace.md` into this package's WP-CLI command scaffolding.

## Current Scaffold

- `file-search-replace-command.php` registers `wp file-search-replace`.
- `src/File_Search_Replace_Command.php` contains an empty `__invoke()` method.
- `composer.json` already defines this as a bundled WP-CLI package and autoloads `src/`.
- `wp-cli.yml` loads the package locally.
- There are currently no `features/` or `tests/` directories.

## Core Logic To Adapt

The reference implementation exposes this reusable API:

```php
$handler->replaceInFile(
	$input_path,
	$output_path,
	array(
		array(
			'from' => 'http://example.com',
			'to'   => 'https://example.com',
		),
	)
);
```

Behavior to preserve:

- Process SQL dump files without requiring a live database.
- Replace plain text occurrences.
- Detect serialized string fragments in SQL dump text.
- Replace inside serialized string contents.
- Recalculate serialized string byte lengths after replacement.
- Preserve escaped SQL dump formatting.
- Support multiple replacements internally, even if the first command version only exposes one pair.

## Compatibility Work

This package's PHPCS config targets PHP `7.2-`, while the reference code uses newer PHP syntax.

Required adaptations:

- Avoid constructor property promotion.
- Avoid typed properties.
- Avoid union types.
- Avoid PHPUnit attributes in tests.
- Use WP-CLI coding standards and array syntax conventions.
- Keep PHPDoc type annotations for PHPStan where useful.

## Proposed File Structure

```text
src/
  File_Search_Replace_Command.php
  File_Search_Replace_Handler.php
  Serialized_Replace_Result.php

features/
  file-search-replace.feature

tests/
  File_Search_Replace_Handler_Test.php
  Fixtures/
    serialized/
      ...
```

`Serialized_Replace_Result.php` could be folded into the handler file, but a separate class keeps autoloading and static analysis simpler.

## Proposed First Command Interface

Recommended initial interface:

```bash
wp file-search-replace <old> <new> <input-file> <output-file>
```

Examples:

```bash
wp file-search-replace example.com example.test dump.sql dump-updated.sql
wp file-search-replace http://example.com https://example.com input.sql output.sql
```

Validation:

- `<old>` must be provided and non-empty.
- `<new>` must be provided, but may be an empty string if supplied through `--new=''`.
- `<input-file>` must exist and be readable.
- `<output-file>` must be writable or creatable.
- Input and output paths should not be the same unless an explicit `--in-place` option is added.

## Optional Interface Additions

Potential options for the first or second iteration:

```text
[--old=<value>]
[--new=<value>]
[--in-place]
[--format=<format>]
```

`--old` and `--new` are useful when values start with `--`, matching the existing `wp search-replace` pattern.

`--in-place` would intentionally overwrite the input file. This should be opt-in because SQL dump changes are potentially destructive.

`--format=count` would require the handler to count replacements. The current reference implementation does not report replacement counts.

## Features To Defer

These are useful but should not be part of the first minimal implementation unless explicitly required:

- Regex replacement.
- Dry runs.
- Replacement count reporting.
- Diff logging.
- Multiple replacement pairs from CLI input.
- Reading from STDIN and writing to STDOUT.
- Database table support. This command should remain file-oriented.

## Implementation Steps

1. Port the reference handler into `src/File_Search_Replace_Handler.php` under the `WP_CLI` namespace.
2. Add `src/Serialized_Replace_Result.php` without PHP 8-only syntax.
3. Implement `File_Search_Replace_Command::__invoke()`.
4. Add command PHPDoc with `OPTIONS` and `EXAMPLES` for WP-CLI help generation.
5. Add argument validation and user-friendly `WP_CLI::error()` messages.
6. Call the handler with one replacement pair.
7. Emit a concise `WP_CLI::success()` message after writing the output file.
8. Add Behat acceptance tests for CLI behavior.
9. Add PHPUnit tests for the handler using selected serialized fixtures from the reference project.
10. Update `README.md` or ensure the command docs can regenerate it later.
11. Run `composer test` and fix lint, coding standards, PHPStan, PHPUnit, and Behat issues.

## Initial Behat Coverage

Acceptance tests should cover:

- Basic plain SQL file replacement.
- Serialized string replacement and length recalculation.
- Missing input file error.
- Empty search value error.
- Refusing to overwrite the input file without `--in-place`, if `--in-place` is implemented.
- `--old` and `--new` handling for values beginning with hyphens, if those flags are implemented.

## Initial PHPUnit Coverage

Handler tests should cover:

- Plain non-serialized replacement.
- Serialized replacement with same-length value.
- Serialized replacement with different-length value.
- Multiple occurrences on one line.
- Escaped serialized delimiters.
- Empty replacements returning original input.
- Invalid replacement array shape raising an exception.
- `replaceInFile()` processing an entire file.

## Open Decisions

1. Should the first version require `<output-file>`, or should it default to STDOUT?
2. Should `--in-place` be included in the first version?
3. Should `--old` and `--new` flags be included immediately for parity with `wp search-replace` argument edge cases?
4. Should the first version expose replacement counts, or only report success/failure?
5. How many reference fixtures should be copied into this package versus keeping a smaller focused fixture set?

## Recommended First Slice

Build the smallest useful command first:

```bash
wp file-search-replace <old> <new> <input-file> <output-file>
```

Include `--old` and `--new` only if we want to handle hyphen-prefixed strings from the start.

Skip `--in-place`, replacement counts, STDIN/STDOUT streaming, regex, and logging until the core command and handler tests are passing.
