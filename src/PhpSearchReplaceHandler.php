<?php

declare( strict_types=1 );

namespace WP_CLI\Search_Replace_File;

/**
 * Public façade for serialization-aware SQL dump replacement.
 *
 * The façade keeps the package's original API stable while the file and line
 * workflows remain independently testable.
 */
final class PhpSearchReplaceHandler {

	/** @var SqlLineReplacer */
	private $line_replacer;
	/** @var SqlFileReplacer */
	private $file_replacer;

	public function __construct( ?SqlLineReplacer $line_replacer = null, ?SqlFileReplacer $file_replacer = null ) {
		$this->line_replacer = $line_replacer ?: new SqlLineReplacer();
		$this->file_replacer = $file_replacer ?: new SqlFileReplacer( $this->line_replacer );
	}

	/**
	 * @param array<int, array{from:string,to:string}> $replacements
	 */
	public function replace_in_file( string $input_path, string $output_path, array $replacements ): void {
		$this->file_replacer->replace_file( $input_path, $output_path, $replacements );
	}

	/**
	 * @param array<int, array{from:string,to:string}> $replacements
	 */
	public function process_line( string $line, array $replacements ): string {
		return $this->line_replacer->replace_line( $line, $replacements );
	}

	public function paths_refer_to_same_file( string $input_path, string $output_path ): bool {
		return $this->file_replacer->paths_refer_to_same_file( $input_path, $output_path );
	}
}
