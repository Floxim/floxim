<div class="static_google_map" fx:template="static_google_map" fx:of="widget_map.show">
    <div class="title"><p>{$map.address}</p></div>
    {* google gives map image larger than 640*640 only with double scale *}
    <div class="map"
            data-map_width="{%map_width type='int' label='Width'}640{/%}"
            data-map_height="{%map_height type='int' label='Height'}640{/%}"
            {set $scale = $map_width > 640 || $map_height > 640 ? 2 : 1 /}
            data-map_zoom="{%map_zoom type='select' values='`range(10,18)`' label='Zoom'}15{/%}">
        {set $src = 'http://maps.googleapis.com/maps/api/staticmap?' .
                    'zoom='. $map_zoom .'&size=' . ($map_width / $scale) . 'x' . ($map_height/$scale) . '&maptype=roadmap&' .
                    'markers=color:blue%7C' . $map.lat . ',' . $map.lon . '&sensor=false&scale=' . $scale /}
        <img src="{$src /}" width="{$map_width}" height="{$map_height}" />
    </div>
</div>