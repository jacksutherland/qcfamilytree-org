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

	$(".contact-form").submit(function(e)
	{
		e.preventDefault();

		var form = $(this);
		var action = form.attr("action");

		// $.ajax({
		//   type: "POST",
		//   url: action,
		//   data: form.serialize(),
		//   dataType: dataType
		//   success: success,
		// });

		$.post( action, form.serialize())
		.done(function( data )
		{
			if (data == "success")
			{
				data = form.find(".ty-message").text();
				form[0].reset();
			}
		    form.find(".contact-form-errors").html( data );

		});

	});
});