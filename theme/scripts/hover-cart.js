$(function()
{
	// This should not be run on the cart page
	if($(document).hasClass("Cart"))
	{
		return;
	}

	const hideEmptyCartLink = true;

	const cartDataUrl = "/Cart/JSON/";
	const removeItemUrl = "/Cart/Action/Remove/{id}/?ajax=redirect";

	const cartLinkSelector = ".js-cart";
	const addToCartLinkSelector = ".js-add-to-cart-link";
	const addToCartFormSelector = ".js-add-to-cart-form";
	const cartLinkTextWhenCartVisible = "Checkout";

	const cartHtml = "\
		<article class='hover-cart'>\
			<div class='controls'>\
				<button class='close js-dismiss'>Close</button>\
			</div>\
			<div class='contents js-contents'></div>\
			<div class='empty-message'>\
				Your cart is empty.\
			</div>\
			<div class='actions'>\
				<a class='cart-button' href='/cart/'>Checkout</a>\
				<button class='js-dismiss cart-button'>Continue Shopping</button>\
			</div>\
		</article>\
	";

	const cartItemHtml = "\
		<div class='row'>\
			<div class='image js-image'></div>\
			<div class='details'>\
				<h3 class='ajax_name'></h3>\
				<div class='ajax_options'></div>\
				<div>Qty: <span class='ajax_quantity'></span></div>\
				<div>Price: <span class='price ajax_price'></span></div>\
				<div><button class='remove js-remove'>Remove</button></div>\
			</div>\
		</div>\
	";

	/**
	 * Makes the cart visible
	 */
	let makeVisible = function()
	{
		$cart.addClass("visible");
		cartVisible = true;
		$cartLinks.html(cartLinkTextWhenCartVisible);
	};

	/**
	 * Retrieves the cart from the Cart page and updates the hover cart
	 */
	let retrieveLineItems = function()
	{
		$cart.addClass("loading");

		$.get(cartDataUrl, undefined, undefined, "json").then(function(cart)
		{
			let $items = $();

			$.each(cart, function(key, value)
			{
				// Ignore the line items array, we'll handle that separately
				if(key === "lineItems")
				{
					return;
				}

				$cart.find(".ajax_" + key).html(value);
			});

			// noinspection JSUnresolvedVariable
			$.each(cart.lineItems, function(index, lineItem)
			{
				let $item = $(cartItemHtml);

				$.each(lineItem, function(key, value)
				{
					$item.find(".ajax_" + key).html(value);
				});

				$item.find(".js-image").css("background-image", "url(" + lineItem.image + ")");

				$item.find(".js-remove").click(function(event)
				{
					event.preventDefault();
					removeItem($item, lineItem.id);
				});

				$items = $items.add($item);
			});

			let $contents = $cart.find(".contents");
			$contents.empty();
			$contents.append($items);
			$cart.removeClass("loading");
			$cart.addClass("refreshed");

			// noinspection JSUnresolvedVariable
			if(cart.lineItems.length > 0)
			{
				$cart.removeClass("empty");
			}
			else
			{
				$cart.addClass("empty");
			}

			// Give the browser a moment to trigger the CSS animation
			window.setTimeout(function()
			{
				$cart.removeClass("refreshed");

				if(hideEmptyCartLink)
				{
					//hide cart link if you've got no items
					if($cart.find('.row').length === 0)
					{
						$(cartLinkSelector).hide();
					}
					else
					{
						$(cartLinkSelector).show();
					}
				}
			}, 1);
		});
	};

	/**
	 * Removes a line item
	 * @param	{jQuery}	$item	The element that will be removed
	 * @param	{number}	id		The identifier for the line item
	 */
	let removeItem = function($item, id)
	{
		$cart.addClass("loading");

		let url = removeItemUrl.replace("{id}", id);
		$item.addClass("removing");

		$.get(url).then(function()
		{
			retrieveLineItems();
		});
	};

	// Setup the cart
	let cartVisible = false;
	let $cart = $(cartHtml);
	let $cartLinks = $(cartLinkSelector);
	$("body").append($cart);
	retrieveLineItems();

	// Keep track of what each link contains
	$cartLinks.each(function()
	{
		$(this).data("initialText", $(this).html());
	});

	// Handle the user clicking on a link
	$cartLinks.click(function(event)
	{
		if(!cartVisible)
		{
			event.preventDefault();
			makeVisible();
		}
	});

	// Handle the user clicking the dismiss button
	$cart.find(".js-dismiss").click(function(event)
	{
		event.preventDefault();
		$cart.removeClass("visible");
		cartVisible = false;

		// Put each link back the way it was
		$cartLinks.each(function()
		{
			$(this).html($(this).data("initialText"));

			//update item count
			$(cartLinkSelector).find('.number').text($cart.find('.row').length);
		});
	});

	// Handles the user clicking on a link that adds a product to the cart
	$(document).on("click", addToCartLinkSelector, function(event)
	{
		event.preventDefault();
		let url = $(this).attr("href");
		$cart.addClass("loading");
		makeVisible();

		$.get(url).then(function()
		{
			retrieveLineItems();
		});
	});

	// Handles the user submitting a form that adds a product to the cart
	$(document).on("submit", addToCartFormSelector, function(event)
	{
		event.preventDefault();
		let data = $(this).serialize();
		let type = $(this).attr("method");
		let url = $(this).attr("action");
		$cart.addClass("loading");
		makeVisible();
		let request;

		if(url.indexOf("?") > -1)
		{
			url += "&ajax=redirect";
		}
		else
		{
			url += "?ajax=redirect";
		}

		if(type === "post")
		{
			request = $.post(url, data);
		}
		else
		{
			request = $.get(url, data);
		}

		request.then(function()
		{
			retrieveLineItems();
		});
	});
});
