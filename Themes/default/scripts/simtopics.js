(function () {
  function get_simtopics() {
    const tlist = document.getElementById('topic_container');

    tlist.innerHTML =
      '<div class="infobox" style="margin-bottom: -10px">' + ajax_notification_text + '</div>';

    const params = new URLSearchParams({
        query: document.querySelector('input[name="subject"]').value,
        board: simOpt.cur_board,
    });

    fetch(smf_scripturl + '?action=post', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
      },
      body: params.toString(),
    })
      .then((response) => response.json())
      .then((data) => {
        tlist.innerHTML = '';

        if (data.error === false) {
          const topics = data.topics;

          if (topics === false) {
            tlist.innerHTML +=
              '<div class="noticebox" style="margin-bottom: -10px">' + simOpt.no_result + '</div>';
          } else {
            for (let id in topics) {
              let topic = topics[id];

              if (typeof topic === 'object') {
                let color_class = 'windowbg';

                if (topic.is_sticky === true && topic.locked === true)
                  color_class += ' sticky locked';
                else if (topic.is_sticky === true) color_class += ' sticky';
                else if (topic.locked === true) color_class += ' locked';

                let topic_author = ' <strong>' + topic.author + '</strong>';

                if (topic.id_author !== 0)
                  topic_author =
                    ' <a href="' + smf_scripturl + '?action=profile;u=' + topic.id_author + '">' + topic.author + '</a>';

                tlist.innerHTML +=
                  '<div class="' +
                  color_class +
                  '" style="padding: 0.6em"><div class="info info_block"><div><div class="message_index_title"><span><a href="' +
                  smf_scripturl +
                  '?topic=' +
                  topic.id_topic +
                  '.0">' +
                  topic.subject +
                  '</a></span></div><p class="floatleft">' +
                  simOpt.by +
                  topic_author +
                  '</p><span class="floatright">' +
                  simOpt.board +
                  ' <a href="' +
                  smf_scripturl +
                  '?board=' +
                  topic.id_board +
                  '.0">' +
                  topic.bname +
                  '</a></span></div></div><div class="board_stats floatright">' +
                  simOpt.replies +
                  ': ' +
                  topic.num_replies +
                  '<br>' +
                  simOpt.views +
                  ': ' +
                  topic.num_views +
                  '</div></div>';
              }
            }
          }
        } else {
          tlist.innerHTML += '<div class="error information">' + data.msg + '</div>';
        }
      });
  }

  const block =
    '<div class="cat_bar"><h3 class="catbg">' +
    simOpt.title +
    '</h3></div><div id="topic_container"></div>';

  simOpt.show_top
    ? document.getElementById('postmodify').insertAdjacentHTML('beforebegin', block)
    : document.getElementById('postmodify').insertAdjacentHTML('afterend', block);

  get_simtopics();

  document.querySelector('input[name="subject"]').addEventListener('blur', function () {
    if (this.value.length < 1) return true;

    get_simtopics();
  });
})();
