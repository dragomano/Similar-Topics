(function($) {
	function get_simtopics() {
		let tlist = $("#topic_container");
		$(tlist).html(ajax_notification_text);

		let request = $.ajax({
			type : "POST",
			dataType : "json",
			url: simOpt.url + "?action=post",
			data: {"query": $("input[name=\"subject\"]").val(), "board": simOpt.cur_board}
		});

		request.done(function(e) {
			$(tlist).html("");

			if (e.error == false) {
				let topics = e.topics;

				if (topics == false) {
					$(tlist).append("<div class=\"noticebox\">" + simOpt.no_result + "</div>")
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
								topic_author = " <a href=\"" + simOpt.url + "index.php?action=profile;u=" + topic.id_author + "\">"+ topic.author +  "</a>";

							$(tlist).append("<div class=\"" + color_class + "\" style=\"padding: 0.6em\"><div class=\"info info_block\"><div><div class=\"message_index_title\"><span><a href=\"" + simOpt.url + "index.php?topic=" + topic.id_topic + ".0\">" + topic.subject + "</a></span></div><p class=\"floatleft\">" + simOpt.by + topic_author + "</p><span class=\"floatright\">" + simOpt.board + " <a href=\"" + simOpt.url + "index.php?board=" + topic.id_board + ".0\">" + topic.bname + "</a></span></div></div><div class=\"board_stats floatright\">" + simOpt.replies + ": " + topic.num_replies + "<br>" + simOpt.views + ": " + topic.num_views + "</div></div>");
						}
					}
				}
			} else {
				$(tlist).append("<div class=\"error information\">" + e.msg + "</div>");
			}
		});
	};

	$("#post_header").before("<div class=\"generic_list_wrapper\" style=\"padding: 8px 10px;	border-radius: 6px 6px 0 0\"><h4><span class=\"main_icons frenemy\"></span> " + simOpt.title + "</h4></div><div id=\"messageindex\" style=\"margin-bottom: 0.2em\"><div id=\"topic_container\"></div></div>");

	get_simtopics();

	$("input[name=\"subject\"]").on("keyup", function() {
		get_simtopics();
	});
})(jQuery);