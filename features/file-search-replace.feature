Feature: Search and replace strings in SQL dump files

	Scenario: Replacing plain text in a SQL file
		Given an empty directory
		And a dump.sql file:
		  """
		  INSERT INTO wp_options VALUES (1, 'siteurl', 'http://example.com');
		  INSERT INTO wp_options VALUES (2, 'home', 'http://example.com');
		  """
		When I run `wp file-search-replace http://example.com https://example.test dump.sql updated.sql`
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
		When I run `wp file-search-replace hello goodbye dump.sql updated.sql`
		Then the updated.sql file should be:
		  """
		  INSERT INTO wp_options VALUES (1, 'widget_text', 's:13:\"goodbye-world\";');
		  """

	Scenario: Error when input file is missing
		Given an empty directory
		When I try `wp file-search-replace old new missing.sql updated.sql`
		Then STDERR should contain:
		  """
		  Error: Input file 'missing.sql' does not exist or is not readable.
		  """
		And the return code should be 1

	Scenario: Error when search value is empty
		Given an empty directory
		And a dump.sql file:
		  """
		  plain text
		  """
		When I try `wp file-search-replace '' new dump.sql updated.sql`
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
		When I try `wp file-search-replace old new dump.sql dump.sql`
		Then STDERR should contain:
		  """
		  Error: The input and output files must be different.
		  """
		And the return code should be 1

	Scenario: Error when writing the output file fails
		Given an empty directory
		And a dump.sql file:
		  """
		  old text
		  """
		When I try `wp file-search-replace old new dump.sql /dev/full`
		Then STDERR should contain:
		  """
		  Error: Unable to write to "/dev/full".
		  """
		And STDOUT should not contain:
		  """
		  Success:
		  """
		And the return code should be 1

	Scenario: Help output documents the command arguments
		When I run `wp help file-search-replace`
		Then STDOUT should contain:
		  """
		  wp file-search-replace <old> <new> <input-file> <output-file>
		  """
