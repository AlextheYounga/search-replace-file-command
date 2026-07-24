wp-cli/search-replace-file-command
====================================

Performs serialization-aware search and replace on SQL dump files.

Quick links: [Using](#using) | [Installing](#installing) | [Contributing](#contributing) | [Support](#support)

## Using

~~~
wp search-replace-file <old> <new> <input-file> <output-file>
~~~

Searches through a SQL dump file and replaces appearances of the first string with the second string.

The command correctly handles serialized PHP strings by recalculating their byte-length declarations after replacement. The input and output files must be different.

**OPTIONS**

	<old>
		A string to search for within the SQL file.

	<new>
		Replace instances of the old string with this new string.

	<input-file>
		Path to the input SQL dump file.

	<output-file>
		Path to write the modified SQL dump file.

**EXAMPLES**

    # Replace a domain name in a SQL dump.
    $ wp search-replace-file example.com example.test dump.sql dump-updated.sql
    Success: Replaced 'example.com' with 'example.test'.

    # Replace a URL including protocol.
    $ wp search-replace-file http://example.com https://example.com input.sql output.sql

    # Remove a string from a SQL dump (empty replacement).
    $ wp search-replace-file 'legacy-prefix' '' dump.sql dump-clean.sql

## Installing

This package is included with WP-CLI itself, no additional installation necessary.

To install the latest version of this package over what's included in WP-CLI, run:

    wp package install git@github.com:wp-cli/search-replace-file-command.git

## Contributing

We appreciate you taking the initiative to contribute to this project.

Contributing isn't limited to just code. We encourage you to contribute in the way that best fits your abilities, by writing tutorials, giving a demo at your local meetup, helping other users with their support questions, or revising our documentation.

For a more thorough introduction, [check out WP-CLI's guide to contributing](https://make.wordpress.org/cli/handbook/contributing/). This package follows those policy and guidelines.

### Reporting a bug

Think you've found a bug? We'd love for you to help us get it fixed.

Before you create a new issue, you should [search existing issues](https://github.com/wp-cli/search-replace-file-command/issues?q=label%3Abug%20) to see if there's an existing resolution to it, or if it's already been fixed in a newer version.

Once you've done a bit of searching and discovered there isn't an open or fixed issue for your bug, please [create a new issue](https://github.com/wp-cli/search-replace-file-command/issues/new). Include as much detail as you can, and clear steps to reproduce if possible. For more guidance, [review our bug report documentation](https://make.wordpress.org/cli/handbook/bug-reports/).

### Creating a pull request

Want to contribute a new feature? Please first [open a new issue](https://github.com/wp-cli/search-replace-file-command/issues/new) to discuss whether the feature is a good fit for the project.

Once you've decided to commit the time to seeing your pull request through, [please follow our guidelines for creating a pull request](https://make.wordpress.org/cli/handbook/pull-requests/) to make sure it's a pleasant experience. See "[Setting up](https://make.wordpress.org/cli/handbook/pull-requests/#setting-up)" for details specific to working on this package locally.

### License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.

## Support

GitHub issues aren't for general support questions. For support resources and next steps, see the WP-CLI Support page: https://make.wordpress.org/cli/handbook/support/
