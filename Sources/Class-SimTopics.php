<?php

/**
 * Class-SimTopics.php
 *
 * @package Similar Topics
 * @link https://dragomano.ru/mods/similar-topics
 * @author Bugo <bugo@dragomano.ru>
 * @copyright 2012-2021 Bugo
 * @license https://opensource.org/licenses/BSD-3-Clause BSD
 *
 * @version 1.0.1
 */

if (!defined('SMF'))
	die('Hacking attempt...');

class SimTopics
{
	/**
	 * Подключаем необходимые хуки
	 *
	 * @return void
	 */
	public static function hooks()
	{
		add_integration_function('integrate_load_theme', __CLASS__ . '::loadTheme', false);
		add_integration_function('integrate_menu_buttons', __CLASS__ . '::menuButtons', false);
		add_integration_function('integrate_load_permissions', __CLASS__ . '::loadPermissions', false);
		add_integration_function('integrate_admin_areas', __CLASS__ . '::adminAreas', false);
		add_integration_function('integrate_modify_modifications', __CLASS__ . '::modifyModifications', false);
	}

	/**
	 * Подключаем языковой файл
	 *
	 * @return void
	 */
	public static function loadTheme()
	{
		loadLanguage('SimTopics/');
	}

	/**
	 * Проверяем, где мы находимся, и подключаем соответствующие скрипты или функции
	 *
	 * @return void
	 */
	public static function menuButtons()
	{
		global $modSettings, $context, $txt, $scripturl, $settings;

		if (empty($modSettings['simtopics_num_topics']) || WIRELESS || isset($_REQUEST['xml']))
			return;

		$context['simtopics_ignored_boards'] = array();
		if (!empty($modSettings['simtopics_ignored_boards']))
			$context['simtopics_ignored_boards'] = explode(",", $modSettings['simtopics_ignored_boards']);

		if (!empty($modSettings['recycle_board']))
			$context['simtopics_ignored_boards'][] = $modSettings['recycle_board'];

		if (isset($context['current_board']) && in_array($context['current_board'], $context['simtopics_ignored_boards']))
			return;

		if (!empty($context['current_topic']) && !empty($modSettings['simtopics_on_display']))
			self::checkTopicsOnDisplay();

		self::showColumns();

		if (allowedTo('simtopics_post') && !empty($context['is_new_topic']) && !empty($modSettings['simtopics_when_new_topic'])) {
			if (isset($_POST['query']))
				self::checkTopicsOnPost();

			$context['insert_after_template'] .= '
		<script type="text/javascript" src="//cdn.jsdelivr.net/npm/jquery@3/dist/jquery.min.js"></script>
		<script type="text/javascript">
			var simOpt = {
				"title": "' . $txt['similar_topics'] . '",
				"url" : "' . str_replace("index.php", "", $scripturl) . '",
				"by": "' . $txt['started_by'] . '",
				"board": "' . $txt['board'] . '",
				"replies": "' . $txt['replies'] . '",
				"views": "' . $txt['views'] . '",
				"no_result": "' . $txt['simtopics_no_result'] . '",
				"cur_board": "' . $context['current_board'] . '"
			};
		</script>
		<script type="text/javascript" src="' . $settings['default_theme_url'] . '/scripts/simtopics.js"></script>';
		}
	}

	/**
	 * Возврат подготовленной строки для поиска в базе
	 *
	 * @param string $title
	 * @return string
	 */
	private static function getCorrectTitle($title)
	{
		global $db_type, $smcFunc;

		// Удаляем все знаки препинания
		$correct_title = preg_replace('/\p{P}+/u', '', $title);

		// Удаляем лишние пробелы
		$correct_title = preg_replace('/[\s]+/i', ' ', $correct_title);

		// Удаляем все слова короче 3 символов
		$correct_title = array_filter(explode(' ', $correct_title), function ($word) use ($smcFunc) {
			return $smcFunc['strlen']($word) > 2;
		});

		if ($db_type == 'postgresql') {
			// Переводим массив в строку с разделителем «|» между словами (означает «ИЛИ»; если нужно «И» - поставить «&»)
			$correct_title = trim(urldecode(implode(' | ', array_filter($correct_title))));
		} else {
			// Преобразуем массив в строку для использования в запросе MATCH AGAINST
			$correct_title = trim(urldecode(implode('* ', $correct_title))) . '*';
		}

		return $smcFunc['strtolower']($correct_title);
	}

