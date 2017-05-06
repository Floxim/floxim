(function($) {
    
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
    this.is_new = !!this.style_class.match(/_style_default$/) && this.mode !== 'edit';
    
    if (!this.is_new) {
        this.$affected = $('.'+cl);
    } else {
        this.$affected = $($fx.front.get_selected_item()).descendant_or_self('.'+this.style_id_class);
    }
};

less_tweaker.prototype.get_first_affected = function() {
    return $($fx.front.get_selected_item()).descendant_or_self('.'+this.style_id_class).first();
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
        var prev_data = that.last_data;
        that.last_data = that.get_data();
        clearTimeout(timer);
        timer = setTimeout(
            function() {
                that.update(that.last_data);
            },
            100
        );
        var source_json = $(e.target).closest('.field').data('source_json'),
            force_redraw = false;
        
        if (source_json && source_json.force_redraw) {
            force_redraw = typeof source_json.force_redraw === 'function' ? 
                            source_json.force_redraw(e) : 
                            source_json.force_redraw;
        }
        $(e.target).trigger('fx_change_passed');
        return !!force_redraw;
    });
};

less_tweaker.prototype.get_stylesheet = function() {
    if (!this.$stylesheet) {
        var has_updates =  false;
        if (!this.is_new) {
            var $ss = $('style#'+this.style_class);
            if ($ss.length > 0) {
                this.$stylesheet = $ss;
                this.initial_css = $ss.text();
            }
            if (!$ss.data('is_tweaked')) {
                has_updates = true;
            }
            $ss.data('is_tweaked', true);
        }
        if (!this.$stylesheet) {
            this.$stylesheet = $('<style type="text/css" id="'+this.get_tweaked_class()+'" data-created_by="tweaker"></style>');
            $('head').append(this.$stylesheet);
            has_updates = true;
        }
        if (has_updates) {
            window.style_watcher.update();
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
    var less_call = this.get_mixin_call(vars),
        less = this.less + "\n" + less_call,
        less_promise = this.render_less(less),
        that = this;
    return less_promise.then(
        function() {
            that.recount_container(vars);
            that.$affected.trigger('fx_style_tweaked');
        },
        function(err) {
            
        }
    );
};

less_tweaker.prototype.make_screenshot = function() {
    var $node = this.get_first_affected();
    
    var bg = $node.hasClass('fx-block_lightness_light') ? '#FFF' : '#000';
    
    return html2canvas($node[0], {
        background: bg
    }).then(
        function(canvas) {
            //document.body.appendChild(canvas);
            return canvas.toDataURL('image/png', 0.95);
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
            c_mods = $fx.front.get_modifiers($item, 'fx-block'),
            item_vars = $.extend(true, {}, new_vars);
        
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
                item_vars[k] = v;
            } else {
                c_mods['has-'+k] = true;
            }
            if (apply_to_self.indexOf(k) !== -1) {
                own_vars[k] = v;
            }
        });
        
        var new_mods = $.extend({}, c_mods, get_mods(own_vars));
        
        $fx.front.set_modifiers($item, 'fx-block', new_mods);
        traverse($item.children(), item_vars);
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
            c_val = lightness ? lightness[0].replace(/^custom_/, '') : null;
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
            that.get_stylesheet().text(css.css);
        },
        function (error) {
            /*
            if (error.line) {
                var lines = less_text.split("\n"),
                    snippet = lines[ error.line - 1 ] + "\n" + lines[ error.line ] + "\n" + lines[error.line + 1];
                console.log(snippet);
            }
            */
            console.log(
                error // , less_text
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
    this.is_canceled = true;
    window.style_watcher.update();
};

less_tweaker.bind_style_preview = function(ls) {
    
    // preload images
    ls.traversePresetValues(function(v) {
        if (v.screenshot) {
            var img = new Image();
            img.src = v.screenshot;
        }
    });
    
    var $monosearch = ls.$node;
    
    var $screen_box = $('<div class="fx-stylevariant-screenbox"></div>');
    $screen_box.css({
        position:'fixed',
        padding:'10px',
        background:'#FFF',
        border:'1px solid #CCC',
        'border-radius':'3px',
        'z-index':10000
    });
    $(document.body).append($screen_box);
    var monobox_ready = false;
    $monosearch.on('fx-livesearch-showbox', function(e, box) {
        if (monobox_ready) {
            return;
        }
        $(box).on('mouseenter', '.search_item', function() {
            var $item = $(this),
                ss = $item.data('value').screenshot;
            if (!ss) {
                $screen_box.hide();
            } else {
                var itembox = this.getBoundingClientRect();
                $screen_box.html('');
                $screen_box.append(
                    '<img src="'+ss+'" />'
                );

                $screen_box.css({
                    top: itembox.top,
                    left: itembox.right
                }).show();
            }
        }).on('mouseleave', '.search_item', function() {
            $screen_box.hide();
        });
    });
};

less_tweaker.handle_style_control = function(ls_json, ls, mode) {
    var current_style = ls.getFullValue(),
        params = {
            ls: ls,
            mode: mode,
            block_name: ls_json.block,
            style_value: current_style.id.replace(/_variant_[^_]+$/, ''),
            style_variant_id: current_style.style_variant_id,
            style_id: ls_json.style_id
        };
    this.edit_style_variant(params);    
};

less_tweaker.edit_style_variant = function(params) {
    var tweaker = null,
        mode = params.mode,
        style_variant_id = params.style_variant_id,
        style_value = params.style_value,
        ls = params.ls,
        block_name = params.block_name,
        style_id = params.style_id,
        source_vars = params.source_vars || {};
        
    $fx.front_panel.load_form(
        {
            entity:'layout',
            action:'style_settings',
            mode: mode,
            style_variant_id: style_variant_id,
            block: block_name,
            style:style_value,
            source_vars: source_vars
        },
        {
            view:'horizontal',
            onready: function($form) {
                var $as_new_button = $('.fx_admin_form__title .fx_icon-type-place', $form);
                $as_new_button.click(function() {
                    var data = $form.formToHash(),
                        new_params = $.extend(
                            {},
                            params, 
                            {
                                mode:'copy',
                                source_vars: data,
                                source_form: $form
                            }
                        );
                    
                    less_tweaker.edit_style_variant(new_params);
                });
                
                var $name_input = $('[name="style_name"]', $form),
                    $name_editable = $('.fx_admin_form__title-inline-editable', $form);
                
                $name_editable.on('input paste', function() {
                    $name_input.val( $name_editable.text() );
                });
                
                var style_meta = $form.data('fx_response').tweaker,
                    tweaker_params = $.extend(
                        {},
                        style_meta,
                        {
                            $form: $form,
                            style_id: style_id,
                            vars: style_meta.tweaked_vars,
                            mode: mode
                        }
                    );
                tweaker = new less_tweaker(tweaker_params);
            },
            onsubmit: function(e) {
                var $form = $(e.target),
                    data = $form.formToHash();
                if (data.pressed_button === "delete") {
                    return;
                }
                
                return;

                return tweaker.make_screenshot().then(function(img_data) {
                   var $inp = $('<input type="hidden" name="screenshot" />');
                   $inp.val(img_data);
                   $form.append($inp);
                });
            },
            onfinish: function(res) {
                if (params.source_form) {
                    $fx.front_panel.hide(null, 1);
                }
                var new_val = style_value+ (res.id ? '_variant_'+res.id : '');

                delete $fx.front.cached_style_variants[block_name];

                ls.updatePresetValues(res.variants);
                ls.setValue(new_val);
                if (res.saved_as_new) {
                    var $ib_node = $($fx.front.get_selected_item()).closest('.fx_infoblock');
                    if ($ib_node.length) {
                        $ib_node.on('fx_infoblock_unloaded', function() {
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
    var source_json = $monosearch.closest('.field').data('source_json'),
        livesearch = $monosearch.data('livesearch');

    less_tweaker.bind_style_preview(livesearch);
    
    
    livesearch.bindValueControls(function() {
        var value = livesearch.getFullValue();
        livesearch.addValueControl({
            //icon: value.style_variant_id ? 'edit' : 'add-round',
            icon: 'edit',
            title: 'Изменить стиль',
            action: function() {
                less_tweaker.handle_style_control(source_json, livesearch, 'edit');
            }
        });
        /*
        livesearch.addValueControl({
            //icon: value.style_variant_id ? 'edit' : 'add-round',
            icon: 'place',
            title: 'Скопировать в новый стиль',
            action: function() {
                less_tweaker.handle_style_control(source_json, livesearch, 'copy');
            }
        });
        */
    });
};

less_tweaker.init_style_group = function($g) {
    var json = $g.data('source_json');
    if (!json || !json.tweaker) {
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
    
    if ($ib.length > 0) {
        $ib.on('fx_infoblock_unloaded', function(e, $new_ib) {
            if (!$new_ib || !$new_ib.length) {
                return;
            }
            if (tweaker.last_data && tweaker.is_new && !tweaker.is_canceled) {
                
                var $el = $new_ib.descendant_or_self('.'+ tweaker.style_id_class ),
                    el_class = $el.attr('class'),
                    style_class = el_class && el_class.match( 
                        new RegExp( tweaker.block_name + '_style_[^\\s]+' ) 
                    );
            
                if (style_class) {
                    var $stylesheet = $('#'+style_class[0]),
                        $old_stylesheet = null;
                        
                    if ($stylesheet.length && tweaker.$stylesheet[0] !== $stylesheet[0]) {
                        $old_stylesheet = tweaker.$stylesheet;
                        tweaker.$stylesheet = $stylesheet;
                    }
                    
                    tweaker.set_style_class(style_class[0]);
                    
                    tweaker.update( tweaker.last_data ).then( function() {
                        if ($old_stylesheet !== null) {
                            $old_stylesheet.remove();
                            window.style_watcher.update();
                        }
                    });
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

$('html').on('fx_adm_form_created fx_adm_form_updated', function(e, data) {
    less_tweaker.init_form_controls($(e.target));
});

window.style_watcher = {
    ready: false,
    init: function() {
        var term = localStorage.getItem('fx_stylewatcher_term', term) || '';
        var $container = $(
            "<div class='sw'>"+
                '<input type="text" value="'+term+'" />'+
                '<div class="sw__toggler"><span>&lt;</span></div>'+
                '<div class="sw__steps"></div>'+
                '<div class="sw__data"></div>'+
            "</div>"
        );
        this.$steps = $('.sw__steps', $container);
        this.$data = $('.sw__data', $container);
        this.$term = $('input', $container);
        $('body').append($container);
        this.ready = true;
        var that = this;
        $container.on('click', '.sw__step', function(e) {
            var $step = $(e.target),
                index = that.$steps.children().index($step);
            that.select(index);
            
        });
        $container.on('click', '.sw__sheet-header', function(e) {
            $(this).closest('.sw__sheet').toggleClass('sw__sheet_active');
        });
        $container.on('click', '.sw__count', function() {
            var $sheet = $(this).closest('.sw__sheet'),
                $items = that.getNodes($sheet);
            
            if ($items.length > 0) {
                $fx.front.scrollTo($items.first());
            }
            
            return false;
        });
        $container.on('click', '.sw__toggler', function() {
            var $ts = $(this).find('span');
            
            if ($container.is('.sw_hidden')) {
                $container.removeClass('sw_hidden').css('left', 0);
                $ts.html("&lt;");
            } else {
                $container.addClass('sw_hidden').css(
                    'left',
                    $container.outerWidth() * -1 + 10
                );
                $ts.html("&gt;");
            }
        });
        
        $container.on('mouseenter', '.sw__sheet-id', function() {
            var $id = $(this),
                id = $id.text(),
                $items = $('.'+id),
                hl_class = 'fx-stylewtacher-hilighted';
            
            $items.addClass(hl_class);
            
            $id.one('mouseleave', function() {
                $items.removeClass(hl_class);
            });
        });
        
        var $adm_control = $('#fx_admin_control'),
            $top_panel = $('.fx-admin-panel');
    
        this.place = function() {
            var ach = $adm_control.height(),
                tph = $top_panel.height(),
                mh = Math.max(ach, tph),
                swh = $(window).height() - mh - 90;
            
            $('.sw__data').css(
                'max-height',
                swh
            );
        };
        
        setInterval(this.place, 200);
        
        setInterval(function() {
            var $last_data = that.$data.children().last();
            $last_data.children().each(function() {
                var $entry  = $(this);
                that.updateEntry($entry);
            });
        }, 500);
        
        this.$term.on('input', function() {
            that.filter();
        });
    },
    select: function(index) {
        var sac = 'sw__step_active',
            iac = 'sw__item_active',
            $item = this.$data.children().eq(index),
            $step = this.$steps.children().eq(index);
        
        this.$steps.find('.'+sac).removeClass(sac);
        this.$data.find('.'+iac).removeClass(iac);    
        
        $item.addClass(iac);
        $step.addClass(sac);
        
    },
    getCss: function($ss) {
        var css = $ss.text();
        
        var res = css.replace(
            /\{(.+?)\}/g, 
            function(all, rule) {
                rule = rule.replace(/;/g, ";\n\t");
                rule = rule.replace(/\{/g, "\n\t{\n\t");
                return "\n{\n\t"+rule+"\n}\n";
            }
        );
        res = res.replace(/#[0-9a-f]{6}/g, function(m) {
            return '<span style="background:'+m+';">'+m+'</span>';
        });
        return res;
    },
    getNodes: function($ss) {
        var id = $ss.data('stylesheet').attr('id');
        return $('.'+id);
    },
    
    updateEntry: function($entry) {
        var $ss = $entry.data('stylesheet'),
            file = $ss.data('file'),
            filemtime = $ss.data('filemtime'),
            $nodes = this.getNodes($entry),
            filename = '';
    
        if (file && file !== 'temp') {
            filename = file.replace(/^\/[^\/]+\/[^\/]+/, '');
        } else if (!file) {
            filename = 'none';
        }
        
        $entry.find('.sw__file a').text(filename).attr('href', file);
        $entry.find('.sw__filemtime').text(filemtime);
        $entry.find('.sw__css').html( this.getCss($ss) );
        $entry.find('.sw__count').text($nodes.length);
    },
    state_flag: 'fx_style_watcher',
    on: function() {
        localStorage.setItem(this.state_flag, 'on');
    },
    off: function() {
        localStorage.setItem(this.state_flag, 'off');
    },
    update: function() {
        if (localStorage.getItem(this.state_flag) !== 'on') {
            return;
        }
        if (!this.ready) {
            this.init();
        }
        var $sheets = $('style'),
            $step = $('<div class="sw__step">'+$sheets.length+'</div>'),
            $data = $('<div class="sw__item"></div>'),
            that = this;
        
        this.$steps.append($step);
        this.$data.append($data);
        
        $sheets.each(function() {
            var $sheet = $(this),
                id = $sheet.attr('id'),
                $entry = $(
                    '<div class="sw__sheet">'+
                        '<div class="sw__sheet-header">'+
                            '<div class="sw__sheet-id">'+id+'</div>'+
                            '<div class="sw__count"></div>'+
                        '</div>'+
                        '<div class="sw__sheet-body">'+
                            '<div class="sw__file">' +
                                '<a></a>' + 
                                ' - <span class="sw__filemtime"></span>' +
                            '</div>'+
                            '<pre class="sw__css"></pre>'+
                        '</div>'+
                    '</div>'
                ),
                $sh = $entry.find('.sw__sheet-header');
                
            $entry.data('stylesheet', $sheet);
            
            that.updateEntry($entry);
            
            if ($sheet.data('is_tweaked')) {
                $sh.append('<span>tweaked</span>');
            }
            var cb = $sheet.data('created_by');
            if (cb) {
                $sh.append('<span>'+cb+'</span>');
            }
            
            $data.append(
                $entry
            );
        });
        
        this.select( this.$steps.children().length - 1 );
        this.filter();
    },
    check: function($sheet, term) {
        if (term === '') {
            return true;
        }
        var id = $('.sw__sheet-id', $sheet).text();
        return id.indexOf(term) !== -1;
    },
    filter: function() {
        var term = this.$term.val(),
            that = this;
        
        this.$data.children().each(function(step) {
            var cnt = 0,
                $sheets = $(this).find('.sw__sheet');
                
            $sheets.each(function() {
                var $sheet = $(this);
                if (that.check($sheet, term)) {
                    cnt++;
                    $sheet.show();
                } else {
                    $sheet.hide();
                }
            });
            that.$steps.children().eq(step).text(cnt);
        });
        localStorage.setItem('fx_stylewatcher_term', term);
    }
};

})($fxj);