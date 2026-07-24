<?php

namespace WP_CLI;

use RuntimeException;
use WP_CLI\Search_Replace_File\PhpSearchReplaceHandler;

/**
 * Performs serialization-aware search and replace on SQL dump files.
 */
class Search_Replace_File_Command extends \WP_CLI_Command {

	/**
	 * Performs serialization-aware search and replace on SQL dump files.
	 *
	 * Processes WordPress SQL dump files, correctly handling serialized PHP
	 * strings by recalculating their byte-length declarations after replacement.
	 * The input and output files must be different.
	 *
	 * ## OPTIONS
	 *
	 * <old>
	 * : A string to search for within the SQL file.
	 *
	 * <new>
	 * : Replace instances of the old string with this new string.
	 *
	 * <input-file>
	 * : Path to the input SQL dump file.
	 *
	 * <output-file>
	 * : Path to write the modified SQL dump file.
	 *
	 * [--force]
	 * : Overwrite an existing output file.
	 *
	 * ## EXAMPLES
	 *
	 *     # Replace a domain name in a SQL dump.
	 *     $ wp search-replace-file example.com example.test dump.sql dump-updated.sql
	 *
	 *     # Replace a URL including protocol.
	 *     $ wp search-replace-file http://example.com https://example.com input.sql output.sql
	 *
	 *     # Remove a string from a SQL dump (empty replacement).
	 *     $ wp search-replace-file 'legacy-prefix' '' dump.sql dump-clean.sql
	 *
	 * @param array<string> $args Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 *
	 * @return void
	 */
	public function __invoke( $args, $assoc_args ) {
		$old         = $args[0];
		$new         = $args[1];
		$input_file  = $args[2];
		$output_file = $args[3];
		$force       = isset( $assoc_args['force'] );
		$handler     = new PhpSearchReplaceHandler();

		if ( '' === $old ) {
			\WP_CLI::error( '<old> must not be empty.' );
		}

		if ( ! is_file( $input_file ) || ! is_readable( $input_file ) ) {
			\WP_CLI::error( sprintf( "Input file '%s' does not exist, is not a file, or is not readable.", $input_file ) );
		}

		if ( $handler->paths_refer_to_same_file( $input_file, $output_file ) ) {
			\WP_CLI::error( 'The input and output files must be different.' );
		}

		if ( is_dir( $output_file ) ) {
			\WP_CLI::error( sprintf( "Output path '%s' is a directory.", $output_file ) );
		}

		$output_dir = dirname( $output_file );
		if ( '' !== $output_dir && ! is_dir( $output_dir ) ) {
			\WP_CLI::error( sprintf( "Output directory '%s' does not exist.", $output_dir ) );
		}

		$output_exists = file_exists( $output_file ) || is_link( $output_file );
		if ( $output_exists && ! $force ) {
			\WP_CLI::error( sprintf( "Output file '%s' already exists. Use --force to overwrite it.", $output_file ) );
		}

		$output_dir_writable = true;
		if ( $output_exists ) {
			if ( ! is_writable( $output_file ) ) {
				$output_dir_writable = false;
			}
		} elseif ( '' !== $output_dir && ! is_writable( $output_dir ) ) {
			$output_dir_writable = false;
		}
		if ( ! $output_dir_writable ) {
			\WP_CLI::error( sprintf( "Output file '%s' is not writable.", $output_file ) );
		}

		try {
			$handler->replace_in_file(
				$input_file,
				$output_file,
				array(
					array(
						'from' => $old,
						'to'   => $new,
					),
				)
			);
			\WP_CLI::success( sprintf( "Replaced '%s' with '%s'.", $old, $new ) );
		} catch ( RuntimeException $e ) {
			\WP_CLI::error( $e->getMessage() );
		}
	}
}
