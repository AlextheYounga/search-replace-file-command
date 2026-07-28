<?php

declare( strict_types=1 );

namespace WP_CLI\Search_Replace_File;

use RuntimeException;

/**
 * Reads an SQL file, delegates line processing, and commits output safely.
 */
final class SqlFileReplacer {

	/** @var SqlLineReplacer */
	private $line_replacer;

	public function __construct( ?SqlLineReplacer $line_replacer = null ) {
		$this->line_replacer = $line_replacer ?: new SqlLineReplacer();
	}

	/**
	 * @param array<int, array{from:string,to:string}> $replacements
	 */
	public function replace_file( string $input_path, string $output_path, array $replacements ): void {
		if ( $this->paths_refer_to_same_file( $input_path, $output_path ) ) {
			throw new RuntimeException( 'The input and output files must be different.' );
		}

		$input          = null;
		$output         = null;
		$existing_mode  = file_exists( $output_path ) ? ( fileperms( $output_path ) & 0777 ) : null;
		$temporary_path = $this->create_temporary_path( $output_path );

		try {
			$input  = $this->open_input( $input_path );
			$output = $this->open_output( $temporary_path, $output_path );
			$this->copy_and_replace( $input, $output, $input_path, $output_path, $replacements );
			$this->commit( $output, $temporary_path, $output_path, $existing_mode );
			$temporary_path = null;
		} finally {
			$this->close( $input );
			$this->close( $output );
			$this->remove_temporary_file( $temporary_path );
		}
	}

	public function paths_refer_to_same_file( string $input_path, string $output_path ): bool {
		$input_real_path  = realpath( $input_path );
		$output_real_path = realpath( $output_path );

		if ( false === $input_real_path ) {
			return false;
		}

		if ( false !== $output_real_path ) {
			return $input_real_path === $output_real_path || $this->same_inode( $input_path, $output_path );
		}

		$output_directory = realpath( dirname( $output_path ) );
		return false !== $output_directory
			&& $input_real_path === $output_directory . DIRECTORY_SEPARATOR . basename( $output_path );
	}

	/**
	 * @return string
	 */
	private function create_temporary_path( string $output_path ): string {
		$output_directory = realpath( dirname( $output_path ) );
		if ( false === $output_directory ) {
			throw new RuntimeException( sprintf( 'Unable to access the output directory for "%s".', $output_path ) );
		}

		$temporary_path = tempnam( $output_directory, '.search-replace-file-' );
		if ( false === $temporary_path ) {
			throw new RuntimeException( sprintf( 'Unable to create a temporary file for "%s".', $output_path ) );
		}

		return $temporary_path;
	}

	/**
	 * @return resource
	 */
	private function open_input( string $input_path ) {
		$input = @fopen( $input_path, 'rb' );
		if ( false === $input ) {
			throw new RuntimeException( sprintf( 'Unable to open "%s" for reading.', $input_path ) );
		}

		return $input;
	}

	/**
	 * @return resource
	 */
	private function open_output( string $temporary_path, string $output_path ) {
		$output = @fopen( $temporary_path, 'wb' );
		if ( false === $output ) {
			$this->remove_temporary_file( $temporary_path );
			throw new RuntimeException( sprintf( 'Unable to create a temporary file for "%s".', $output_path ) );
		}

		return $output;
	}

	/**
	 * @param resource $input
	 * @param resource $output
	 * @param array<int, array{from:string,to:string}> $replacements
	 */
	private function copy_and_replace( $input, $output, string $input_path, string $output_path, array $replacements ): void {
		$line_number = 0;
		while ( true ) {
			$line = fgets( $input );
			if ( false === $line ) {
				break;
			}

			++$line_number;
			try {
				$line = $this->line_replacer->replace_line( $line, $replacements );
			} catch ( RuntimeException $exception ) {
				throw new RuntimeException(
					sprintf( 'Unable to safely process serialized data in "%s" at line %d. %s', $input_path, $line_number, $exception->getMessage() ),
					0,
					$exception
				);
			}

			$this->write( $output, $line, $output_path );
		}

		if ( ! feof( $input ) ) {
			throw new RuntimeException( sprintf( 'Unable to read from "%s".', $input_path ) );
		}
	}

	/**
	 * @param resource $output
	 */
	private function commit( $output, string $temporary_path, string $output_path, ?int $existing_mode ): void {
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- The failure is converted to a command error.
		if ( ! @fflush( $output ) ) {
			throw new RuntimeException( sprintf( 'Unable to write to "%s".', $output_path ) );
		}

		$this->close( $output );

		if ( null !== $existing_mode ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- The failure is converted to a command error.
			@chmod( $temporary_path, $existing_mode );
		}

		if ( ! @rename( $temporary_path, $output_path ) ) {
			throw new RuntimeException( sprintf( 'Unable to replace "%s".', $output_path ) );
		}
	}

	/** @param resource|null $stream */
	private function close( $stream ): void {
		if ( is_resource( $stream ) ) {
			fclose( $stream );
		}
	}

	private function remove_temporary_file( ?string $temporary_path ): void {
		if ( null !== $temporary_path && file_exists( $temporary_path ) ) {
			unlink( $temporary_path );
		}
	}

	private function same_inode( string $input_path, string $output_path ): bool {
		$input_stat  = stat( $input_path );
		$output_stat = stat( $output_path );
		return false !== $input_stat && false !== $output_stat
			&& $input_stat['dev'] === $output_stat['dev'] && $input_stat['ino'] === $output_stat['ino'];
	}

	/** @param resource $stream */
	private function write( $stream, string $contents, string $output_path ): void {
		$written = 0;
		$length  = strlen( $contents );
		while ( $written < $length ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- The failure is converted to a command error.
			$result = @fwrite( $stream, substr( $contents, $written ) );
			if ( false === $result || 0 === $result ) {
				throw new RuntimeException( sprintf( 'Unable to write to "%s".', $output_path ) );
			}
			$written += $result;
		}
	}
}
