if('undefined'==typeof bbtCustomFunctions) {
	var bbtCustomFunctions={};
}
if(undefined!=typeof window.jQuery) {
(function($) {

	vdfnTag={
		/**
		 * This is the tag input
		 *
		 * @var string
		 */
		'input' : '#taginput',

		/**
		 * This is the place where we put tags
		 *
		 * @var string
		 */
		'tagplace' : '#tagplace',

		/**
		 * This is the selctor for the labels of tags.
		 *
		 * @var string
		 */
		'labels' : '#tagplace span',

		/**
		 * This is the textarea of the content
		 *
		 * @var string
		 */
		'textarea' : 'textarea#content',

		/**
		 * This is the list of current tags.
		 * It's used to not duplicate tags.
		 *
		 * @var array
		 */
		'current' : [],

		/**
		 * This is the timeout for the AJAX request.
		 *
		 * @var ???
		 */
		'timeout' : null,

		/**
		 * This is the original value before suggestions.
		 *
		 * @var string
		 */
		'original' : '',

		/**
		 * This function initializes the whole system.
		 */
		'init' : function() {
			target=$(vdfnTag.input);

			// Not standard yet :-(
			target.attr('autocomplete', 'off');

			// Remove [tag] from textarea
			vdfnTag.rmTextarea();
			$('textarea#content').blur(vdfnTag.rmTextarea);

			// Init our system
			vdfnTag.add();
			target.val('');
			target.keydown(vdfnTag.keypress).focus(vdfnTag.keypress);

			// Restore the tag in the input at the submissum of the form
			$('form').submit(vdfnTag.onsubmit);
		},

		/**
		 * This function removes tags from the input and
		 * add them to the tagplace.
		 */
		'add' : function() {
			target=$(vdfnTag.input);
			// Get the current tags and put them in an array
			tags=$(target).val();
			tags=$.trim(tags);
			tags=tags.split(',');

			// Parse every tag
			for(i=0; i<tags.length; i++) {
				// Clean the tag
				tags[i]=$.trim(tags[i]);

				if(tags[i]=='') {
					// Not a real tag
				} else if($.inArray(tags[i], vdfnTag.current)!=-1) {
					// Don't duplicate
				} else {
					// Create the span
					span=$('<span></span>').text(tags[i]).attr('title', vdfnTagRemove);
					// Remove when you click on them
					span.click(function() {
						tag=$(this).text();
						vdfnTag.current.splice(vdfnTag.current.indexOf(tag), 1);
						$(this).remove();
					});
					// Append the span to the tagplace
					$(vdfnTag.tagplace).append(span, ' ');
					// Add the tag to our current list
					vdfnTag.current.push(tags[i]);
				}

			}

			$(target).val('');

			// Delete the suggestions
			vdfnTag.destroySugg();
		},

		/**
		 * This function strips [tag]...[/tag] from the textarea.
		 */
		'rmTextarea' : function() {
			// Get the scroll of the textarea
			scrollL=$(vdfnTag.textarea).scrollLeft();
			scrollT=$(vdfnTag.textarea).scrollTop();

			// Get the textarea content
			textarea=$(vdfnTag.textarea).val();

			// The regexp of the tag tags
			pattern=/\[tag\](.*)\[\/tag\]/im;

			// To work with multiple tag tags
			while(null!=(found=pattern.exec(textarea))) {
				val=$(target).val();
				val+=','+found[1];
				$(vdfnTag.input).val(val);
				textarea=textarea.replace(found[0], '');
			}

			// Finally remove the tags also from the field
			$(vdfnTag.textarea).val(textarea);
			vdfnTag.add();

			// Reset the scroll of the textarea
			$(vdfnTag.textarea).scrollLeft(scrollL);
			$(vdfnTag.textarea).scrollTop(scrollT);

			return true;
		},

		/**
		 * This function is called when the form is submitted.
		 */
		'onsubmit' : function() {
			target=$(vdfnTag.input);
			tagadd='';

			$(vdfnTag.labels).each(function() {
				tagadd+=$(this).text()+',';
			});

			tagadd+=target.val();
			target.unbind();
			target.val(tagadd);
		},

		/**
		 * This function is the handler for the keypress event.
		 *
		 * @param object event: The event data
		 * @return boolean
		 */
		'keypress' : function(event) {
			target=$(this);

			if(event.keyCode==13 || event.keyCode==188) {
				// 13: Enter; 44: Comma
				vdfnTag.add(target);
				return false;

			} else if(event.keyCode==38 && $('.tagsuggestions').length>0) {
				// Up key
				current=$('.tagsuggestions .over');

				if(current.length==0) {
					vdfnTag.suggover($('.tagsuggestions li:last'), true);
				} else if(current.is('.tagsuggestions li:first-child')) {
					$(this).val(vdfnTag.original);
					vdfnTag.suggout(current, true);
				} else {
					vdfnTag.suggout(current, true);
					vdfnTag.suggover(current.prev(), true);
				}


			} else if(event.keyCode==40 && $('.tagsuggestions').length>0) {
				// Down key
				current=$('.tagsuggestions .over');

				if(current.length==0) {
					vdfnTag.suggover($('.tagsuggestions li:first'), true);
				} else if(current.is('.tagsuggestions li:last-child')) {
					$(this).val(vdfnTag.original);
					vdfnTag.suggout(current, true);
				} else {
					vdfnTag.suggout(current, true);
					vdfnTag.suggover(current.next(), true);
				}

			} else if(event.keyCode==9) {
				// Tab: change input --> Blur
				return true;

			} else {
				// It's better wainting for a timeout before making the request
				clearTimeout(vdfnTag.timeout);
				vdfnTag.timeout=setTimeout(function () {
					suggtag=$(vdfnTag.input).val();
					$.ajax({
						'url' : vdfnTagUrl,
						'dataType' : 'html',
						'data' : 'tag='+suggtag,
  						'success' : vdfnTag.suggestions,
					});
				}, 60);
				return true;
			}

			return false;
		},

		/**
		 * This function creates the suggestions.
		 * It's called by the success of AJAX.
		 *
		 * @param string data: The response of ajax
		 */
		'suggestions' : function(data) {
			// Save the last value before sugestions
			vdfnTag.original=$(vdfnTag.input).val();

			// First of all: clean other suggestions
			vdfnTag.destroySugg();

			// Length==0? No suggestions!
			if(data.length==0) {
				return;
			}

			// Create the suggestions div
			div=$('<div></div>').addClass('tagsuggestions').html(data);
			div.appendTo('body');

			// Get the position
			target=$(vdfnTag.input);
			toppos=target.offset().top+target.outerHeight()+'px';
			leftpos=target.offset().left+'px';
			// Get the width: compute border and add them to te outer width of the target
			widthval=div.width()-div.outerWidth()+target.outerWidth()+'px';
			div.css({
				'top' : toppos,
				'left' : leftpos,
				'width' : widthval,
			});

			// Use mouse function instead of :hover to handle keyboard
			$('.tagsuggestions li').click(function() {
				$(vdfnTag.input).val($(this).text());
				vdfnTag.add();
				return false;
			}).mouseover(vdfnTag.suggover).mouseout(vdfnTag.suggout);

			// On blur we remove the suggestions
			target.one('blur', vdfnTag.destroySugg);
		},

		/**
		 * This function destroys the suggestions when you blur
		 * the tag input.
		 *
		 * @return boolean
		 */
		'destroySugg' : function() {
			$('.tagsuggestions').remove();
			return true;
		},

		/**
		 * This function is called to highlight a suggestion.
		 *
		 * @param mixed element: The element
		 * @param boolean iskeyboard
		 */
		'suggover' : function(element, iskeyboard) {
			if(!iskeyboard) {
				element=this;
			}

			$(element).addClass('over');

			if(iskeyboard) {
				$(vdfnTag.input).val($(element).text());
			} else {
				$(vdfnTag.input).unbind('blur');
			}
		},

		/**
		 * This function is called to remove highlight from a suggestion.
		 *
		 * @param mixed element: The element
		 * @param boolean iskeyboard
		 */
		'suggout' : function(element, iskeyboard) {
			if(!iskeyboard) {
				element=this;
			}

			$(element).removeClass('over');

			if(!iskeyboard) {
				$(vdfnTag.input).one('blur', vdfnTag.destroySugg);
			}
		},

	}

	$(document).ready(vdfnTag.init);

	/**
	 * This function modifies the default behavior of BBToolbar's
	 * tag code.
	 *
	 * @return boolean
	 */
	bbtCustomFunctions.tag=function() {
		target=$(vdfnTag.input);
		target.focus();
		$(window).scrollTop(target.offset().top);
		return false;
	};
})(window.jQuery);
}