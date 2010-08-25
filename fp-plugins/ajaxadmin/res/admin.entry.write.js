
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

		$('#preview').click(function() {

		  var formData = $("form").getFormValues();
		  formData.preview = 'Preview';
		  console.log(formData);
		  $.post($('form').attr('action'), formData, 
			function(data){
				$('#post-preview').replaceWith($(data).find('#post-preview'));
		     	$('html,body').animate({scrollTop: $("#post-preview").offset().top},'slow');		
		  	});
		  return false;

		});
		});
		
		
