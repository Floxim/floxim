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

less_tweaker.prototype.set_style_class = function(cl) {
    this.style_class = cl;
    this.is_new = !!this.style_class.match(/_style_default$/);
    if (!this.is_new) {
        this.$affected = $('.'+cl);
    } else {
        this.$affected = $($fx.front.get_selected_item()).descendant_or_self('.'+this.style_id_class);
    }
};

less_tweaker.prototype.init = function() {
    this.handle_form();
    var cl = this.style_class,
        that = this;
    
    this.block_name = cl.replace(/_style_[^_]+$/, '');
    
    //this.style_class = cl;
    
    this.style_id_class = this.block_name+'_style-id_'+this.style_id;
    
    this.set_style_class(cl);
    
    this.initial_data = this.get_data();
    this.update( 
        this.initial_data
    ).then(function() {
        if (that.is_new) {
            that.$affected.removeClass(cl).addClass(that.get_tweaked_class());
        }
    });
};

less_tweaker.prototype.get_tweaked_class = function() {
    if (!this.is_new) {
        return this.style_class;
    }
    if (typeof this.tweaked_class === 'undefined') {
        this.tweaked_class = this.style_class + '-tweaked-'+Math.round(Math.random()*1000000);
    }
    return this.tweaked_class;
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
            $inp = that.$form.find(':input[name="'+inp_name+'"]');
        
        var value = $inp.val(),
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
        that.last_data = that.get_data();
        clearTimeout(timer);
        timer = setTimeout(
            function() {
                that.update(that.last_data);
            },
            100
        );
        return false;
    });
};

