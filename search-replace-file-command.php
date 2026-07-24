<?php

if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

require_once __DIR__ . '/src/SerializedReplaceResult.php';
require_once __DIR__ . '/src/SerializedStringParser.php';
require_once __DIR__ . '/src/SqlLineReplacer.php';
require_once __DIR__ . '/src/SqlFileReplacer.php';
require_once __DIR__ . '/src/PhpSearchReplaceHandler.php';
require_once __DIR__ . '/src/Search_Replace_File_Command.php';

WP_CLI::add_command(
	'search-replace-file',
	'\WP_CLI\Search_Replace_File_Command',
	array(
		'when' => 'before_wp_load',
	)
);
