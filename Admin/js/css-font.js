(function($) {

window.fx_font_control = function(json) {
    // nav 16px bold italic uppercase underline
    var $row =  $t.jQuery('form_row', json),
        cl = $t.getBem('fx-css-font-field'),
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
            var val = font.styles[i]+'',
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
        
        var $extras = $('<div class="'+cl('extras')+' fx_overlay fx_admin_form"></div>');
        
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
        
        var $handler = $('<div class="'+cl('handler')+'" title="Больше настроек" tabindex="0">...</div>'),
            closer;
        
        function show_extras() {
            $extras.show();
            var handler_box = $handler[0].getBoundingClientRect(),
                extra_box = $extras[0].getBoundingClientRect(),
                extra_left = Math.max(handler_box.left - extra_box.width, 10);
            
            
            $extras.css({
                left: extra_left,
                top: handler_box.bottom + 5
            });
            closer = $fx.close_stack.push(
                function() {
                    $extras.hide();
                    $handler.focus();
                },
                $extras
            );
            $fx_form.focus_first($extras);
        }
        
        $extras.on('keydown', function(e) {
            if (e.which === 13) {
                closer();
            }
        });
        
        $handler.click(function() {
            show_extras();
        }).keydown(function(e) {
            if (e.which === 32 || e.which === 13) {
                show_extras();
                return false;
            }
        });
        
        $controls.append($handler);
        
        $extras.hide();
        $(document.body).append($extras);
        
        
        //$controls.append($extras);

        var $transform = $fx_fields['css-text-transform']({
            value: c_value.transform,
            label:'Регистр'
        });
        
        
        $extras.append($transform);
        controls.transform = $transform.find('.livesearch');
        
        // var $decoration = $fx_fields.toggle_button('<u>Пч</u>', c_value.decoration === 'underline', 'Подчеркнутый');
        
        var $decoration = $fx_fields.livesearch(
            {
                values: [
                    {id:'auto', name:'Авто', title:'Подчеркнутый для ссылок'},
                    {id:'none', name:'Нет'},
                    {id:'underline', name:'Подчеркнутый'},
                    {id:'line-through', name:'Зачеркнутый'}
                ],
                value: c_value.decoration,
                label: 'Оформление'
            }
        );

        $extras.append($decoration);
        controls.decoration = $decoration.find('.livesearch');
        
        if (c_value['letter-spacing'] === 'em') {
            c_value['letter-spacing'] = 0;
        }
        
        var $letter_spacing = $fx_fields.number(
            {
                min:-0.15,
                max:1,
                units:'em',
                step:0.01,
                value: c_value['letter-spacing'],
                label:'Межбуквенное'
            }
        );
        
        $extras.append($letter_spacing);
        controls['letter-spacing'] = $letter_spacing.find('input');
        
        var $line_height = $fx_fields.number(
            {
                min:0.5,
                max:2,
                units:'em',
                step:0.05,
                value: c_value['line-height'],
                label:'Высота строки'
            }
        );
        $extras.append($line_height);
        controls['line-height'] = $line_height.find('input');
        
        $controls.on('change input', function(e) {
            update_value();
            return false;
        });
        $extras.on('change input', function(e) {
            update_value();
            return false;
        });
    }

    function update_value() {
        var res = [];
        res.push(controls.family.data('livesearch').getValue());
        res.push(controls.size.val() + 'px');
        res.push(controls.style.data('livesearch').getValue());
        res.push(controls.transform.data('livesearch').getValue());
        res.push(controls.decoration.data('livesearch').getValue());
        res.push(controls['line-height'].val()+'em');
        res.push(controls['letter-spacing'].val()+'em');
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
            decoration:parts[5],
            'line-height':parts[6],
            'letter-spacing':parts[7]
        };
        if (typeof res['line-height'] === 'undefined') {
            res['line-height'] = 1.4;
        }
        if (typeof res['letter-spacing'] === 'undefined') {
            res['letter-spacing'] = 0;
        }
        return res;
    }

    init_controls();

    return $row;
};

})($fxj);