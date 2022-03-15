(function($) {
	function get_simtopics() {
		let tlist = $("#topic_container");

		$(tlist).html('<div class="infobox" style=\"margin-bottom: -10px\">' + ajax_notification_text + '</div>');

		let request = $.ajax({
			type : "POST",
			dataType : "json",
			url: smf_scripturl + "?action=post",
			data: {"query": $("input[name=\"subject\"]").val(), "board": simOpt.cur_board}
		});

		request.done(function(e) {
			$(tlist).html("");

			if (e.error == false) {
				let topics = e.topics;

				if (topics == false) {
					$(tlist).append("<div class=\"noticebox\" style=\"margin-bottom: -10px\">" + simOpt.no_result + "</div>")
				} else {
					for (id in topics) {
						let topic = topics[id];

						if (typeof topic == "object") {
							let color_class = "windowbg";

							if (topic.is_sticky == true && topic.locked == true)
								color_class += ' sticky locked';
							else if (topic.is_sticky == true)
								color_class += ' sticky';
							else if (topic.locked == true)
								color_class += ' locked';

							let topic_author = " <strong>" + topic.author + "</strong>";

							if (topic.id_author != 0)
								topic_author = " <a href=\"" + smf_scripturl + "?action=profile;u=" + topic.id_author + "\">" + topic.author +  "</a>";

							$(tlist).append("<div class=\"" + color_class + "\" style=\"padding: 0.6em\"><div class=\"info info_block\"><div><div class=\"message_index_title\"><span><a href=\"" + smf_scripturl + "?topic=" + topic.id_topic + ".0\">" + topic.subject + "</a></span></div><p class=\"floatleft\">" + simOpt.by + topic_author + "</p><span class=\"floatright\">" + simOpt.board + " <a href=\"" + smf_scripturl + "?board=" + topic.id_board + ".0\">" + topic.bname + "</a></span></div></div><div class=\"board_stats floatright\">" + simOpt.replies + ": " + topic.num_replies + "<br>" + simOpt.views + ": " + topic.num_views + "</div></div>");
						}
					}
				}
			} else {
				$(tlist).append("<div class=\"error information\">" + e.msg + "</div>");
			}
		});
	};

	let block = "<div class=\"cat_bar\"><h3 class=\"catbg\">" + simOpt.title + "</h3></div><div id=\"topic_container\"></div>";

	simOpt.show_top ? $("#postmodify").before(block) : $("#postmodify").after(block);

	get_simtopics();

	$("input[name=\"subject\"]").on("keyup", function() {
		if(this.value.length < 1) return true;

		let timer;
		clearTimeout(timer);
		timer = setTimeout(() => get_simtopics(), 1800);
	});
})(jQuery);