$(function()
{
	$(".main-nav a").hover(function()
	{
		$(".drop-down-nav").addClass("open");
	}, function()
	{
		$(".drop-down-nav").removeClass("open");
	});
});