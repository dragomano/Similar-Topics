<?php

/**
 * Class-SimTopics.php
 *
 * @package Similar Topics
 * @link https://dragomano.ru/mods/similar-topics
 * @author Bugo <bugo@dragomano.ru>
 * @copyright 2012-2022 Bugo
 * @license https://opensource.org/licenses/BSD-3-Clause BSD
 *
 * @version 1.2.3
 */

if (!defined('SMF'))
	die('No direct access...');

final class SimTopics
{
	public function hooks()
	{
		add_integration_function('integrate_load_theme', __CLASS__ . '::loadTheme#', false, __FILE__);
		add_integration_function('integrate_menu_buttons', __CLASS__ . '::menuButtons#', false, __FILE__);
		add_integration_function('integrate_load_permissions', __CLASS__ . '::loadPermissions#', false, __FILE__);
		add_integration_function('integrate_admin_areas', __CLASS__ . '::adminAreas#', false, __FILE__);
		add_integration_function('integrate_admin_search', __CLASS__ . '::adminSearch#', false, __FILE__);
		add_integration_function('integrate_modify_modifications', __CLASS__ . '::modifyModifications#', false, __FILE__);
	}

	public function loadTheme()
	{
		loadLanguage('SimTopics/');
	}

	public function menuButtons()
	{
		global $modSettings, $context, $txt, $scripturl, $settings;

		if (empty($modSettings['simtopics_num_topics']) || isset($_REQUEST['xml']))
			return;

		$context['simtopics_ignored_boards'] = array();
		if (!empty($modSettings['simtopics_ignored_boards']))
			$context['simtopics_ignored_boards'] = explode(',', $modSettings['simtopics_ignored_boards']);

		if (!empty($modSettings['recycle_board']))
			$context['simtopics_ignored_boards'][] = $modSettings['recycle_board'];

		if (isset($context['current_board']) && in_array($context['current_board'], $context['simtopics_ignored_boards']))
			return;

		if (!empty($context['current_topic']) && !empty($modSettings['simtopics_on_display']))
			$this->checkTopicsOnDisplay();

		$this->prepareColumns();

		if (allowedTo('simtopics_post') && !empty($context['is_new_topic']) && !empty($modSettings['simtopics_when_new_topic'])) {
			if (isset($_POST['query']))
				$this->checkTopicsOnPost();

			$context['insert_after_template'] .= '
		<script>
			let simOpt = {
				"title": "' . $txt['similar_topics'] . '",
				"by": "' . $txt['started_by'] . '",
				"board": "' . $txt['board'] . '",
				"replies": "' . $txt['replies'] . '",
				"views": "' . $txt['views'] . '",
				"no_result": "' . $txt['simtopics_no_result'] . '",
				"cur_board": "' . ($context['current_board'] ?? 0) . '",
				"show_top": ' . ($modSettings['simtopics_when_new_topic'] === '1' ? 'true' : 'false') . '
			};
		</script>
		<script src="' . $settings['default_theme_url'] . '/scripts/simtopics.js"></script>';
		}
	}

