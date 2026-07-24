<?php

declare( strict_types=1 );

namespace WP_CLI\Search_Replace_File;

use RuntimeException;

/**
 * Applies replacements to SQL text while preserving serialized string lengths.
 */
final class SqlLineReplacer {

	/** @var SerializedStringParser */
	private $serialized_parser;

	public function __construct( ?SerializedStringParser $serialized_parser = null ) {
		$this->serialized_parser = $serialized_parser ?: new SerializedStringParser();
	}

	/**
	 * @param array<int, array{from:string,to:string}> $replacements
	 */
	public function replace_line( string $line, array $replacements ): string {
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
			$replacement_part = $this->serialized_parser->replace_serialized( $remaining, $replacements );
			$result          .= $replacement_part->before . $replacement_part->serialized;
			$remaining        = $replacement_part->after;
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
