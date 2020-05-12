$(document).on('expanded.pushMenu', function() {thisPageManager.toggle_push_menu('expand');});
$(document).on('collapsed.pushMenu', function() {thisPageManager.toggle_push_menu('collapse');});
$.ajaxSetup({ cache: false });

$(window).keydown(function(e){
	var key = (e.which) ? e.which : e.keyCode;
	if (key == 13) {
		if ($(":focus").attr("class") == "chatWindowWriter") {
			var chatWindowId = $(":focus").attr("id").replace(/chatWindowWriter/g, '');
			sendChatMessage(chatWindowId);
		}
	}
});