	/**
	 * Поиск похожих тем при создании новой
	 *
	 * @return void
	 */
	public static function checkTopicsOnPost()
	{
		global $smcFunc, $txt, $db_type, $modSettings, $context;

		$output = array(
			'msg'    => '',
			'topics' => false,
			'error'  => false
		);

		$query = !empty($_POST['query']) ? array_filter(explode(' ', $smcFunc['htmlspecialchars']($_POST['query']))) : [];
		$count = count($query);
		$board = !empty($_REQUEST['board']) ? (int) $_REQUEST['board'] : 0;

		if (empty($query)) {
			$output['msg']   = $txt['simtopics_no_subject'];
			$output['count'] = $count;
			$output['error'] = true;
		} else {
			$search_string = $smcFunc['db_escape_string'](implode(' ', $query));
			$title = self::getCorrectTitle($search_string);
		}

		if (!empty($count) && !empty(ltrim($title, '*'))) {
			$result = $smcFunc['db_query']('', '
				SELECT DISTINCT
					t.id_topic, t.id_board, t.is_sticky, t.locked, t.id_member_started as id_author, t.num_replies, t.num_views,
					b.name as bname, mf.icon, mf.poster_name as author, mf.subject,' . ($db_type == 'postgresql' ? '
					ts_rank_cd(to_tsvector(mf.subject), to_tsquery({string:title}))' : '
					MATCH (mf.subject) AGAINST ({string:title} IN BOOLEAN MODE)') . 'AS score
				FROM {db_prefix}topics AS t
					LEFT JOIN {db_prefix}messages AS m ON m.id_topic = t.id_topic
					LEFT JOIN {db_prefix}boards AS b ON b.id_board = t.id_board
					LEFT JOIN {db_prefix}messages AS mf ON mf.id_msg = t.id_first_msg
				WHERE m.approved = {int:is_active}' . (!empty($modSettings['simtopics_only_cur_board']) ? '
					AND t.id_board = {int:current_board}' : '') . (!empty($context['simtopics_ignored_boards']) ? '
					AND b.id_board NOT IN ({array_int:ignore_boards})' : '') . '
					AND {query_wanna_see_board}
					AND {query_see_board}' . ($db_type == 'postgresql' ? '
					AND to_tsvector(mf.subject) @@ to_tsquery({string:title})' : '
					AND MATCH (mf.subject) AGAINST ({string:title} IN BOOLEAN MODE)') . '
				ORDER BY score DESC
				LIMIT {int:limit}',
				array(
					'title'         => $title,
					'is_active'     => 1,
					'current_board' => $board,
					'ignore_boards' => !empty($context['simtopics_ignored_boards']) ? $context['simtopics_ignored_boards'] : null,
					'limit'         => !empty($modSettings['simtopics_num_topics']) ? $modSettings['simtopics_num_topics'] : 5
				)
			);

			$topics = array();
			while ($topic = $smcFunc['db_fetch_assoc']($result))
				$topics[$topic['id_topic']] = $topic;

			$smcFunc['db_free_result']($result);

			$output['topics'] = count($topics) == 0 ? false : $topics;
		}

		exit(json_encode($output));
	}

