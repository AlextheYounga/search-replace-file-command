<?php

declare( strict_types=1 );

namespace WP_CLI\Search_Replace_File;

use RuntimeException;

/**
 * Port of Automattic's go-search-replace serialized string fixer.
 * Works directly on SQL dump text without requiring a live database.
 */
class PhpSearchReplaceHandler {

	/**
	 * Run replacements against a SQL file and write the result to another file.
	 *
	 * @param array<int, array{from:string,to:string}> $replacements
	 */
	public function replace_in_file( string $input_path, string $output_path, array $replacements ): void {
		$normalized = $this->normalize_replacements( $replacements );

		if ( $this->paths_refer_to_same_file( $input_path, $output_path ) ) {
			throw new RuntimeException( 'The input and output files must be different.' );
		}

		$input = @fopen( $input_path, 'rb' );
		if ( false === $input ) {
			throw new RuntimeException( sprintf( 'Unable to open "%s" for reading.', $input_path ) );
		}

		$output_directory = realpath( dirname( $output_path ) );
		if ( false === $output_directory ) {
			fclose( $input );
			throw new RuntimeException( sprintf( 'Unable to access the output directory for "%s".', $output_path ) );
		}

		$temporary_path = tempnam( $output_directory, '.search-replace-file-' );
		if ( false === $temporary_path ) {
			fclose( $input );
			throw new RuntimeException( sprintf( 'Unable to create a temporary file for "%s".', $output_path ) );
		}

		$output = @fopen( $temporary_path, 'wb' );
		if ( false === $output ) {
			fclose( $input );
			unlink( $temporary_path );
			throw new RuntimeException( sprintf( 'Unable to create a temporary file for "%s".', $output_path ) );
		}

		try {
			$line_number = 0;
			while ( true ) {
				$line = fgets( $input );
				if ( false === $line ) {
					break;
				}

				++$line_number;

				try {
					$processed_line = $this->process_line( $line, $normalized );
				} catch ( RuntimeException $exception ) {
					throw new RuntimeException(
						sprintf(
							'Unable to safely process serialized data in "%s" at line %d. %s',
							$input_path,
							$line_number,
							$exception->getMessage()
						),
						0,
						$exception
					);
				}

				$this->write_to_stream( $output, $processed_line, $output_path );
			}

			if ( ! feof( $input ) ) {
				throw new RuntimeException( sprintf( 'Unable to read from "%s".', $input_path ) );
			}

			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- The failure is converted to a command error.
			if ( ! @fflush( $output ) ) {
				throw new RuntimeException( sprintf( 'Unable to write to "%s".', $output_path ) );
			}

			fclose( $input );
			$input = null;

			fclose( $output );
			$output = null;

			if ( ! @rename( $temporary_path, $output_path ) ) {
				throw new RuntimeException( sprintf( 'Unable to replace "%s".', $output_path ) );
			}

			$temporary_path = null;
		} finally {
			if ( is_resource( $input ) ) {
				fclose( $input );
			}

			if ( is_resource( $output ) ) {
				fclose( $output );
			}

			if ( null !== $temporary_path && file_exists( $temporary_path ) ) {
				unlink( $temporary_path );
			}
		}
	}

	/**
	 * Determine whether two paths resolve to the same file.
	 */
	public function paths_refer_to_same_file( string $input_path, string $output_path ): bool {
		$input_real_path = realpath( $input_path );
		if ( false === $input_real_path ) {
			return false;
		}

		$output_real_path = realpath( $output_path );
		if ( false !== $output_real_path && $input_real_path === $output_real_path ) {
			return true;
		}

		if ( false === $output_real_path ) {
			$output_directory = realpath( dirname( $output_path ) );
			if ( false !== $output_directory ) {
				$output_real_path = $output_directory . DIRECTORY_SEPARATOR . basename( $output_path );
				if ( $input_real_path === $output_real_path ) {
					return true;
				}
			}

			return false;
		}

		$input_stat  = stat( $input_path );
		$output_stat = stat( $output_path );

		return false !== $input_stat
			&& false !== $output_stat
			&& $input_stat['dev'] === $output_stat['dev']
			&& $input_stat['ino'] === $output_stat['ino'];
	}

