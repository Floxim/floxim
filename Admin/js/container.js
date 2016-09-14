(function($) {
    
$fx.container = {};
return;
    
$fx.container = {

field_rex: /container_.+?_([^_\]]+)\]?$/,

parse_color_code: function(color_code) {
    if (!color_code) {
        return;
    }
    var color_parts = color_code.split(/\s+/);
    return {
        type: color_parts[0],
        level: color_parts[1],
        opacity: color_parts.length > 2 ? color_parts[2] : 1
    }
},
    
color_from_code: function(color_code) {
    if (!color_code) {
        return;
    }
    var color = this.parse_color_code(color_code);
    if (!color) {
        return;
    }

    var color_group = this.colors[color.type];

    if (!color_group) {
        return;
    }

    var color_hex = color_group[color.type+' '+color.level];

    if (!color_hex) {
        return;
    }

    var rgba = tinycolor(color_hex).setAlpha(color.opacity).toRgbString();
    return rgba;
},

get_measure_parts : function(val) {
    if (val === undefined) {
        val = '';
    }
    var parts = val.split(' ');
    for (var i = 0; i < 4; i++) {
        if (!parts[i] || parts[i] === '0') {
            parts[i] = 0;
        }
    }
    return parts;
},

append_container_styles: function($node, props) {
    var css = {
        'background-color': 'transparent',
        'background-image': 'none',
        'background-position': '',
        'background-repeat': '',
        'background-size': '',
        'margin': '',
        'padding': '',
        'z-index': ''
    };

    var medias = {default:''};

    this.color_from_code(props['bg-color']);

    var c1 = props['bg-color'],
        c2 = props['bg-color-2'],
        img = props['bg-image'];

    if (!c1 && !c2 && !img) {
        // do nothing
    } 
    // first color only
    else if (c1 && !c2 && !img) {
        var cv1 = this.color_from_code(c1);
        css['background-color'] = cv1;
    } 
    // image only
    else if (!c1 && !c2 && img) {
        css['background-image'] = 'url("'+img+'")';
    } 
    // use gradient: two colors or color(s) and image
    else {
        var cv1 = this.color_from_code(c1),
            cv2 = this.color_from_code(c2);

        var bg  = 'linear-gradient(to bottom, ';

        bg += (cv1 ? cv1 : 'transparent') + ', ';
        bg += cv2 ? cv2 : cv1;
        bg += ')';
        if (img) {
            bg += ', url("'+img+'")';
        }
        css['background-image'] = bg;
    }
    if (img && props['bg-position']) {
        var pos_val = props['bg-position'],
            pos = '',
            size = '',
            repeat = '';
        switch (pos_val) {
            case 'cover':
                repeat = 'no-repeat';
                size = 'cover';
                break;
            case 'repeat':
                repeat = 'repeat';
                break;
            default:
                repeat = 'no-repeat';
                size = 'contain';
                var pos_parts = pos_val.split('-'),
                    h_map = {
                        left:'0',
                        center:'50%',
                        right:'100%'
                    },
                    v_map = {
                        top:'0',
                        middle:'50%',
                        bottom:'100%'
                    };
                pos = h_map[pos_parts[0]] +' '+v_map[pos_parts[1]];
                break;
        }
        css['background-position'] = pos;
        css['background-size'] = size;
        css['background-repeat'] = repeat;
    }

    css['box-shadow'] = 'none';
    if (props['shadow-spread']) {
        var shadow_opacity = props['shadow-opacity'] || 0.3;
        css['box-shadow'] =  '0 0 '+props['shadow-spread']+'px rgba(0,0,0,'+shadow_opacity+')';
    }

    css.padding = props['padding'];
    css.margin = props['margin'];

    var width = props.width,
        parent_width = $node.attr('class').match(/fx-content_parent-width_([^\s]+)/);

    parent_width = parent_width ? parent_width[1] : 'full';

    if (width === 'layout' && (parent_width === 'layout' || parent_width === 'column')) {
        width = 'container';
    }

    if (width === 'full' && parent_width === 'full') {
        width = 'container';
    }


    if (width === 'custom' && props['width-custom']) {
        css.width = props['width-custom']+'%';
    }

    if (props.margin && props.margin !== '0 0 0 0') {
        css.margin = props.margin;
    }

    var layout_sizes = this.layout_sizes,
        margin_parts = this.get_measure_parts(props.margin),
        padding_parts = this.get_measure_parts(props.padding),
        sides = {1 : 'right', 3  : 'left'};

    if (width === 'full' || width === 'full-outer') {

        var f_margin = 50 - (5000 / layout_sizes.width),
            f_bp_margin = 'calc( ( 100vw - ' + layout_sizes['max-width'] + 'px) / -2  ',
            res_bp = {},
            outer_padding = (100 - layout_sizes.width) / 2;

        $.each (sides, function(side_index, side) {
            var c_margin = margin_parts[side_index],
                c_padding = padding_parts[side_index];

            if (parent_width === 'layout' || parent_width === 'full-outer') {
                css['margin-' + side] = !c_margin ? f_margin + '%' : 'calc(' + f_margin + '% + ' + c_margin + ')';
                res_bp ['margin-' + side] = f_bp_margin + (!c_margin ? '' : ' + ' + c_margin) + ')';
            }
            if (width === 'full-outer') {
                //css['padding-' + side] = !c_padding ? (f_margin * -1) + '%' : 'calc(' + (f_margin * -1) + '% + ' + c_padding + ')';
                css['padding-' + side] = !c_padding ? outer_padding + '%' : 'calc(' + outer_padding + '% + ' +  c_padding + ')';
                res_bp['padding-' + side] = f_bp_margin + ' * -1 ' + (!c_padding ? '' : ' + ' + c_padding) + ')';
            }
        });
        medias[layout_sizes.breakpoint] = res_bp;
    } else if (width === 'layout') {
        var f_margin = 'calc( (100vw - ' + layout_sizes['max-width'] + "px) / 2",
            res_bp = {};
        $.each (sides, function(side_index , side) {
            var c_margin = margin_parts[side_index];
            res_bp ['margin-' + side] = f_margin  +( !c_margin ? '' : ' + ' + c_margin) + ')';
        });
        medias[layout_sizes.breakpoint] = res_bp;
    }

    if (props['z-index']) {
        css['z-index'] = props['z-index'];
    }


    css['border-radius'] = props['corners'];

    medias['default'] = css;

    var $ss = this.get_node_stylesheet($node);

    var container_data = $node.data('fx_container') || {},
        container_name = container_data.name;

    if (!container_name) {
        //console.log('no container info', $node);
        return;
    }

    var css_text = '',
        container_class = 'fx-container_name_'+container_name;

    $.each(medias, function(media, rules) {
        var group_css =  '.'+container_class+" { \n";
        $.each(rules, function(prop, value) {
            if (value) {
                group_css += prop + ':' + value + ";\n";
            }
        });
        group_css += "}\n\n";
        if (media !== 'default') {
            group_css = '@media ('+media+') {'+group_css+'}';
        }
        css_text += group_css;
    });
    $ss.text(css_text);
},

get_node_stylesheet: function($node) {
    var $ss = $node.data('fx_container_stylesheet');
    if (!$ss) {
        $ss = $('<style type="text/css"></style>');
        $('head').append($ss);
        $node.data('fx_container_stylesheet', $ss);
    }
    return $ss;
},

append_container_classes: function($node, props) {
    var class_props = ['align', 'valign', 'height', 'lightness', 'width'],
        mods = {};
    for (var i = 0; i < class_props.length; i++) {
        var cp = class_props[i];
        
        if (props[cp] !== undefined) {
            mods[cp] = props[cp].replace(/^@/, '').replace(/\s+/, '-') || false;
        }
    }

    var c1 = this.parse_color_code(props['bg-color']),
        c2 = props['bg-color-2'],
        img = props['bg-image'];

    if (c1 && c1.opacity === 1 && !c2 && !img) {
        mods['bg-color'] = c1.type +'-'+c1.level;
    }

    var e_handler = function(e) {
        if (e.node_name !== 'fx-container') {
            return;
        }
        var mods = e.modifiers,
            $node = $(e.target),
            $upper_containers = $node.parents('.fx-container');
        
        function traverse_children ($children, prop, new_mods) {
            $.each($children, function() {
                var $c = $(this);
                if ($c.hasClass('fx-content')) {
                    $fx.front.set_modifiers($c, 'fx-content', new_mods);
                }
                if ($c.hasClass('fx-container')) {
                    var cmods = $fx.front.get_modifiers($c, 'fx-container');
                    if (cmods[prop]) {
                        return;
                    }
                }
                traverse_children($c.children(), prop, new_mods);
            });
        }
    
    
        $.each(mods, function(prop, vals) {
            var new_v = vals['new'],
                is_empty = !new_v || (prop === 'lightness' && new_v === 'transparent');
            if (is_empty) {
                new_v = '';
                $upper_containers.each(function() {
                    var $cc = $(this),
                        mods = $fx.front.get_modifiers($cc, 'fx-container');
                    if (mods[prop]) {
                        new_v = mods[prop];
                        return false;
                    }
                });
            }
            if (!new_v) {
                new_v = false;
            }
            var mods = {};
            mods['parent-'+prop] = new_v;
            traverse_children($node.children(), prop, mods);
        });
    };
    $node.on('fx_set_modifiers', e_handler);
    $fx.front.set_modifiers($node, 'fx-container', mods);
    $node.off('fx_set_modifiers', e_handler);
},

data_to_vars: function(data) {
    var vars = {};
    for (var i = 0; i < data.length; i++) {
        var prop_name = data[i].name.match(this.field_rex);
        if (!prop_name) {
            continue;
        }
        prop_name = prop_name[1];
        vars[prop_name] = data[i].value;
    }
    return vars;
},

get_color_info: function(c) {

    var rgb = tinycolor(c).toRgb(),
        res = {
            opacity: rgb.a,
            brightness: (rgb.r * 299 + rgb.g * 587 + rgb.b * 114) / 1000
        };
    return res;
},

count_lightness: function(vars) {
    var c1 = this.color_from_code(vars['bg-color']);
    var c2 = this.color_from_code(vars['bg-color-2']);
    var img = vars['bg-image'];
    if (!c1 && !c2 && !img) {
        return '';
    }
    var brightness = [],
        opacity = [];
    if (c1) {
        var i1 = this.get_color_info(c1);
        brightness.push(i1.brightness);
        opacity.push(i1.opacity);
    }
    if (c2) {
        var i2 = this.get_color_info(c2)
        brightness.push(i2.brightness);
        opacity.push(i2.opacity);
    }
    var total_opacity = 0;
    for (var i = 0; i < opacity.length; i++) {
        total_opacity += opacity[i];
    }
    if (total_opacity / opacity.length < 0.5) {
        return '';
    }
    var total_brightness = 0;
    for (var i = 0; i < brightness.length; i++) {
        total_brightness += brightness[i];
    }
    var avg_brightness = total_brightness / brightness.length,
        threshold = 140;
    return avg_brightness < threshold ? 'dark' : 'light';
},

form_handler: function ($form, $node, prefix) {

    this.set_node = function($node) {
        this.$node = $node;
    };

    if ($node) {
        this.set_node($node);
    }
    var that = this,
        container = $fx.container,
        $lightness_input = $('select[name*="lightness"]'),
        initial_data = $form.serializeArray(),
        last_vars = container.data_to_vars(initial_data),
        rnd = Math.random();

    function handle_form_data (data) {
        if (!that.$node.is('.fx-container')) {
            return;
        }
        var vars = container.data_to_vars(data);
        if (
            $form && last_vars && (
                vars['bg-color'] !== last_vars['bg-color'] ||
                vars['bg-color-2'] !== last_vars['bg-color-2']
            )
        ) {
            var counted_lightness = container.count_lightness(vars);

            if (vars.lightness !== counted_lightness) {
                $lightness_input.val(counted_lightness);
                vars.lightness = counted_lightness;
            }
        }

        last_vars = vars;

        container.append_container_styles(that.$node, vars);
        container.append_container_classes(that.$node, vars);
    };

    var handle_change = function(e) {
        if (!e.target || (typeof e.target.name !== 'string') ) {
            return;
        }
        if (e.target.name.match($fx.container.field_rex)) {
            e.stopImmediatePropagation();
            var data = $form.serializeArray();
            handle_form_data(data, $form);
            return false;
        }
    };

    var events = 'change.fx_front fx_change_file.fx_front input.fx_front';

    $form.on(events, handle_change);
    
    this.reset_block = function() {
        $form.off(events, handle_change);
        if (initial_data) {
            handle_form_data(initial_data);
        }
    };
}

};


})($fxj);