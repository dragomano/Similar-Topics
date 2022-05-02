<?php

if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
	require_once(dirname(__FILE__) . '/SSI.php');
elseif(!defined('SMF'))
	die('<b>Error:</b> Cannot install - please verify that you put this file in the same place as SMF\'s index.php and SSI.php files.');

if ((SMF == 'SSI') && !$user_info['is_admin'])
	die('Admin privileges required.');

global $smcFunc, $db_type;

$smcFunc['db_query']('', "DELETE FROM {db_prefix}settings WHERE variable LIKE 'simtopics_%'");

if ($db_type == 'postgresql') {
	$smcFunc['db_query']('', '
		DROP INDEX IF EXISTS {db_prefix}messages_st_idx',
		array(
			'db_error_skip' => true
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
}

if (SMF == 'SSI')
	echo 'Database changes are complete! Please wait...';