	/**
	 * Replace strings inside a line or chunk of SQL text.
	 *
	 * @param array<int, array{from:string,to:string}> $replacements
	 */
	public function process_line( string $line, array $replacements ): string {
		if ( '' === $line ) {
			return '';
		}

		$normalized = $this->normalize_replacements( $replacements );
		if ( [] === $normalized ) {
			return $line;
		}

		return $this->fix_line( $line, $normalized );
	}

	/**
	 * @param array<int, array{from:string,to:string}> $replacements
	 * @return array<int, array{from:string,to:string}>
	 */
	private function normalize_replacements( array $replacements ): array {
		$normalized = array();
		foreach ( $replacements as $replacement ) {
			if ( ! is_array( $replacement ) || ! isset( $replacement['from'], $replacement['to'] ) ) {
				throw new RuntimeException( 'Replacements must be arrays with "from" and "to" keys.' );
			}

			$from = (string) $replacement['from'];
			$to   = (string) $replacement['to'];

			if ( '' === $from ) {
				continue;
			}

			$normalized[] = array(
				'from' => $from,
				'to'   => $to,
			);
		}

		return $normalized;
	}

	/**
	 * @param resource $stream
	 */
	private function write_to_stream( $stream, string $contents, string $output_path ): void {
		$length  = strlen( $contents );
		$written = 0;

		while ( $written < $length ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- The failure is converted to a command error.
			$result = @fwrite( $stream, substr( $contents, $written ) );
			if ( false === $result || 0 === $result ) {
				throw new RuntimeException( sprintf( 'Unable to write to "%s".', $output_path ) );
			}

			$written += $result;
		}
	}

	/**
	 * @param array<int, array{from:string,to:string}> $replacements
	 */
	private function fix_line( string $line, array $replacements ): string {
		$line_part = $line;
		$rebuilt   = '';

		while ( '' !== $line_part ) {
			$result = $this->fix_line_with_serialized_data( $line_part, $replacements );

			$rebuilt  .= $result->pre . $result->serialized_portion;
			$line_part = $result->post;

			if ( '' === $line_part ) {
				break;
			}
		}

		return $rebuilt;
	}

	/**
	 * @param array<int, array{from:string,to:string}> $replacements
	 */
	private function fix_line_with_serialized_data( string $line_part, array $replacements ): SerializedReplaceResult {
		$prefix = $this->find_serialized_prefix( $line_part );

		if ( null === $prefix ) {
			return new SerializedReplaceResult( $this->replace_by_part( $line_part, $replacements ), '', '' );
		}

		$pre = substr( $line_part, 0, $prefix['start'] );
		$pre = $this->replace_by_part( $pre, $replacements );

		$original_byte_size  = (int) $prefix['raw_length'];
		$content_start_index = $prefix['content_start'];

		$current_content_index = $content_start_index;
		$content_byte_count    = 0;
		$content_end_index     = 0;
		$next_slice_index      = null;
		$next_slice_found      = false;
		$line_part_length      = strlen( $line_part );
		$max_index             = $line_part_length - 1;

		while ( $current_content_index < $line_part_length ) {
			if ( $current_content_index + 2 > $max_index ) {
				throw new RuntimeException( 'faulty serialized data: out-of-bound index access detected' );
			}

			$char        = $line_part[ $current_content_index ];
			$second_char = $line_part[ $current_content_index + 1 ];
			$third_char  = $line_part[ $current_content_index + 2 ];

			if ( '\\' === $char && $content_byte_count < $original_byte_size ) {
				$unescaped              = $this->get_unescaped_bytes_if_escaped( substr( $line_part, $current_content_index, 2 ) );
				$content_byte_count    += strlen( $unescaped );
				$current_content_index += 2;
				continue;
			}

			if ( '\\' === $char && '"' === $second_char && ';' === $third_char && $content_byte_count >= $original_byte_size ) {
				$next_slice_index  = $current_content_index + 3;
				$content_end_index = $current_content_index - 1;
				$next_slice_found  = true;
				break;
			}

			if ( $content_byte_count > $original_byte_size ) {
				throw new RuntimeException( 'faulty serialized data: calculated byte count does not match given data size' );
			}

			++$content_byte_count;
			++$current_content_index;
		}

		if ( ! $next_slice_found || null === $next_slice_index ) {
			throw new RuntimeException( 'faulty serialized data: end of serialized data not found' );
		}

		$content        = substr( $line_part, $content_start_index, $content_end_index - $content_start_index + 1 );
		$content        = $this->replace_by_part( $content, $replacements );
		$content_length = strlen( $this->unescape_content( $content ) );

		$escaped_quote             = '\\"';
		$rebuilt_serialized_string = 's:' . $content_length . ':' . $escaped_quote . $content . $escaped_quote . ';';

		return new SerializedReplaceResult( $pre, $rebuilt_serialized_string, substr( $line_part, $next_slice_index ) );
	}

