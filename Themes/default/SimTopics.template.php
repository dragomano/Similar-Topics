<?php

function template_simtopics()
{
	global $context, $settings, $txt;

	if (!empty($context['similar_topics']))	{
		echo '
		<div id="related_block" class="cat_bar">
			<h3 class="catbg">
				<span class="ie6_header floatleft">
					<img class="icon" alt="" src="', $settings['default_images_url'], '/similar.png" />
					', $txt['similar_topics'], ' (', count($context['similar_topics']), ')
				</span>
			</h3>
		</div>
		<div class="topic_table">
			<table class="table_grid" itemscope itemtype="http://schema.org/WebPage">
				<tbody>';

		foreach ($context['similar_topics'] as $topic) {
			if ($topic['is_sticky'] && $topic['is_locked'])
				$color_class = 'stickybg locked_sticky';
			elseif ($topic['is_sticky'])
				$color_class = 'stickybg';
			elseif ($topic['is_locked'])
				$color_class = 'lockedbg';
			else
				$color_class = 'windowbg';

			$alternate_class = $color_class . '2';

			echo '
					<tr>';

			if (!empty($context['simtopics_displayed_columns'][1]['show']))
				echo '
						<td class="icon2 ', $color_class, '" width="4%">
							<img src="', $topic['first_post']['icon_url'], '" alt="" />
						</td>';

			if (!empty($context['simtopics_displayed_columns'][2]['show'])) {
				echo '
						<td class="subject ', $alternate_class, '"', empty($context['simtopics_displayed_columns'][1]['show']) ? ' style="padding-left: 20px"' : '', '>
							', $topic['is_sticky'] ? '<strong>' : '', $topic['first_post']['link'], $topic['is_sticky'] ? '</strong>' : '';

				if ($topic['new'] && $context['user']['is_logged'])
					echo '
							<a href="', $topic['new_href'], '" id="newicon' . $topic['first_post']['id'] . '"><img src="', $settings['lang_images_url'], '/new.gif" alt="', $txt['new'], '" /></a>';

				echo '
							<p>', $txt['started_by'], ' ', $topic['first_post']['member_link'], !empty($topic['first_post']['board']) ? '<span class="floatright">' . $txt['board'] . ' ' . $topic['first_post']['board'] . '</span>' : '', '</p>
						</td>';
			}

			if (!empty($context['simtopics_displayed_columns'][3]['show']))
				echo '
						<td class="stats ', $color_class, '" width="14%">
							', $txt['replies'], ': ', $topic['replies'], '<br />', $txt['views'], ': ', $topic['views'], '
						</td>';

			if (!empty($context['simtopics_displayed_columns'][4]['show']))
				echo '
						<td class="lastpost ', $alternate_class, '" width="22%">
							<a href="', $topic['last_post']['href'], '">
								<img src="', $settings['images_url'], '/icons/last_post.gif" alt="', $txt['last_post'], '" title="', $txt['last_post'], '" />
							</a>
							', $topic['last_post']['time'], '<br />
							', $txt['by'], ' ', $topic['last_post']['member_link'], '
						</td>
					</tr>';
		}

		echo '
				</tbody>
			</table>
		</div>';
	}
}

function template_simtopics_top_above()
{
	template_simtopics();
}

function template_simtopics_top_below()
{
}

function template_simtopics_bot_above()
{
}

function template_simtopics_bot_below()
{
	template_simtopics();
}

function template_callback_displayed_columns()
{
	global $context;

	echo '
		<dt></dt><dd></dd></dl>
		<ul class="ignoreboards floatleft" style="margin-top: -30px">';

	$i = 0;
	$limit = ceil(count($context['simtopics_displayed_columns']) / 2);
	foreach ($context['simtopics_displayed_columns'] as $column) {
		if ($i == $limit)
			echo '
		</ul>
		<ul class="ignoreboards floatright" style="margin-top: -30px">';

		echo '
			<li class="category">
				<label for="ignore_column', $column['id'], '">
					<input type="checkbox" id="ignore_column', $column['id'], '" name="ignore_column[', $column['id'], ']" value="', $column['id'], '"', !empty($column['show']) ? ' checked="checked"' : '', ' class="input_check"', $column['protect'] ? ' disabled="true"' : '', ' /> ', $column['name'], '
				</label>
			</li>';

		$i++;
	}

	echo '
		</ul>
		<br class="clear" />
		<dl><dt></dt><dd></dd>';
}

function template_callback_ignored_boards()
{
	global $context;

	echo '
		<dt></dt><dd></dd></dl>
		<ul class="ignoreboards floatleft" style="margin-top: -30px">';

	$i = 0;
	$limit = ceil($context['num_boards'] / 2);

	foreach ($context['categories'] as $category) {
		if ($i == $limit) {
			echo '
		</ul>
		<ul class="ignoreboards floatright" style="margin-top: -30px">';

			$i++;
		}

		echo '
			<li class="category">
				<strong>', $category['name'], '</strong>
				<ul>';

		foreach ($category['boards'] as $board)	{
			if ($i == $limit)
				echo '
				</ul>
			</li>
		</ul>
		<ul class="ignoreboards floatright">
			<li class="category">
				<ul>';

			echo '
					<li class="board" style="margin-', $context['right_to_left'] ? 'right' : 'left', ': ', $board['child_level'], 'em;">
						<label for="ignore_board', $board['id'], '"><input type="checkbox" id="ignore_board', $board['id'], '" name="ignore_board[', $board['id'], ']" value="', $board['id'], '"', $board['selected'] ? ' checked="checked"' : '', ' class="input_check" /> ', $board['name'], '</label>
					</li>';

			$i++;
		}

		echo '
				</ul>
			</li>';
	}

	echo '
		</ul>
		<br class="clear" />
		<dl><dt></dt><dd></dd>';
}
