(function($)
{
	// let IS_DEBUG_IP = false; $.get('/processes/ajax/is-debug-ip.php', '', function(data){ IS_DEBUG_IP = data.result; }, 'json' ); // debugging helper

	$(document).ready(function()
	{
		let licenceKey = ""; // We've got an actual license somewhere, but this stops it displaying a warning, so good enough
		lightGallery($(".gallery").get(0), {plugins: [lgThumbnail], thumbnail: true, exThumbImage: 'data-thumb', licenseKey: licenceKey});
		lightGallery($("product-images").get(0), {selector: '.image', licenseKey: licenceKey});

		//forms
		setupForms();

		$(".popup").click(function(event)
		{
			event.preventDefault();

			// noinspection JSUnusedGlobalSymbols
			$.featherlight($(this).attr('href') + '?popup',
			{
				afterOpen: function()
				{
					setupForms('.featherlight');
				}
			});
		});

		//featherlight form popup
		$("body").on("click", ".js-open-form", function(event)
		{
			event.preventDefault();
			var targetId = $(this).attr('href');

			// fallback to something with .js-popup-form
			var $clone = targetId.charAt(0) === '#' && targetId.length > 1 ? $(targetId).clone() : $(".js-popup-form").clone();

			$.featherlight($clone, {variant: 'open-popup-form'});

			window.setTimeout(function()
			{
				var $form = $('.featherlight.open-popup-form form');
				setupForm($form);

				if (!$form.hasClass('js-ajax-form'))//Forms module forms are ajaxed by default
				{
					$form.submit(function(event)
					{
						event.preventDefault();
						ajaxForm($(this), event);
					});
				}
			}, 0);
		});

		//Lightweight alternative to jQuery UI's accordion
		$(".js-fader").hide();

		$(".js-activator").click(function(event)
		{
			event.preventDefault();
			
			// find the target:
			let targetId = '';

			// if this fader /is/ open, we leave the target blank and it will close
			if(!$(this).hasClass('open'))
			{
				// does the clicked element have an href (is it an a or similar)?
				if($(this).attr("href") !== undefined)
				{
					targetId = $(this).attr("href");
				}
				// no? are we passing the target in a data attribute?
				else if($(this).data('target') !== undefined)
				{
					targetId = $(this).data('target');
				}
				// no? find the next fader element if one exists and use that as the target.
				else
				{
					let $target = $(this).nextAll('.js-fader').first();
					if($target.length === 0)
					{
						// well then, take no action
						return false;
					}
					// else

					targetId = $target[0].id;
					if(targetId === '')
					{
						// give target a practically unique id for the rest of this page load
						targetId = Date.now().toString();
						$target[0].id = targetId;
					}

					targetId  = '#' + targetId;

					// lets not have to nextAll() etc if this is clicked again
					$(this).data('target', targetId);
				}
			}

			// destyle currently open activators (if any)
			$('.js-activator').removeClass('open');
			
			// (start to) close open faders (if any)
			$(".js-fader").slideUp(200);

			if(targetId !== '')
			{
				// (start to) open the requested fader
				$(targetId).slideDown(200);
				
				// style this activator
				$(this).addClass('open');
			}
		});

		//alternative to jQuery UI's tabs
		$('.js-tab-link').click(function(event)
		{
			event.preventDefault();
			
			$('.js-tab-link').removeClass('active');
			$(this).addClass('active');

			$('.js-tab').removeClass('active');
			$($(this).attr('href')).addClass('active');
		});

		//mobile nav
		$('.open-nav').click(function(event)
		{
			event.preventDefault();
			$(this).toggleClass('open');
			$(".main-navigation").toggleClass("open");
		});

		$('.main-navigation').find('li .open-sub').click(function()
		{
			$(this).closest('li').toggleClass('open');
		});

		//login popup
		if ($('.account-nav').length > 0 && $('.account-nav').hasClass('do-form'))
		{
			$('.account-nav > a').click(function(event)
			{
				event.preventDefault();
				$('.account-nav').toggleClass('open');
			});

			// close login form when you click away
			$('body').click(function(event)
			{
				$('.account-nav').removeClass('open');
			});

			//override close if you click inside the login form
			$('.account-nav').click(function(event)
			{
				event.stopPropagation();
			});
		}
	});

	$(window).on('load', function()
	{
		// Slick autoplay doesn't work on ready, so make it go here instead
		startSlideshows();
	});

	/*
	 * initialise slideshow
	 */
	function startSlideshows()
	{
		$('.slider').slick(
		{
			autoplay: true,
			autoplaySpeed: 6000,
			speed: 3000,
			fade: true,
			arrows: true,
			dots: false,
			centerMode: false,
			centerPadding: 0,
			slidesToShow: 1,
			mobileFirst: true,
			respondTo: 'min'
		});

		$('.product-images .small-images').slick(
		{
			autoplay: false,
			autoplaySpeed: 6000,
			arrows: true,
			dots: false,
			centerPadding: 0,
			slidesToShow: 3,
			slidesToScroll: 3,
			mobileFirst: true,
			respondTo: 'min',
			centerMode: false,
				centerPadding: '0'
		});

		$('.js-featured-products').slick(
		{
			autoplay: false,
			autoplaySpeed: 6000,
			arrows: true,
			dots: false,
			centerPadding: 0,
			slidesToShow: 4,
			slidesToScroll: 4,
			mobileFirst: false,
			respondTo: 'min',
			centerMode: false,
			centerPadding: '0',
			responsive: 
			[{
				breakpoint: 1200,
				settings: 
				{
					slidesToShow: 3,
					slidesToScroll: 3,
					dots: true,
					arrows: false
				}
			},
			{
				breakpoint: 1000,
				settings: 
				{
					slidesToShow: 2,
					slidesToScroll: 2,
					dots: true,
					arrows: false
				}
			},
			{
				breakpoint: 600,
				settings: 
				{
					slidesToShow: 1,
					slidesToScroll: 1,
					dots: true,
					arrows: false
				}
			}]
		});

		//fix for .slider having a scrollbar like gap on the right side when there is no scrollbar
		$(window).trigger("resize");
	}

	/**
	* Submits a form using ajax. To be called as the function for submit()
	* @param $form 	the form to be ajaxed
	*/
	function ajaxForm($form, event)
	{
		if ($form.isValid())
		{
			$.ajax(
			{
				contentType: false,
				data: constructFormData($form),
				processData: false,
				type: "POST",
				url: $form.attr("action") + "?ajaxed=true",
				
				success: function(data)
				{
					if (data['success'] == true)
					{
						if(data['redirect'] != undefined)
						{
							document.location = data['redirect'];
						}
						else
						{
							$form.html(data['message']);
							// keep the submit bubbling up for eg google tracking which watches document node
							$form.parent().trigger(new $.Event(event));
						}
					}
					else
					{
						if ($form.find('.message').length > 0)
						{
							$form.find('.message').html(data['message'])[0].scrollIntoView();
						}
						else
						{
							$form.prepend('<p class="message">' + data['message'] + '</p>')[0].scrollIntoView();
						}
						setupCaptcha($form);//refresh captcha
					}
				}
			});
		}
	}

	/**
	 * This will grab all the form data, including file uploads which serialize() would miss
	 * 
	 * @param {jQuery} $form  The form to get the data of 
	 * @return {FormData}  A form data object, containing the data in the form
	 */
	let constructFormData = function($form)
	{
		let formData = new FormData();
		
		$form.find("input:not([type=radio], [type=checkbox], [type=submit]), input[type=radio]:checked, input[type=checkbox]:checked, select, textarea").each(function()
		{
			if($(this).is("input[type=file]"))
			{
				let $input = $(this);
				let files = $(this).get(0).files;
				
				if(files && files[0])
				{
					$.each(files, function(index, file)
					{
						formData.append($input.attr("name"), file);
					});
				}
			}
			else
			{
				formData.append($(this).attr("name"), $(this).val());
			}
		});
		
		return formData;
	};

	/**
	 * Sets up captcha for a form.
	 * Can also be used to refresh the captcha
	 *
	 * @param $form 	The form to set up captcha for
	 */
	function setupCaptcha($form)
	{
		var usingRecaptcha = ($('.g-recaptcha').length > 0);

		// captcha - should only be one of these in each form
		var $input = $form.find('input[name=auth]')
		var $wrapper = $input.closest('.security-wrapper');
		if($wrapper.length > 0)
		{
			if(usingRecaptcha)
			{
				$wrapper.remove();
			}
			else
			{
				// all these operations will happen on multiple items in the collection if necessary
				$wrapper.hide();

				var $hidden = $("<input />",
				{
					type: "hidden",
					name: $input.attr("name")
				});

				$.get("/resources/captcha/CaptchaSecurityImages.php?r=" + Math.random(), function(text)
				{
					$hidden.val(text);
					// this will actually have the effect of leaving only instance in the form
					$wrapper.html($hidden);
				});
			}
		}
	}

	/**
	 * Sets up a single form
	 * @param $form 	form to setup
	 */
	function setupForm($form)
	{
		setupCaptcha($form);

		// should only be at most one of these in each form
		$form.find('.has-toggle [type=password]').each(function()
		{
			var $toggle = $('<button type="button" class="toggle-password">Show</button>')
				.click(function(){
					var $this = $(this);
					$this.siblings('input').togglePassword().focus();
					$this.html($this.text() == 'Show' ? 'Hide' : 'Show');
				});
			$(this).after($toggle);
		});

		$form.find('input[type="file"]').change(function(event)
		{
			const file = event.target.files[0];

			if (file !== undefined) 
			{
				$(this).closest(".js-file-wrapper").find('.js-uploaded').html(file.name);
			}
			else 
			{
				var $uploaded = $(this).closest(".js-file-wrapper").find('.js-uploaded');
				$uploaded.html($uploaded.data().defaultText);
			}
		});

		if ($form.hasClass("js-ajax-form"))
		{
			$form.submit(function(event) 
			{
				event.preventDefault();
				ajaxForm($(this), event);
			});
		}
	}

	/**
	 * set up all forms either within a container or in general
	 *
	 * @param String 	container 	Selector for a container. Only set up forms within this container
	 */
	function setupForms(container)
	{
		// iterate through forms
		var $scope = (container === undefined) ? jQuery('form') : jQuery(container).find('form');

		// iterate through forms
		$scope.each(function()
		{
			setupForm($(this));
		});
	}

	//noinspection JSUnusedGlobalSymbols,JSUnusedLocalSymbols
	/**
	 * output a formatted price, used in shopping carts
	 * calculate_shipping function declared in page to include db-generated values
	 * @todo replace with money.js
	 */
	function currencyFormat(id, val)
	{
		val = parseFloat(val);
		if(val <= 0 || isNaN(val))
		{
			$('#' + id).html('');
		}
		else
		{
			val = Math.floor(val * 100 + 0.50000000001);
			let cents = val % 100;
			if(cents < 10)
			{
				cents = "0" + cents;
			}
			val = Math.floor(val / 100).toString();
			//add thousands separator
			for(let i = 0; i < Math.floor((val.length - (1 + i)) / 3); i++)
			{
				val = val.substring(0, val.length - (4 * i + 3)) + ',' + val.substring(val.length - (4 * i + 3));
			}

			$('#' + id).html('$' + val + '.' + cents);
		}
	}

	// noinspection JSUnusedLocalSymbols
	/**
	 * Smoothly scrolls to a particular element on the page
	 * @param	{jQuery}	$element	A jQuery element to scroll to
	 */
	function scrollTo($element)
	{
		// noinspection JSUnresolvedVariable
		if($element.length > 0)
		{
			const scroll = $element.offset().top;
			const $scrollArea = $("html, body");

			if($scrollArea.css("scroll-behavior") === "smooth")
			{
				// noinspection JSValidateTypes
				$scrollArea.scrollTop(scroll);
			}
			else
			{
				$scrollArea.stop().animate(
				{
					scrollTop: scroll
				}, 1000);
			}
		}
	}

	// Handles switching between the login and register tabs
	//should this be moved to cart.js too?
	$(function()
	{
		let $loginSections = $(".login-group .main-section");
		let $headings = $loginSections.find("h1");

		$loginSections.addClass("activated");
		$headings.attr("tabindex", 1);

		let triggerSection = function($section)
		{
			$loginSections.removeClass("selected");
			$section.addClass("selected");
		};

		$loginSections.click(function()
		{
			triggerSection($(this));
		});

		$loginSections.keydown(function(event)
		{
			// The enter key
			if(event.keyCode === 13)
			{
				triggerSection($(this));
			}
		});
	});

	// Handles updating the price for priced options
	$(function()
	{
		let $price = $(".js-product-price");
		let $adjuster = $(".js-product-price-adjuster");
		
		$price.attr('role', 'alert');
		
		$adjuster.find("option").each(function()
		{
			let price = $(this).data("price");
			let label = $(this).text();
			label = label.replace(" - " + price, "");
			$(this).text(label);
		});
		
		$adjuster.change(function()
		{
			$price.text($(this).find("option:selected").data("price"));
		});
	});
})(jQuery);

$(function()
{
	$('.js-open-search').click(function(event)
	{
		$('.js-search').toggleClass('open');
	});
});