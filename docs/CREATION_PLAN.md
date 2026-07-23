# File Search Replace Command Creation Plan

## Goal

Create a WP-CLI command that performs serialization-aware string replacement against WordPress SQL dump files.

The command will adapt the existing PHP port documented in `docs/references/php-search-replace.md` into this package's WP-CLI command scaffolding.

## Current Scaffold

- `file-search-replace-command.php` registers `wp file-search-replace`.
- `src/File_Search_Replace_Command.php` — command with implemented `__invoke()`.
- `src/PhpSearchReplaceHandler.php` — PHP 7.2-safe port of the serialization-aware replacement engine.
- `src/SerializedReplaceResult.php` — value object for serialized-string fix segments.
- `tests/PhpSearchReplaceHandlerTest.php` — PHPUnit test covering full fixture matrix (50 assertions).
- `tests/Fixtures/serialized/` — 12 pairs of `.input.sql` / `.expected.sql` files.
- `features/file-search-replace.feature` — Behat acceptance tests for CLI behavior.
- `composer.json` defines autoloading, bundled command, and test scripts.
- `wp-cli.yml` loads the package locally.

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

## First Command Interface

```bash
wp file-search-replace <old> <new> <input-file> <output-file>
```

All four arguments are positional and required:

- `<old>` — search string (must be non-empty).
- `<new>` — replacement string (may be empty).
- `<input-file>` — path to input SQL dump file (must exist and be readable).
- `<output-file>` — path to write the modified result (must differ from input; no `--in-place` in v1).

Examples:

```bash
wp file-search-replace example.com example.test dump.sql dump-updated.sql
wp file-search-replace http://example.com https://example.com input.sql output.sql
```

## Deferred Features

These are deferred to future iterations:

- [--in-place]
- [--format=<format>]
- Regex replacement.
- Dry runs.
- Replacement count reporting.
- Diff logging.
- Multiple replacement pairs from CLI input.
- Reading from STDIN and writing to STDOUT.
- Database table support. This command should remain file-oriented.

## Implementation Steps

### Completed

1. Ported reference handler to `src/PhpSearchReplaceHandler.php` (PHP 7.2-safe, under original `PhpSearchReplace` namespace).
2. Added `src/SerializedReplaceResult.php` without PHP 8-only syntax.
3. Added PHPUnit tests at `tests/PhpSearchReplaceHandlerTest.php` with all 12 serialized fixture pairs.
4. Added minimum Behat scaffolding at `features/file-search-replace.feature`.
5. Wired up `phpunit.xml.dist`, updated `phpstan.neon.dist`, configured phpcs ignores.
6. Full `composer test` passes end-to-end.

### Remaining

1. Expand Behat acceptance tests for CLI behavior (basic replacement, missing file, empty old, input=output error).
2. Run `composer test` and fix any lint, phpcs, phpstan, phpunit, or behat issues.

## Initial Behat Coverage

Acceptance tests should cover:

- Basic plain SQL file replacement.
- Serialized string replacement and length recalculation.
- Missing input file error.
- Empty search value error.
- Refusing to overwrite the input file (no `--in-place` in v1).
- Help output via `wp help file-search-replace`.

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

## Decisions (Locked)

1. **`<output-file>` required?** Yes. Always require an explicit output path.
2. **`--in-place` in v1?** No. Deferred to a future iteration. Input/output paths must differ.
3. **`--old`/`--new` flags?** No. Follow the `wp search-replace 'old' 'new' [tables]` positional syntax.
4. **Replacement counts?** No. v1 reports success/failure only. Count reporting is deferred.
5. **Fixture set size?** All of them. The full 12-pair fixture set from the reference project is already copied into `tests/Fixtures/serialized/`.

## First Slice

```bash
wp file-search-replace <old> <new> <input-file> <output-file>
```

No flags beyond the 4 positional args. No `--in-place`, no counts, no streaming, no regex.
