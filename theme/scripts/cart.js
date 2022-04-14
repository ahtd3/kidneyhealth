$(function()
{
    $(document).ready(function()
	{
        $('.js-shipping-select').change(function() 
        {
            let regionId = $(this).val();
            let $shippingSelect = $(this);
			
			$shippingSelect.data().overweightPromise = new Promise(function(resolve)
			{
				$.ajax(
				{
					contentType: false,
					url: "/processes/ajax/check-cart-weight.php?shippingRegionId=" + regionId,
					
					success: function(data)
					{
						if (data['overweight'] == true)
						{
							$shippingSelect.data().overweight = true;
						}
						else 
						{
							$shippingSelect.data().overweight = false;
						}
						
						resolve($shippingSelect.data().overweight);
						
						$('.js-max-weight').text(data['maxWeight'] + 'kg');

						window.setTimeout(function() 
						{
							$shippingSelect.isValid();
						}, 1000);
					}
                });
                
                //update totals, ajax like below functions
                let $cartSummary = $(".js-cart-summary");
                $cartSummary.addClass("loading");

                let data = {'shippingRegion' : regionId};
                let url = '/Cart/Action/Shipping/' + regionId;

                $.post(url, data, function(html)
                {
                    let $html = $(html);
                    let $newCartSummary = $html.find(".js-cart-summary");

                    $cartSummary.replaceWith($newCartSummary);
                });
			});
			
			$('.js-shipping-form').load("/processes/ajax/shipping-enquiry-form.php?shippingRegionId=" + regionId, function( response, status, xhr ) 
			{
			});
        });
        $('.js-shipping-select').change();//trigger change in case they already had a region selected, but have updated quantity on the previous page

		$(".js-has-weight-validation").addValidation(function()
	  	{
	  		var $self = $(this);
	 
	  		return new Promise(function(resolve, reject)
	  		{				
				let promise = $self.data().overweightPromise;
				
				let nextStep = function()
				{
					//disable button
					let $button = $self.closest('form').find('.submit-button');
					
					if(!$self.data().overweight)
					{
						$button.attr('disabled', false);
						resolve();
					}
					else
					{
						$button.attr('disabled', true);
						reject($('.js-overweight-error').html() + $('.js-max-weight').text());
					}
				}
				
				if(promise === undefined)
				{
					nextStep();
				}
				else
				{
					promise.then(nextStep);
	  			}
	  		});
	  	});
		
		$(".js-has-weight-validation").addErrorHandler(function(errorMessage)
		{
			var $fieldWrapper = $(this).closest(".field-wrapper");
			
			//add error message container
			if ($fieldWrapper.find('.append-errors').length == 0)
			{
				$fieldWrapper.append('<div class="append-errors"></div>');
			}
			var $error = $fieldWrapper.find('.append-errors');
			
			$error.html(errorMessage);
			
		});

    });

    $(window).on('load', function()
	{
        if ($('.js-weight-check').length > 0) 
        {
            //make sure jquery validate is loaded
            window.setTimeout(function() 
            {
				$('.js-weight-check').isValid();
            });
        }
    });

    function updateWeightChecker() 
    {
        $.ajax(
        {
            contentType: false,
            url: "/processes/ajax/check-cart-weight.php",
            
            success: function(data)
            {
                if (data['overweight'] == true)
                {
                    $('.js-weight-check').data().overweight = true;
                }
                else 
                {
                    $('.js-weight-check').data().overweight = false;
                }
                $('.js-max-weight').text(data['maxWeight'] + 'kg');
				$('.js-weight-check').isValid();
            }
        });
    }
    

    /*
     * Handles using AJAX to update and remove items from the main cart page
     */

    $("body").on("change", ".js-cart-quantity", function()
    {
        let $cartSummary = $(".js-cart-summary");
        let $lineItems = $(".js-line-items");
        let $lineItem = $(this).closest(".js-line-item");
        let $form = $(this).closest("form");
        $lineItem.addClass("loading");
        $cartSummary.addClass("loading");

        let data = $form.serialize();
        let url = $form.attr("action");

        $.post(url, data, function(html)
        {
            let $html = $(html);
            let $newLineItems = $html.find(".js-line-items");
            let $newCartSummary = $html.find(".js-cart-summary");

            $lineItems.replaceWith($newLineItems);
            $cartSummary.replaceWith($newCartSummary);

            updateWeightChecker();
        });
    });

    $("body").on("click", ".js-cart-remove", function(event)
    {
        event.preventDefault();

        let $cartSummary = $(".js-cart-summary");
        let $lineItems = $(".js-line-items");
        let $lineItem = $(this).closest(".js-line-item");
        $lineItem.addClass("loading");
        $cartSummary.addClass("loading");
        let url = $(this).attr("href");

        $.get(url, function(html)
        {
            let $html = $(html);
            let $newLineItems = $html.find(".js-line-items");
            let $newCartSummary = $html.find(".js-cart-summary");

            $lineItems.replaceWith($newLineItems);
            $cartSummary.replaceWith($newCartSummary);

            updateWeightChecker();
        });
    });
});

// Handles showing and hiding the billing address
$(function()
{
    let $sameAddress = $(".js-same-address");
    let $fieldsHolder = $(".js-payment-fields");

    if($sameAddress.length > 0)
    {
        if ($sameAddress.prop('checked'))
        {
            $fieldsHolder.addClass("hide-duplicate-fields");
            $fieldsHolder.find('.duplicate-field input').attr('disabled', true);
        }

        $sameAddress.change(function()
        {
            $fieldsHolder.toggleClass("hide-duplicate-fields");

            if ($sameAddress.prop('checked'))
            {
                $fieldsHolder.find('.duplicate-field input').attr('disabled', true);
            }
            else 
            {
                
                $fieldsHolder.find('.duplicate-field input').attr('disabled', false);
            }
        });
    }
});