	/**
	 * Поиск похожих тем внутри текущей темы
	 *
	 * @return void
	 */
	public static function checkTopicsOnDisplay()
	{
		global $context, $topicinfo, $user_info, $modSettings, $options, $smcFunc, $db_type, $scripturl, $settings;

		if (!allowedTo('simtopics_view') || WIRELESS || isset($_REQUEST['xml']) || !empty($context['is_new_topic']))
			return;

		if (empty($topicinfo['subject']) && empty($context['subject']))
			return;

		if (($context['similar_topics'] = cache_get_data('similar_topics-' . $context['current_topic'] . '-u' . $user_info['id'], $modSettings['simtopics_cache_int'])) == null) {
			$context['pageindex_multiplier'] = empty($modSettings['disableCustomPerPage']) && !empty($options['messages_per_page']) && !WIRELESS ? $options['messages_per_page'] : $modSettings['defaultMaxMessages'];

			$sort_type = 'score';
			if (!empty($modSettings['simtopics_sorting'])) {
				switch ($modSettings['simtopics_sorting']) {
					case 1:
						$sort_type = 'score DESC';
					break;
					case 2:
						$sort_type = 'first_subject';
					break;
					case 3:
						$sort_type = 'first_subject DESC';
					break;
					case 4:
						$sort_type = 'first_poster_time';
					break;
					case 5:
						$sort_type = 'first_poster_time DESC';
					break;
					case 6:
						$sort_type = 'last_poster_time';
					break;
					case 7:
						$sort_type = 'last_poster_time DESC';
					break;
				}
			}

			$title = self::getCorrectTitle(isset($topicinfo['subject']) ? $topicinfo['subject'] : $context['subject']);

			if (!empty(ltrim($title, '*'))) {
				$request = $smcFunc['db_query']('', '
					SELECT
						t.id_topic, t.id_board, t.num_views, t.num_replies, t.is_sticky, t.locked, t.id_poll, t.id_first_msg, t.id_last_msg,
						ml.subject AS last_subject, ml.id_member AS last_id_member, ml.poster_time AS last_poster_time, ml.id_msg_modified,
						mf.subject AS first_subject, mf.id_member AS first_id_member, mf.poster_time AS first_poster_time, mf.icon, b.name,
						' . ($user_info['is_guest'] ? '0' : 'IFNULL(lt.id_msg, IFNULL(lmr.id_msg, -1)) + 1') . ' AS new_from,
						IFNULL(meml.real_name, ml.poster_name) AS last_poster_name, IFNULL(memf.real_name, mf.poster_name) AS poster_name,' . ($db_type == 'postgresql' ? '
						ts_rank_cd(to_tsvector(mf.subject), to_tsquery({string:title}))' : '
						MATCH (mf.subject) AGAINST ({string:title} IN BOOLEAN MODE)') . 'AS score
					FROM {db_prefix}topics AS t
						INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
						INNER JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)
						LEFT JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
						LEFT JOIN {db_prefix}members AS meml ON (meml.id_member = ml.id_member)
						LEFT JOIN {db_prefix}members AS memf ON (memf.id_member = mf.id_member)' . ($user_info['is_guest'] ? '' : '
						LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})
						LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = {int:current_board} AND lmr.id_member = {int:current_member})') . '
					WHERE t.id_topic != {int:current_topic}
						AND t.approved = {int:is_approved}' . (!empty($modSettings['simtopics_only_cur_board']) ? '
						AND t.id_board = {int:current_board}' : '') . (!empty($context['simtopics_ignored_boards']) ? '
						AND t.id_board NOT IN ({array_int:ignore_boards})' : '') . '
						AND {query_wanna_see_board}
						AND {query_see_board}' . ($db_type == 'postgresql' ? '
						AND to_tsvector(mf.subject) @@ to_tsquery({string:title})' : '
						AND MATCH (mf.subject) AGAINST ({string:title} IN BOOLEAN MODE)') . '
					ORDER BY {raw:sort_type}
					LIMIT {int:limit}',
					array(
						'title'          => $title,
						'current_topic'  => $context['current_topic'],
						'current_board'  => $context['current_board'],
						'is_approved'    => 1,
						'current_member' => $user_info['id'],
						'ignore_boards'  => !empty($context['simtopics_ignored_boards']) ? $context['simtopics_ignored_boards'] : null,
						'sort_type'      => $sort_type,
						'limit'          => !empty($modSettings['simtopics_num_topics']) ? $modSettings['simtopics_num_topics'] : 5
					)
				);

				while ($row = $smcFunc['db_fetch_assoc']($request)) {
					censorText($row['first_subject']);

					if ($row['id_first_msg'] == $row['id_last_msg'])
						$row['last_subject'] = $row['first_subject'];
					else
						censorText($row['last_subject']);

					$context['similar_topics'][] = array(
						'id' => $row['id_topic'],
						'first_post' => array(
							'id' => $row['id_first_msg'],
							'member_link' => !empty($row['first_id_member']) ? '<a href="' . $scripturl . '?action=profile;u=' . $row['first_id_member'] . '">' . $row['poster_name'] . '</a>' : $row['poster_name'],
							'time' => timeformat($row['first_poster_time']),
							'subject' => $row['first_subject'],
							'icon_url' => $settings['images_url'] . '/post/' . $row['icon'] . '.gif',
							'link' => '<a itemprop="relatedLink" href="' . $scripturl . '?topic=' . $row['id_topic'] . '.0">' . $row['first_subject'] . '</a>',
							'board' => empty($modSettings['simtopics_only_cur_board']) ? '<a href="' . $scripturl . '?board=' . $row['id_board'] . '.0">' . $row['name'] . '</a>' : '',
						),
						'last_post' => array(
							'id' => $row['id_last_msg'],
							'member_link' => !empty($row['last_id_member']) ? '<a href="' . $scripturl . '?action=profile;u=' . $row['last_id_member'] . '">' . $row['last_poster_name'] . '</a>' : $row['last_poster_name'],
							'time' => timeformat($row['last_poster_time']),
							'subject' => $row['last_subject'],
							'href' => $scripturl . '?topic=' . $row['id_topic'] . ($user_info['is_guest'] ? ('.' . (!empty($options['view_newest_first']) ? 0 : ((int) (($row['num_replies']) / $context['pageindex_multiplier'])) * $context['pageindex_multiplier']) . '#msg' . $row['id_last_msg']) : (($row['num_replies'] == 0 ? '.0' : '.msg' . $row['id_last_msg']) . '#new')),
						),
						'is_sticky' => !empty($modSettings['enableStickyTopics']) && !empty($row['is_sticky']),
						'is_locked' => !empty($row['locked']),
						'views' => $row['num_views'],
						'replies' => $row['num_replies'],
						'new' => $row['new_from'] <= $row['id_msg_modified'],
						'new_href' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['new_from'] . '#new',
						'new_from' => $row['new_from']
					);
				}

				$smcFunc['db_free_result']($request);
			} else {
				$context['similar_topics'] = [];
			}

			cache_put_data('similar_topics-' . $context['current_topic'] . '-u' . $user_info['id'], $context['similar_topics'], $modSettings['simtopics_cache_int']);
		}

		loadTemplate('SimTopics');
		$context['template_layers'][] = empty($modSettings['simtopics_position']) ? 'simtopics_top' : 'simtopics_bot';
	}

	/**
	 * Настраиваем права доступа
	 *
	 * @param array $permissionGroups
	 * @param array $permissionList
	 * @return void
	 */
	public static function loadPermissions(&$permissionGroups, &$permissionList)
	{
		$permissionGroups['membergroup']['simple'] = array('simtopics');
		$permissionGroups['membergroup']['classic'] = array('simtopics');

		$permissionList['membergroup']['simtopics_view'] = array(false, 'simtopics', 'simtopics');
		$permissionList['membergroup']['simtopics_post'] = array(false, 'simtopics', 'simtopics');
	}

	/**
	 * Объявляем вкладку «Похожие темы» в настройках модификаций
	 *
	 * @param array $admin_areas
	 * @return void
	 */
	public static function adminAreas(&$admin_areas)
	{
		global $txt;

		$admin_areas['config']['areas']['modsettings']['subsections']['simtopics'] = array($txt['similar_topics']);
	}

	/**
	 * Подключаем страницу с настройками мода
	 *
	 * @param array $subActions
	 * @return void
	 */
	public static function modifyModifications(&$subActions)
	{
		$subActions['simtopics'] = array('SimTopics', 'settings');
	}

	/**
	 * Настройки мода в админке
	 *
	 * @return void
	 */
	public static function settings()
	{
		global $context, $txt, $scripturl;

		loadTemplate('SimTopics');

		$context['page_title']     = $txt['simtopics_settings'];
		$context['settings_title'] = $txt['settings'];
		$context['post_url']       = $scripturl . '?action=admin;area=modsettings;save;sa=simtopics';
		$context[$context['admin_menu_name']]['tab_data']['tabs']['simtopics'] = array('description' => $txt['simtopics_desc']);

		self::showColumns();
		self::ignoreBoards();

		$config_vars = array(
			array('int', 'simtopics_num_topics', 'subtext' => $txt['simtopics_nt_desc']),
			array('check', 'simtopics_only_cur_board', 'subtext' => $txt['simtopics_ocb_desc']),
			array('check', 'simtopics_when_new_topic'),
			array('check', 'simtopics_on_display'),
			array('select', 'simtopics_position', $txt['simtopics_position_variants']),
			array('select', 'simtopics_sorting', $txt['simtopics_sorting_variants']),
			array('int', 'simtopics_cache_int', 'postinput' => $txt['simtopics_ci_post']),

			array('title', 'edit_permissions'),
			array('permissions', 'simtopics_view'),
			array('permissions', 'simtopics_post'),

			array('title', 'simtopics_displayed_columns'),
			array('callback', 'displayed_columns'),

			array('title', 'simtopics_ignored_boards'),
			array('callback', 'ignored_boards')
		);

		// Saving?
		if (isset($_GET['save'])) {
			if (empty($_POST['ignore_column']))
				$_POST['ignore_column'] = array();

			unset($_POST['st_displayed_columns']);

			if (isset($_POST['ignore_column'])) {
				if (!is_array($_POST['ignore_column']))
					$_POST['ignore_column'] = array($_POST['ignore_column']);

				foreach ($_POST['ignore_column'] as $k => $d) {
					$d = (int) $d;
					if ($d != 0)
						$_POST['ignore_column'][$k] = $d;
					else
						unset($_POST['ignore_column'][$k]);
				}

				$_POST['st_displayed_columns'] = implode(',', $_POST['ignore_column']);
				unset($_POST['ignore_column']);
			}

			if (empty($_POST['ignore_board']))
				$_POST['ignore_board'] = array();

			unset($_POST['st_ignore_boards']);

			if (isset($_POST['ignore_board'])) {
				if (!is_array($_POST['ignore_board']))
					$_POST['ignore_board'] = array($_POST['ignore_board']);

				foreach ($_POST['ignore_board'] as $k => $d) {
					$d = (int) $d;
					if ($d != 0)
						$_POST['ignore_board'][$k] = $d;
					else
						unset($_POST['ignore_board'][$k]);
				}

				$_POST['st_ignore_boards'] = implode(',', $_POST['ignore_board']);
				unset($_POST['ignore_board']);
			}

			checkSession();

			saveDBSettings($config_vars);
			updateSettings(array('simtopics_displayed_columns' => $_POST['st_displayed_columns']));
			updateSettings(array('simtopics_ignored_boards' => $_POST['st_ignore_boards']));
			clean_cache();

			redirectexit('action=admin;area=modsettings;sa=simtopics');
		}

		prepareDBSettingContext($config_vars);
	}

	/**
	 * Отображение активных столбцов в таблице похожих тем
	 *
	 * @return void
	 */
	private static function showColumns()
	{
		global $modSettings, $context, $txt;

		$columns = !empty($modSettings['simtopics_displayed_columns']) ? explode(',', $modSettings['simtopics_displayed_columns']) : [];

		$protect_columns = array(2);

		$column_values = array(
			$txt['current_icon'],
			$txt['title'] . ' / ' . $txt['board'],
			$txt['replies'] . ' / ' . $txt['views'],
			$txt['latest_post']
		);

		$i = 1;
		foreach ($column_values as $value) {
			$context['simtopics_displayed_columns'][$i] = array(
				'id'      => $i,
				'name'    => $value,
				'protect' => in_array($i, $protect_columns)
			);
			$i++;
		}

		foreach ($context['simtopics_displayed_columns'] as $column) {
			if (in_array($column['id'], $columns) || in_array($column['id'], $protect_columns))
				$context['simtopics_displayed_columns'][$column['id']]['show'] = true;
		}
	}

	/**
	 * Обработка игнорируемых разделов
	 *
	 * @return void
	 */
	private static function ignoreBoards()
	{
		global $smcFunc, $modSettings, $context;

		$request = $smcFunc['db_query']('order_by_board_order', '
			SELECT b.id_cat, c.name AS cat_name, b.id_board, b.name, b.child_level,
				'. (!empty($modSettings['simtopics_ignored_boards']) ? 'b.id_board IN ({array_int:ignore_boards})' : '0') . ' AS is_ignored
			FROM {db_prefix}boards AS b
				LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
			WHERE b.redirect = {string:empty_string}' .	(!empty($modSettings['recycle_board']) ? '
				AND b.id_board != {int:recycle_board}' : ''),
			array(
				'ignore_boards' => !empty($modSettings['simtopics_ignored_boards']) ? explode(',', $modSettings['simtopics_ignored_boards']) : null,
				'recycle_board' => !empty($modSettings['recycle_board']) ? (int) $modSettings['recycle_board'] : null,
				'empty_string'  => ''
			)
		);

		$context['num_boards'] = $smcFunc['db_num_rows']($request);
		$context['categories'] = array();

		while ($row = $smcFunc['db_fetch_assoc']($request)) {
			if (!isset($context['categories'][$row['id_cat']]))
				$context['categories'][$row['id_cat']] = array(
					'id'     => $row['id_cat'],
					'name'   => $row['cat_name'],
					'boards' => array()
				);

			$context['categories'][$row['id_cat']]['boards'][$row['id_board']] = array(
				'id'          => $row['id_board'],
				'name'        => $row['name'],
				'child_level' => $row['child_level'],
				'selected'    => $row['is_ignored']
			);
		}
		$smcFunc['db_free_result']($request);

		$temp_boards = array();
		foreach ($context['categories'] as $category) {
			$context['categories'][$category['id']]['child_ids'] = array_keys($category['boards']);

			$temp_boards[] = array(
				'name'      => $category['name'],
				'child_ids' => array_keys($category['boards'])
			);
			$temp_boards = array_merge($temp_boards, array_values($category['boards']));
		}

		$max_boards = ceil(count($temp_boards) / 2);
		if ($max_boards == 1)
			$max_boards = 2;

		$context['board_columns'] = array();
		for ($i = 0; $i < $max_boards; $i++) {
			$context['board_columns'][] = $temp_boards[$i];
			if (isset($temp_boards[$i + $max_boards]))
				$context['board_columns'][] = $temp_boards[$i + $max_boards];
			else
				$context['board_columns'][] = array();
		}
	}
}
