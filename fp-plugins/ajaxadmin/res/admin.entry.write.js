
jQuery.fn.getFormValues = function(){ 
    var formvals = {}; 
    jQuery.each(jQuery(':input',this).serializeArray(),function(i,obj){ 
        if (formvals[obj.name] == undefined) 
            formvals[obj.name] = obj.value; 
        else if (typeof formvals[obj.name] == Array) 
            formvals[obj.name].push(obj.value); 
        else formvals[obj.name] = [formvals[obj.name],obj.value]; 
    }); 
    return formvals; 
} 



$(document).ready(function() {

	var preview = function(adminEvent) {

		var formData = $("form").getFormValues();
		formData [ adminEvent ] = 1;

		$.post($('form').attr('action'), formData, 
			function(data){
				
				$('#errorlist').replaceWith($(data).find('#errorlist'));
				$('#admin-post-preview').replaceWith($(data).find('#admin-post-preview'));
				$('input[type=hidden]').replaceWith($(data).find('input[type=hidden]'));
				if ([]!==$('#errorlist .errors')) $('.field-error').removeClass('field-error');


				setTimeout(function(){$('ul.msgs').fadeOut();},5000);
		     	$('html,body').animate({scrollTop: $("#errorlist").offset().top},'slow');		
		});
		return false;

	};

	$('#savecontinue').click(function() { return preview('savecontinue'); });
	$('#preview').click(function() { return preview('preview'); });

});

