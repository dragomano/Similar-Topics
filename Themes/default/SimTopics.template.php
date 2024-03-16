<?php

function template_simtopics()
{
	global $context, $txt, $modSettings;

	if (!empty($context['similar_topics']))	{
		echo '
		<div class="cat_bar clear">
			<h3 class="catbg">', $txt['similar_topics'], ' (', count($context['similar_topics']), ')</h3>
		</div>
		<div id="messageindex" style="margin-bottom: 0.6em">
			<div id="topic_container" itemscope itemtype="https://schema.org/WebPage">';

		foreach ($context['similar_topics'] as $topic) {
			echo '
				<div class="', $topic['css_class'], '">';

			if (!empty($context['simtopics_displayed_columns'][1]['show'])) {
				echo '
					<div class="board_icon">
						<img src="', $topic['first_post']['icon_url'], '" alt="', $topic['id'], '">
					</div>';
			}

			if (!empty($context['simtopics_displayed_columns'][2]['show'])) {
				echo '
					<div class="info info_block"', empty($context['simtopics_displayed_columns'][1]['show']) ? ' style="padding-left: 20px"' : '', '>
						<div class="icons floatright">';

				if ($topic['is_locked']) {
					echo '
							<span class="main_icons lock"></span>';
				}

				if ($topic['is_sticky']) {
					echo '
							<span class="main_icons sticky"></span>';
				}

				if ($topic['is_redirect']) {
					echo '
							<span class="main_icons move"></span>';
				}

				if ($topic['is_poll']) {
					echo '
							<span class="main_icons poll"></span>';
				}

				echo '
						</div>
						<div class="message_index_title">
							', $topic['new'] && $context['user']['is_logged'] ? '<a href="' . $topic['new_href'] . '" id="newicon' . $topic['first_post']['id'] . '"><span class="new_posts">' . $txt['new'] . '</span></a>' : '', '
							<span class="preview', $topic['is_sticky'] ? ' bold_text' : '', '" title="', $topic[(empty($modSettings['message_index_preview_first']) ? 'last_post' : 'first_post')]['preview'], '">
								<span id="msg_', $topic['first_post']['id'], '">', $topic['first_post']['link'], '</span>
							</span>
						</div>
						<p class="floatleft">', $txt['started_by'], ' ', $topic['first_post']['member']['link'], '</p>
						<br class="clear">
					</div>';
			}

			if (!empty($context['simtopics_displayed_columns'][3]['show'])) {
				echo '
					<div class="board_stats centertext">
						<p>', $txt['replies'], ': ', $topic['replies'], '<br>', $txt['views'], ': ', $topic['views'], '</p>
					</div>';
			}

			if (!empty($context['simtopics_displayed_columns'][4]['show'])) {
				echo '
					<div class="lastpost">
						', sprintf($txt['last_post_topic'], '<a href="' . $topic['last_post']['href'] . '">' . $topic['last_post']['time'] . '</a>', $topic['last_post']['member']['link']), '
					</div>';
			}

			echo '
				</div>';
		}

		echo '
			</div>
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
		<ul class="half_content">';

	$i = 0;
	$limit = ceil(count($context['simtopics_displayed_columns']) / 2);
	foreach ($context['simtopics_displayed_columns'] as $column) {
		if ($i == $limit)
			echo '
		</ul>
		<ul class="half_content">';

		echo '
			<li>
				<label for="displayed_column', $column['id'], '">
					<input type="checkbox" id="displayed_column', $column['id'], '" name="displayed_column[', $column['id'], ']" value="', $column['id'], '"', !empty($column['show']) ? ' checked="checked"' : '', ' class="input_check"', $column['protect'] ? ' disabled="true"' : '', '> ', $column['name'], '
				</label>
			</li>';

		$i++;
	}

	echo '
		</ul>
		<br class="clear">';
}
