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

$('html').on('fx_panel_form_ready', function(e) {
    var $form = $(e.target),
        $scope_inp = $('input[name="scope[type]"]', $form),
        $scope_params_inp = $('input[name="scope[params]"]', $form);
        
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
                    console.log(new_cond);
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

})($fxj);