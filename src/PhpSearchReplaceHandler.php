<?php

declare( strict_types=1 );

namespace WP_CLI\Search_Replace_File;

/**
 * Public API for serialization-aware SQL dump replacement.
 */
class PhpSearchReplaceHandler {

	private $line_replacer;
	private $file_replacer;

	public function __construct( ?SqlLineReplacer $line_replacer = null, ?SqlFileReplacer $file_replacer = null ) {
		$this->line_replacer = $line_replacer ?: new SqlLineReplacer();
		$this->file_replacer = $file_replacer ?: new SqlFileReplacer( $this->line_replacer );
	}

	/**
	 * @param array<int, array{from:string,to:string}> $replacements
	 */
	public function replace_in_file( string $input_path, string $output_path, array $replacements ): void {
		$this->file_replacer->replace( $input_path, $output_path, $replacements );
	}

	/**
	 * @param array<int, array{from:string,to:string}> $replacements
	 */
	public function process_line( string $line, array $replacements ): string {
		return $this->line_replacer->replace( $line, $replacements );
	}

	public function paths_refer_to_same_file( string $input_path, string $output_path ): bool {
		return $this->file_replacer->paths_refer_to_same_file( $input_path, $output_path );
	}
}
