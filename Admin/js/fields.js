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
    
    wheel_freezer_added: false,
    
    handle_number_wheel: function ($inp, params) {
        
        var that = this;
        if (!this.wheel_freezer_added) {
            var is_waiting = false;
            
            $('body').on('wheel', function(e) {
                if (is_waiting) {
                    return;
                }
                is_waiting = true;
                that.wheel_target = e.target;
                setTimeout(
                    function() {
                        that.wheel_target = null;
                        is_waiting = false;
                    }, 
                    800
                );
            });
            this.wheel_freezer_added = true;
        }
        params = $.extend(
            {
                min: $inp.attr('min')*1,
                max: $inp.attr('max')*1,
                step: $inp.attr('step')*1,
                focus: true
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
        
        var c_delta = 0;
        
        $target.on('wheel', function(e) {
            if (that.wheel_target && that.wheel_target !== e.target) {
                return;
            }
            c_delta += e.originalEvent.deltaY;

            if (Math.abs(c_delta) < 20) {
                return false;
            }

            var delta = e.originalEvent.deltaY > 0 ? -1 : 1,
                c_value = $inp.val() * 1,
                new_value = ( c_value * multiplier + params.step * delta * multiplier ) / multiplier;
            if (new_value < params.min) {
                new_value = params.min;
            } else if (new_value > params.max ) {
                new_value = params.max;
            }
            if (params.focus) {
                $inp.focus();
            }
            $inp.val(new_value).trigger('change');
            c_delta = 0;
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
        
        var source_json = $.extend(true, {}, json);
        
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
        if (typeof json.label === 'undefined') {
            var labels = {
                borders: 'Рамка и углы',
                margin: 'Внешний отступ',
                padding: 'Внутренний отступ'
            };
            json.label = labels[json.prop];
        }
        var $row = $t.jQuery('form_row', json);
        $fx.measures.create($row, json);
        return $row;
    },
    
    ratio: function(json, template) {
        if (!json.label) {
            json.label = 'Пропорции';
        }
        function to_ratio(val) {
            if (val === 'none') {
                return val;
            }
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
            
            if (ratio === 'none') {
                ratio = 5;
            }
            
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
        if (json.auto) {
            avail_ratios.unshift(['none', 'Авто']);
        }
        
        json.value = to_ratio(json.value);
        
        for (var i = 0; i < avail_ratios.length; i++) {
            var c_ratio = avail_ratios[i],
                c_val = c_ratio[0],
                $container = $('<div class="fx-ratio-input__item-container"></div>'),
                $item = $('<div class="fx-ratio-input__item" data-value="'+c_val+'"><span>'+c_ratio[1]+'</span></div>');
            if (c_val === 'none') {
                $container.addClass('fx-ratio-input__item-container_auto');
            }
            append_ratio_size($item, c_val);
            
            $container.append($item);
            $control.append($container);
        }
        
        var handle_escape = function(e) {
            if (e.which === 27) {
                hide_control();
                e.stopImmediatePropagation();
                return false;
            };
        };
        
        $('body').append($control);
        
        var active_class = 'fx-ratio-input__control_active';
        
        
        function show_control () {
            $control.addClass(active_class);
            $('html').on('click.fx-ratio-input', function(e) {
                if ($(e.target).closest('.fx-ratio-input__control').length === 0) {
                    hide_control();
                    return false;
                }
            }).on('keydown.fx-ratio-input', handle_escape);
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
        if (!json.label) {
            json.label = 'Фон';
        }
        var $row = $t.jQuery('form_row', json),
            $node = $row.find('.fx-background-control');
        var bg_control = new fx_background_control($node, json);
        $node.data('bg_control', bg_control);
        return $row;
    },
    
    'css-shadow': function(json) {
        if (!json.label) {
            json.label = 'Тень';
        }
        var $row = $t.jQuery('form_row', json),
            $node = $row.find('.fx-shadow-control');
        var bg_control = new fx_shadow_control($node, json);
        $node.data('shadow_control', bg_control);
        return $row;
    },
    
    'css-align': function(json, tpl) {
        json.type = 'livesearch';
        json.values = {
            none:'Авто',
            left:'Слева',
            center:'По центру',
            right:'Справа'
        };
        if (!json.label) {
            json.label = 'Выравнивание';
        }
        return this.livesearch(json, tpl);
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
        $.each($fx.layout_fonts, function(index, font) {
            if (typeof values[font.keyword] !== 'undefined') {
                handy_values.push(
                    {
                        id: font.keyword,
                        name: "<span style='font-family:"+font.family+";' title='"+font.family+"'>"+font.type+'</span>'
                    }
                );
            }
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
            $palette = $row.find('.fx-palette'),
            $colors = $row.find(el('colors')),
            $value = $row.find(el('value')),
            $cval = $row.find( el('value-color') ),
            opacity_slider = null,
            $opacity = null,
            c_color = [];
        
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
            $palette.removeClass('fx-palette_active');
        }
        
        var closer = null;

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

            if (hide && closer) {
                closer();
            }

            $value.val(value);

            var active_class = 'fx-palette__color-level_active';

            $colors.find( '.' + active_class ).removeClass( active_class );
            $color.addClass(active_class);

            if (light_value !== null) {
                $light_color.addClass(active_class);
                var c1 = $color.data('color'),
                    c2 = $light_color.data('color');
                
                c_color = [c1, c2];
                
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
                c_color = [val_bg];
                $cval.css('background', val_bg);
            }
            if (json.opacity) {
                var color_value = value ? $color.data('color') : '#fff';
                $opacity.css(
                    'background-image',
                    'linear-gradient(to right, transparent, '+color_value+')'
                );
            }
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
        
        function show_colors() {
            $('.fx-palette_active').each(function() {
                if (this === $palette[0]) {
                    return;
                }
                $(this).data('palette').hide();
            });
            var box = $cval[0].getBoundingClientRect();
            $colors.css({
                top: box.top + box.height,
                left: box.left,
                display:'block'
            });
            
            closer = $fx.close_stack.push(
                function() {
                    hide_colors();
                },
                $colors
            );
    
            $palette.addClass('fx-palette_active');
    
            if (first_opened) {
                $value.parents().one('fx_destroy', function() {
                    $colors.remove();
                });
                first_opened = false;
            }
        }
        
        function handle_click() {
            
            if ($colors.is(':visible')) {
                if (closer) {
                    closer();
                }
                return;
            }
            show_colors();
        }

        
        var mdt = null;
        $cval.on('mousedown', function() {
            mdt = new Date();
        });
        $cval.on('mouseup', function() {
            var mut = new Date();
            if (mut - mdt < 250) {
                handle_click();
                return false;
            }
        });
        $palette.on('fx-palette-toggle', function(e, show) {
            if (typeof show === 'undefined') {
                handle_click();
                return;
            }
            if (show) {
                show_colors();
            } else if (closer) {
                closer();
            }
        });
        $palette.data('palette', {
            $colors: $colors,
            show: function() {
                show_colors();
            },
            hide: function() {
                if (closer) {
                    closer();
                } else {
                    console.log('no closr');
                }
            },
            val: function(v) {
                if (typeof v === 'undefined') {
                    return $value.val();
                }
                set_value(v);
            },
            get_color: function(index) {
                return typeof index === 'undefined' ? c_color : c_color[index];
                //return $cval.css('background');
            }
        });
        return template  === 'input' ? $row.find('.fx-palette') : $row;
    },

    group:function(json) {
        var $row =  $t.jQuery('form_row', json),
            b = 'fx-field-group',
            exp_class = b+'_expanded',
            $group = $('.'+b, $row),
            $fields = $('.'+b+'__fields', $group),
            data_loaded = false;
            
        function is_expanded() {
            return $group.hasClass(exp_class);
        }
        
        function expand() {
            if (json.loader && !data_loaded) {
                json.loader().then(function(data) {
                    if (typeof data === 'string') {
                        $fields.append(data);
                    } else {
                        $fx_form.draw_fields(data, $fields);
                    }
                    data_loaded = true;
                    expand();
                });
                return;
            }
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
            
        if (json.fields && json.fields.length > 0) {       
            $row.one('fx_field_attached', function(e) {
                $fx_form.draw_fields(json, $fields);
            });
        }
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
        if (json.value && json.units) {
            var urex = new RegExp(json.units+'\s*?$');
            if ((json.value+'').match(urex)) {
                json.value = json.value.replace(urex, '');
            }
        }
        json.class_name = 'number' + (json.class_name || '');
        var $res = $t.jQuery(template ? template : 'form_row', json);
        var $inp = template === 'input' ? $res : $res.find('input');
        this.handle_number_wheel($inp);
        return $res;
    },

    file: function (json, template) {
        var $row =  $t.jQuery('form_row', json),
            $field = $row.find('.fx_image_field');

        new fx_file_control($field);
        return template === 'input' ? $field : $row;
    },

    image: function ( json , template) {
        var $row =  $t.jQuery('form_row', json),
            $field = $row.find('.fx_image_field');
    
        new fx_file_control($field);
        return template === 'input' ? $field : $row;
    },

    textarea: function(json, template) {
        json.field_type = 'textarea';
        var $row = $t.jQuery('form_row', json),
            $field = $row.find('.fx_textarea_container');
        return template === 'input' ? $field : $row;
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
    
    bool:function(json, tpl) {
        delete json.values;
        json.type = 'checkbox';
        return $fx_fields.checkbox(json, tpl);
    },

    checkbox: function(json, template) {
        var is_toggler = json.class === 'toggler';
        if (is_toggler) {
            json.class_name = json.class;
        }
        var $res = $t.jQuery(template || 'form_row', json);
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
        if (template === 'input') {
            var $wrapper = $('<div class="field_checkbox"></div>');
            $wrapper.append($res);
            return $wrapper;
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
        return fx_livesearch.create(json, template);
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

    button: function (json, template) {
        if (!json.type) {
            json = $.extend({}, json, {type: 'button'} );
        }
        var $res = $t.jQuery('form_row', json);
        if (template !== 'input') {
            return $res;
        }
        return $res.find('.fx_button');
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

    float: function (json , template) {
        template = template || 'form_row';
        return $t.jQuery(template, json);
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
            tabKey:false,
            changeCallback: function() {
                if (this.$textarea) {
                    this.$textarea.trigger('input');
                }
            }
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
        $node.on('set_value', function(e) {
            $node.val(e.value);
            $node.redactor('code.set', e.value);
        });
    },
    
    set_value: function($inp, value) {
        var e = $.Event('set_value', {value:value});
        $inp.trigger(e);
        if (e.result === false) {
            return;
        }
        $inp.val(value);
    },
    
    name_to_path: function(field_name) {
        return field_name.replace(/\]$/, '').split(/\]?\[/);
    },
    
    path_to_name: function(path) {
        var base = path.shift();
        return path.length === 0 ? base : base + '['+ path.join('][')+']';
    },
    
    replace_last_name: function(old_full, new_last) {
        var full = this.name_to_path(old_full);
        full[full.length - 1] = new_last;
        return this.path_to_name(full);
    },
    
    init_fieldset: function(html, _c) {
        return fieldset(html, _c);
    }
};


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