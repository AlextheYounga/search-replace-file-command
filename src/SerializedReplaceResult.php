<?php

declare( strict_types=1 );

namespace WP_CLI\Search_Replace_File;

class SerializedReplaceResult {

	/**
	 * @var string
	 */
	public $pre;

	/**
	 * @var string
	 */
	public $serialized_portion;

	/**
	 * @var string
	 */
	public $post;

	public function __construct( $pre, $serialized_portion, $post ) {
		$this->pre                = $pre;
		$this->serialized_portion = $serialized_portion;
		$this->post               = $post;
	}
}