	/**
	 * @param array<int, array{from:string,to:string}> $replacements
	 */
	private function replace_by_part( string $part, array $replacements ): string {
		foreach ( $replacements as $replacement ) {
			$part = str_replace( $replacement['from'], $replacement['to'], $part );
		}

		return $part;
	}

	/**
	 * Locate a serialized string prefix within a chunk of SQL.
	 *
	 * @return array{start:int, raw_length:string, content_start:int}|null
	 */
	private function find_serialized_prefix( string $line_part ): ?array {
		$length = strlen( $line_part );

		for ( $index = 0; $index < $length - 4; $index++ ) {
			if ( 's' !== $line_part[ $index ] || ':' !== $line_part[ $index + 1 ] ) {
				continue;
			}

			$digit_start = $index + 2;
			if ( $digit_start >= $length || ! ctype_digit( $line_part[ $digit_start ] ) ) {
				continue;
			}

			$digit_end = $digit_start;
			while ( $digit_end < $length && ctype_digit( $line_part[ $digit_end ] ) ) {
				++$digit_end;
			}

			if ( $digit_end >= $length || ':' !== $line_part[ $digit_end ] ) {
				continue;
			}

			if ( $digit_end + 2 >= $length ) {
				break;
			}

			if ( '\\' !== $line_part[ $digit_end + 1 ] || '"' !== $line_part[ $digit_end + 2 ] ) {
				continue;
			}

			$raw_length = substr( $line_part, $digit_start, $digit_end - $digit_start );

			return array(
				'start'         => $index,
				'raw_length'    => $raw_length,
				'content_start' => $digit_end + 3,
			);
		}

		return null;
	}

	private function get_unescaped_bytes_if_escaped( string $pair ): string {
		if ( '' === $pair || '\\' !== $pair[0] ) {
			return $pair;
		}

		$map = array(
			'\\' => '\\',
			"'"  => "'",
			'"'  => '"',
			'n'  => "\n",
			'r'  => "\r",
			't'  => "\t",
			'b'  => "\x08",
			'f'  => "\f",
			'0'  => '0',
		);

		$second = $pair[1] ?? '';

		if ( '' !== $second && isset( $map[ $second ] ) ) {
			return $map[ $second ];
		}

		return $pair;
	}

	private function unescape_content( string $escaped ): string {
		$unescaped = '';
		$length    = strlen( $escaped );
		$index     = 0;

		while ( $index < $length ) {
			if ( '\\' === $escaped[ $index ] && $index + 1 < $length ) {
				$pair      = substr( $escaped, $index, 2 );
				$converted = $this->get_unescaped_bytes_if_escaped( $pair );
				if ( 1 === strlen( $converted ) ) {
					$unescaped .= $converted;
					$index     += 2;
					continue;
				}
			}

			$unescaped .= $escaped[ $index ];
			++$index;
		}

		return $unescaped;
	}
}
