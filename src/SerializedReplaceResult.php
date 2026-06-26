<?php

// phpcs:ignoreFile

declare( strict_types=1 );

namespace PhpSearchReplace;

class SerializedReplaceResult {

	/**
	 * @var string
	 */
	public $pre;

	/**
	 * @var string
	 */
	public $serializedPortion;

	/**
	 * @var string
	 */
	public $post;

	public function __construct( $pre, $serializedPortion, $post ) {
		$this->pre                = $pre;
		$this->serializedPortion  = $serializedPortion;
		$this->post               = $post;
	}
}
