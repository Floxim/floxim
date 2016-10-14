(function($) {

window.fx_font_control = function(json) {
    // nav 16px bold italic uppercase underline
    var $row =  $t.jQuery('form_row', json),
        el = $t.getBemElementFinder('fx-css-font-field'),
        $controls = $row.find( el('controls')),
        $value = $row.find( el('value')),
        controls = {},
        fonts = $fx.layout_fonts || [];
    
    function get_font(type) {
        for (var i = 0; i < fonts.length; i++ ) {
            if (fonts[i].keyword === type) {
                return fonts[i];
            }
        }
    }

    function get_style_values(font_type) {
        var font = get_font(font_type),
            res = [];
        if (!font || !font.styles) {
            return [];
        }
        var weight_names = {
            '300':'Тонкий',
            '400':'Нормальный',
            regular:'Нормальный',
            normal:'Нормальный',
            bold:'Жирный',
            '700':'Полужирный',
            '900':'Жирный'
        };
        for (var i = 0 ; i < font.styles.length; i++) {
            var val = font.styles[i],
                weight = val.match(/\d00/),
                italic = val.match(/italic/);

            weight = weight ? weight[0] : 'normal';
            var style = 'font-family: '+font.family+';';
            style += 'font-weight:'+weight+';';

            if (italic) {
                style += 'font-style: italic;';
            }

            var value = weight +' ' +(italic ? 'italic' : 'normal');

            var name = weight_names[weight] || weight;
            if (italic) {
                name += ' курсив';
            }


            res.push({
                id: value,
                name: "<span style='"+style+"'>"+name+'</span>'
            });
        }
        return res;
    }

    function parse_style_value(value) {
        var map = {
            normal:400,
            regular:400,
            bold:700
        };
        var parts = value.split(' '),
            weight = parts[0],
            italic = parts[1];

        if (!weight.match(/\d00/)) {
            if (map[weight]) {
                weight = map[weight]
            } else {
                weight = 400;
            }
        }
        return {
            weight: weight * 1,
            italic: italic === 'italic'
        };
    }

    function find_style_value(value, values) {

        value = parse_style_value(value);

        values = values.map(function(v) {
            var pv = parse_style_value(v.id);
            pv.id = v.id;
            pv.diff = Math.abs(value.weight - pv.weight);
            return pv;
        })
        .sort( function(a, b) {
            var diff = a.diff - b.diff;
            if (diff !== 0) {
                return diff;
            }
            return a.italic === value.italic ? -1 : 1;
        });

        return values[0].id;
    }

    function get_style_control(value) {

        var family_ls = controls.family.data('livesearch'),
            current_family = family_ls.getValue();

        var style_values = get_style_values(current_family);

        value = find_style_value(value, style_values);

        var $style = $fx_fields.control({
            type: 'livesearch',
            allow_empty:false,
            values: style_values,
            value: value
        });
        controls.style = $style;
        return $style;
    }

    function init_controls() {
        var c_value = parse_value(json.value);
        var $family = $fx_fields['css-font-family'](c_value.family, json.family);
        $controls.append($family);

        controls.family = $family;

        var $style = get_style_control(c_value.weight+' '+c_value.style);

        $controls.append($style);

        $family.on('change', function() {
            var $old_style = controls.style,
                old_value = $old_style.data('livesearch').getValue(),
                $new_style = get_style_control(old_value);

            $old_style.before($new_style);
            $old_style.remove();
        });

        var size_params = {
            min:11,
            max:82,
            step:1,
            units:'px',
            value:c_value.size
        };

        if (json.size) {
            var size = json.size.split(/[\s\-]+/);
            size_params.min = size[0];
            size_params.max = size[1];
        }
        var $size = $fx_fields.number(size_params, 'input');
        $controls.append($size);

        controls.size = $size;

        var $transform = $fx_fields['css-text-transform']({
            value: c_value.transform
        }, 'input');
        $controls.append($transform);
        controls.transform = $transform;
        
        var $decoration = $fx_fields.toggle_button('<u>Пч</u>', c_value.decoration === 'underline', 'Подчеркнутый');
            $controls.append($decoration);
            controls.decoration = $decoration;

        $controls.on('change input', function(e) {
            update_value();
            return false;
        });
    }

    function update_value() {
        var res = [];
        res.push(controls.family.data('livesearch').getValue());
        res.push(controls.size.val() + 'px');
        //res.push(controls.weight.val() ? 'bold' : 'normal');
        //res.push(controls.style.val() ? 'italic' : 'normal');
        res.push(controls.style.data('livesearch').getValue());
        res.push(controls.transform.data('livesearch').getValue());
        res.push(controls.decoration.val() ? 'underline' : 'none');
        $value.val( res.join(' ') ).trigger('change');
    }

    function parse_value(value) {
        if (!value || value === 'none') {
            value = 'text 16px normal normal none none';
        }
        var parts = value.split(/\s+/);

        if (!parts[1]) {
            parts[1] = '16px';
        }

        var  res = {
            family: parts[0],
            size: parts[1].replace(/[^\d]+/, ''),
            weight: parts[2],
            style: parts[3],
            transform:parts[4],
            decoration:parts[5]
        };
        return res;
    }

    init_controls();

    return $row;
};

})($fxj);