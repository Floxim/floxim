(function() {
    
function less_tweaker(params) {
    
    $.extend(this, params);
    
    var that = this;
    this.load_less().then(
        function(less) {
            that.less = less;
            that.init();
        }
    );
}

less_tweaker.prototype.init = function() {
    this.handle_form();
    var cl = this.style_class,
        that = this;
    
    this.style_class = cl;
    
    var style_id_class = cl.replace(/style_[^_]+$/, 'style-id_'+this.style_id);
    
    if (!this.is_new) {
        this.$affected = $('.'+cl);
    } else {
        this.$affected = $($fx.front.get_selected_item()).descendant_or_self('.'+style_id_class);
    }
    
    this.update( 
        this.get_data() 
    ).then(function() {
        that.$affected.removeClass(cl).addClass(that.get_tweaked_class());
    });
};

less_tweaker.prototype.get_tweaked_class = function() {
    return this.style_class + '-tweaked';
};

less_tweaker.loaded_files = {};

less_tweaker.prototype.load_less = function() {
    var that = this;
    return new Promise(
        function(resolve, reject) {
            var f = that.tweaker_file;
            if (typeof less_tweaker.loaded_files[f] !== 'undefined') {
                resolve(less_tweaker.loaded_files[f]);
                return;
            }
            $.ajax({
                url:that.tweaker_file,
                success:function(res) {
                    less_tweaker.loaded_files[f] = res;
                    resolve(res);
                }
            });
        }
    );
};

less_tweaker.prototype.get_data = function() {
    var res = {},
        that = this;
    
    for (var i = 0; i < this.vars.length; i++) {
        var name = this.vars[i],
            inp_name = that.base_name ? that.base_name + '['+name+']' : name,
            $inp = that.$form.find(':input[name="'+inp_name+'"]'),
            value = $inp.val(),
            units = $inp.data('units');
        if (units) {
            value += units;
        }
        if (value === '') {
            value = 'none';
        }
        res[name] = value;
    }
    return res;
};

less_tweaker.prototype.handle_form = function() {
    var timer = null,
        that = this;
    this.$form.on('change', function(e) {
        clearTimeout(timer);
        timer = setTimeout(
            function() {
                that.update(
                    that.get_data()
                );
            },
            100
        );
        return false;
    });
};

less_tweaker.prototype.get_stylesheet = function() {
    if (!this.$stylesheet) {
        this.$stylesheet = $('<style type="text/css"></style>');
        $('head').append(this.$stylesheet);
    }
    return this.$stylesheet;
};

less_tweaker.prototype.get_mixin_call = function(data) {
    var that = this;
        
    var res = '.' + this.get_tweaked_class() +" {\n";
    res +=    '.' + this.mixin_name + "(\n";
    $.each(data, function(prop, val) {
        if ( that.vars.indexOf(prop) === -1) {
            return;
        }
        res += "@"+prop+": "+val+";\n";
    });

    res += ");\n";
    res += "\n}";
    return res;
};

less_tweaker.prototype.update = function(vars) {
    var less = this.less + "\n" + this.get_mixin_call(vars),
        less_promise = this.render_less(less),
        that = this;
    return less_promise.then(
        function() {
            that.recount_container(vars);
        },
        function(err) {
            
        }
    );
};

less_tweaker.prototype.recount_container = function(vars) {
    var c_vars = this.current_container_vars || null,
        new_vars = less_tweaker.get_container_props(this.container, vars);
    
    if (JSON.stringify(c_vars) === JSON.stringify(new_vars)) {
        return;
    }
    
    function get_mods(props) {
        var res = {};
        $.each(props, function(prop,v) {
            res[ prop === 'lightness' ? prop : 'parent-'+prop ] = v;
        });
        return res;
    }
    
    this.current_container_vars = new_vars;
    
    function traverse($items, vars) {
        $.each($items, function () {
            var $item = $(this);
            if ($item.is('.fx-block')) {
                var sub_vars = {},
                    c_mods = $fx.front.get_modifiers($item, 'fx-block'),
                    has_mods = false;
                
                $.each(vars, function(k, v) {
                    if (!c_mods['has-'+k]) {
                        has_mods = true;
                        sub_vars[k] = v;
                    }
                });
                if (has_mods) {
                    $fx.front.set_modifiers($item, 'fx-block', get_mods(sub_vars));
                    traverse($item.children(), sub_vars);
                }
            } else {
                traverse($item.children(), vars);
            }
        });
    }
    
    $.each(this.$affected, function() {
        var $item = $(this),
            c_mods = $fx.front.get_modifiers($item, 'fx-block');
            
        console.log(new_vars);
        if (new_vars.lightness) {
            if (new_vars.lightness === 'none') {
                var $lp = $item.closest('.fx-block_has-lightness');
                if ($lp.length === 0) {
                    c_mods.lightness = 'light';
                } else {
                    var lp_mods = $fx.front.get_modifiers($lp, 'fx-block');
                    c_mods.lightness = lp_mods.lightness;
                }
                new_vars.lightness = c_mods.lightness;
            } else {
                c_mods.lightness = new_vars.lightness;
                c_mods['has-lightness'] = true;
            }
        }
        console.log(new_vars, c_mods);
        $fx.front.set_modifiers($item, 'fx-block', c_mods);
        traverse($item.children(), new_vars);
    });
};

less_tweaker.get_container_props = function(container, vars) {
    if (!container) {
        return null;
    }
    var res = {};
    $.each(container, function(prop, exp) {
        exp = exp.replace(/^\s+|\s+$/g, '');
        switch (prop) {
            case 'lightness':
                var c_val = vars[exp];
                if (c_val) {
                    c_val  = c_val.match(/^[^\,]+/)[0];
                    res[prop] = c_val;
                }
                break;
            default:
                var c_val = vars[exp];
                if (c_val) {
                    res[prop] = c_val;
                }
                break;
        }
    });
    return res;
};

less_tweaker.prototype.render_less = function(less_text) {
    var options = {
            plugins: [
                new BemLessPlugin({})
            ],
            rootpath: this.rootpath
        },
        that = this;
      
    return less.render(
        less_text,
        options
    ).then(
        function(css) {
            that.get_stylesheet().text(css.css);
        },
        function (error) {
            console.log(error, less_text);
        }
    );
};

less_tweaker.prototype.destroy = function() {
    if (this.$stylesheet) {
        this.$stylesheet.remove();
    }
    if (this.$affected) {
        this.$affected.removeClass(this.get_tweaked_class()).addClass(this.style_class);
    }
};

less_tweaker.init_style_select = function($monosearch) {
    var ls = $monosearch.data('livesearch');
    if (!ls || !ls.preset_values) {
        return;
    }
    var c_value = ls.getFullValue();
    if (!c_value || !c_value.is_tweakable) {
        return;
    }
    var $settings_button = $('<a class="fx-style-tweaker-link fx_icon fx_icon-type-settings"></a>'),
        that = this,
        source_json = $monosearch.closest('.field').data('source_json'),
        style_id = source_json.style_id;
    
    $monosearch.after ( $settings_button );
    $settings_button.on('click', function() {
        var $ls = $(this).closest('.field').find('.livesearch'),
            ls_json = $ls.data('json'),
            ls = $ls.data('livesearch'),
            current_style = ls.getFullValue(),
            block_name = ls_json.block,
            style_value = current_style.id.replace(/_variant_[^_]+$/, ''),
            style_variant_id = current_style.style_variant_id,
            tweaker = null;
            
        $fx.front_panel.load_form(
            {
                entity:'layout',
                action:'style_settings',
                style_variant_id: style_variant_id,
                block: block_name,
                style:style_value
            },
            {
                view:'horizontal',
                onready: function($form) {
                    var style_meta = $form.data('fx_response').tweaker;
                    tweaker = new less_tweaker({
                        $form:$form,
                        tweaker_file:style_meta.tweaker_file,
                        style_class: style_meta.style_class,
                        style_id: style_id,
                        vars: style_meta.tweaked_vars,
                        mixin_name: style_meta.mixin_name,
                        is_new: style_variant_id === null
                    });
                },
                onfinish: function(res) {
                    var $inp = $monosearch.find('input[type="hidden"]'),
                        new_val = style_value+ (res.id ? '_variant_'+res.id : '');

                    var change_event = $.Event('change');
                    change_event.fx_forced = true;

                    $inp.val(new_val).trigger(change_event);
                    
                    // destroy when the selected block is reloaded
                    var $ib = $($fx.front.get_selected_item()).closest('.fx_infoblock');
                    if ($ib.length > 0) {
                        $ib.on('fx_infoblock_unloaded', function() {
                            tweaker.destroy();
                        });
                    }
                },
                oncancel: function() {
                    if (tweaker) {
                        tweaker.destroy();
                    }
                }
            }
        );
    });
};

less_tweaker.init_style_group = function($g) {
    var json = $g.data('source_json');
    if (!json.tweaker) {
        return;
    }
    var tweaker = new less_tweaker(
        $.extend(
            json.tweaker, 
            {
                $form:$g,
                base_name: json.name
            }
        )
    );
    // drop stylesheet when form is canceled
    $g.closest('form').on('fx_form_cancel', function() {
        tweaker.destroy();
    });
    // or when the selected block is reloaded
    var $ib = $($fx.front.get_selected_item()).closest('.fx_infoblock');
    if ($ib.length > 0) {
        $ib.on('fx_infoblock_unloaded', function() {
            tweaker.destroy();
        });
    }
};

less_tweaker.init_style_controls = function($form) {
    var that = this;
    $('.monosearch', $form).each(function() {
        that.init_style_select($(this));
    });

    $('.field_group', $form).each(function() {
        that.init_style_group($(this));
    });
};

less_tweaker.init_form_controls = function($form) {
    
    this.init_style_controls($form);
    var that = this;
    $form.on('fx_infoblock_visual_fields_updated', function() {
        that.init_style_controls($form);
    });
};

$('html').on('fx_adm_form_created', function(e, data) {
    less_tweaker.init_form_controls($(e.target));
});

})($fxj);