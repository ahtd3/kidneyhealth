/**
 * initialise google maps in page
 * @see \GoogleMaps\GoogleMap::output() for where calls to this fuction are generated
 *
 * @param {string} elemId target element id
 * @param {array} myLatLng co-ordinates
 * 		we assume pin goes at center of map
 * @param {string} title label for pin
 */
function googleMapInitialize(elemId, myLatLng, title) {

	// co-ordinates of address marker
	//noinspection JSUnresolvedVariable,JSUnresolvedFunction
	//var siteLatlng = new google.maps.LatLng(-43.545906,172.640011);
	var siteLatLng = myLatLng;
	var centerLatLng = myLatLng;
	
	// eg center offset by lng -0.20 so marker visible to right of overlay
	//centerLatlng = new google.maps.LatLng(-43.545906,172.640011);

	var styles = [
		//{[{ "saturation": -100 } ]},  //greyscale
    ];
	
    var mapOptions = {
        center: centerLatLng,
        zoom: 15,
		styles: styles, 
		mapTypeId: google.maps.MapTypeId.ROADMAP
    };

	//noinspection JSUnresolvedVariable,JSUnresolvedFunction
	var map = new google.maps.Map(document.getElementById(elemId),
            mapOptions);

	//noinspection JSUnresolvedVariable,JSUnresolvedFunction
	new google.maps.Marker({
		position: siteLatLng,
		map: map,
		title: title
	});
}
//noinspection JSUnresolvedVariable,JSUnresolvedFunction
//google.maps.event.addDomListener(window, 'load', initialize);