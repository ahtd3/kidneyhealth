/**
 * Prevents featherlight from dissapearing when you drag from the cropping area to outside of the featherlight content
 */
$(function()
{
	//add variable to cropping area so we know a cropping drag is happening
	$.featherlight.defaults.afterOpen = function()
	{
		$('.featherlight .featherlight-content').on('mousedown', function(event)
		{
			console.log(event.target);
			$(this).data().mousedown = true;
		});

		$('.featherlight .featherlight-content').on('mousemove', function(event)
		{
			if ($(this).data().mousedown) 
			{
				$(this).data().dragging = true;
			}
		});
	};

	//check if cropping drag is happening and remove it after giving featherlight a chance to pick it up
	$('body').on('mouseup', function(event)
	{
		var $popContent = $('.featherlight .featherlight-content');

		if ($popContent.length > 0) 
		{
			window.setTimeout(function()
			{
				$popContent.data().mousedown = false;
				$popContent.data().dragging = false;
			}, 0);
		} 
	});

	//check for cropping drag variable and prevent featherlight from closing
	$.featherlight.defaults.beforeClose = function(event)
	{
		var $popContent = $('.featherlight .featherlight-content');

		if ($popContent.length > 0 && $popContent.data().dragging !== undefined && $popContent.data().dragging === true)
		{
			return false;
		}
	}
});
