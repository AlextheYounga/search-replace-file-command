<?php

if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

require_once __DIR__ . '/src/SerializedReplaceResult.php';
require_once __DIR__ . '/src/PhpSearchReplaceHandler.php';
require_once __DIR__ . '/src/File_Search_Replace_Command.php';

WP_CLI::add_command(
	'file-search-replace',
	'\WP_CLI\File_Search_Replace_Command',
	array(
		'when' => 'before_wp_load',
	)
);
