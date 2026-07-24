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
		$search      = $args[0];
		$replacement = $args[1];
		$input_file  = $args[2];
		$output_file = $args[3];
		$force       = isset( $assoc_args['force'] );
		$handler     = new PhpSearchReplaceHandler();

		if ( '' === $search ) {
			\WP_CLI::error( '<old> must not be empty.' );
		}

		$this->validate_input_file( $input_file );
		$this->validate_output_file( $handler, $input_file, $output_file, $force );

		try {
			$handler->replace_in_file(
				$input_file,
				$output_file,
				array(
					array(
						'from' => $search,
						'to'   => $replacement,
					),
				)
			);
			\WP_CLI::success( sprintf( "Replaced '%s' with '%s'.", $search, $replacement ) );
		} catch ( RuntimeException $e ) {
			\WP_CLI::error( $e->getMessage() );
		}
	}

	private function validate_input_file( string $input_file ): void {
		if ( ! is_file( $input_file ) || ! is_readable( $input_file ) ) {
			\WP_CLI::error( sprintf( "Input file '%s' does not exist, is not a file, or is not readable.", $input_file ) );
		}
	}

	private function validate_output_file( PhpSearchReplaceHandler $handler, string $input_file, string $output_file, bool $force ): void {
		if ( $handler->paths_refer_to_same_file( $input_file, $output_file ) ) {
			\WP_CLI::error( 'The input and output files must be different.' );
		}

		if ( is_dir( $output_file ) ) {
			\WP_CLI::error( sprintf( "Output path '%s' is a directory.", $output_file ) );
		}

		$output_directory = dirname( $output_file );
		if ( ! is_dir( $output_directory ) ) {
			\WP_CLI::error( sprintf( "Output directory '%s' does not exist.", $output_directory ) );
		}

		$output_exists = file_exists( $output_file ) || is_link( $output_file );
		if ( $output_exists && ! $force ) {
			\WP_CLI::error( sprintf( "Output file '%s' already exists. Use --force to overwrite it.", $output_file ) );
		}

		if ( ! $this->is_writable_destination( $output_file, $output_directory, $output_exists ) ) {
			\WP_CLI::error( sprintf( "Output file '%s' is not writable.", $output_file ) );
		}
	}

	private function is_writable_destination( string $output_file, string $output_directory, bool $output_exists ): bool {
		if ( $output_exists ) {
			return is_writable( $output_file );
		}

		return is_writable( $output_directory );
	}
}
