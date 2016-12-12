window.init_google_maps = function() {
    var listener;
    while ( (listener = window.init_google_maps.listeners.pop()) ) {
        listener();
    }
};

window.init_google_maps.listeners = [];

(function($) {
    window.fx_google_map_field = function($field, params) {
        var $map_container = $('.map_container', $field);
            
    
        /*
        var popup = new $fx.popup({
            target:$('.map_link', $field),
            ok_button:false
        });
        */
       
        if (params.name.match(/\]$/)) {
            params.lat_field = params.name.replace(/\[[^\]]+\]$/, '[' + params.lat_field + ']');
            params.lon_field = params.name.replace(/\[[^\]]+\]$/, '[' + params.lon_field + ']');
        }
        
        //popup.$body.append($map_container);
        //$field.append(popup.$node);
        
        // validate google
        function init_gmap() {
            var init_center = new google.maps.LatLng("-37.81411", "144.96328"),
                map = new google.maps.Map(
                    $map_container[0],
                    {
                        zoom: 14,
                        center: init_center,
                        mapTypeId : google.maps.MapTypeId.ROADMAP
                    }
                ),
                $form = $field.closest('form'),
                $address_input = $('.map_address_input', $field),
                $lat_input = $(':input[name="'+params.lat_field+'"]', $form),
                $lon_input = $(':input[name="'+params.lon_field+'"]', $form),
                autocomplete = new google.maps.places.Autocomplete( 
                    $address_input[0],
                    {
                        //types: ['address']
                    }
                ),
                marker = new google.maps.Marker({
                    draggable: true,
                    raiseOnDrag: true,
                    position:init_center,
                    map: map
                }),
                geocoder = new google.maps.Geocoder();
            
            $address_input.on('keyup keydown keypress', function(e) {
                if (e.which === 13 || e.which === 27) {
                    return false;
                }
            });
            function set_value(lat, lon) {
                var latlon = new google.maps.LatLng( lat, lon );
                $lat_input.val(lat);
                $lon_input.val(lon).trigger('change');
                marker.setPosition(latlon);
                map.setCenter( latlon );
            };
            google.maps.event.addListener(autocomplete, 'place_changed', function( e ) {
                var place = this.getPlace();
                if (place.geometry) {
                    var lat = place.geometry.location.lat(),
                        lon = place.geometry.location.lng();
                    set_value(lat, lon);
                }
            });
            google.maps.event.addListener( marker, 'dragend', function(){
                var position = marker.getPosition(),
                    lat = position.lat(),
                    lon = position.lng(),
                    latlon = new google.maps.LatLng( lat, lon );

                geocoder.geocode({'latLng' : latlon}, function( data, status ){
                    if (status !== google.maps.GeocoderStatus.OK || data.length === 0) {
                        return;
                    }
		    $address_input.val(data[0].formatted_address);
                    set_value(lat, lon);
                });
                
            });
            $field.on('click', '.map_link', function() {
                /*
                if (popup.$node.is(':visible')) {
                    popup.hide();
                } else {
                    popup.position();
                    google.maps.event.trigger(map, 'resize');
                    var position = marker.getPosition();
                    map.setCenter( new google.maps.LatLng( position.lat(), position.lng()));
                }
                */
            });
            var init_lat = $lat_input.val(),
                init_lon = $lon_input.val();
        
            if (init_lat && init_lon) {
                set_value(init_lat, init_lon);
            }
            
        }
        if( typeof google === 'undefined' ) {
            var url = 'https://maps.googleapis.com/maps/api/js',
               libraries = ['places'],
               key = 'AIzaSyAQIW4Tp8he1hwyzChjAw35vCNKeG0zXYo';
            
            window.init_google_maps.listeners.push(init_gmap);
            $.getScript(
                url+'?key='+key+'&libraries='+libraries.join(',')+'&callback=init_google_maps'
            );
    
        } else {
            setTimeout(init_gmap, 200);
        }
    };
})(jQuery);