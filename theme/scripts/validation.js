/**
 * Validation functions, relies heavily on native validation methods.
 */
(function($)
{
	const FORM_ELEMENTS = "input, textarea, select";
	const VALIDATION_FUNCTIONS_KEY = "validationFunctions";
	const ERROR_HANDLER_FUNCTIONS_KEY = "errorHandlerFunctions";
	const CHECKING_VALIDATION_KEY = "checkingValidation";
	const PENDING_VALIDATION_CLASS = "pending-validation";

	/**
	 * Adds a function to an element to check if it's valid or not
	 *
	 * @example
	 * 	$("input").addValidation(function()
	 * 	{
	 * 		var $self = $(this);
	 *
	 * 		return new Promise(function(resolve, reject)
	 * 		{
	 * 			if($self.val() === "hello")
	 * 			{
	 * 				resolve();
	 * 			}
	 * 			else
	 * 			{
	 * 				reject("Must say 'hello'");
	 * 			}
	 * 		});
	 * 	});
	 *
	 * @param	{function}	callback	Returns a promise that either resolves, or rejects with the validation message
	 * @param	{string}	[events]	Optionally, space separated JQuery events for when the check should be triggered on the element (defaults to "input change")
	 * @return	{jQuery}				This element
	 */
	$.fn.addValidation = function(callback, events)
	{
		let currentValidation = this.data(VALIDATION_FUNCTIONS_KEY);

		if(currentValidation === undefined)
		{
			currentValidation = [];
		}

		currentValidation.push(callback);
		this.data(VALIDATION_FUNCTIONS_KEY, currentValidation);

		// noinspection JSIgnoredPromiseFromCall
		this.isValid();
		validateOnSubmit(this.closest("form"));
		addValidationChecking(this, events);

		return this;
	};

	/**
	 * Adds a custom error handler to an element, can be used to insert an error message into the DOM
	 *
	 * @example
	 * 	$("input").addErrorHandler(function(errorMessage)
	 * 	{
	 * 		$(this).closest("label").find(".error").text(errorMessage);
	 * 	});
	 *
	 * @param	{function}	callback	A function to run when the error message changes. An empty error message means the element is valid
	 * @param	{string}	[events]	Optionally, space separated JQuery events for when the check should be triggered on the element (defaults to "input change")
	 * @return	{jQuery}				This element
	 */
	$.fn.addErrorHandler = function(callback, events)
	{
		let currentHandlers = this.data(ERROR_HANDLER_FUNCTIONS_KEY);

		if(currentHandlers === undefined)
		{
			currentHandlers = [];
		}

		currentHandlers.push(callback);
		this.data(ERROR_HANDLER_FUNCTIONS_KEY, currentHandlers);
		// noinspection JSIgnoredPromiseFromCall
		this.isValid();
		addValidationChecking(this, events);

		return this;
	};

	/**
	 * Checks if an element or a container of elements is invalid
	 *
	 * @example
	 * 	$("form .tab").isValid().then(function(isValid)
	 * 	{
	 * 		if(isValid)
	 * 		{
	 * 			nextTab();
	 * 		}
	 * 	});
	 *
	 * @return	{Promise}	Resolves to whether the element/elements are valid or not
	 */
	$.fn.isValid = function()
	{
		const $elements = this.filter(FORM_ELEMENTS).add(this.find(FORM_ELEMENTS));
		const promises = [];

		for(let i = 0; i < $elements.length; i += 1)
		{
			promises.push(checkIsValid($elements.eq(i)));
		}

		return new Promise(function(resolve)
		{
			Promise.all(promises).then(function()
			{
				resolve(true);
			}).catch(function()
			{
				resolve(false);
			});
		});
	};

	/**
	 * Adds validation checking if it's not already on the element
	 * @param	{jQuery}	$element	The element to apply the checking to
	 * @param	{string}	[events]	Optionally, space separated JQuery events for when the check should be triggered on the element (defaults to "input change")
	 */
	const addValidationChecking = function($element, events)
	{
		if(events === undefined)
		{
			events = "input change";
		}

		if($element.data(CHECKING_VALIDATION_KEY) !== events)
		{
			$element.on(events, function()
			{
				$(this).isValid();
			});

			$element.data(CHECKING_VALIDATION_KEY, events);
		}
	};

	/**
	 * Checks whether a form element is valid or not
	 * @param	{jQuery}	$element	A single element to check
	 * @return	{Promise}				Resolves or rejects
	 */
	const checkIsValid = function($element)
	{
		$element.addClass(PENDING_VALIDATION_CLASS);

		return new Promise(function(resolve, reject)
		{
			let validationFunctions = $element.data(VALIDATION_FUNCTIONS_KEY);

			if(validationFunctions === undefined)
			{
				validationFunctions = [];
			}

			const promises = [];
			const element = $element.get(0);

			for(let i = 0; i < validationFunctions.length; i += 1)
			{
				promises.push(validationFunctions[i].call(element));
			}

			Promise.all(promises).then(function()
			{
				element.setCustomValidity("");

				if(element.checkValidity())
				{
					resolve();
				}
				else
				{
					reject();
				}
			}).catch(function(rejectionMessage){
				element.setCustomValidity(rejectionMessage);
				reject();
			}).then(function()
			{
				$element.removeClass(PENDING_VALIDATION_CLASS);

				let errorHandlers = $element.data(ERROR_HANDLER_FUNCTIONS_KEY);

				if(errorHandlers === undefined)
				{
					errorHandlers = [];
				}

				for(let i = 0; i < errorHandlers.length; i += 1)
				{
					errorHandlers[i].call(element, element.validationMessage);
				}
			});
		});
	};

	/**
	 * Validates the form when submitted, and blocks it if it's invalid
	 * @param	{jQuery}	$element	The form element to validate
	 */
	const validateOnSubmit = function($element)
	{
		// noinspection JSUnresolvedFunction
		$element.each(function()
		{
			if($(this).data(CHECKING_VALIDATION_KEY))
			{
				return;
			}

			$(this).data(CHECKING_VALIDATION_KEY, true);

			let skipCheck = false;

			$(this).submit(function(event)
			{
				if(skipCheck)
				{
					return true;
				}

				event.preventDefault();
				const $self = $(this);

				$(this).isValid().then(function(isValid)
				{
					if(isValid)
					{
						skipCheck = true;
					}

					$self.submit();
				});
			});
		});
	};
})(jQuery);
