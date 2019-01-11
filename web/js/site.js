$(function()
{
	$(".main-nav li[data-submenu]").hover(function()
	{
		$($(this).data("submenu")).addClass("open");
	}, function()
	{
		$(".drop-down-nav").removeClass("open");
	});
	$("#hamburger").click(function(e)
	{
		e.preventDefault();
		$(".mobile-menu").toggle();
	});
});