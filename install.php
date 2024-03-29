<?php

if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
	require_once(dirname(__FILE__) . '/SSI.php');
elseif(!defined('SMF'))
	die('<b>Error:</b> Cannot install - please verify that you put this file in the same place as SMF\'s index.php and SSI.php files.');

global $user_info, $db_type, $smcFunc, $modSettings;

if ((SMF == 'SSI') && !$user_info['is_admin'])
	die('Admin privileges required.');

db_extend('search');

if ($db_type == 'postgresql') {
	$smcFunc['db_query']('', '
		DROP INDEX IF EXISTS {db_prefix}messages_st_idx',
		array(
			'db_error_skip' => true
		)
	);

	$language_ftx = $smcFunc['db_search_language']();

	$smcFunc['db_query']('', '
		CREATE INDEX {db_prefix}messages_st_idx ON {db_prefix}messages
		USING gin(to_tsvector({string:language}, subject))',
		array(
			'language' => $language_ftx
		)
	);
} else {
	$smcFunc['db_query']('', '
		ALTER TABLE {db_prefix}messages
		DROP INDEX st_idx_subject',
		array(
			'db_error_skip' => true
		)
	);

	$smcFunc['db_query']('', '
		ALTER TABLE {db_prefix}messages
		ADD FULLTEXT st_idx_subject (subject)',
		array()
	);
}

$initial_settings = array(
	'simtopics_num_topics'     => 5,
	'simtopics_only_cur_board' => 1,
	'simtopics_on_display'     => 1,
	'simtopics_position'       => 1,
	'simtopics_cache_int'      => 3600
);

$vars = array();
foreach ($initial_settings as $option => $value) {
	if (!isset($modSettings[$option]))
		$vars[$option] = $value;
}
updateSettings($vars);

if (SMF == 'SSI')
	echo 'Database changes are complete! Please wait...';
