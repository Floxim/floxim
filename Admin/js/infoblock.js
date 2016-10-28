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

$('html').on('fx_panel_form_loaded', function(e) {
    var $form = $(e.target),
        $scope_inp = $('input[name="scope[type]"]', $form),
        $scope_params_inp = $('input[name="scope[params]"]', $form);
        
    // handle_favorites($form);
    
    if ($scope_inp.length === 0 ){ 
        return;
    }
    var $scope_ls = $scope_inp.closest('.livesearch'),
        scope_ls = $scope_ls.data('livesearch');

    if (!scope_ls) {
        return;
    }

    var infoblock_id = $form.find('input[name="id"]').val(),
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
                
                var $cond_control = $('.fx-condition-builder', $scope_form),
                    cond_builder = $cond_control.data('condition_builder'),
                    $type_field = $('.field_name__type .livesearch', $scope_form),
                    type_ls = $type_field.data('livesearch'),
                    $check_res = $("<div class='fx-scope-check-result'></div>");
            
                $cond_control.after($check_res);
            
                function is_special_scope() {
                    if (!type_ls) {
                        return true;
                    }
                    return type_ls.getValue() === 'custom';
                }
                
                function validate_cond(e) {
                    
                    if (!is_special_scope()) {
                        $check_res.hide();
                        $fx_form.unlock_form($scope_form);
                        return;
                    }
                    
                    $fx_form.lock_form($scope_form);
                    
                    var conditions = cond_builder.getValue();
                    if (!conditions) {
                        $check_res.hide();
                        return;
                    }
                    
                    $fx.post(
                        {
                            entity:'infoblock',
                            action:'check_scope',
                            conditions: JSON.stringify(conditions)
                        },
                        function(res) {
                            var cl = 'fx-scope-check-result';
                            var title = res.total_readable;
                            var unlock = res.has_current_page;
                            
                            if (res.total > 1) {
                                title += ',<br />';
                                if (res.has_current_page) {
                                    title += '<span>включая текущую</span>';
                                } else {
                                    title += '<span class="'+cl+'__warning">среди них нет текущей страницы!</span>';
                                }
                            }
                            
                            var h = '<p class="'+cl+'__title">'+title+'</p>',
                                hide_list = res.pages.length > 5;
                            
                            if (res.total > 0) {
                                if (hide_list) {
                                    h += '<p class="'+cl+'__expander"><a>показать</a></p>';
                                }
                                h += '<ul '+ (hide_list ? 'style="display:none;" ' : '')+'>';
                                for (var i = 0; i < res.pages.length; i++) {
                                    var page = res.pages[i];
                                    h += '<li>'+
                                        '<span>'+page.type_name+'</span> &laquo;'+
                                        '<a href="'+page.url+'" target="_blank">'+page.name+'</a>&raquo;'+
                                        '</li>';
                                }
                                if (res.total > res.pages.length) {
                                    h += '<li><b>и еще '+(res.total - res.pages.length)+'</b></li>';
                                }
                                h += '</ul>';
                            }
                            
                            if (!res.has_current_page && res.total > 0) {
                                h += '<div class="'+cl+'__warning">'+
                                        '<p>Внимание! Блок <b>исчезнет</b> с текущей страницы!</p>'+
                                        '<p><a class="'+cl+'__unlock">все равно хочу сохранить</a></p>'+
                                     '</div>';
                            }
                            
                            $check_res.html(h).show();
                            
                            if (hide_list) {
                                $check_res.find('.'+cl+'__expander').click(function() {
                                    $check_res.find('ul').show();
                                    $(this).remove();
                                });
                            }
                            
                            $check_res.attr('class', cl);
                            
                            if (unlock) {
                                $fx_form.unlock_form($scope_form);
                                $check_res.addClass(cl+'_valid');
                            } else {
                                $check_res.addClass(cl+'_invalid');
                                $check_res.find('.'+cl+'__unlock').click(function() {
                                    $fx_form.unlock_form($scope_form);
                                    $(this).remove();
                                });
                            }
                        }
                    );
                    
                    
                }
                
                $cond_control.on('change', validate_cond);
                $type_field.on('change', validate_cond);
                validate_cond();
            },
            onsubmit: function(e) {
                var $scope_form = $(e.target),
                    form_data = $scope_form.formToHash();
                    
                if ($fx_form.form_is_locked($scope_form)) {
                    return;
                }
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
                scope_ls.setValue(last_scope_type, true);
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





})($fxj);