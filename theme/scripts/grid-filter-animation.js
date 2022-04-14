(function($)
{
	/**
	 * Animate filtering a grid/flex element's children
	 * @param	{jQuery}	$elements	The elements to display
	 * @param	{int}		speed		The speed to run the animation, in milliseconds (defaults to 300)
	 */
	$.fn.gridFilter = function($elements, speed)
	{
		// Default speed is 300 milliseconds (0.3s)
		speed = speed || 300;
		
		// Record everything's current position and visibility
		$(this).children().each(function(index)
		{
			// Make sure that any previous transforms are removed
			$(this).css(
			{
				transform: "none",
				transition: "none"
			});
			
			$(this).data("old-position", $(this).position());
			$(this).data("was-visible", $(this).is(":visible"));
		});
		
		// Move invisible elements to the end, so visible elements will end up in the right position
		$(this).children().each(function(index)
		{
			// We can safely ignore elements that will remain invisible
			if(!$(this).data("was-visible") && !$elements.is($(this)))
			{
				return;
			}
			
			$(this).show();
			$(this).css("order", $elements.is($(this)) ? "" : "99999");
		});
		
		$(this).children().each(function(index)
		{
			// Transform elements that moved, so they look like they haven't moved yet, ignore elements that are transitioning from invisible to visible, they should start in their new position
			if($(this).data("was-visible"))
			{
				let oldPosition = $(this).data("old-position");
				let newPosition = $(this).position();
				let xDifference = oldPosition.left - newPosition.left;
				let yDifference = oldPosition.top - newPosition.top;
				
				$(this).css("transform", "translateX(" + xDifference + "px) translateY(" + yDifference + "px)");
			}
			
			// Fade out elements that should no longer be visible
			if($(this).data("was-visible") && !$elements.is($(this)))
			{
				$(this).fadeOut(speed);
			}
			// Fade in elements that should be visible
			else if(!$(this).data("was-visible") && $elements.is($(this)))
			{
				$(this).hide().fadeIn(speed);
			}
			// Move any elements that need to move
			else
			{
				let $self = $(this);
				
				// Make sure that the transition does not trigger on the previous transform
				window.setTimeout(function()
				{
					$self.css(
					{
						transition: "transform " + speed + "ms",
						transform: "none"
					});
				}, 0);
			}
		});
	};
})(jQuery);