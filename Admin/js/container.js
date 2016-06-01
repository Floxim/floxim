(function($) {
    
    
$fx.container = {

field_rex: /container_.+?_([^_\]]+)\]?$/,

append_container_styles: function($node, props) {
    var css = {
        'background-color': '',
        'background-image': '',
        'background-position': '',
        'background-repeat': '',
        'background-size': '',
        'margin-top': '',
        'margin-bottom': ''
    };

    var c1 = props.bg_color,
        c2 = props.bg_color_2,
        img = props.bg_image;

    if (!c1 && !c2 && !img) {
        // do nothing
    } 
    // first color only
    else if (c1 && !c2 && !img) {
        //css['background-color'] = c1;
    } 
    // image only
    else if (!c1 && !c2 && img) {
        css['background-image'] = 'url("'+img+'")';
    } 
    // use gradient: two colors or color(s) and image
    else {
        var bg  = 'linear-gradient(to bottom, ';
        bg += (c1 ? c1 : 'transparent') + ', ';
        bg += c2 ? c2 : c1;
        bg += ')';
        if (img) {
            bg += ', url("'+img+'")';
        }
        css['background-image'] = bg;
    }
    if (img && props.bg_position) {
        var pos_val = props.bg_position,
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

    var container_meta = $node.data('fx_container'),
        container_name = container_meta ? container_meta.name : '';


    $.each(['overlap-bottom', 'overlap-top'], function(n, prop) {
        var prop_val = props[prop] * 1,
            prop_type = prop.replace(/overlap\-/, '');

        css['padding-'+prop_type] = '';

        if (!prop_val) {
            return;
        }
        var prop_hash = prop_type +'-'+container_name;
        if (prop_val > 0 || (['top-layout_header', 'bottom-layout_footer'].indexOf(prop_hash) === -1)) {
            css['margin-' + prop_type] = prop_val + 'px';
        }
        if (prop_val < 0) {
            css['padding-' + prop_type ] = (prop_val * -1) + 'px';
        }
    });

    $.each(['bottom', 'top'], function(n, prop_type) {
        var prop = 'border-radius-' + prop_type,
            prop_val = props[prop] * 1,
            prop_val = prop_val > 0 ? prop_val +'px' : ''; 

        css['border-' + prop_type + '-left-radius'] = prop_val;
        css['border-' + prop_type + '-right-radius'] = prop_val;
    });
    css['box-shadow'] = '';
    if (props['shadow-spread']) {
        var shadow_opacity = props['shadow-opacity'] || 0.3;
        css['box-shadow'] =  '0 0 '+props['shadow-spread']+'px rgba(0,0,0,'+shadow_opacity+')';
    }
    $node.css(css);
},

append_container_classes: function($node, props) {
    var class_props = ['align', 'valign', 'sizing', 'padding', 'lightness', 'bg-color'],
        mods = {};
    for (var i = 0; i < class_props.length; i++) {
        var cp = class_props[i];
        
        if (props[cp] !== undefined) {
            mods[cp] = props[cp].replace(/^@/, '') || false;
        }
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

get_real_color:  function(code) {
    var colors = $('.fx-palette__value').data('colors');

    if (!colors[code]) {
        return '';
    }
    var color = tinycolor(colors[code]);
    return color.toRgbString();
},

get_color_info: function(c) {

    var parts = c.match(/(\d+), (\d+), (\d+)(, ([\d\.]+))?/);
    if (!parts) {
        return;
    }
    var rgb = {
            r:parts[1]*1,
            g:parts[2]*1,
            b:parts[3]*1
        },
        res = {
            opacity: parts[5] ? parts[5]*1 : 1,
            brightness: (rgb.r * 299 + rgb.g * 587 + rgb.b * 114) / 1000
        };
    return res;
},

count_lightness: function(vars) {
    var c1 = this.get_real_color(vars['bg-color']);
    var c2 = this.get_real_color(vars['bg-color-2']);
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