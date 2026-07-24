Feature: Search and replace strings in SQL dump files

	Scenario: Replacing plain text in a SQL file
		Given an empty directory
		And a dump.sql file:
		  """
		  INSERT INTO wp_options VALUES (1, 'siteurl', 'http://example.com');
		  INSERT INTO wp_options VALUES (2, 'home', 'http://example.com');
		  """
		When I run `wp search-replace-file http://example.com https://example.test dump.sql updated.sql`
		Then STDOUT should contain:
		  """
		  Success: Replaced 'http://example.com' with 'https://example.test'.
		  """
		And the updated.sql file should be:
		  """
		  INSERT INTO wp_options VALUES (1, 'siteurl', 'https://example.test');
		  INSERT INTO wp_options VALUES (2, 'home', 'https://example.test');
		  """

	Scenario: Recalculating serialized string lengths
		Given an empty directory
		And a dump.sql file:
		  """
		  INSERT INTO wp_options VALUES (1, 'widget_text', 's:11:\"hello-world\";');
		  """
		When I run `wp search-replace-file hello goodbye dump.sql updated.sql`
		Then the updated.sql file should be:
		  """
		  INSERT INTO wp_options VALUES (1, 'widget_text', 's:13:\"goodbye-world\";');
		  """

	Scenario: Error when input file is missing
		Given an empty directory
		When I try `wp search-replace-file old new missing.sql updated.sql`
		Then STDERR should contain:
		  """
		  Error: Input file 'missing.sql' does not exist, is not a file, or is not readable.
		  """
		And the return code should be 1

	Scenario: Error when search value is empty
		Given an empty directory
		And a dump.sql file:
		  """
		  plain text
		  """
		When I try `wp search-replace-file '' new dump.sql updated.sql`
		Then STDERR should contain:
		  """
		  Error: <old> must not be empty.
		  """
		And the return code should be 1

	Scenario: Error when output file matches input file
		Given an empty directory
		And a dump.sql file:
		  """
		  old text
		  """
		When I try `wp search-replace-file old new dump.sql dump.sql`
		Then STDERR should contain:
		  """
		  Error: The input and output files must be different.
		  """
		And the return code should be 1

	Scenario: Error when output file is a relative-path alias of the input
		Given an empty directory
		And a dump.sql file:
		  """
		  old text
		  """
		When I try `wp search-replace-file old new dump.sql ./dump.sql`
		Then STDERR should contain:
		  """
		  Error: The input and output files must be different.
		  """
		And the dump.sql file should be:
		  """
		  old text
		  """
		And the return code should be 1

	Scenario: Error when output file is a symlink to the input
		Given an empty directory
		And a dump.sql file:
		  """
		  old text
		  """
		When I run `ln -s dump.sql alias.sql`
		And I try `wp search-replace-file old new dump.sql alias.sql`
		Then STDERR should contain:
		  """
		  Error: The input and output files must be different.
		  """
		And the dump.sql file should be:
		  """
		  old text
		  """
		And the return code should be 1

	Scenario: Error when output file is a hard link to the input
		Given an empty directory
		And a dump.sql file:
		  """
		  old text
		  """
		When I run `ln dump.sql alias.sql`
		And I try `wp search-replace-file old new dump.sql alias.sql`
		Then STDERR should contain:
		  """
		  Error: The input and output files must be different.
		  """
		And the dump.sql file should be:
		  """
		  old text
		  """
		And the return code should be 1

	Scenario: Existing output requires force
		Given an empty directory
		And a dump.sql file:
		  """
		  old text
		  """
		And an updated.sql file:
		  """
		  existing output
		  """
		When I try `wp search-replace-file old new dump.sql updated.sql`
		Then STDERR should contain:
		  """
		  Error: Output file 'updated.sql' already exists. Use --force to overwrite it.
		  """
		And the updated.sql file should be:
		  """
		  existing output
		  """
		And the return code should be 1

	Scenario: Force atomically replaces existing output
		Given an empty directory
		And a dump.sql file:
		  """
		  old text
		  """
		And an updated.sql file:
		  """
		  existing output
		  """
		When I run `wp search-replace-file old new dump.sql updated.sql --force`
		Then the updated.sql file should be:
		  """
		  new text
		  """

	Scenario: Malformed serialized data aborts without creating output
		Given an empty directory
		And a dump.sql file:
		  """
		  s:4:\"too-long\"; old text
		  """
		When I try `wp search-replace-file old new dump.sql updated.sql`
		Then STDERR should contain:
		  """
		  Error: Unable to safely process serialized data in "dump.sql" at line 1.
		  """
		And the updated.sql file should not exist
		And the return code should be 1

	Scenario: Malformed serialized data preserves existing forced output
		Given an empty directory
		And a dump.sql file:
		  """
		  s:4:\"too-long\"; old text
		  """
		And an updated.sql file:
		  """
		  existing output
		  """
		When I try `wp search-replace-file old new dump.sql updated.sql --force`
		Then STDERR should contain:
		  """
		  Error: Unable to safely process serialized data in "dump.sql" at line 1.
		  """
		And the updated.sql file should be:
		  """
		  existing output
		  """
		And the return code should be 1

	Scenario: Error when input path is a directory
		Given an empty directory
		And an empty dump.sql directory
		When I try `wp search-replace-file old new dump.sql updated.sql`
		Then STDERR should contain:
		  """
		  Error: Input file 'dump.sql' does not exist, is not a file, or is not readable.
		  """
		And the return code should be 1

	Scenario: Error when output path is a directory
		Given an empty directory
		And a dump.sql file:
		  """
		  old text
		  """
		And an empty updated.sql directory
		When I try `wp search-replace-file old new dump.sql updated.sql`
		Then STDERR should contain:
		  """
		  Error: Output path 'updated.sql' is a directory.
		  """
		And the return code should be 1

	Scenario: Help output documents the command arguments
		When I run `wp help search-replace-file`
		Then STDOUT should contain:
		  """
		  wp search-replace-file <old> <new> <input-file> <output-file>
		  """
		And STDOUT should contain:
		  """
		  [--force]
		  """
