(function($){
window.$fx_fields = {
    
    default:function(json){
        return $t.jQuery('form_row', json);
    },
    
    html: function (json) {
      return json.html || json.value;  
    },
    
    raw: function(json) {
        if (json.wrap) {
            return $t.jQuery('form_row', json);
        }
        return json.value;
    },
    
    handle_number_wheel: function ($inp, params) {
        params = $.extend(
            {
                min: $inp.attr('min')*1,
                max: $inp.attr('max')*1,
                step: $inp.attr('step')*1
            },
            params
        );
        var defaults = {
            min:-100000,
            max:100000,
            step:1
        };
        $.each(defaults, function(prop, val) {
            if (params[prop] === undefined || isNaN(params[prop]) ) {
                params[prop] = val;
            }
        });
        var $target=  params.$target || $inp;
        var decimal_part = (params.step % 1).toString().match(/\.(.+)/),
            multiplier = decimal_part ? decimal_part[1].length * 10 : 1;
        
        var frozen = false;
        
        $target.on('mousewheel', function(e) {
            
            if (!frozen) {
                
                var delta = e.originalEvent.deltaY > 0 ? -1 : 1,
                    c_value = $inp.val() * 1,
                    new_value = ( c_value * multiplier + params.step * delta * multiplier ) / multiplier;
                if (new_value < params.min) {
                    new_value = params.min;
                } else if (new_value > params.max ) {
                    new_value = params.max;
                }
                $inp.focus().val(new_value).trigger('change');
                frozen = true;
                setTimeout(function() {
                    frozen = false;
                }, 80);
            }
            return false;
        });
    },
    
    row: function(json) {
        if (json.type === undefined) {
            json.type = 'input';
        }
        json.type = json.type.replace(/^field_/, '');
        var type='';
        switch(json.type) {
            case 'hidden': case 'string': case 'short': case 'medium': case 'long': case 'int':
                type = 'input';
                break;
            case 'textarea': case 'text':
                type = 'textarea';      
                break;
            default:
                type = json.type;
                break;
        }
        if (!this[type]) {
            type = 'default';
        }
        
        var source_json = JSON.parse(JSON.stringify(json));
        
        var $node = this[type](json);
        if ($node && $node.length && typeof $node !== 'string') {
            if (json.field_meta) {
                $node.data('field_meta', json.field_meta);
            }
            try {
                $node.data('source_json', source_json);
            } catch (e) {
                console.log(e);
            }
        }
        return $node;
    },
    
    colorset: function(json) {
        var $row = $t.jQuery('form_row', json);
        new $fx.colorset($row, json);
        return $row;
    },

    measures: function(json) {
        var $row = $t.jQuery('form_row', json);
        $fx.measures.create($row, json);
        return $row;
    },
    
    ratio: function(json, template) {
        if (!json.label) {
            json.label = 'Пропорции';
        }
        function to_ratio(val) {
            if (typeof val !== 'string') {
                return val;
            }
            var parts = val.match(/(\d+)\s*\:\s*(\d+)/);
            if (!parts) {
                return val * 1;
            }
            return parts[1] / parts[2];
        };
        
        function append_ratio_size($node, ratio, square) {
            square = square ||  1900;
            var height = Math.sqrt( square / ratio ),
                width = height * ratio;

            $node.css({
                width: width + 'px',
                height: height + 'px'
            });
        }
       
        var $row = $t.jQuery('form_row', json),
            $control = $('.fx-ratio-input__control', $row),
            $trigger = $('.fx-ratio-input__trigger', $row),
            all_ratios = [
                [5,"5:1"],
                [4,"4:1"],
                [3,"3:1"],
                [2.5,"5:2"],
                [2,"2:1"],
                [1.5,"3:2"],
                [1.33,"4:3"],
                [1,"1:1"],
                [0.75,"3:4"],
                [0.67,"2:3"],
                [0.5,"1:2"],
                [0.33,"1:3"],
                [0.25,"1:4"]
            ],
            avail_ratios = all_ratios;
        
        if (json.min) {
            json.min = to_ratio(json.min);
        }
        if (json.max) {
            json.max = to_ratio(json.max);
        }
        if (json.min || json.max) {
            avail_ratios = [];
            for (var i = 0; i < all_ratios.length; i++) {
                var c_ratio = all_ratios[i][0];
                if ( 
                    (!json.min || c_ratio >= json.min) &&
                    (!json.max || c_ratio <= json.max )
                ) {
                    avail_ratios.push( all_ratios[i] );
                }
            }
        }
        if (!json.value) {
            json.value = '1:1';
        }
        
        json.value = to_ratio(json.value);
        
        
        for (var i = 0; i < avail_ratios.length; i++) {
            var c_ratio = avail_ratios[i],
                $container = $('<div class="fx-ratio-input__item-container"></div>'),
                $item = $('<div class="fx-ratio-input__item" data-value="'+c_ratio[0]+'"><span>'+c_ratio[1]+'</span></div>');
            append_ratio_size($item, c_ratio[0]);
            
            $container.append($item);
            $control.append($container);
        }
        
        $('body').append($control);
        
        var active_class = 'fx-ratio-input__control_active';
        
        
        function show_control () {
            $control.addClass(active_class);
            $control.attr('tabindex',0).focus().on('keydown.fx-ratio-input', function(e) {
                if (e.which === 27) {
                    hide_control();
                    e.stopImmediatePropagation();
                    return false;
                }
            });
            $('html').on('click.fx-ratio-input', function(e) {
                if ($(e.target).closest('.fx-ratio-input__control').length === 0) {
                    hide_control();
                    return false;
                }
            });
        }
        
        function hide_control() {
            $('html').off('.fx-ratio-input');
            $control.attr('tabindex','').removeClass(active_class).off('.fx-ratio-input');
        }
        
        $trigger.on('click', function() {
            if (!$control.is('.'+active_class)) {
                show_control();
                var offset = $trigger.offset();
                
                $control.offset({
                    top: offset.top + $trigger.height() + 10,
                    left: offset.left
                });
            } else {
                hide_control();
            }
            return false;
        });
        
        function set_value(ratio) {
            var active_item_class = 'fx-ratio-input__item_active';
            $control.find('.'+active_item_class).removeClass(active_item_class);
            var $item = $control.find('.fx-ratio-input__item[data-value="'+ratio+'"]');
            $item.addClass(active_item_class);
            var $vis = $trigger.find('.fx-ratio-input__visible');
            $vis.find('span').text($item.text());
            append_ratio_size($vis, ratio, ratio > 1 ? 1900 : 1400);
            $trigger.find('.fx-ratio-input__value').val( $item.data('value') ).trigger('change');
        }
        
        $control.on('click', '.fx-ratio-input__item', function() {
            var $item = $(this);
            set_value($item.data('value'));
            hide_control();
        });
        
        set_value(json.value);
        
        return $row;
    },
    
    'css-background': function(json) {
        var $row = $t.jQuery('form_row', json),
            $node = $row.find('.fx-background-control');
        var bg_control = new fx_background_control($node, json);
        $node.data('bg_control', bg_control);
        return $row;
    },

    'css-font': function(json) {
        return fx_font_control(json);
    },

    'toggle_button': function(label, value, title) {
        var cl = 'fx-toggle-button';
        var $node = $('<div class="'+cl+' ' + (value ? ' '+cl+'_on' : '')+'">'+label+'</div>');
        if (title) {
            $node.attr('title', title);
        }
        $node.on('click', function() {
            $node.toggleClass('fx-toggle-button_on');
            $node.trigger('change');
        });
        $node.val = function(value) {
            if (value === undefined) {
                return $node.hasClass(cl+'_on');
            }
            $node.toggleClass(cl+'_on', value);
        };
        return $node;
    },

    'css-font-family': function(value, avail_families){
        var all_families = {
            text: 'Основной',
            headers: 'Для заголовков',
            nav: 'Для навигации'
        },
        values = {};
        if (!avail_families) {
            values = all_families;
        } else {
            if (typeof avail_families === 'string') {
                avail_families = avail_families.split(/[\s\,]+/);
            }
            for (var i = 0; i < avail_families.length; i++) {
                var c_family = avail_families[i];
                values[c_family] = all_families[c_family];
            }
        }
        var handy_values = [];
        $.each(values, function(code, label) {
            var family_name = $fx.layout_vars['font_'+code];
            handy_values.push(
                {
                    id:code,
                    name:"<span style='font-family:"+family_name+";' title='"+family_name+"'>"+label+'</span>'
                }
            );
        });
        return this.livesearch({
            values:handy_values,
            value:value,
            'allow_empty': false
        }, 'input');
    },

    'css-text-transform': function(json, template) {
        json.type = 'livesearch';
        json.values = {
            none:'Abc',
            uppercase:'ABC',
            lowercase:'abc'
        };
        json.allow_empty = false;
        return this.livesearch(json, template);
    },

    palette: function(json, template) {
        var $row = $t.jQuery('form_row', json),
            el = $t.getBemElementFinder('fx-palette'),
            $colors = $row.find(el('colors')),
            $value = $row.find(el('value')),
            $cval = $row.find( el('value-color') ),
            opacity_slider = null,
            $opacity = null;

        $fx.container.colors = json.colors;

        $('body').append($colors);

        if (json.opacity) {
            $opacity = $colors.find( el('opacity') );
            var v = parse_value(json.value),
                initial_opacity = v ? v.opacity : 1;

            opacity_slider = new number_slider(
                $opacity, {
                    min:0,
                    max:1,
                    step:0.05,
                    value: initial_opacity,
                    round:2,
                    change:function(val) {
                        var $c_color = $colors.find( el('color-level.active')),
                            color_value = $c_color.data('value');
                        set_value(color_value ? color_value + ' ' + val : color_value, false);
                        $value.trigger('change');
                    }
                }
            );
        }

        var first_opened = true;

        function hide_colors() {
            $colors.css('display', 'none');
            $cval.off('blur');
            $('html').off('mousedown.fx_palette_clickout');
        }

        function parse_value(value) {
            var color_parts = value.match(/([a-z]+)\s+(\d+)(\s+[\d\.]+)?$/);
            if (!color_parts) {
                return;
            }
            var res = {
                level: color_parts[2] * 1,
                opacity: 1,
                type: color_parts[1]
            };
            if (json.opacity && color_parts[3]) {
                res.opacity = color_parts[3].replace(/\s+/, '') * 1;
            }
            return res;
        }

        function set_value(value, hide) {
            var light_value = null,
                $color = null;

            hide = hide === undefined ? true : hide;

            if (value) {
                var v  = parse_value(value);

                if (!v) {
                    return;
                }

                var $row_colors = $colors.find(el('color.type_' + v.type)).find(el('color-level')),
                    $light_color = null;

                $color = $row_colors.eq(v.level);

            } else {
                $color = $colors.find( el('color-level') ).last();
            }

            if (json.transparent === true && v) {

                if (v.level >= 3 ) {
                    v.level = 5 - v.level;
                    value = v.type + ' ' + v.level;
                    $color = $row_colors.eq(v.level);
                }
                var light_level  = 5 - v.level;
                light_value = v.type + ' ' + light_level;
                $light_color = $row_colors.eq(light_level);
            }

            if (json.opacity) {
                value ? opacity_slider.enable() : opacity_slider.disable();
            }

            $cval.toggleClass('fx-palette__value-color_empty', !value);

            if (hide) {
                hide_colors();
            }

            $value.val(value);

            var active_class = 'fx-palette__color-level_active';

            $colors.find( '.' + active_class ).removeClass( active_class );
            $color.addClass(active_class);

            if (light_value !== null) {
                $light_color.addClass(active_class);
                var c1 = $color.data('color'),
                    c2 = $light_color.data('color');
                $cval.css(
                    'background',
                    'linear-gradient(135deg, '+c1+', '+c1+' 55%, '+ c2 +' 55.5%, '+c2 +')'
                );
            } else {
                var val_bg = '';
                if (value) {
                    val_bg = $color.data('color');
                    var tc = tinycolor(val_bg);
                    if (json.opacity) {
                        tc.setAlpha(v.opacity);
                        //val_bg = tinycolor(val_bg).setAlpha(v.opacity).toRgbString();
                    }
                    val_bg = tc.toRgbString();
                    $value.data('rgb-value', tc.toRgb());
                }
                $cval.css('background', val_bg);
            }
            if (json.opacity) {
                var color_value = value ? $color.data('color') : '#fff';
                $opacity.css(
                    'background-image',
                    'linear-gradient(to right, transparent, '+color_value+')'
                );
            }
            $cval.focus();
        }

        set_value( $value.val() );

        $colors.on('click', el('color-level'), function() {
            var c_value = $(this).data('value');
            if (json.opacity && c_value) {
                c_value += ' '+opacity_slider.get();
            }
            set_value(c_value, !json.opacity);
            $value.trigger('change');
        });
        
        function handle_click() {
            var box = $cval[0].getBoundingClientRect();
            if ($colors.is(':visible')) {
                hide_colors();
                return;
            }
            $colors.css({
                top: box.top + box.height,
                left: box.left,
                display:'block'
            });
            $cval.on('keydown', function(e) {
                if (e.which === 27 || e.which === 13 || e.which === 32) {
                    hide_colors();
                    return false;
                }
            }).on('blur', function() {
                $cval.focus();
            });
            setTimeout(
                function() {
                    $('html').on('mousedown.fx_palette_clickout', function (e) {
                        var $t = $(e.target);
                        if (
                            $t.closest($colors).length === 0 &&
                            $t.closest($cval).length === 0
                        ) {
                            hide_colors();
                        }
                    });
                },
                10
            );
            if (first_opened) {
                $value.parents().one('fx_destroy', function() {
                    $colors.remove();
                });
                first_opened = false;
            }
        }

        //$cval.click();
        var mdt = null;
        $cval.on('mousedown', function() {
            mdt = new Date();
        });
        $cval.on('mouseup', function() {
            var mut = new Date();
            if (mut - mdt < 250) {
                handle_click();
            }
        });
        return template  === 'input' ? $row.find('.fx-palette') : $row;
    },

    group:function(json) {
        var $row =  $t.jQuery('form_row', json),
            b = 'fx-field-group',
            exp_class = b+'_expanded',
            $group = $('.'+b, $row),
            $fields = $('.'+b+'__fields', $group);
            
        if (json.fields && json.fields.length > 0) {
            $fx_form.draw_fields(json, $fields);
        }
        
        function is_expanded() {
            return $group.hasClass(exp_class);
        }
        
        function expand() {
            $group.addClass(exp_class);
            var fields_height = $fields.height();
            $fields.css({
                overflow:'hidden',
                height:0
            }).animate(
                {
                    height:fields_height
                },
                300,
                null,
                function(){ 
                    $fields.attr('style', '');
                }
            );
        }
        
        function collapse() {
            $fields.css({
                overflow:'hidden'
            }).animate(
                {
                    height:0
                }, 
                300, 
                null, 
                function() {
                    $group.removeClass(exp_class);
                    $fields.attr('style', '');
                }
            );
            
        }
        
        function toggle() {
            if (is_expanded()) {
                collapse();
            } else {
                expand();
            }
        }
        
        $group
            .find('.'+b+'__title')
            .on('click', toggle)
            .on('keydown', function(e) {
                // enter or space - toggle
                if (e.which === 13 || e.which === 32) {
                    toggle();
                    return false;
                }
                // down or right - expand
                if ( (e.which === 40 || e.which === 39) && !is_expanded()) {
                    expand();
                    return false;
                }
                // up left or escape - collapse
                if ( (e.which === 37 || e.which === 38 || e.which === 27) && is_expanded()) {
                    collapse();
                    return false;
                }
            });
        
        return $row;
    },

    label: function(json) {
        return $t.jQuery('field_label', json);
    },

    input: function(json) {
        return $t.jQuery('form_row', json);
    },
    
    control: function(json) {
        var type = json.type || 'string',
            $res;
        if (typeof this[type] === 'function') {
            $res = this[type](json, 'input');
        } else {
            $res = $t.jQuery('input', json);
        }
        return $res;
    },
    
    number: function(json, template) {
        if (!json.type ) {
            json.type = 'number';
        }
        json.class_name = 'number' + (json.class_name || '');
        var $res = $t.jQuery(template ? template : 'form_row', json);
        var $inp = template === 'input' ? $res : $res.find('input');
        this.handle_number_wheel($inp);
        return $res;
    },

    file: function (json) {
        return $t.jQuery('form_row', json);
    },

    image: function ( json , template) {
        var $row =  $t.jQuery('form_row', json);
        return template === 'input' ? $row.find('.fx_image_field') : $row;
    },

    textarea: function(json) {
        json.field_type = 'textarea';
        return $t.jQuery('form_row', json);
    },

    select: function(json) {
        return $t.jQuery('form_row', json);
    },

    radio: function(json) {
        return $t.jQuery('form_row', json);
    },

    radio_facet: function (json , template) {
        template = template || 'form_row';
        var $node = $t.jQuery(template, json),
            cl = 'fx-radio-facet',
            vcl = cl + '__variant';
        function select_variant($variant) {
            $('.'+vcl, $node).removeClass(vcl+'_active');
            $variant.addClass(vcl+'_active');
            $('input[type="hidden"]',$node).val($variant.data('value')).trigger('change');
        }
        $node.on('click', '.'+vcl, function() {
            select_variant($(this));
        });
        $node.on('keydown', '.'+vcl, function(e) {
            if (e.which === 13 || e.which === 32) {
                select_variant($(this));
                return false;
            }
        });
        return $node;
    },
    
    bool:function(json) {
        delete json.values;
        json.type = 'checkbox';
        return $fx_fields.checkbox(json);
    },

    checkbox: function(json) {
        var is_toggler = json.class === 'toggler';
        if (is_toggler) {
            json.class_name = json.class;
        }
        var $res = $t.jQuery('form_row', json);
        if (is_toggler) {
            var $toggler = $('.fx_toggler', $res),
                $input = $('input', $res),
                $control = $('.fx_toggler__control', $res);
            function toggle() {
                if ($toggler.hasClass('fx_toggler_on')) {
                    $toggler.removeClass('fx_toggler_on').addClass('fx_toggler_off');
                    $input.val(0);
                } else {
                    $toggler.removeClass('fx_toggler_off').addClass('fx_toggler_on');
                    $input.val(1);
                }
                $control.focus();
            }
            $toggler.click(toggle).keydown(function(e) {
                if (e.which === 13 || e.which === 32) {
                    toggle();
                    return false;
                }
            });
        }
        return $res;
    },

    color: function(json) {
        var $res = $t.jQuery('form_row', json),
            $inp = $('.fx-colorpicker-input', $res);
        
        setTimeout(
            function() {
                $inp.spectrum({
                    preferredFormat:'rgb',
                    showInput: true,
                    allowEmpty:true,
                    showAlpha: json.alpha === undefined ? true : json.alpha,
                    clickoutFiresChange: true,
                    move:function(c) {
                        $inp.spectrum('set', c === null ? c : c.toRgbString());
                        $inp.trigger('change');
                    },
                    hide:function(c) {
                        $inp.spectrum('set', c === null ? c : c.toRgbString());
                        $inp.trigger('change');
                    }
                });
            },
            50
        );
        return $res;
    },

    iconselect: function(json) {
        return $t.jQuery('form_row', json);
    },

    livesearch: function(json, template) {
        template = template || 'form_row';
        json.params = json.params || {};
        if (!json.type) {
            json.type = 'livesearch';
        }
        
        if (json.content_type && !json.params.content_type) {
            json.params.content_type = json.content_type;
        }
        
        function vals_to_obj(vals, path) {
            var res = [];
            if (path === undefined) {
                path = [];
            }
            
            for (var i = 0; i < vals.length; i++) {
                var val = vals[i],
                    res_val = val;
                
                if (val instanceof Array && val.length >= 2) {
                    res_val = {
                        id:val[0]
                    };
                    if (typeof val[1] === 'string') {
                        res_val.name = val[1];
                    } else if (typeof val[1] === 'object') {
                        res_val = $.extend({}, res_val, val[1]);
                    }
                    if (val.length > 2) {
                        res_val =  $.extend({}, res_val, val[2]);
                    }
                }
                if ( !(json.value instanceof Array) && json.value == res_val.id) {
                    json.value = res_val;
                    json.value.path = path.slice(0);
                }
                path.push(res_val.name);
                if (res_val.children && res_val.children.length) {
                    res_val.children = vals_to_obj(res_val.children, path);
                }
                path.pop();
                res.push(res_val);
                
            }
            return res;
        }
        
        if (json.fontpicker) {
            window.fx_font_preview.init_stylesheet();
            json.values = window.fx_font_preview.get_livesearch_values( json.fontpicker );
        }
        
        if (json.values) {
            var preset_vals = json.values,
                has_custom = false;
            if ( ! (json.values instanceof Array) ) {
                preset_vals = [];
                $.each(json.values, function(k, v) {
                    preset_vals.push([k, v]);
                });
            }
            
            json.params.preset_values = vals_to_obj(preset_vals);
            
            for (var i = 0; i < json.params.preset_values.length; i++) {
                if (json.params.preset_values[i].custom) {
                    has_custom = true;
                    break;
                }
            }
            
            if (typeof json.allow_empty === 'undefined') {
                json.allow_empty = false;
            }
            
            if (json.allow_empty === false && (!json.value || typeof json.value.id === 'undefined')) {
                if (!has_custom) {
                    json.value = json.params.preset_values[0];
                }
            }
        }
        if (json.allow_select_doubles) {
            json.params.allow_select_doubles = json.allow_select_doubles;
        }
        
        var $ls = $t.jQuery(template, json);
        if (json.values && json.values.length === 0) {
            $ls.hide();
        }
        if (json.fontpicker) {
            var $input = $ls.find('.livesearch__input');
            function add_input_style(value) {
                if (!value) {
                    return;
                }
                var $v = $(value);
                $input.addClass($v.attr('class'));
                $input.attr('style', $v.attr('style'));
            }
            if (json.value && json.value.name) {
                add_input_style( json.value.name );
            }
            
            $ls.on('livesearch_value_added', function(e) {
                add_input_style(e.value_name);
            });
        }
        return $ls;
    },


    set: function(json) {
        return $t.jQuery('form_row', json);
    },

    tree: function(json) {
        return $t.jQuery('form_row', json);
    },

    table: function (json) {
        return $t.jQuery('form_row', json);
    },
    
    buttons: function(json) {
        return $t.jQuery('form_row', json);
    },

    button: function (json) {
        if (!json.type) {
            json = $.extend({}, json, {type: 'button'} );
        }
        return $t.jQuery('form_row', json);
    },

    link: function(json) {
        return $t.jQuery('form_row', json);
    },

    list: function(json) {
        return $t.jQuery('form_row', json);
    },

    datetime: function ( json , template) {
        template = template || 'form_row';
        return $t.jQuery(template, json);
    },

    float: function (json ) {
        return $t.jQuery('form_row', json);
    },
    
    password: function(json){
        return $t.jQuery('form_row', json);
    },
    map: function (json) {
        var $field = $t.jQuery('form_row', json);
        new fx_google_map_field($field, json);
        return $field;
    },
    joined_group: function(json) {
        return $t.jQuery('form_row', json);
    },
    make_codemirror: function(textarea, options) {
        
    },
    make_redactor: function($node, options) {
        options = $.extend({
            imageUpload : document.baseURI + 'vendor/Floxim/Floxim/Admin/Controller/redactor-upload.php',
            tidyHtml:false,
            toolbarFixed:false,
            buttons: [
                    'html', 
                    //'formatting',  
                    'bold', 'italic', 'deleted', 'link',
                    'unorderedlist', 'orderedlist', 'alignment'
                        //'outdent', 'indent',
                        /*'image', 'video', 'file', 'table',  'horizontalrule'*/
                    ],
            //plugins: ['fontcolor'],
            cleanSpaces:false,
            lang: $fx.lang('lang'),
            formatting: ['p', 'h2', 'h3'],
            tabKey:false
        }, options);
        
        if (options.toolbarPreset === 'inline') {
            options.buttons = ['bold', 'italic', 'deleted'];
        }
        
        if (options.extra_buttons) {
            for(var i = 0; i < options.extra_buttons.length; i++) {
                options.buttons.push(options.extra_buttons[i]);
            }
            delete options.extra_buttons;
        }
        var e = $.Event('fx_create_redactor');
        e.redactor_options = options;
        $node.trigger(e);
        $node.redactor(options);
    },
    init_fieldset: function(html, _c) {
        $('tbody.fx_fieldset_rows', html).sortable();

        var fs = $('.fx_fieldset', html);

        if (!_c.values) {
            _c.values = _c.value || [];
        }
        var flag = false;
        $.each(_c.values, function(row, val) {
            var val_num = 0;
            var inputs = [];
            var row_index = val._index || row;
            $.each(_c.tpl, function(tpl_index, tpl_props) {
                var inp_props = $.extend(
                    {}, 
                    tpl_props, 
                    {
                        name:_c.name+'['+row_index+']['+tpl_props.name+']',
                        value:val[tpl_props.name]
                    }
                );
                if (tpl_props.type === 'radio' && !tpl_props.values) {
                    inp_props.$input_node = $('<input type="radio" name="'+_c.name+'['+tpl_props.name+']" value="'+row_index+'" />');
                    if (tpl_props.value == row_index) {
                        inp_props.$input_node.attr('checked', 'checked');
                    }
                }
                inputs.push(inp_props);
            });
            $('.fx_fieldset_rows', html).append($t.jQuery('fieldset_row', inputs, {index:row, set_field: _c}));
        });
        function remove_row($row) {
            var $next_row = $row.next('.fx_fieldset_row');
            $row.remove();
            if ($next_row.length > 0) {
                $next_row.find(':input, .fx_fieldset_remove').first().focus();
            } else {
                $('.fx_fieldset_add', fs).focus();
            }
        }
        function add_row() {
            var inputs = [];
            var index = $('.fx_fieldset_row', fs).length + 1;
            for (var i = 0; i < _c.tpl.length; i++) {
                inputs.push( 
                    $.extend({}, _c.tpl[i], {
                        name:_c.name+'[new_'+index+']['+_c.tpl[i].name+']'
                    })
                );
            }
            var $new_row = $t.jQuery('fieldset_row', inputs, {index:index, set_field: _c});
            $('.fx_fieldset_rows', fs).append($new_row);
            $new_row.find(':input:visible').first().focus();
        }
        fs.on('click', '.fx_fieldset_remove', function() {
            remove_row($(this).closest('.fx_fieldset_row'));
        });
        fs.on('keydown', '.fx_fieldset_remove', function(e) {
            if (e.which === 32 || e.which === 13) {
                remove_row($(this).closest('.fx_fieldset_row'));
                return false;
            }
        });
        $('.fx_fieldset_add', fs).click( function() {
            add_row();
        }).on('keydown', function(e) {
            if (e.which === 32 || e.which === 13) {
                add_row();
            }
        });
    }
};

// file field

var $html = $('html');
$html.on('keydown', '.fx_image_field .fx_file_control', function(e) {
    var $target = $(e.target);
    // skip bubbled events
    if (!$target.is('.fx_file_control')) {
        return;
    }
    if (e.which === 32 || e.which === 13) {
        $target.click();
        return false;
    }
});

$html.on('click.fx', '.fx_image_field .fx_remote_file_block',  function() {
    var $block = $(this);
    if ($block.is('active')) {
        return;
    }
    $block.addClass('active');
    $block.closest('.fx_preview').addClass('fx_preview_active');
    var $inp = $block.find('input');
    $inp.focus().off('keydown.fx_blur').on('keydown.fx_blur', function(e) {
        if (e.which === 27) {
            $(this).blur().trigger('change');
            $block.focus();
            return false;
        }
    }).trigger('change');
});

$html.on('blur.fx', '.fx_image_field .fx_remote_file_block input', function() {
    var $inp = $(this);
    if ($inp.attr('disabled') !== undefined) {
        return;
    }
    $inp.closest('.fx_remote_file_block').removeClass('active');
    $inp.closest('.fx_preview').removeClass('fx_preview_active');
});

function handle_upload(data, $block) {
    if (data.format === 'fx-response') {
        data = data.response;
    }
    var $res_inp = $('.real_value', $block);
    var field_type = $block.data('field_type');
    $res_inp.val(data.path);
    var $panel = $res_inp.closest('.fx_node_panel');
    if ($panel.length === 0) {
        if (field_type === 'image') {
            $('.fx_preview img', $block).attr('src', data.path).show();
        }
        var $fi = $('.fx_file_info', $block);
        
        $('.fx_file_name', $fi)
            .attr('href', data.path)
            .text(data.filename);
        
        $('.fx_file_size', $fi)
            .text(data.size);
    
        if (field_type === 'image') {
            $('.fx_image_size')
                .html(data.width+'&times;'+data.height);
        }
        
    }
    $('.fx_file_killer', $block).show();
    $('.fx_file_input', $block).hide();
    $('.fx_preview', $block).addClass('fx_preview_filled');
    
    $res_inp.data('fx_upload_response', data);
    
    var e = $.Event('fx_change_file');
    e.upload_response = data;
    $res_inp.trigger(e);
}

$html.on('change.fx', '.fx_image_field input.file', function() {
    var $field = $(this);
    var $block = $field.closest('.fx_image_field');
    var inp_id = $field.attr('id');
    var $real_inp = $('.real_value', $block);
    var format = $real_inp.data('format_modifier');
    $.ajaxFileUpload({
        url: $fx.settings.action_link,
        secureuri:false,
        fileElementId:inp_id,
        dataType: 'json',
        data: { entity:'file', fx_admin:1, action:'upload_save', format:format },
        success: function ( data ) {
            handle_upload(data, $block);
        }
    });
});

function load_cropper($inp) {
    var format = $inp.data('format_modifier'),
        src = $inp.val();
    $fx.post({
        entity:'file',
        action:'get_image_meta',
        file: src,
        format: format
    }, function(res) {
        create_cropper($inp, res);
    });
}

function create_cropper($inp, meta) {
    var format = $inp.data('format_modifier'),
        src = $inp.val(),
        parsed_format = meta.format,
        current_crop = meta.current ? meta.current.crop : {},
        aspect_ratio = null,
        $block = $inp.closest('.fx_image_field');
    
    if (!src){
        return;
    }
    if (parsed_format && parsed_format.width && parsed_format.height) {
        aspect_ratio = parsed_format.width / parsed_format.height;
    }
    
    var cl = 'fx-cropper-popup',
        $popup = $(
            '<div class="'+cl+' fx_overlay">'+
                '<div class="'+cl+'__wrapper">'+
                    '<div class="'+cl+'__image-container">'+
                        '<img src="'+src+'" class="'+cl+'__image" />'+
                    '</div>'+
                '</div>'+
                '<div class="'+cl+'__controls"></div>'+
                '<input type="hidden" value="" class="'+cl+'__value" />'+
            '</div>'
        ),
        $wrapper = $('.'+cl+'__wrapper', $popup),
        $img = $('.'+cl+'__image', $popup),
        $controls = $('.'+cl+'__controls', $popup),
        $cancel = $fx_fields.button({class:'cancel',label:'Отмена'}),
        $save = $fx_fields.button({label:'Готово',is_active:true}),
        $val = $('.'+cl+'__value', $popup);
        
    $popup.css('opacity', 0);
    $('body').append($popup);
    var c_color = current_crop && current_crop.color ? current_crop.color : '',
        $color = $fx_fields.color({
            name:'cropper-color', 
            value: c_color, 
            type:'color',
            alpha:false
        }),
        $color_input = $('.fx-colorpicker-input', $color);
    
    if (c_color) {
        $wrapper.css('background', c_color).addClass(cl+'__wrapper_has-color');
    }
    
    $controls.append($color);
    $controls.append($cancel).append($save);
    
    $cancel.click(function() {$popup.remove();});
    
    var  sides = {
        n: 'Y',
        e: 'X',
        s: 'Y',
        w: 'X'
    };
    
    function get_crop_data() {
        var crop = {},
            data = $img.cropper('getData');
    
        $.each(['width', 'height', 'x', 'y'], function() {
            crop[this] = Math.round( data[this] );
        });
        return crop;
    }
    
    var bounds = {};
    
    $img.cropper({
        //background: c_color ? false : true,
        modal:false,
        dragMode:'move',
        data:current_crop,
        aspectRatio:aspect_ratio,
        autoCropArea:1,
        movable:false,
        built: function(e) {
            var image = $img.cropper('getImageData'),
                container = $img.cropper('getContainerData'),
                ratio = image.naturalHeight / image.height;
            if (ratio < 1) {
                var image_data = {
                    width:image.naturalWidth,
                    height:image.naturalHeight,
                    top: (container.height - image.naturalHeight) / 2,
                    left: (container.width - image.naturalWidth) / 2
                },
                crop_data = get_crop_data();
                
                $img.cropper('setCanvasData', image_data);
                
                $img.cropper('setData', crop_data);
                /*
                if (current_crop) {
                    $img.cropper('setData', current_crop);
                }
                */
            }
            $popup.css('opacity', 1);
        },
        cropstart: function(e) {
            var canvas = $img.cropper('getCanvasData'),
                box = $img.cropper('getCropBoxData'),
                sx = e.originalEvent.pageX,
                sy = e.originalEvent.pageY;
        
            bounds = {
                    n: sy - (box.top - canvas.top),
                    e: sx - ((box.left + box.width) - (canvas.left + canvas.width)),
                    s: sy - ((box.top + box.height) - (canvas.top + canvas.height)),
                    w: sx - (box.left  - canvas.left)
                };
        },
        cropmove: function(e) {
            
            var oe = e.originalEvent;
            if (oe && oe.ctrlKey) {
                return;
            }
            if (!e.action || !oe) {
                return;
            }
            var tolerance = 15;

            var act = e.action === 'all' ? 'nesw' : e.action,
                offsets = {
                    X:[],
                    Y:[],
                    mapX:{},
                    mapY:{}
                };

            $.each(bounds, function(k, v) {
                var axis = sides[k],
                    val = oe['page'+axis],
                    diff = Math.abs(val - v);
                if (diff > tolerance) {
                    return;
                }
                offsets[axis].push(diff);
                offsets['map'+axis][diff] = v;
            });
            if (!offsets.X.length && !offsets.Y.length) {
                return;
            }

            var fe = $.extend(
                $.Event(oe.type), 
                oe, 
                {
                    preventDefault:function() {}
                }
            );

            if (offsets.X.length) {
                fe.pageX = offsets.mapX[ Math.min.apply(null, offsets.X) ];
            }
            if (offsets.Y.length) {
                fe.pageY = offsets.mapY[ Math.min.apply(null, offsets.Y) ];
            }
            e.preventDefault();
            $(oe.target).trigger(fe);
        },
        crop: function(e) {
            var crop = get_crop_data();
            $val.data( 'crop_data', crop );
        },
        zoom: function(e) {
            if (e.originalEvent) {
                e.preventDefault();
                if (e.ratio > 4) {
                    return;
                }
                var crop_data = get_crop_data();
                $img.cropper('zoomTo', e.ratio);
                $img.cropper('setData', crop_data);
            }
        }
    });
    window.$cropper = $img;
    $color_input.on('change', function() {
        var color = $color_input.val();
        $wrapper.css('background-color', color);
        $wrapper.toggleClass(cl+'__wrapper_has-color', color ? true : false);
    });
    
    $save.click(function() {
        var crop = $val.data('crop_data');
        var color = $color_input.val();
        if (color) {
            crop.color = color;
        }
        var data = {
            entity:'file',
            action:'save_image_meta',
            file: src,
            format: format,
            crop: JSON.stringify(crop)
        };
        $fx.post(data, function(res) {
            $popup.remove();
            handle_upload(res, $block);
        });
    });
}

$html.on('click.fx', '.fx_image_field .fx_file_uploader', function(e) {
    var $control = $(this);
    
    $control.closest('.fx_image_field').find('input.file').focus().click();
    $control.focus();
});

$html.on('click.fx', '.fx_image_field .fx_image_cropper', function(e) {
    
    var $real_inp = 
            $(this)
                .closest('.fx_image_field')
                .find('.real_value');
    
    load_cropper($real_inp);
    return false;
});

$html.on('click.fx', '.fx_image_field .fx_file_killer', function() {
   var $field = $(this).closest('.fx_image_field'); 
   $('.real_value', $field).val('').trigger('fx_change_file');
   $('.fx_file_input', $field).show();
   $('.fx_preview', $field).removeClass('fx_preview_filled');
   $(this).hide();
   $field.find('.fx_file_control:visible').last().focus();
});

$html.on('paste.fx', '.fx_image_field .remote_file_location', function() {
    var $inp = $(this);
    var $block = $inp.closest('.fx_image_field');
    var $real_inp = $('.real_value', $block);
    var format = $real_inp.data('format_modifier');
    setTimeout(function() {
        var val = $inp.val();
        if (!val.match(/https?:\/\/.+/)) {
            return;
        }
        $inp.attr('disabled', 'disabled').val($fx.lang('loading')+'...');
        $.ajax({
            url:$fx.settings.action_link,
            type:'post',
            data: { entity:'file', fx_admin:1, action:'upload_save' , file:val, format:format},
            dataType: 'json',
            success: function ( data ) {
                handle_upload(data, $block);
                $inp.get(0).removeAttribute('disabled');
                $inp.val('').blur();
            },
            error: function( data ) {
                
                $inp.get(0).removeAttribute('disabled');
                $inp.val('').focus();
                var message = $fx.lang('Can not load this file');
                if (data && data.error_text) {
                    message += '<br />'+data.error_text;
                }
                $fx.alert(message, 'error');
            }
        });
    }, 50);
});

window.$fx_fields.parse_std_date = function(date_str) {
    var parts = date_str.split(' ');
    var date_parts = parts[0].split('-');
    var time_parts = parts[1].split(':');
    // year, month, date[, hours, minutes, seconds
    var res = new Date(
        date_parts[0],
        date_parts[1]-1,
        date_parts[2],
        time_parts[0],
        time_parts[1],
        time_parts[2]
    );
    return res;
};

window.$fx_fields.handle_date_field = function(html) {
    var inp  = $('input.date_input', html);

function export_parts() {
    var res = '',
        months = {
            '01':'Jan', '02':'Feb', '03':'Mar', '04':'Apr', '05':'May', '06':'Jun',
            '07':'Jul', '08':'Aug', '09':'Sep', '10':'Oct', '11':'Nov', '12':'Dec'
        },
        filled = true;
    $.each(
        'd,m,y,h,i'.split(','), 
        function(index, item) {
            var c_val = $('.fx_date_part_'+item, html).val();
            if (!c_val) {
                if (item === 'h' || item === 'i') {
                    c_val = '00';
                } else {
                    filled = false;
                }
            }
            if (item === 'm') {
                c_val = months[c_val];
            }
            res += c_val;
            res += (index < 2 ? ' ' : index === 2 ? ' ' : ':');
        }
    );
    res += '00';
    if (filled) {
        var date = new Date(res);
        if (date && !isNaN(date.getTime())) {
            inp.val( format_date ( date ) );
            inp.trigger('change');
        }
    }
};

html.on('keydown', '.fx_date_part', function(e) {
    var $part = $(this),
        part_val = $part.val(),
        max = $part.data('max'),
        min = $part.data('min') || 0,
        len = $part.data('len'),
        strikes = ( $part.data('strikes') || 0) + 1;
    
    $part.data('strikes', strikes);

    if (e.which === 40 || e.which === 38) { // down or up
        part_val = part_val*1;
        part_val += (e.which === 40 ? -1 : 1);
        if (part_val < min) {
            part_val = max;
        } else if (part_val > max) {
            part_val = min;
        }
        
        if (len === 2 && part_val < 10) {
            part_val = '0'+part_val;
        }
        
        $part.val(part_val);
        return false;
    }
});

function format_date(d) {
    var res = $.datepicker.formatDate("yy-mm-dd", d );
    res += ' ';
    var h = d.getHours();
    res += (h < 10 ? '0' : '')+h + ':';
    var m = d.getMinutes();
    res += (m < 10 ? '0' : '')+m+':00';
    return res;
}

html.on('focus mouseup click', '.fx_date_part', function(e) {
    this.setSelectionRange(0, this.value.length);
    $(this).data('strikes', 0);
    return false;
});

html.on('keyup', '.fx_date_part', function(e) {
    var $part = $(this),
        part_val = $part.val(),
        min = $part.data('min'),
        max = $part.data('max'),
        len = $part.data('len');
    
    if (part_val.length > len) {
        part_val = part_val.slice(0, len);
    }
    
    if (part_val.match(/[^0-9]/)) {
        part_val = part_val.replace(/[^0-9]+/g, '');
    }
    
    var int_val = part_val*1;
    
    if (int_val > max) {
        part_val = max;
    }
    if (part_val + '' !== $part.val()) {
        $part.val(part_val);
    }
    
    export_parts();
    
    if (this.selectionStart !== undefined && this.selectionStart === this.selectionEnd) {
        if (this.selectionStart === 0 && e.which === 37) {
            var $prev = $part.prevAll('.fx_date_part').first();
            if ($prev.length) {
                $prev.focus().focus();
            }
        } else if (this.selectionEnd === part_val.length && e.which === 39) {
            var $next = $part.nextAll('.fx_date_part').first();
            if ($next.length) {
                $next.focus().focus();
            }
        }
    }
    
    if (e.which < 48 || e.which > 57 || !$part.data('strikes')) { 
        return;
    }
    
    if (part_val.length === $part.data('len')) {
        var int_val = part_val*1;
        if (int_val >= min && int_val <= max) {
            var $next = $part.nextAll('.fx_date_part').first();
            if ($next.length) {
                $next[0].setSelectionRange(0, $next.val().length);
                $next.focus().focus();
            }
        }
    }
});
/*
function get_inp_time(inp) {
    var v = inp.val();
    var v_time = v.replace(/^[^\s]+\s/, '');
    inp.data('time', v_time);
}
get_inp_time(inp);
inp.keyup(function() {get_inp_time($(this))});
inp.click(function() {
    $(this).datepicker('show');
});
*/

var show_format = 'yy-mm-dd';

inp.datepicker({
    changeMonth: true,
    changeYear: true,
    firstDay:1,
    dateFormat: show_format,
    onSelect:function(dateText, datepicker) {
        var d = new Date(dateText);
        $('.fx_date_part_d', html).val( $.datepicker.formatDate("dd", d) );
        $('.fx_date_part_m', html).val( $.datepicker.formatDate("mm", d) );
        $('.fx_date_part_y', html).val( $.datepicker.formatDate("yy", d) );
        export_parts();
    }
});
inp.datepicker('widget').addClass('fx_overlay');

$('.fx_datepicker_icon', html).click(function() {
    inp.datepicker('show');
});

};

})($fxj);