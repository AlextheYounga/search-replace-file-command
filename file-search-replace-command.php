<?php

if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

$wpcli_file_search_replace_autoloader = __DIR__ . '/vendor/autoload.php';
if ( file_exists( $wpcli_file_search_replace_autoloader ) ) {
	require_once $wpcli_file_search_replace_autoloader;
}

WP_CLI::add_command( 'file-search-replace', '\WP_CLI\File_Search_Replace_Command' );
