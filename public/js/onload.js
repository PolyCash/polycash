$(document).on('expanded.pushMenu', function() {toggle_push_menu('expand');});
$(document).on('collapsed.pushMenu', function() {toggle_push_menu('collapse');});

$(window).keydown(function(e){
	var key = (e.which) ? e.which : e.keyCode;
	if (key == 13) {
		if ($(":focus").attr("class") == "chatWindowWriter") {
			var chatWindowId = $(":focus").attr("id").replace(/chatWindowWriter/g, '');
			sendChatMessage(chatWindowId);
		}
	}
});