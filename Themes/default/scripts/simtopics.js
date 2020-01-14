(function($) {
	function get_simtopics() {
		var tlist = $(".table_grid");
		$(tlist).html(ajax_notification_text);

		var request = $.ajax({
			type : "POST",
			dataType : "json",
			url: simOpt.url + "?action=post",
			data: {"query": $("input[name=\"subject\"]").val(), "board": simOpt.cur_board}
		});

		request.done(function(e) {
			$(tlist).html("");

			if (e.error == false) {
				var topics = e.topics;

				if (topics == false)
					$(tlist).append("<div class=\"information\">" + simOpt.no_result + "</div>")
				else {
					for (id in topics) {
						var topic = topics[id];

						if (typeof topic == "object") {
							var color_class = "";

							if (topic.is_sticky == true && topic.locked == true)
								color_class = 'stickybg locked_sticky';
							else if (topic.is_sticky == true)
								color_class = 'stickybg';
							else if (topic.locked == true)
								color_class = 'lockedbg';
							else
								color_class = 'windowbg';

							var alt_class = color_class + '2';

							$(tlist).append("<tr><td class=\"icon2 " + color_class + "\" width=\"4%\"><img alt=\"\" src=\"" + smf_images_url + "/post/" + topic.icon + ".gif\" /></td><td class=\"subject " + alt_class + "\"><strong><a href=\"" + simOpt.url + "index.php?topic=" + topic.id_topic + ".0\">" + topic.subject + "</a></strong><p>" + simOpt.by + " <a href=\"" + simOpt.url + "index.php?action=profile;u=" + topic.id_author + "\">"+ topic.author +  "</a><span class=\"floatright\">" + simOpt.board + " <a href=\"" + simOpt.url + "index.php?board=" + topic.id_board + ".0\">" + topic.bname + "</a></span></p></td><td class=\"stats " + color_class + "\" width=\"14%\">" + simOpt.replies + ": " + topic.num_replies + "<br />" + simOpt.views + ": " + topic.num_views + "</td></tr>");
						}
					}
				}
			} else
				$(tlist).append("<div class=\"error information\">" + e.msg + "</div>");
		});
	};

	$("#post_header").after("<div class=\"title_barIC\"><h4 class=\"titlebg\"><span class=\"ie6_header floatleft\"><img alt=\"\" src=" + smf_default_theme_url + "/images/similar.png" + " class=\"icon\" />" + simOpt.title + "</span></h4></div><div class=\"topic_table\"><table class=\"table_grid\"><tbody></tbody></table></div>");

	$("hr.clear").css("display", "none");

	get_simtopics();

	$("input[name=\"subject\"]").on("keyup", function() {
		get_simtopics();
	});
})(jQuery);