	private function getCorrectTitle(string $title): string
	{
		global $smcFunc, $db_type;

		// Удаляем все знаки препинания
		$correct_title = preg_replace('/\p{P}+/u', '', $title);

		// Удаляем лишние пробелы
		$correct_title = preg_replace('/[\s]+/i', ' ', $correct_title);

		// Удаляем все слова короче 3 символов
		$correct_title = array_filter(explode(' ', $correct_title), function ($word) use ($smcFunc) {
			return $smcFunc['strlen']($word) > 2;
		});

		if ($db_type === 'postgresql') {
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
	 */
	public function checkTopicsOnPost()
	{
		global $smcFunc, $txt, $db_connection, $db_type, $modSettings, $context;

		$output = array(
			'msg'    => '',
			'topics' => false,
			'error'  => false
		);

		$query = empty($_POST['query']) ? [] : array_filter(explode(' ', $smcFunc['htmlspecialchars']($_POST['query'])));
		$count = count($query);
		$board = empty($_REQUEST['board']) ? 0 : (int) $_REQUEST['board'];

		if (empty($query)) {
			$output['msg']   = $txt['simtopics_no_subject'];
			$output['error'] = true;
			exit(json_encode($output));
		} else {
			$search_string = $smcFunc['db_escape_string'](implode(' ', $query), $db_connection);
			$title = $this->getCorrectTitle($search_string);
		}

		if (!empty($count) && !empty(ltrim($title, '*'))) {
			db_extend('search');

			$result = $smcFunc['db_query']('', '
				SELECT DISTINCT
					t.id_topic, t.id_board, t.is_sticky, t.locked, t.id_member_started as id_author, t.num_replies, t.num_views,
					b.name as bname, mf.icon, mf.poster_name as author, mf.subject,' . ($db_type === 'postgresql' ? '
					ts_rank_cd(to_tsvector(mf.subject), to_tsquery({string:language}, {string:title}))' : '
					MATCH (mf.subject) AGAINST ({string:title} IN BOOLEAN MODE)') . 'AS score
				FROM {db_prefix}topics AS t
					LEFT JOIN {db_prefix}messages AS m ON m.id_topic = t.id_topic
					LEFT JOIN {db_prefix}boards AS b ON b.id_board = t.id_board
					LEFT JOIN {db_prefix}messages AS mf ON mf.id_msg = t.id_first_msg
				WHERE m.approved = {int:is_active}' . (!empty($modSettings['simtopics_only_cur_board']) ? '
					AND t.id_board = {int:current_board}' : '') . (!empty($context['simtopics_ignored_boards']) ? '
					AND b.id_board NOT IN ({array_int:ignore_boards})' : '') . '
					AND {query_wanna_see_board}
					AND {query_see_board}' . ($db_type === 'postgresql' ? '
					AND to_tsvector(mf.subject) @@ to_tsquery({string:language}, {string:title})' : '
					AND MATCH (mf.subject) AGAINST ({string:title} IN BOOLEAN MODE)') . '
				ORDER BY score DESC
				LIMIT {int:limit}',
				array(
					'language'      => $db_type === 'postgresql' ? $smcFunc['db_search_language']() : '',
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
	 */
	public function checkTopicsOnDisplay()
	{
		global $context, $user_info, $modSettings, $options, $smcFunc, $db_type, $settings, $scripturl, $txt;

		if (!allowedTo('simtopics_view') || isset($_REQUEST['xml']) || !empty($context['is_new_topic']) || empty($context['subject']))
			return;

		if (($context['similar_topics'] = cache_get_data('similar_topics-' . $context['current_topic'] . '-u' . $user_info['id'], $modSettings['simtopics_cache_int'])) == null) {
			$context['pageindex_multiplier'] = empty($modSettings['disableCustomPerPage']) && !empty($options['messages_per_page']) ? $options['messages_per_page'] : $modSettings['defaultMaxMessages'];

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

			$title = $this->getCorrectTitle($context['subject']);

			if (!empty(ltrim($title, '*'))) {
				db_extend('search');

				$request = $smcFunc['db_query']('substring', '
					SELECT
						t.id_topic, t.id_board, t.num_views, t.num_replies, t.is_sticky, t.locked, t.id_poll, t.id_first_msg, t.id_last_msg, t.id_redirect_topic, ml.subject AS last_subject, ml.id_member AS last_id_member, ml.poster_time AS last_poster_time, ml.id_msg_modified, ml.icon AS last_icon, ml.poster_name AS last_member_name,
						mf.subject AS first_subject, mf.id_member AS first_id_member, COALESCE(memf.real_name, mf.poster_name) AS first_display_name, mf.poster_time AS first_poster_time, mf.icon AS first_icon, mf.poster_name AS first_member_name, b.name, ' . ($user_info['is_guest'] ? '0' : 'COALESCE(lt.id_msg, COALESCE(lmr.id_msg, -1)) + 1') . ' AS new_from,
						COALESCE(meml.real_name, ml.poster_name) AS last_display_name, ' . (!empty($modSettings['preview_characters']) ? '
						SUBSTRING(ml.body, 1, ' . ($modSettings['preview_characters'] + 256) . ') AS last_body,
						SUBSTRING(mf.body, 1, ' . ($modSettings['preview_characters'] + 256) . ') AS first_body,' : '') . 'ml.smileys_enabled AS last_smileys, mf.smileys_enabled AS first_smileys,' . ($db_type === 'postgresql' ? '
						ts_rank_cd(to_tsvector(mf.subject), to_tsquery({string:language}, {string:title}))' : '
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
						AND (t.approved = {int:is_approved}' . ($user_info['is_guest'] ? '' : ' OR t.id_member_started = {int:current_member}') . ')' . (!empty($modSettings['simtopics_only_cur_board']) ? '
						AND t.id_board = {int:current_board}' : '') . (!empty($context['simtopics_ignored_boards']) ? '
						AND t.id_board NOT IN ({array_int:ignore_boards})' : '') . '
						AND {query_wanna_see_board}
						AND {query_see_board}' . ($db_type === 'postgresql' ? '
						AND to_tsvector(mf.subject) @@ to_tsquery({string:language}, {string:title})' : '
						AND MATCH (mf.subject) AGAINST ({string:title} IN BOOLEAN MODE)') . '
					ORDER BY is_sticky DESC, {raw:sort_type}
					LIMIT {int:limit}',
					array(
						'language'       => $db_type === 'postgresql' ? $smcFunc['db_search_language']() : '',
						'title'          => $this->getCorrectTitle($context['subject']),
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
					if (!empty($modSettings['preview_characters']))	{
						$row['first_body'] = strip_tags(strtr(parse_bbc($row['first_body'], $row['first_smileys'], $row['id_first_msg']), array('<br>' => '&#10;')));
						if ($smcFunc['strlen']($row['first_body']) > $modSettings['preview_characters'])
							$row['first_body'] = $smcFunc['substr']($row['first_body'], 0, $modSettings['preview_characters']) . '...';

						censorText($row['first_subject']);
						censorText($row['first_body']);

						if ($row['id_first_msg'] == $row['id_last_msg']) {
							$row['last_subject'] = $row['first_subject'];
							$row['last_body']    = $row['first_body'];
						} else {
							$row['last_body'] = strip_tags(strtr(parse_bbc($row['last_body'], $row['last_smileys'], $row['id_last_msg']), array('<br>' => '&#10;')));
							if ($smcFunc['strlen']($row['last_body']) > $modSettings['preview_characters'])
								$row['last_body'] = $smcFunc['substr']($row['last_body'], 0, $modSettings['preview_characters']) . '...';

							censorText($row['last_subject']);
							censorText($row['last_body']);
						}
					} else {
						$row['first_body'] = '';
						$row['last_body']  = '';
						censorText($row['first_subject']);

						if ($row['id_first_msg'] == $row['id_last_msg'])
							$row['last_subject'] = $row['first_subject'];
						else
							censorText($row['last_subject']);
					}

					if (empty($context['icon_sources'])) {
						$context['icon_sources'] = array();
						foreach ($context['stable_icons'] as $icon)
							$context['icon_sources'][$icon] = 'images_url';
					}

					if (!empty($modSettings['messageIconChecks_enable'])) {
						if (!isset($context['icon_sources'][$row['first_icon']]))
							$context['icon_sources'][$row['first_icon']] = file_exists($settings['theme_dir'] . '/images/post/' . $row['first_icon'] . '.png') ? 'images_url' : 'default_images_url';
						if (!isset($context['icon_sources'][$row['last_icon']]))
							$context['icon_sources'][$row['last_icon']] = file_exists($settings['theme_dir'] . '/images/post/' . $row['last_icon'] . '.png') ? 'images_url' : 'default_images_url';
					} else {
						if (!isset($context['icon_sources'][$row['first_icon']]))
							$context['icon_sources'][$row['first_icon']] = 'images_url';
						if (!isset($context['icon_sources'][$row['last_icon']]))
							$context['icon_sources'][$row['last_icon']] = 'images_url';
					}

					$colorClass = 'windowbg';

					if ($row['is_sticky'])
						$colorClass .= ' sticky';

					if ($row['locked'])
						$colorClass .= ' locked';

					$context['similar_topics'][] = array(
						'id'         => $row['id_topic'],
						'first_post' => array(
							'id'        => $row['id_first_msg'],
							'member'    => array(
								'username' => $row['first_member_name'],
								'name'     => $row['first_display_name'],
								'id'       => $row['first_id_member'],
								'href'     => !empty($row['first_id_member']) ? $scripturl . '?action=profile;u=' . $row['first_id_member'] : '',
								'link'     => !empty($row['first_id_member']) ? '<a href="' . $scripturl . '?action=profile;u=' . $row['first_id_member'] . '" title="' . (isset($txt['profile_of']) ? ($txt['profile_of'] . ' ' . $row['first_display_name']) : sprintf($txt['view_profile_of_username'], $row['first_display_name'])) . '" class="preview">' . $row['first_display_name'] . '</a>': $row['first_display_name']
							),
							'time'      => timeformat($row['first_poster_time']),
							'subject'   => $row['first_subject'],
							'preview'   => $row['first_body'],
							'icon_url'  => $settings[$context['icon_sources'][$row['first_icon']]] . '/post/' . $row['first_icon'] . '.png',
							'link'      => '<a itemprop="relatedLink" href="' . $scripturl . '?topic=' . $row['id_topic'] . '.0">' . $row['first_subject'] . '</a>',
							'board'     => empty($modSettings['simtopics_only_cur_board']) ? '<a href="' . $scripturl . '?board=' . $row['id_board'] . '.0">' . $row['name'] . '</a>' : '',
						),
						'last_post' => array(
							'id'        => $row['id_last_msg'],
							'member'    => array(
								'username' => $row['last_member_name'],
								'name'     => $row['last_display_name'],
								'id'       => $row['last_id_member'],
								'href'     => !empty($row['last_id_member']) ? $scripturl . '?action=profile;u=' . $row['last_id_member'] : '',
								'link'     => !empty($row['last_id_member']) ? '<a href="' . $scripturl . '?action=profile;u=' . $row['last_id_member'] . '">' . $row['last_display_name'] . '</a>' : $row['last_display_name']
							),
							'time'      => timeformat($row['last_poster_time']),
							'subject'   => $row['last_subject'],
							'preview'   => $row['last_body'],
							'href'      => $scripturl . '?topic=' . $row['id_topic'] . ($user_info['is_guest'] ? ('.' . (!empty($options['view_newest_first']) ? 0 : ((int) (($row['num_replies']) / $context['pageindex_multiplier'])) * $context['pageindex_multiplier']) . '#msg' . $row['id_last_msg']) : (($row['num_replies'] == 0 ? '.0' : '.msg' . $row['id_last_msg']) . '#new')),
						),
						'is_sticky'   => !empty($row['is_sticky']),
						'is_locked'   => !empty($row['locked']),
						'is_redirect' => !empty($row['id_redirect_topic']),
						'is_poll'     => $modSettings['pollMode'] == '1' && $row['id_poll'] > 0,
						'icon'        => $row['first_icon'],
						'icon_url'    => $settings[$context['icon_sources'][$row['first_icon']]] . '/post/' . $row['first_icon'] . '.png',
						'subject'     => $row['first_subject'],
						'new'         => $row['new_from'] <= $row['id_msg_modified'],
						'new_href'    => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['new_from'] . '#new',
						'new_from'    => $row['new_from'],
						'views'       => $row['num_views'],
						'replies'     => $row['num_replies'],
						'css_class'   => $colorClass
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

	public function loadPermissions(array &$permissionGroups, array &$permissionList)
	{
		$permissionGroups['membergroup']['simple'] = array('simtopics');
		$permissionGroups['membergroup']['classic'] = array('simtopics');

		$permissionList['membergroup']['simtopics_view'] = array(false, 'simtopics', 'simtopics');
		$permissionList['membergroup']['simtopics_post'] = array(false, 'simtopics', 'simtopics');
	}

	public function adminAreas(array &$admin_areas)
	{
		global $txt;

		$admin_areas['config']['areas']['modsettings']['subsections']['simtopics'] = array($txt['similar_topics']);
	}

	public function adminSearch(array &$language_files, array &$include_files, array &$settings_search)
	{
		$settings_search[] = array(array($this, 'settings'), 'area=modsettings;sa=simtopics');
	}

	public function modifyModifications(array &$subActions)
	{
		$subActions['simtopics'] = array($this, 'settings');
	}

	/**
	 * @return array|void
	 */
	public function settings(bool $return_config = false)
	{
		global $context, $txt, $scripturl;

		loadTemplate('SimTopics');

		$context['page_title']     = $txt['simtopics_settings'];
		$context['settings_title'] = $txt['settings'];
		$context['post_url']       = $scripturl . '?action=admin;area=modsettings;save;sa=simtopics';

		$config_vars = array(
			array('int', 'simtopics_num_topics', 'subtext' => $txt['simtopics_nt_desc']),
			array('check', 'simtopics_only_cur_board', 'subtext' => $txt['simtopics_ocb_desc']),
			array('select', 'simtopics_when_new_topic', $txt['simtopics_when_new_topic_variants']),
			array('check', 'simtopics_on_display'),
			array('select', 'simtopics_position', $txt['simtopics_position_variants']),
			array('select', 'simtopics_sorting', $txt['simtopics_sorting_variants']),
			array('int', 'simtopics_cache_int', 'postinput' => $txt['simtopics_ci_post']),
			array('boards', 'simtopics_ignored_boards'),
			array('title', 'edit_permissions'),
			array('permissions', 'simtopics_view'),
			array('permissions', 'simtopics_post'),
			array('title', 'simtopics_displayed_columns'),
			array('callback', 'displayed_columns')
		);

		if ($return_config)
			return $config_vars;

		$context[$context['admin_menu_name']]['tab_data']['description'] = $txt['simtopics_desc'];

		// Saving?
		if (isset($_GET['save'])) {
			checkSession();

			$_POST['simtopics_displayed_columns'] = $columns = $_POST['displayed_column'] ?? [];

			$save_vars = $config_vars;
			$save_vars[] = ['select', 'simtopics_displayed_columns', $columns, 'multiple' => true];

			saveDBSettings($save_vars);
			redirectexit('action=admin;area=modsettings;sa=simtopics');
		}

		prepareDBSettingContext($config_vars);
	}

	private function prepareColumns()
	{
		global $modSettings, $txt, $context;

		$columns = smf_json_decode($modSettings['simtopics_displayed_columns'] ?? '', true);

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
}
