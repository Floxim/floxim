(function() {
    
    
function bg($container, params) {
    this.$container = $container;
    this.$popup = $container.find(bg.el('popup'));
    this.$input = $container.find(bg.el('value'));
    this.$handler = $container.find(bg.el('handler'));
    this.params = params;
    this.init();
}

bg.cl = $t.getBem('fx-background-control');

cls = function() {
    return ' class="'+bg.cl.apply(null, arguments)+'" ';
};

bg.el = $t.getBemElementFinder(bg.cl());

bg.parse_value = function(v) {
    var res = {
            levels: []
        },
        val = parse_css_value(v);
    
    var lightness = val.shift()[0];
        
    res.lightness_locked = !lightness.match(/^custom_/);
    res.lightness = lightness.replace(/^custom_/, '');
    
    for (var i = 0; i < val.length; i += 3) {
        var params = val[i],
            type = params.shift(),
            values = val[i + 1],
            sizing = val[i + 2];
        res.levels.push([
            type,
            params,
            values,
            sizing
        ]);
    }
    return res;
};

bg.get_color_info = function(rgb) {
    return {
        opacity: rgb.a,
        brightness: (rgb.r * 299 + rgb.g * 587 + rgb.b * 114) / 1000
    };
};

bg.prototype.count_lightness = function() {
    var $levels = this.$popup.find( bg.el('level') );
    
    var cb = 0,
        co = null,
        n = 0,
        treshold = 140;
    
    $levels.each(function() {
        n++;
        var $l = $(this),
            level = $l.data('bg-level'),
            lr = level.count_lightness();
            
        if (co === null) {
            co = lr.opacity;
            cb = lr.brightness;
        } else {
            var old_opacity = co,
                old_brightness = cb,
                opacity_delta = ( 1 - old_opacity ) * lr.opacity;
            
            co = old_opacity + opacity_delta;
            
            if (old_opacity > 0) {
                var level_coeff = (1 - co) / old_opacity;
                cb = (old_brightness + (lr.brightness * level_coeff )) / (1 + level_coeff);
            } else {
                cb = lr.brightness;
            }
        }
    });
    
    var res = co < 0.5 ? 'none' : (cb > treshold ? 'light' : 'dark');
    return res;
};

bg.prototype.update_handle = function() {
    this.$handler.html('');
    var $levels = this.$popup.find( bg.el('level') ),
        that = this;

    $levels.each(function() {
        var $l = $(this),
            level = $l.data('bg-level');
        that.$handler.append( level.get_handler() );
    });
};

bg.prototype.get_value = function() { 
    
    
    var $levels = this.$popup.find( bg.el('level') ),
        level_vals = [],
        res;

    $levels.each(function() {
        var $l = $(this),
            level = $l.data('bg-level'),
            level_val = level.get_value();
            
        if (level_val) {
            level_vals.push(level_val);
        }
    });
    if (level_vals.length > 0) {
        res = this.get_lightness_value() + ', ';
        res += level_vals.join(', ');
    } else {
        if (!this.lightness_is_locked()) {
            res = this.get_lightness_value();
        } else {
            res = 'none';
        }
    }
    return res;
};

bg.prototype.update = function(skip_lightness) {
    
    if (
        (typeof skip_lightness === 'undefined' || skip_lightness === false)
        && this.lightness_is_locked() 
    ) {
        var lightness = this.count_lightness();
        this.$lightness.data('livesearch').setValue(lightness, true);
    }
    
    this.$input.val( this.get_value() ).trigger('change');
    
    var has_color = this.$popup.find(bg.el('level.type_color')).length > 0;
    
    this.$popup.find( bg.el('add.type_color') ).toggleClass(bg.cl('add')+'_inactive', has_color);
    
    if (this.$popup.is(':visible')) {
        this.place_popup();
    }
    this.update_handle();
};

bg.prototype.get_lightness_value = function() {
    
    var val = this.$lightness.data('livesearch').getValue(),
        res = (this.lightness_is_locked() ? '' : 'custom_') + val;
    return res;
};

bg.prototype.place_popup = function() {
    this.$popup.css('left', 0).show();
    var rect = this.$handler[0].getBoundingClientRect(),
        popup_rect = this.$popup[0].getBoundingClientRect(),
        body_rect = document.body.getBoundingClientRect(),
        offset = body_rect.right - (rect.left + popup_rect.width);

    this.$popup.css({
        top: rect.top,
        left: rect.left + Math.min(offset, 0)
    });
};

bg.prototype.hide_popup = function() {
    this.$popup.hide();
    $('html').off('.fx_bg_clickout');
};

bg.prototype.lock_lightness = function() {
    this.$lightness_container.addClass(bg.cl('lightness')+ '_locked');
    this.$lightness_lock.find('input')[0].checked = true;
    var lightness = this.count_lightness();
    this.$lightness.data('livesearch').setValue(lightness, true);
};

bg.prototype.unlock_lightness = function() {
    this.$lightness_container.removeClass(bg.cl('lightness')+ '_locked');
    this.$lightness_lock.find('input')[0].checked = false;
};

bg.prototype.lightness_is_locked = function() {
    return this.$lightness_lock.find('input').val() === "1";
};

bg.prototype.init = function() {
    var raw_value = this.params.value,
        value = bg.parse_value(raw_value),
        $levels = this.$popup.find(bg.el('levels')),
        that = this;

    $('body').append(this.$popup);
    
    this.$popup.addClass('fx_overlay fx_admin_form '+bg.cl());
    
    var first_open = true;
    
    
    
    function show_popup(){ 
        
        that.place_popup();
        
        that.closer = $fx.close_stack.push(
            function() {
                that.hide_popup();
            },
            that.$popup
        );
        if (first_open) {
            that.$container.parents().one('fx_destroy', function() {
                that.$popup.remove();
            });
            first_open = false;
        }
    }
    
    this.$handler.on('click', function() {
        show_popup();
        return false;
    });
    
    this.$popup.find( bg.el('close') ).on('click', function(e) {
        //that.hide_popup();
        that.closer(e);
        return false;
    });
    
    var lightness_locked = value.lightness_locked;

    this.$lightness = $fx_fields.control({
        type: 'livesearch',
        label: 'Светлота',
        allow_empty: false,
        values: {
            light: 'Светлый',
            dark: 'Темный',
            none: 'Прозрачный'
        },
        value: value.lightness,
        disabled: true
    });
    
    this.$lightness_lock = $fx_fields.input({
        type: 'checkbox',
        class_name:'locker'
    });
    
    this.$lightness_lock.on('change', function() {
        that.lightness_is_locked() ? that.lock_lightness() : that.unlock_lightness();
        that.update();
    });
    
    this.$lightness_container = this.$popup.find( bg.el('lightness') );
    
    this.$lightness_container.append(this.$lightness);
    this.$lightness_container.append(this.$lightness_lock);
    
    this.$lightness.on('change', function() {
        that.unlock_lightness();
        that.update(true);
    });
    
    if (lightness_locked) {
        this.lock_lightness();
    } else {
        this.unlock_lightness();
    }
    
    for (var i =0 ; i < value.levels.length; i++) {
        var level = background_level.create.apply(background_level, value.levels[i]);
        level.draw($levels);
    }
    
    $levels.sortable({
        items:'>div:not('+bg.el('level.type_color')+')',
        handle: bg.el('level-drag'),
        stop: function() {
            that.update();
        }
    });
    
    $levels.on('click', bg.el('level-drop'), function(e) {
        setTimeout(
            function() {
                $(e.target).closest(bg.el('level')).remove();
                that.update();
            },
            10
        );
    });
    
    this.$popup.on('click', bg.el('add')+' a', function() {
        var level = background_level.create($(this).data('type'));
        level.draw($levels);
        that.update();
    });
    
    $levels.on('change', function() {
        that.update();
    });
    that.update();
};

function background_level(params, value, sizing) {
    this.params = params || this.default_params;
    this.value = value || this.default_value;
    this.sizing = sizing || this.default_sizing;
};

background_level.create = function(type, params, value, sizing) {
    if (!this[type]) {
        throw "Unknown type: "+type;
    }
    var level = new this[type](params, value, sizing);
    level.type = type;
    return level;
};

background_level.prototype.get_handler = function() {
    return '<span title="'+this.type+'"></span>';
};

background_level.prototype.default_params = [];
background_level.prototype.default_value = [];
background_level.prototype.default_sizing = ['~"0% 0% / 100% 100%"', 'no-repeat', 'scroll'];

background_level.prototype.draw = function($target) {
    this.$node = $(
        '<div' + cls('level', 'type_'+this.type) + '>'+
            '<div'+cls('level-controls')+'>'+
                '<span '+cls('level-drop')+'>&times</span>'+
                (this.type === 'color' ? '' : '<span '+cls('level-drag')+'>&#8597;</span>')+
            '</div>'+
            '<div'+cls('level-value')+'"></div>'+
            '<div'+cls('level-size')+'"></div>'+
            '<div'+cls('level-position')+'"></div>'+
            '<div'+cls('level-repeat')+'"></div>'+
            '<div'+cls('level-attachment')+'"></div>'+
        '</div>'
    );
    
    this.$node.data('bg-level', this);
    
    var that = this;
    this.el = function() {
        var sel = bg.el.apply(null, arguments);
        return that.$node.find(sel);
    };
    this.draw_value();
    this.draw_sizing();
    var $color_level = $(bg.el('level.type_color'), $target);
    if ($color_level.length) {
        $color_level.before(this.$node);
    } else {
        $target.append(this.$node);
    }
};

background_level.prototype.draw_value = function() {};

background_level.prototype.get_sizing_value = function() {
    var size = this.sizing_controls.$size.val(),
        pos_x = this.sizing_controls.$pos_x.val(),
        pos_y = this.sizing_controls.$pos_y.val(),
        repeat = this.sizing_controls.$repeat.is(':checked'),
        fix = this.sizing_controls.$attachment.is(':checked');
    
    var  res = '~"'+pos_x+'% '+pos_y+'% / '+size+'%" '+
                (repeat ? 'repeat ' : 'no-repeat ')+
                (fix ? 'fixed ' : 'scroll ');
    return res;
};

background_level.prototype.get_value = function() {};

background_level.prototype.draw_sizing = function() {
    
    this.sizing_controls = {};
    var $attachment = $fx_fields.control({
        type:'checkbox',
        value: this.sizing[2] === 'fixed'
    });
    
    this
        .el('level-attachment')
        .append('<div'+cls('label')+'>Фикс?</div>')
        .append($attachment);
    
    this.sizing_controls.$attachment = $attachment.find('input[type="checkbox"]');
    
    var pos_and_size = this.sizing[0].replace(/^[~"]+|\"$/g, '').split(/\s*\/\s*/),
        size = pos_and_size[1].match(/^(\d+)%/)[1],
        pos = pos_and_size[0].replace(/\s+$/, '').split(/\s/);
    
    this.sizing_controls.$size = $fx_fields.control({
        type:'number',
        units:'%',
        min:1,
        max:100,
        step:1,
        value: size
    });
    this
        .el('level-size')
        .append('<div'+cls('label')+'>Размер</div>')
        .append(this.sizing_controls.$size);
    
    var that = this;
    
    
    this.el('level-position').append('<div'+cls('label')+'>Положение</div>');
    
    $.each(['x', 'y'], function(i, key) {
        that.sizing_controls['$pos_'+key] = $fx_fields.control({
            type:'number',
            units:'%',
            min:0,
            max:100,
            value:pos[i].replace(/%/, '')
        });
        that
            .el('level-position')
            .append(that.sizing_controls['$pos_'+key]);
    });
    
    var $repeat = $fx_fields.control({
        type:'checkbox',
        value: this.sizing[1] !== 'no-repeat'
    });
    this
        .el('level-repeat')
        .append('<div'+cls('label')+'>Повторить?</div>')
        .append($repeat);
    
    this.sizing_controls.$repeat = $repeat.find('input[type="checkbox"]');
};

// Linear gradient BG
background_level.linear = function() {
    background_level.apply(this, arguments);
};
background_level.linear.prototype = Object.create(background_level.prototype);

background_level.linear.prototype.default_params = [
    '180deg'
];
background_level.linear.prototype.default_value = [
    'main', 0, 0.3, '0%',
    'main', 0, 0.1, '100%'
];

background_level.linear.prototype.draw_sizing = function() {
    
};

background_level.linear.prototype.get_handler = function() {
    var $h = $('<span title="Градиент"></span>');
    $h.css('background', this.get_css());
    return $h;
};

background_level.linear.prototype.get_sizing_value = function() {
    return '~"0% 0% / 100%" no-repeat scroll';
};

background_level.linear.prototype.draw_value = function() {
    
    var $vals = this.el('level-value');
    
    var $slider_wrapper = $('<div'+cls('linear-wrapper')+'></div>');
    $vals.append($slider_wrapper);
    
    this.$slider = $('<div'+cls('linear')+'></div>');
    
    $slider_wrapper.append(this.$slider);
    
    var that = this;
    this.$slider.on('mousedown', function(e) {
        if (e.ctrlKey || e.metaKey) {
            var $p = $(e.target).closest('.fx-number-slider__point');
            if ($p.length) {
                that.slider.remove_point($p);
                that.draw_gradient();
                $vals.trigger('click');
            } else {
                var $closest_point = that.slider.get_closest_point(e),
                    new_value = 'main 0 0.5';
                if ($closest_point && $closest_point.length) {
                    var closest_value = $closest_point.find('.fx-palette__value').val();
                    if (closest_value) {
                        new_value = closest_value;
                    }
                }
                var $new_point = that.add_point(new_value, that.slider.e_to_val(e));
                $(document.body).one(
                    'mouseup',
                    function() {
                        $new_point.find('.fx-palette__value-color').trigger('fx-palette-toggle');
                    }
                );
            }
            e.stopImmediatePropagation();
            that.$slider.trigger('change');
            return false;
        }
    });
    
    this.slider = new number_slider(this.$slider, {
        min:0,
        max:100,
        multiple:true,
        round:0,
        change: function(val, $point) {
            that.draw_gradient();
            $point.find('.fx-palette__value-color').trigger('fx-palette-toggle', [false]);
            that.$slider.trigger('change');
        }
    });
    
    for (var i = 0; i < this.value.length; i += 4) {
        var c = this.value[i],
            l = this.value[i+1],
            o = this.value[i+2],
            p = this.value[i+3].replace(/%/, '');
        this.add_point(c+ ' ' + l + ' ' + o, p);
    }
    
    this.$angle = $fx_fields.control({
        type:'number',
        units:'&deg;',
        min:0,
        max:360,
        step:5,
        value: this.params[0].replace(/deg/, '')
    });
    
    $vals.append(this.$angle);
};

background_level.linear.prototype.get_value = function() {
    var res = 'linear ';
    res += this.$angle.val() + 'deg, ';
    
    var points = this.get_sorted_points(),
        point_vals = [];
    
    for (var i = 0; i < points.length; i++) {
        var $p = points[i],
            pos = $p.value,
            color = $p.find('.fx-palette__value').val();
        point_vals.push(color +' '+  pos + '%');
    }
    res += point_vals.join(' ');
    res += ', ' + this.get_sizing_value();
    return res;
};

background_level.linear.prototype.count_lightness = function() {
    var points = this.get_sorted_points(),
        res = {
            opacity:0,
            brightness:0
        };
    
    for (var i = 0; i < points.length; i++) {
        var $p = points[i],
            color = $p.find('.fx-palette__value').data('rgb-value'),
            info = bg.get_color_info(color);
        
        res.brightness += info.brightness;
        res.opacity += info.opacity;
    }
    res.brightness = res.brightness / points.length;
    res.opacity = res.opacity / points.length;
    return res;
};


background_level.linear.prototype.get_sorted_points = function() {
    return this.slider.points.sort(
        function($a, $b) {
            return $a.value - $b.value;
        }
    );
};

background_level.linear.prototype.get_css = function() {
    var points = this.get_sorted_points(),
        css = 'linear-gradient(90deg, ',
        pts = [];
    
    
    for (var i = 0; i < points.length; i++) {
        var $p = points[i],
            color = $p.find('.fx-palette__value-color').css('background-color'),
            distance = $p.value + '%';
        pts.push( color + ' ' + distance  );
    }
    css += pts.join(',')+')';
    return css;
};

background_level.linear.prototype.draw_gradient = function() {
    var css = this.get_css();
    this.$slider.css('background', css);
};


background_level.linear.prototype.add_point = function(color, distance) {
    var $p = this.slider.add_point(distance);
    
    var $color = $fx_fields.control({
        type:'palette',
        empty: false,
        opacity: true,
        value: color
    });
    
    $p.append($color);
    
    var that = this;
    $color.on('change', function() {
        that.draw_gradient();
    });
    this.draw_gradient();
    return $p;
};


// Image BG
background_level.image = function() {
    background_level.apply(this, arguments);
};
background_level.image.prototype = Object.create(background_level.prototype);

background_level.image.prototype.draw_value = function() {
    var val = this.value && this.value.length ? this.value[0].replace(/^~"|"$/g, '') : null;
    var $c = $fx_fields.control({
        type:'image',
        name:'img',
        value: val ? {
            path:val
        } : null
    });
    this.$image_input = $c.find('.real_value');
    this.el('level-value').append($c);
};

background_level.image.prototype.get_value = function() {
    var url = this.$image_input.val();
    
    if (!url) {
        return;
    }
    
    var res = 'image, ';
    res += '~"'+url+'", ';
    res += this.get_sizing_value();
    return res;
};

background_level.image.prototype.count_lightness = function() {
    return {
        brightness:0,
        opacity:0
    };
};

// Color BG
background_level.color = function() {
    background_level.apply(this, arguments);
};
background_level.color.prototype = Object.create(background_level.prototype);

background_level.color.prototype.default_value = ['alt', '5', '1'];

background_level.color.prototype.draw_sizing = function() {};
background_level.color.prototype.get_sizing_value = function() {};

background_level.color.prototype.get_value = function() {
    return 'color, '+this.$color_inp.val()+', none';
};

background_level.color.prototype.draw_value = function() {
    var $c = $fx_fields.control({
        type:'palette',
        opacity:true,
        value: this.value.join(' ')
    });
    this.$color_inp = $c.find('.fx-palette__value');
    this.el('level-value').append($c);
};

background_level.color.prototype.get_handler = function() {
    var $h = $('<span title="Цвет"></span>');
    var $wrap = this.$color_inp.parent().find('.fx-palette__value-color');
    $h.attr('style', $wrap.attr('style'));
    return $h;
};

background_level.color.prototype.count_lightness = function() {
    var color_rgb = this.$color_inp.data('rgb-value'),
        res = {opacity:0,brightness:0};
    
    if (color_rgb) {
        res = bg.get_color_info(color_rgb);
    }
    return res;
};


window.fx_background_control = bg;


function parse_css_value(s) {
    var seps = [' ', ','],
        res = [
            ['']
        ],
        index = [
            0,
            0
        ];
    
    s = s.replace(/^\s+|\s+$/, '');
    s = s.replace(/\s/g, ' ');
    
    var q = null,
        last_sep = null;
    
    for (var i = 0; i < s.length; i++) {
        var ch = s[i];
        var sep_level = seps.indexOf(ch);
        if (sep_level === -1 || q !== null) {
            
            if (last_sep !== null) {
                if (last_sep === 1) {
                    index[0]++;
                    index[1] = 0;
                    res[index[0]] = [''];
                } else {
                    index[1]++;
                    res[index[0]][index[1]] = '';
                }
                last_sep = null;
            }
            
            res[index[0]][index[1]] += ch;
            if (['"', "'"].indexOf(ch) !== -1 && q === null) {
                q = ch;
            } else if (ch === q && (i !== 0 && s[i-1] !== '\\') ) {
                q = null;
            }
            continue;
        }
        if (last_sep === null || last_sep < sep_level) {
            last_sep = sep_level;
        }
    }
    return res;
};
    
    
})();