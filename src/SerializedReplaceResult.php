<?php

declare( strict_types=1 );

namespace WP_CLI\Search_Replace_File;

final class SerializedReplaceResult {

	/**
	 * @var string
	 */
	public $before;

	/**
	 * @var string
	 */
	public $serialized;

	/**
	 * @var string
	 */
	public $after;

	public function __construct( string $before, string $serialized, string $after ) {
		$this->before     = $before;
		$this->serialized = $serialized;
		$this->after      = $after;
	}
}
