<?php

declare( strict_types=1 );

namespace WP_CLI\Search_Replace_File;

use RuntimeException;

/**
 * Finds and rebuilds serialized PHP strings embedded in SQL text.
 */
final class SerializedStringParser {

	/**
	 * Replace serialized data at the start of a text chunk.
	 *
	 * @param array<int, array{from:string,to:string}> $replacements
	 */
	public function replace_serialized( string $line_part, array $replacements ): SerializedReplaceResult {
		$prefix = $this->find_prefix( $line_part );

		if ( null === $prefix ) {
			return new SerializedReplaceResult( $this->replace_text( $line_part, $replacements ), '', '' );
		}

		$before         = $this->replace_text( substr( $line_part, 0, $prefix['start'] ), $replacements );
		$content_data   = $this->read_content( $line_part, $prefix );
		$content        = $this->replace_text( $content_data['value'], $replacements );
		$content_length = strlen( $this->unescape_content( $content ) );

		$quote      = '\\"';
		$serialized = 's:' . $content_length . ':' . $quote . $content . $quote . ';';

		return new SerializedReplaceResult( $before, $serialized, substr( $line_part, $content_data['next_index'] ) );
	}

	/**
	 * @param array{start:int,raw_length:string,content_start:int} $prefix
	 * @return array{value:string,next_index:int}
	 */
	private function read_content( string $line_part, array $prefix ): array {
		$length          = strlen( $line_part );
		$index           = $prefix['content_start'];
		$byte_count      = 0;
		$end_index       = 0;
		$original_length = (int) $prefix['raw_length'];

		while ( $index < $length ) {
			if ( $index + 2 >= $length ) {
				throw new RuntimeException( 'faulty serialized data: out-of-bound index access detected' );
			}

			$pair = substr( $line_part, $index, 2 );
			if ( '\\' === $line_part[ $index ] && $byte_count < $original_length ) {
				$byte_count += strlen( $this->unescape_pair( $pair ) );
				$end_index   = $index + 1;
				$index      += 2;
				continue;
			}

			if ( '\\";' === substr( $line_part, $index, 3 ) && $byte_count >= $original_length ) {
				return array(
					'value'      => substr( $line_part, $prefix['content_start'], $end_index - $prefix['content_start'] + 1 ),
					'next_index' => $index + 3,
				);
			}

			if ( $byte_count > $original_length ) {
				throw new RuntimeException( 'faulty serialized data: calculated byte count does not match given data size' );
			}

			++$byte_count;
			$end_index = $index;
			++$index;
		}

		throw new RuntimeException( 'faulty serialized data: end of serialized data not found' );
	}

	/**
	 * @return array{start:int,raw_length:string,content_start:int}|null
	 */
	private function find_prefix( string $line_part ): ?array {
		$length = strlen( $line_part );

		for ( $index = 0; $index < $length - 4; ++$index ) {
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

			if ( $digit_end + 2 >= $length || '\\' !== $line_part[ $digit_end + 1 ] || '"' !== $line_part[ $digit_end + 2 ] ) {
				continue;
			}

			return array(
				'start'         => $index,
				'raw_length'    => substr( $line_part, $digit_start, $digit_end - $digit_start ),
				'content_start' => $digit_end + 3,
			);
		}

		return null;
	}

	/**
	 * @param array<int, array{from:string,to:string}> $replacements
	 */
	private function replace_text( string $part, array $replacements ): string {
		foreach ( $replacements as $replacement ) {
			$part = str_replace( $replacement['from'], $replacement['to'], $part );
		}

		return $part;
	}

	private function unescape_content( string $escaped ): string {
		$unescaped = '';
		$length    = strlen( $escaped );

		for ( $index = 0; $index < $length; ++$index ) {
			if ( '\\' === $escaped[ $index ] && $index + 1 < $length ) {
				$converted = $this->unescape_pair( substr( $escaped, $index, 2 ) );
				if ( 1 === strlen( $converted ) ) {
					$unescaped .= $converted;
					++$index;
					continue;
				}
			}

			$unescaped .= $escaped[ $index ];
		}

		return $unescaped;
	}

	private function unescape_pair( string $pair ): string {
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
			'f'  => "\x0c",
			'0'  => '0',
		);

		$second = $pair[1] ?? '';
		return '' !== $second && isset( $map[ $second ] ) ? $map[ $second ] : $pair;
	}
}
