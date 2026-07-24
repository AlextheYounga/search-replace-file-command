<?php

declare( strict_types=1 );

namespace WP_CLI\Search_Replace_File;

use RuntimeException;

/**
 * Applies replacements to SQL text while preserving serialized string lengths.
 */
class SqlLineReplacer {

	private $serialized_parser;

	public function __construct( ?SerializedStringParser $serialized_parser = null ) {
		$this->serialized_parser = $serialized_parser ?: new SerializedStringParser();
	}

	/**
	 * @param array<int, array{from:string,to:string}> $replacements
	 */
	public function replace( string $line, array $replacements ): string {
		if ( '' === $line ) {
			return '';
		}

		$replacements = $this->normalize_replacements( $replacements );
		if ( [] === $replacements ) {
			return $line;
		}

		$remaining = $line;
		$result    = '';

		while ( '' !== $remaining ) {
			$part      = $this->serialized_parser->replace( $remaining, $replacements );
			$result   .= $part->pre . $part->serialized_portion;
			$remaining = $part->post;
		}

		return $result;
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
			if ( '' === $from ) {
				continue;
			}

			$normalized[] = array(
				'from' => $from,
				'to'   => (string) $replacement['to'],
			);
		}

		return $normalized;
	}
}
