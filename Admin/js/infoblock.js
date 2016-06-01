/**
 * misc scripts related to infoblock management
 */


(function ($) {

window.fx_controller_tree = function(html) {
    var b = 'fx-block-select',
        $tab_labels = $('.'+b+'__tab-label', html),
        $tabs = $('.'+b+'__tab', html);

    $tab_labels.click(function() {
        var $tab_label = $(this),
            c_index = $tab_labels.index( $tab_label);
        
        $tab_labels.removeClass(b+'__tab-label_active');
        $tabs.removeClass(b+'__tab_active');
        $tab_label.addClass(b+'__tab-label_active');
        $tabs.eq( c_index ).addClass(b+'__tab_active');
    });
    
    $(html).on('click', '.'+b+'__item', function() {
        var $item = $(this),
            id = $item.data('id'),
            $form = $item.closest('form'),
            $inp = $form.find('.tree_value_input');
        $inp.val(id);
        $form.submit();
    });
};

window.fx_preset_tree = function(html) {
    html.on('click', '.fx-block-presets__preset', function() {
        var $preset = $(this),
            $form = $preset.closest('form'),
            id = $preset.data('id'),
            $inp = $('.fx-block-presets__input', $form);
        $inp.val(id);
        $form.submit();
    });
};

function handle_favorites($form) {
    /*
    var $fav_button = $('.fx_button_key_favorite', $form);
    $fav_button.on('click', function() {
        $fx.front_panel.show_form(
            {
                fields:[
                    {
                        name:'name',
                        label:'Preset name',
                        type:'string'
                    }
                ]
            },
            {
                style:'alert',
                onsubmit: function(e) {
                    e.stopImmediatePropagation();
                    $form.append('<input type="hidden" name="is_preset" value="1" />');
                    $form.append('<input type="hidden" name="preset_name" value="1" />');
                }
            }
        );
        return false;
    });
    */
}

$('html').on('fx_panel_form_ready', function(e) {
    var $form = $(e.target),
        $scope_inp = $('input[name="scope[type]"]', $form),
        $scope_params_inp = $('input[name="scope[params]"]', $form);
        
    handle_favorites($form);
    
    if ($scope_inp.length === 0 ){ 
        return;
    }
    var $scope_ls = $scope_inp.closest('.livesearch'),
        scope_ls = $scope_ls.data('livesearch'),
        infoblock_id = $form.find('input[name="id"]').val(),
        $scope_link = $('<a class="fx_form_extra_link">Настроить...</a>'),
        last_scope_type = scope_ls.getValue();
        
    $scope_ls.before($scope_link);
    
    
    
    function handle_scope_link_visibility() {
        if ($scope_ls.data('livesearch').getValue() !== 'custom') {
            $scope_link.hide();
            $scope_ls.show();
        } else {
            $scope_ls.hide();
            $scope_link.show();
        }
    }
    
    handle_scope_link_visibility();
    
    function show_scope_dialog(dialog_params) {
        dialog_params = $.extend(
            {
                entity:'infoblock',
                action:'scope',
                infoblock_id:infoblock_id,
                fx_admin:true
            },
            dialog_params || {}
        );
        
        $fx.front_panel.load_form(
            dialog_params, {
            //style:'alert',
            form_class:'fx_scope_form',
            is_fluid:true,
            onready: function($scope_form) {
                var $page_control = $('input[name="scope[page_id]"]', $scope_form),
                    $cond_control = $('.fx-condition-builder', $scope_form),
                    cond_builder = $cond_control.data('condition_builder'),
                    path_ids = [],
                    last_page_id = $page_control.val();
                    
                $('.fx-radio-facet__variant', $page_control).each(function() {
                    path_ids.push( $(this).data('value') );
                });
                
                function is_page_cond(cond, page_id) {
                    if (cond.field !== 'entity' || cond.inverted ) {
                        return false;
                    }
                    for (var i = 0; i < cond.value.length; i++) {
                        if (cond.value[i] === page_id) {
                            return true;
                        }
                    }
                    return false;
                }
                
                $page_control.on('change', function() {
                    var c_cond = cond_builder.getValue(),
                        c_page_id = $page_control.val(),
                        is_last_page = $('.fx-radio-facet__variant_active', $page_control).is(':last-child'),
                        page_cond = {
                            field:'entity',
                            type: is_last_page ? 'is_in' : 'is_under_or_equals',
                            inverted:false,
                            value:[c_page_id]
                        },
                        new_cond = null,
                        replace_cond_page = function(cond, last_page_id, c_page_id) {
                            for (var i = 0; i < cond.value; i++) {
                                if (cond.value[i] === last_page_id) {
                                    cond.value[i] = c_page_id;
                                }
                            }
                            if (!is_last_page && cond.type === 'is_in') {
                                cond.type = page_cond.type;
                            }
                            return cond;
                        };
                    // no conditions
                    if (c_cond === undefined) {
                        new_cond = page_cond;
                    } 
                    // one condition
                    else if (c_cond.type !== 'group') {
                        if (is_page_cond(c_cond, last_page_id )) {
                            new_cond = replace_cond_page(c_cond, last_page_id, c_page_id);
                        } else {
                            new_cond = {
                                type:'group',
                                logic:'AND',
                                values:[
                                    c_cond,
                                    page_cond
                                ]
                            };
                        }
                    } 
                    // many conditions
                    else {
                        new_cond = c_cond;
                        var page_cond_found = false;
                        for (var i = 0; i < new_cond.values.length; i++) {
                            var c_prev_cond = new_cond.values[i];
                            if (is_page_cond(c_prev_cond, last_page_id)) {
                                new_cond.values[i] = replace_cond_page(c_prev_cond, last_page_id, c_page_id);
                                page_cond_found = true;
                            }
                        }
                        if (!page_cond_found) {
                            new_cond.values.push(page_cond);
                        }
                    }
                    cond_builder.redraw(new_cond);
                    last_page_id = c_page_id;
                });
            },
            onsubmit: function(e) {
                var $scope_form = $(e.target),
                    form_data = $scope_form.formToHash();
            
                // no valid conditions
                if (!form_data.scope.conditions) {
                    form_data.type = last_scope_type === 'custom' ? 'all_pages' : last_scope_type;
                }
                if (form_data.type && form_data.type !== 'custom') {
                    $scope_ls.data('livesearch').setValue(form_data.type);
                } else {
                    $scope_params_inp.val( JSON.stringify(form_data.scope) );
                }
                handle_scope_link_visibility();
                $fx.front_panel.hide();
                e.stopImmediatePropagation();
                last_scope_type = scope_ls.getValue();
                return false;
            },
            oncancel: function(){
                scope_ls.setValue(last_scope_type);
            }
        });
    }
    
    $scope_link.on('click', function() {
        show_scope_dialog({
            force_scope_type:true
        });
    });
    
    $scope_ls.on('change', function() {
        var ls_value = $scope_ls.data('livesearch').getValue();
        if (ls_value !== 'custom') {
            last_scope_type = ls_value;
            return;
        }
        show_scope_dialog();
    });
    
});

var style_tweaker = {
    handle_form: function($form) {
        $form.on('click', '.fx-style-tweaker-link', function() {
            var $inp = $(this).closest('.field').find('select'),
                style_name = $inp.attr('name').match(/([^\]\[]+)\]?$/),
                block_name = style_name[1].replace(/_style/, ''),
                style_value = $inp.val(),
                $stylesheet = null,
                tweak_less = '',
                tweak_class = block_name+'_style_'+style_value+'-tweaked',
                $target_block = $('.fx_selected').descendant_or_self('.'+block_name);

            console.log(tweak_class, $target_block);
            $target_block.addClass(tweak_class);

            function render_styles(data) {
                console.log($stylesheet, data);
                var vars = {};

                $.each(data, function(i) {
                    var prop = data[i];
                    if (!prop.value) {
                        return;
                    }
                    if (/^@color-/.test(prop.value)) {
                        vars[prop.name + '-tweaked'] = prop.value.replace(/^@color-/, '').replace(/\-(\d+)/, ' $1');
                    } else {
                        if (/^entity|action|sent|style_tweaker_file|_base_url/.test(prop.name)) {
                            return;
                        }
                        var $inp = $('input[name="'+prop.name+'"]');
                        var units = $inp.data('units');
                        if (units) {
                            prop.value += units;
                        }
                        vars[prop.name + '-tweaked'] = prop.value;
                    }
                });
                render_less(vars, tweak_less, $stylesheet);
                console.log(vars);
            }

            $fx.front_panel.load_form(
                {
                    entity:'layout',
                    action:'style_settings',
                    block: block_name,
                    style:style_value
                },
                {
                    view:'horizontal',
                    onready: function($form) {
                        $stylesheet = get_tweaker_stylesheet(block_name, style_value);
                        var tweaker_file = $form.find('input[name="style_tweaker_file"]').val(),
                            timer = null;
                        $.ajax({
                            url: tweaker_file,
                            success: function(res) {
                                tweak_less = res;
                                $form.on('change input', function() {
                                    clearTimeout(timer);
                                    timer = setTimeout(function() {
                                        render_styles($form.serializeArray());
                                    }, 500);
                                });
                            }
                        });
                    },
                    oncancel: function() {
                        if ($stylesheet) {
                            $stylesheet.remove();
                        }
                        $target_block.removeClass(tweak_class);
                    }
                }
            )
        });
    }
};

function get_tweaker_stylesheet(block, style) {
    var ss_class = 'fx-layout-tweak-stylesheet';
    if (block && style) {
        ss_class += '_'+block+'_'+style;
    }
    var $stylesheet = $('.'+ss_class);

    if ($stylesheet.length === 0) {
        $stylesheet = $('<style type="text/css" class="'+ss_class+'"></style>');
        $('body').append($stylesheet);
    }
    return $stylesheet;
}

function render_less(vars, tweak_less, $stylesheet) {

    var vars_less = '';
    $.each(vars, function (k, v) {
        vars_less += '@'+k+':'+v+";\n";
    });

    var final_less = tweak_less + vars_less;

    less.render(final_less).then(
        function(css) {
            $stylesheet.text( css.css );
        },
        function (error) {
            console.log(error, final_less);
        }
    );
}

$('html').on('fx_adm_form_created', function(e, data) {

    var $form = $(e.target);

    style_tweaker.handle_form($form);

    if (!data ||  !data.request || data.request.action !== "theme_settings") {
        return;
    }
    var tweak_url = $('input[name="less_tweak_file"]').val();
    if (!tweak_url) {
        return;
    }
    var $form = $(e.target),
        tweak_less,
        timer = null;
    $.ajax({
        url: tweak_url,
        success: function(res) {
            tweak_less = res;
            $form.on('change input', function() {
                clearTimeout(timer);
                timer = setTimeout(function() {
                    render_styles();
                }, 500);
            });
        }
    });
    
    $form.on('fx_form_cancel', function() {
        get_tweaker_stylesheet().remove();
    });
    
    function render_styles() {
        var vars = {},
            data = $form.serializeArray();
        
        $.each(data, function(i) {
            var prop = data[i];
            if (!prop.value) {
                return;
            }
            if (/^color-/.test(prop.name)) {
                var colorset = $.parseJSON(prop.value);
                $.each(colorset, function(color_prop, color_val) {
                    vars[color_prop] = color_val;
                });
            } else {
                if (/^entity|action|sent|less_tweak_file|_base_url/.test(prop.name)) {
                    return;
                }
                var $inp = $('input[name="'+prop.name+'"]');

                var units = $inp.data('units');
                if (units) {
                    prop.value += units;
                }
                vars[prop.name] = prop.value;
                if ( prop.name.match(/^font/) ) {
                    window.fx_font_preview.load_font( prop.value );
                }
            }
        });

        render_less( vars, tweak_less, get_tweaker_stylesheet());
    }
});

})($fxj);