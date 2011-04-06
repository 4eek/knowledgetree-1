/* Functionality required for new UI */
jQuery(document).ready(function() {

    //jQuery(".form_actions input[type='submit']:last-child").css({background:"url('resources/graphics/newui/gridtoolbarright.png') 100% 50% no-repeat"});

    jQuery(".form_actions input[type='submit']:last-child").css({background:"none"});
    jQuery(".form_actions a:last-child").css({background:"none"});
    jQuery('.form_actions').prepend('<div class="roundleft"></div>').prepend('<div class="roundright2"></div>');

    if (jQuery("#middle_nav ul").length == 1) {
        //jQuery("#middle_nav").css({display:'none'});
        //jQuery('#breadcrumbs').appendTo(jQuery('#middle_nav'));
    }

    jQuery('.buttonsList').appendTo(jQuery('#bigbuttons'));

});

/**
 * Documents View Page
 */

(function($){
	$(document).ready(function(){
		$('#activityfeed-container').html($('#viewlet-activityfeed').html());
		$('#viewlet-activityfeed').remove();
		$('.withviewlets').removeClass('withviewlets');

		$('#doc_thumb').append($('.thumb-shadow img')).addClass('thumb-shadow').css({'margin-right': '15px', 'margin-bottom': '15px'});
		$('#doc_thumb img').css({width: '105px'});

		$('.view_doc_tabs').buttontabs({
			containerId:'doc_view_container',
			containerClass:''
		});
		kt.lib.setFooter();

		$('td.info a').prepend('<img class="leftimg" src="resources/graphics/newui/midbarleft.png" />');
		$('td.info a').prepend('<img class="rightimg" src="resources/graphics/newui/midbarright.png" />');

		$('a.arrow_upload').attr('href', 'javascript:kt.app.upload.showUploadWindow();');
//
//		$.ajax({
//			url: '/upload.html',
//			success: function(data) {
//			  $('body').append(data);
//			}
//		});
	});
})(jQuery);

jQuery(function() {

				jQuery(".split").click(function() {

					if (jQuery(this).parent().next().is(':visible'))
					{
						jQuery(".splitmenu").hide();
						jQuery(".split").removeClass('selected');

					}
					else
					{
						jQuery(".splitmenu").hide();
						jQuery(".split").removeClass('selected');
						jQuery(this).addClass('selected').parent().next().show();

					}

				});

				 jQuery('html').click(function() {
				 	jQuery(".splitmenu").hide();
				 	jQuery(".split").removeClass('selected');
				 });

				jQuery('.split').click(function(event){
				     event.stopPropagation();
				 });
			});

/**
 * Functions to clear default form text
 */
(function($){
	$(document).ready(function()
	{
	    $(".default-text").focus(function(srcc)
	    {
	        if ($(this).val() == $(this)[0].title)
	        {
	            $(this).removeClass("default-text-active");
	            $(this).val("");
	        }
	    });

	    $(".default-text").blur(function()
	    {
	        if ($(this).val() == "")
	        {
	            $(this).addClass("default-text-active");
	            $(this).val($(this)[0].title);
	        }
	    });

	    $(".default-text").blur();
	});
})(jQuery);
/**
 * Functions to float the footer. setFooter() is called from <body> onLoad()
 */

kt.lib.getWindowHeight = function() {
	var windowHeight = 0;
	if (typeof(window.innerHeight) == 'number') {
		windowHeight = window.innerHeight;
	}
	else {
		if (document.documentElement && document.documentElement.clientHeight) {
			windowHeight = document.documentElement.clientHeight;
		}
		else {
			if (document.body && document.body.clientHeight) {
				windowHeight = document.body.clientHeight;
			}
		}
	}
	return windowHeight;
}

/* We set the top margin of the footer to keep the footer at the bottom of the window */
kt.lib.setFooter = function() {
	var diff = (kt.lib.getWindowHeight() - (jQuery('#wrapper').height() + jQuery('#footer').height()));
	if (diff > 0) {
		jQuery('#footer').css('margin-top', diff);
	} else {
		jQuery('#footer').css('margin-top', '0');
	}
}