less_tweaker.prototype.get_stylesheet = function() {
    if (!this.$stylesheet) {
        if (!this.is_new) {
            var $ss = $('style#'+this.style_class);
            if ($ss.length > 0) {
                this.$stylesheet = $ss;
                this.initial_css = $ss.text();
            }
            $ss.data('is_tweaked', true);
        }
        if (!this.$stylesheet) {
            this.$stylesheet = $('<style type="text/css" class="oh"></style>');
            $('head').append(this.$stylesheet);
        }
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
    
    var defaults = {
            lightness:'light',
            width:'full',
            align:'left'
        },
        apply_to_self = ['lightness'];
    
    this.current_container_vars = new_vars;
    
    function traverse($items, vars) {
        $.each($items, function () {
            var $item = $(this);
            if ($item.is('.fx-block')) {
                var sub_vars = {},
                    own_vars = {},
                    c_mods = $fx.front.get_modifiers($item, 'fx-block'),
                    has_sub_mods = false;
                
                $.each(vars, function(k, v) {
                    if (!c_mods['has-'+k]) {
                        has_sub_mods = true;
                        sub_vars[k] = v;
                    }
                    if (apply_to_self.indexOf(k) === -1 || !c_mods['has-'+k]) {
                        own_vars[k] = v;
                    }
                });
                
                
                var own_mods = $.extend({}, c_mods, get_mods(own_vars));
                
                $fx.front.set_modifiers($item, 'fx-block', own_mods);
                
                if (has_sub_mods) {
                    traverse($item.children(), sub_vars);
                }
            } else {
                traverse($item.children(), vars);
            }
        });
    }
    
    $.each(this.$affected, function() {
        var $item = $(this),
            own_vars = {},
            c_mods = $fx.front.get_modifiers($item, 'fx-block');
            
        
        $.each(new_vars, function(k, v) {
            if (v === 'none') {
                var $par = $item.parent().closest('.fx-block_has-'+k);
                if ($par.length === 0) {
                    v = defaults[k];
                } else {
                    var par_mods = $fx.front.get_modifiers($par, 'fx-block');
                    v = par_mods['own-'+k];
                }
                c_mods['has-'+k] = false;
                new_vars[k] = v;
            } else {
                c_mods['has-'+k] = true;
            }
            if (apply_to_self.indexOf(k) !== -1) {
                own_vars[k] = v;
            }
        });
        var new_mods = $.extend({}, c_mods, get_mods(own_vars));
        $fx.front.set_modifiers($item, 'fx-block', new_mods);
        traverse($item.children(), new_vars);
    });
};

function eval_expression(expr, vars) {
    var expr = expr.replace(
        /\@([a-z0-9_-]+)/g,
        function(all, var_name) {
            if (typeof vars[var_name] === 'undefined') {
                return 'null';
            }
            var c_val = vars[var_name];
            if (typeof c_val === 'string') {
                c_val = '"'+c_val.replace(/"/g, '\\"')+'"';
            }
            return c_val;
        }
    );
    var res = eval(expr);
    return res;
}


less_tweaker.get_container_props = function(container, vars) {
    if (!container) {
        return null;
    }
    
    var res = {};
    $.each(container, function(prop, exp) {
        var c_val = null;
        
        exp = exp.replace(/^\s+|\s+$/g, '');
        
        if (/\@/.test(exp)) {
            c_val = eval_expression(exp, vars);
        } else {
            c_val = vars[exp];
        }
        if (c_val && prop === 'lightness') {
            var lightness = c_val.match(/^[^,]+/);
            c_val = lightness ? lightness[0] : null;
        }
        if (c_val) {
            res[prop] = c_val;
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
            //css.css += '.src {is:tweaker;}';
            that.get_stylesheet().text(css.css);
        },
        function (error) {
            console.log(
                error //, less_text
            );
        }
    );
};

less_tweaker.prototype.cancel = function() {
    if (this.$stylesheet) {
        if (this.is_new) {
            this.$stylesheet.remove();
        } else if (this.initial_css) {
            this.$stylesheet.text(this.initial_css);
            this.$stylesheet.data('is_tweaked', false);
        }
    }
    
    this.recount_container(this.initial_data);
    
    if (this.$affected && this.is_new) {
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
        var $c_field = $(this).closest('.field'),
            $ls = $c_field.find('.livesearch'),
            ls_json = $c_field.data('source_json'),
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
                    var $as_new_inp = $('input[name="save_as_new"]', $form);
                    if ($as_new_inp.length) {
                        var $name_input = $('[name="style_name"]', $form),
                            initial_name = $name_input.val(),
                            last_name = initial_name;
                        
                        $as_new_inp.on('change', function() {
                            if ($as_new_inp.val()*1 === 1) {
                                last_name = $name_input.val();
                                if (last_name === initial_name) {
                                    $name_input.val('').focus();
                                }
                            } else {
                                $name_input.val(last_name);
                            }
                        });
                    }
                    
                    var style_meta = $form.data('fx_response').tweaker,
                        params = $.extend(
                            {},
                            style_meta,
                            {
                                $form: $form,
                                style_id: style_id,
                                vars: style_meta.tweaked_vars//,
                                //is_new: style_variant_id === null
                            }
                        );
                    
                    tweaker = new less_tweaker(params);
                },
                onfinish: function(res) {
                    var new_val = style_value+ (res.id ? '_variant_'+res.id : ''),
                        style_ls = $monosearch.data('livesearch');
                    
                    delete $fx.front.cached_style_variants[block_name];
                    
                    style_ls.updatePresetValues(res.variants);
                    style_ls.setValue(new_val);
                    if (res.saved_as_new) {
                        var $ib_node = $($fx.front.get_selected_item()).closest('.fx_infoblock');
                        if ($ib_node.length) {
                            $ib_node.on('fx_infoblock_unloaded', function() {
                                console.log('canca');
                                tweaker.cancel();
                            });
                        } else {
                            tweaker.cancel();
                        }
                    }
                },
                oncancel: function() {
                    if (tweaker) {
                        tweaker.cancel();
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
        tweaker.cancel();
    });
    
    // or when the selected block is reloaded
    var $ib = $($fx.front.get_selected_item()).closest('.fx_infoblock');

    if ($ib.length > 0 && tweaker.is_new) {
        $ib.on('fx_infoblock_unloaded', function(e, $new_ib) {
            if (tweaker.last_data) {
                var $el = $new_ib.descendant_or_self('.'+ tweaker.style_id_class ),
                    el_class = $el.attr('class'),
                    style_class = el_class && el_class.match( 
                        new RegExp( tweaker.block_name + '_style_[^\\s]+' ) 
                    );
                if (style_class) {
                    tweaker.set_style_class(style_class[0]);
                    tweaker.update( tweaker.last_data );
                }
            }
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