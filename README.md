# Search Replace File Command

Performs literal, serialization-aware search and replace on WordPress SQL dump files.

Unlike `wp search-replace`, this command operates on an existing SQL dump rather than a live database. It reads one file, applies the replacement, repairs the byte-length declarations of affected serialized PHP strings, and writes the result to a separate output file.

> **Project status**
>
> This package is under development and is being prepared for possible contribution to the WP-CLI project. It is not currently included with WP-CLI and is not an official WP-CLI package.

## Installation

Install the development version directly from GitHub:

```bash
wp package install https://github.com/AlextheYounga/search-replace-file-command.git
```

Confirm that the command is available:

```bash
wp help search-replace-file
```

## Usage

```bash
wp search-replace-file <old> <new> <input-file> <output-file> [--force]
```

The command performs a literal, case-sensitive replacement throughout the dump.

It also recognizes serialized PHP strings in supported SQL dump syntax and recalculates their byte-length declarations when a replacement changes the length of their contents.

### Arguments

#### `<old>`

The non-empty string to search for.

#### `<new>`

The replacement string. This may be empty.

#### `<input-file>`

The readable SQL dump to process.

#### `<output-file>`

The path where the transformed dump will be written. The input and output paths must be different.

### Options

#### `--force`

Overwrite an existing output file. Without this flag, the command refuses to replace an existing file.

## Examples

Replace a domain throughout a WordPress dump:

```bash
wp search-replace-file \
    'example.com' \
    'example.test' \
    dump.sql \
    dump-updated.sql
```

Replace a complete URL:

```bash
wp search-replace-file \
    'http://example.com' \
    'https://example.com' \
    dump.sql \
    dump-https.sql
```

Remove a string by replacing it with an empty value:

```bash
wp search-replace-file \
    'legacy-prefix' \
    '' \
    dump.sql \
    dump-clean.sql
```

Paths and values containing spaces should be quoted:

```bash
wp search-replace-file \
    'Old Site Name' \
    'New Site Name' \
    'backups/old site.sql' \
    'backups/new site.sql'
```

## Why use this command?

A normal text replacement can corrupt PHP serialized values.

For example, changing a string to a value with a different byte length requires the corresponding `s:<length>:` declaration to be updated. This command performs the replacement while repairing those declarations in supported WordPress SQL dump data.

The command operates directly on files and does not require a live WordPress database.

## Safety

The input dump is not modified, including when the output path is a relative alias, symbolic link, or hard link to the input. A separate output path is required.

The command writes to a temporary file in the destination directory and only replaces the requested output after processing completes successfully. Existing output requires `--force`; if parsing or writing fails, the previous output is preserved.

Malformed serialized data causes the command to stop with the input line number instead of silently skipping replacements.

Always inspect or test the resulting dump before importing it into a production database.

## Compatibility and limitations

This command is intended for WordPress SQL dumps using supported MySQL-style escaping and PHP serialization.

Current limitations include:

* Literal, case-sensitive replacement only.
* One replacement pair per invocation.
* No regular-expression mode.
* No in-place modification.
* No STDIN or STDOUT streaming interface.
* No direct modification of a live database.

For live WordPress databases, use the standard [`wp search-replace`](https://developer.wordpress.org/cli/commands/search-replace/) command instead.

## Development

Clone the repository and install its Composer dependencies:

```bash
git clone https://github.com/AlextheYounga/search-replace-file-command.git
cd search-replace-file-command
composer install
```

Run the complete test suite:

```bash
composer test
```

The test suite includes unit tests for the serialization-aware transformation engine and Behat tests for the WP-CLI command interface.

## Attribution

The serialization-aware transformation engine was adapted from [`AlextheYounga/php-search-replace`](https://github.com/AlextheYounga/php-search-replace), a PHP port based on Automattic's Go search-and-replace implementation.

## Contributing

Bug reports and pull requests are welcome.

Before reporting a bug, search the repository's existing issues. A useful report should include:

* The command that was run.
* The relevant WP-CLI and PHP versions.
* A minimal input fixture.
* The expected output.
* The actual output or error.
* Whether the problem involves serialized data.

Please do not include private production data, credentials, customer information, or a complete database dump in an issue.

## License

This project is licensed under the MIT License. See [LICENSE](LICENSE) for details.
