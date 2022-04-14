/**
 * Handles auto addresses for the cart
 * @author	Callum Muir <callum@activatedesign.co.nz>
 */

/**
 * Sets up the autocomplete, should be automatically loaded by the Google Maps JavaScript
 */
function initAutocomplete()
{
	let addressFields = document.getElementsByClassName("js-address");
	
	document.addEventListener("DOMContentLoaded", function()
	{
		if(addressFields.length > 0)
		{
			let autoComplete = new google.maps.places.Autocomplete(addressFields[0], {types: ["geocode"]});
			
			autoComplete.addListener("place_changed", function()
			{
				let place = this.getPlace();
				let components = {};
				
				for(let i = 0; i < place.address_components.length; i += 1)
				{
					let addressComponent = place.address_components[i];
					
					for(let j = 0; j < addressComponent.types.length; j += 1)
					{
						let type = addressComponent.types[j];
						components[type] = addressComponent.long_name;
					}
				}
				
				let suburbFields = document.getElementsByClassName("js-suburb");
				let cityFields = document.getElementsByClassName("js-city");
				let postCodeFields = document.getElementsByClassName("js-post-code");
				let countryFields = document.getElementsByClassName("js-country");
				
				if(components.street_number !== undefined && components.route !== undefined)
				{
					addressFields[0].value = components.street_number + " " + components.route;
				}
				
				if(suburbFields[0] !== undefined && components.sublocality !== undefined)
				{
					suburbFields[0].value = components.sublocality;
				}
				
				if(cityFields[0] !== undefined && components.locality !== undefined)
				{
					cityFields[0].value = components.locality;
				}
				
				if(postCodeFields[0] !== undefined && components.postal_code !== undefined)
				{
					postCodeFields[0].value = components.postal_code;
				}
				
				if(countryFields[0] !== undefined && components.country !== undefined)
				{
					countryFields[0].value = components.country;
				}
			});
		}
	});
}