/* global Function */

window.less = {
    async: true
};

(function($) {
    
var drop_fx_data_atts = false;

window.fx_front = function () {
    
    this.mode = '';
    this.is_frozen = false;
    $('html').on('mouseover.fx_front', function(e) {
        $fx.front.mouseover_node = e.target;
    });
    
    this.node_panel = new fx_node_panels();
    
    this.move_down_body();
    
    this.mode_selectable_selector = null;
    
    this.image_stub = "/vendor/Floxim/Floxim/Admin/style/images/no.png";
    
    $('#fx_admin_front_menu').on('click.fx_mode_click', '.fx_front_mode', function() {
        var mode = $(this).data('key');
        if (mode !== $fx.front.mode) {
            $fx.front.load(mode);
        }
        return false;
    });
    
    $('html').on('keydown.fx_front_keydown', function(e) {
        if ($fx.front_panel.parse_stris_visible) {
            return;
        }
        
        switch (e.which) {
            // F2 / Command + E
            case 113:
            case 69:
                if (e.which === 69 && !e.metaKey) {
                    return;
                }
                $fx.front.load($fx.front.mode === 'edit' ? 'view' : 'edit');
                break;
            /*
            // +
            case 187:
                if ($(e.target).closest('.fx_var_editable').length > 0) {
                    break;
                }
                var $panel = $fx.front.get_node_panel();
                if (!$panel || $panel.length === 0 || !$panel.is(':visible')) {
                    break;
                }
                var $add_button = $('.fx_add_button', $panel).first();
                if ($add_button.length === 0) {
                    break;
                }
                $add_button.trigger('click');
                break;
            // Del
            case 46:
                var $panel = $fx.front.get_node_panel();
                if (!$panel || $panel.length === 0 || !$panel.is(':visible')) {
                    break;
                }
                var $target = $(e.target);
                if ($target.closest('.fx_var_editable').length > 0 || $target.is(':input')) {
                    break;
                }
                $('.fx_admin_button_delete', $panel).first().trigger('click');
                break;
            */
        }
    });
    
    this.c_hover = null;
    
    $(function() {
        var $role_control = $('.fx-menu-item_code_role-control');
        if ($role_control.length) {
            var data = $role_control.data('data');
            var $role_ls = $fx_fields.livesearch({
                values: data.values,
                value: data.value,
                allow_empty:false
            }, 'input');
            $role_control.append($role_ls);
            
            $role_control.on('change', function(e){
                var new_value = $role_ls.data('livesearch').getValue(),
                    html_class = $('html').attr('class'),
                    role_class = 'fx-root_user-role_'+new_value;
                    
                html_class = html_class.replace(/fx-root_user-role_[^\s]+/, '');
                $('html').attr('class', html_class).addClass(role_class);
            });
            
            $role_control.trigger('change');
        }
        
        var target_ib = ( document.location.hash || '').match(/fx-locate-infoblock_(.+)$/);
        if (target_ib) {
            $fx.front.load('edit').then(function() {
                var $target_ib = $('.fx_infoblock_'+target_ib[1]);
                if ($target_ib.length) {
                    $fx.front.select_item($target_ib);
                    $fx.front.scrollTo($target_ib);
                }
            });
        }
    });
};

fx_front.prototype.handle_mouseover = function(e) {
    
    if ($fx.front.mode === 'view') {
        return;
    }
    if ($fx.front.hilight_disabled) {
        return;
    }
    if (e.fx_hilight_done) {
        return;
    }
    if (!$fx.front.is_selectable(this)) {
        return;
    }
    var $node = $(this);
    if ($node.closest('.fx_is_moving').length > 0) {
        return;
    }
    if ($node.hasClass('fx_selected')) {
        e.fx_hilight_done = true;
        return;
    } 
    if (
        // @todo: fix make_content_editable for bound-to-entity vars
        //!$fx.front.is_selectable(this) || 
        $node.closest('.fx_entity_adder_placeholder').length 
    ) {
        return;
    }
    
    $fx.front.outline_block_off($($fx.front.c_hover),100);
    var $editable = $(e.target).closest('.fx_template_var'),
        field_type = ($editable.data('fx_var') || {}).type,
        make_content_editable = $editable.length > 0 
                                && field_type !== 'datetime' && field_type !== 'file' 
                                && field_type !== 'image' && field_type !== 'select'
                                && $fx.front.mode === 'edit' 
                                && !($editable.get(0).nodeName === 'A' && e.ctrlKey);
    var is_hover_parent = $fx.front.last_hover_node 
                            && $.contains(this, $fx.front.last_hover_node);
    $fx.front.c_hover = this;
    
    setTimeout(
        function() {
            if ($fx.front.c_hover !== $node.get(0)) {
                return;
            }
            if ($node.hasClass('fx_selected')) {
                return;
            }
            if ($fx.front.hilight_disabled) {
                return false;
            }
            $fx.front.last_hover_node = $node[0];
            if (!$node.hasClass('fx_hilight_hover') && $node.closest('.fx_infoblock_disabled').length === 0) {
                $('.fx_hilight_hover').removeClass('fx_hilight_hover');
                $node.addClass('fx_hilight_hover');
                if ($node.closest('.fx_is_moving').length === 0) {
                    $fx.front.outline_block($node, 'hover', 300);
                }
                if (make_content_editable) {
                    $editable.addClass('fx_var_editable').attr('contenteditable', 'true');
                }
            }
        },
        is_hover_parent ? 300 : 30
    );
    $node.one('mouseout.fx_front_mouseout', function() {
        $fx.front.c_hover = null;
        if ($node.closest('.fx_selected').length > 0) {
            return false;
        }
        setTimeout(
            function() {
                if ($node.closest('.fx_selected').length > 0) {
                    return false;
                }
                if ($fx.front.c_hover !== $node[0]) {
                    $node.removeClass('fx_hilight_hover');
                    $fx.front.outline_block_off($node, 100);
                    $editable.removeClass('fx_var_editable').attr('contenteditable', null);
                }
            },
            100
        );
    });
    e.fx_hilight_done = true;
    return;
};

fx_front.prototype.handle_click = function(e) {
    
    if ($fx.front.mode === 'view') {
        return;
    }
    
    var $target = $(e.target);
    
    if ($fx.front.select_disabled && $target.closest('.fx_overlay').length === 0) {
        return false;
    }
    
    // don't remove selection when mousedown target doesn't match current click (mouseup) target
    // e.g. user tries to select some text and stops selection when pointer is outside the edited node
    if (e.target !== $fx.front.last_mousedown_node && !e.fxForced) {
        if ($target.closest('a').length) {
            return false;
        }
        return;
    }
    
    if ($target.closest('.fx_overlay, .redactor-dropdown, #redactor-modal-box').length > 0) {
        return;
    }
    var closest_selectable = null;
    if ($fx.front.is_selectable($target)) {
        closest_selectable = $target;
    } else {
        closest_selectable = $fx.front.get_selectable_up($target);
    }
    
    // nothing to choose
    if (!closest_selectable) {
        // the cases when the target was beyond the primary tree
        // as with jqueryui-datepicker at redrawing
        if ($target.closest('html').length === 0) {
            return;
        }
        // remove the selection and end processing
        $fx.front.deselect_item();
        return;
    }

    // move between pages via links to squeezed control,
    // and even saves the current mode
    var clicked_link = $target.closest('a');
    if (clicked_link.length > 0 && e.ctrlKey && clicked_link.attr('href')) {
        clicked_link.add(clicked_link.parents()).attr('contenteditable', 'false');
        document.location.href = clicked_link.attr('href');
        return false;
    }

    e.stopImmediatePropagation();
    
    if ($target.attr('onclick')) {
        $target.attr('onclick', null);
    }
    
    // catch only contenteditable
    if ($(closest_selectable).hasClass('fx_selected')) {
        e.preventDefault();
        return;
    }
    var $link = $target.closest('a[href], .fx_click_handler');
    $fx.front.select_item(closest_selectable);
    if ($link.length && $link.closest('.fx_entity_adder_placeholder').length === 0) {
        var is_link = !$link.is('.fx_click_handler'),
            url = is_link && $link.attr('href');
        
        var $follow_button = $fx.front.add_follow_button(url);
        if (!is_link) {
            $follow_button.click(function() {
                $link.data('fx_click_handler')();
            });
        }
        
    }
    return false;
};


fx_front.prototype.add_follow_button = function(url) {
    var panel = $fx.front.node_panel.get();
    
    if (panel.$panel.find('.fx_icon-type-follow').length > 0) {
        return;
    }
        
    
    var button = {
            type:'icon',
            keyword:'follow'
        };
    if (url) {
        button.href = url;
    }
    var $button = panel.add_button(
        button, 
        null, 
        panel.$panel.find('>*:visible').first()
    );
    return $button;
};

fx_front.prototype.freeze = function() {
    this.disable_hilight();
    this.disable_select();
    this.disable_node_panel();
    this.is_frozen = true;
    
    $('.fx_front_overlay').css('opacity', 0);
};

fx_front.prototype.unfreeze = function() {
    this.enable_node_panel();
    this.enable_select();
    if (!this.get_selected_item()) {
        this.enable_hilight();
    }
    $('.fx_front_overlay').css('opacity', 1);
    this.is_frozen = false;
};

fx_front.prototype.disable_hilight = function() {
    this.hilight_disabled = true;
    $('.fx_front_overlay .fx_inline_adder-visible').each(function() {
        this.fx_hide_inline_adder(0);
    });
    this.disable_adders('infoblock');
    this.disable_adders('entity');
};

fx_front.prototype.enable_hilight = function(){
    this.enable_adders('infoblock');
    this.enable_adders('entity');
    this.hilight_disabled = false;
};

fx_front.prototype.disable_select = function() {
    this.select_disabled = true;
};

fx_front.prototype.enable_select = function(){
    this.select_disabled = false;
};

fx_front.prototype.get_area_meta = function($area_node) {
    var meta = $area_node.data('fx_area') || {};
    if (typeof meta.scope === 'undefined') {
        var $parent_area = $area_node.parents('.fx_area');
        if ($parent_area.length) {
            var parent_meta = this.get_area_meta($parent_area);
            if (typeof parent_meta.scope !== 'undefined') {
                meta.scope = parent_meta.scope;
            }
        }
    }
    var $container_ib = $area_node.closest('.fx_infoblock');
    if ($container_ib.length) {
        var container_meta = $container_ib.data('fx_infoblock');
        if (container_meta) {
            meta.container_scope_type = container_meta.scope_type;
        }
    }
    if (typeof meta.size === 'undefined') {
        meta.size = '';
        /*
        // It would be nice to calculate
        var full_size = 1000;
        if ($area_node.outerWidth() < full_size*0.5) {
            meta.size = 'narrow';
        } else {
            meta.size = '';
        }
        */
        $area_node.data('fx_area', meta);
    }
    return meta;
};

fx_front.prototype.show_adder_placeholder = function($placeholder, $rel_node, rel_position) {
    
    var $placeholder_parent = $placeholder[0].$fx_placeholder_parent;
    
    var $hidden_block = $placeholder_parent.closest('.fx_hidden_placeholded');
    var block_was_hidden = $hidden_block.length > 0;
    var $block_mark = $([]);
    if (block_was_hidden) {
        $hidden_block.removeClass('fx_hidden_placeholded');
        $block_mark = $hidden_block.children('.fx_hidden_placeholder_mark');
        $hidden_block.children().show();
    }
    
    
    
    // store node right before placeholder to place placeholder back on cancel
    var $placeholder_pre = $placeholder.prev().first(),
        placeholder_meta = $placeholder.data('fx_entity_meta'),
        speed = 500,
        null_size = {width:'',height:'','min-width':''};
    
    if (placeholder_meta.add_to_top) {
        $rel_node = $placeholder_parent.find('.fx_entity').first();
        rel_position = 'before';
    }
    
    if ($rel_node && $rel_node.data('fx_entity')) {
        var rel_entity = $rel_node.data('fx_entity');
        var rel_id = rel_entity[2] ? rel_entity[2] : rel_entity[0];
        if (rel_position === 'before') {
            $rel_node.before($placeholder);
            placeholder_meta.placeholder.__move_before = rel_id;
        } else {
            $rel_node.after($placeholder);
            placeholder_meta.placeholder.__move_after = rel_id;
        }
    } else {
        if ($placeholder_parent.is('.fx_pseudo_parent')) {
            $placeholder_parent.before($placeholder);
            $placeholder_parent.hide();
            $placeholder.on('fx_infoblock_unloaded', function() {
                $placeholder_parent.remove();
            });
        } else {
            $placeholder_parent.append($placeholder);
        }
    }

    show_placeholder();
    
    $fx.front.scrollTo($placeholder, true);
    
    function get_size() {
        // decrease size by one pixel to suppress rouning effect in chrome 
        // when real size is defined in percents
        var size = {
            height: $placeholder.height() - 1,
            width: $placeholder.width() - 1
        };
        size['min-width'] = size.width;
        return size;
    }
    
    
    function show_placeholder() {
        
        function create_finish_form() {
            var form_data = {
                header:$fx.lang('Adding') + ' '+placeholder_meta.placeholder_name,
                
                form_button:[
                    {
                        key:'cancel'
                    },
                    {
                        key:'save'
                    }
                ],
                fields:[
                    {
                        type:'icon_text',
                        icon:'edit',
                        label:$fx.lang('Edit in form')
                    }
                ],
                onsubmit: function() {
                    var callback = null;
                    var $go_checkbox = $('input:visible[name="go_to_page"]', this);
                    if ($go_checkbox.length && $go_checkbox[0].checked) {
                        callback = function(res) {
                            var new_entity = null;
                            $.each(res.saved_entities, function() {
                                if (this.type === placeholder_meta.placeholder.type) {
                                    new_entity = this;
                                    return false;
                                }
                            });
                            if (!new_entity) {
                                return false;
                            }
                            if (new_entity.url) {
                                document.location.href = new_entity.url;
                            }
                        };
                    }
                    fx_eip.fix();
                    var modified_vars = fx_eip.get_modified_vars();

                    if (modified_vars.length) {
                        fx_eip.save(callback);
                    } else {
                        fx_eip.stop();
                        hide_placeholder();
                    }
                    return false;
                }
            };
            if (placeholder_meta.has_page) {
                form_data.fields.push(
                    {
                        name:'go_to_page',
                        type:'checkbox',
                        label:'и перейти к странице',
                        value:'0'
                    }
                );
            }
            var $form = $fx.front_panel.show_form(form_data, {
                view:'horizontal',
                style:'finish',
                skip_focus:true,
                keep_hilight_on:true,
                oncancel:function() {
                    hide_placeholder();
                },
                onready: function($form) {
                    $form.find('.fx_button-icon-edit').click(function() {
                        $fx.front.get_edit_closure($placeholder)();
                    });
                }
            });
            return $form;
        }
                
                
        $placeholder.addClass('fx_entity_adder_placeholder_active');
        
        $('.fx_hilight_hover').each(function() {
            $fx.front.outline_block_off($(this));
        });
        $fx.front.disable_hilight();

        var before_show_event = jQuery.Event( "fx_before_show_adder_placeholder" );
        before_show_event.hide_placeholder = hide_placeholder;
        $placeholder.trigger(before_show_event);
        if ( before_show_event.isDefaultPrevented() ) {
            return;
        }

        var is_linker_placeholder = placeholder_meta.placeholder_linker;
                
        if (is_linker_placeholder) {
            $('.fx_unselectable', $placeholder).removeClass('fx_unselectable');
        } else {
            if (!placeholder_meta.has_page) {
                var $form = create_finish_form();
                $placeholder.data('fx_finish_form', $form);
            }
        }
        
        $fx.front.hilight($placeholder);
        var target_size = get_size();
        
        $block_mark.slideUp(speed/1.5);
        
        var initial_style_att = $placeholder.attr('style');
        
        $placeholder.css({width:0,height:0, 'min-width':0});
        if (placeholder_meta.replace_last) {
            var $last_entity = $placeholder.parent().find('>.fx_entity:not(.fx_entity_adder_placeholder)').last();
            $last_entity.addClass('fx_entity_last_hidden');
        }
        $placeholder.animate(
            target_size,
            speed,
            null,
            function() {
                $placeholder.css(null_size);
                $placeholder.attr('style', initial_style_att);
                $placeholder.trigger('fx_after_show_adder_placeholder');
                var $placeholder_focus = $placeholder,
                    // select "name" field if exists, else - first visible editable
                    find_focus_field = function() {
                        var res = {};
                        $placeholder_fields = $placeholder.descendant_or_self('.fx_template_var, .fx_template_var_in_att');
                        for (var i = 0; i < $placeholder_fields.length; i++) {
                            var $c_field = $placeholder_fields.eq(i);
                            if ($fx.front.is_selectable($c_field) && $c_field.is(':visible')) {
                                if (!res.first && $c_field.data('fx_var')) {
                                    res.first = $c_field;
                                }
                                // found name field
                                if ( ($c_field.data('fx_var') || {}).name === 'name') {
                                    //return $c_field;
                                    res.name = $c_field;
                                }
                            }
                        }
                        return res.name || res.first || $placeholder;
                    };
                
                if (is_linker_placeholder) {
                    $placeholder.addClass('fx_linker_placeholder');
                    $('.fx_hilight', $placeholder).removeClass('fx_hilight').addClass('fx_unselectable');
                } else {
                    $placeholder_focus = find_focus_field();
                }
                
                $fx.front.scrollTo($placeholder);
                
                if (!is_linker_placeholder) {
                    if (placeholder_meta.has_page) {
                        $fx.front.get_edit_closure($placeholder, {
                            oncancel: function() {
                                hide_placeholder();
                            }
                        })();
                    } else {
                        $fx.front.select_item($placeholder_focus);
                    }
                } else {
                    $fx.front.select_item($placeholder_focus);
                    
                    var 
                        link_field_name = $placeholder.data('fx_entity_meta').placeholder_linker._link_field,
                        selector_selector = '.fx_node_panel__item-field_name-'+link_field_name,
                        panel = $fx.front.node_panel.get(),
                        $label = panel.add_label( '<a class="fx_label_link">'+$fx.lang('Create')+'</a>' ),
                        $panel = panel.$panel,
                        $selector = $(selector_selector, $panel).first(),
                        $placeholder_fields = $('.fx_node_panel__item-type-field', $panel).not(selector_selector);
                        
                    $placeholder_fields.hide();
                    setTimeout(function() {
                        $selector.find(':input:visible').focus();
                    },50);
                    
                    $placeholder.one('fx_deselect.hide_placeholder', function() {
                        hide_placeholder();
                        return false;
                    });
                    
                    $label.click(function() {
                        $label.remove();
                        $selector.hide();
                        
                        $('.fx_unselectable', $placeholder).removeClass('fx_unselectable');
                        $placeholder.removeClass('fx_linker_placeholder fx_unselectable');
                        
                        if (!placeholder_meta.has_page) {
                            var $form = create_finish_form();
                            $placeholder.data('fx_finish_form', $form);
                            $placeholder.off('fx_deselect.hide_placeholder');
                            $fx.front.select_item($placeholder_focus);

                            $fx.front.hilight($placeholder);
                            var $focus_field = find_focus_field();
                            $fx.front.select_item( $focus_field );
                        } else {
                            $fx.front.get_edit_closure($placeholder, {
                                oncancel: function() {
                                    hide_placeholder();
                                }
                            })();
                        }
                    });
                }
            }
        );
    };
    
    function hide_placeholder() {
        $fx.front.disable_hilight();
        $fx.front.outline_block_off($placeholder);
        fx_eip.stop();
        $fx.front.deselect_item();
        if ($placeholder.data('fx_finish_form')) {
            $fx.front_panel.hide();
        }
        $placeholder.data('fx_finish_form', null);
        $placeholder.trigger('fx_before_hide_adder_placeholder');
        $placeholder
          .css(get_size())
          .animate(
            null_size, 
            speed,
            null,
            function() {
                
                $placeholder.removeClass('fx_entity_adder_placeholder_active').css(null_size);
                
                window.$last_placeholder = $placeholder;
                
                // reset real_value's
                for( var i in $placeholder.data()) {
                    if (!/^fx_template_var/.test(i)) {
                        continue;
                    }
                    var meta = $placeholder.data(i);
                    meta.real_value = meta.initial_value;
                }
                
                $('.fx_template_var', $placeholder)
                        .html('')
                        .removeClass('fx_var_editable')
                        .removeClass('fx_editable_empty');
                
                if ($placeholder_pre.length) {
                    $placeholder_pre.after($placeholder);
                    placeholder_meta.placeholder.__move_before = null;
                    placeholder_meta.placeholder.__move_after = null;
                }
                
                if (block_was_hidden) {
                    $hidden_block.addClass('fx_hidden_placeholded');
                }
                
                if (placeholder_meta.replace_last) {
                    var $last_entity = $placeholder.parent().find('>.fx_entity_last_hidden');
                    $last_entity.removeClass('fx_entity_last_hidden');
                }
                
                $placeholder.trigger('fx_after_hide_adder_placeholder');
                
                //$placeholder.remove();
                $placeholder.detach();
                $fx.front.enable_hilight();
            }
        );
        $block_mark.slideDown(speed);
    }
};

fx_front.prototype.get_placeholder_adder_closure = function ($placeholder) {
    return function (event) {
        var $button = $(event.target),
            $current_node = $($fx.front.get_selected_item()),
            dir = $button.hasClass('fx_before') ? 'before' : 
                    ($button.hasClass('fx_after') ? 'after' : null);
            
            $fx.front.show_adder_placeholder($placeholder, $current_node, dir);
    };
};

fx_front.prototype.is_top_entity = function($node) {
    // let's check that there's no other entity 
    // between this one and the infoblock node   
    var is_top_entity = $node.is('.fx_entity');
    $node.parents('.fx_entity, .fx_infoblock').each(function() {
        var $this = $(this);
        if ($this.is('.fx_entity')) {
            is_top_entity = false;
            return false;
        }
        if ($this.is('.fx_infoblock')) {
            return false;
        }
    });
    return is_top_entity;
};

fx_front.prototype.find_placeholder_by_meta = function(meta, $placeholders) {
    var meta_sign = function(meta) {
        meta = meta || {};
        return [ meta.type, meta.infoblock_id, meta.parent_id ].join ('-');
    };
    var needle_sign = meta_sign(meta);
    var $found_placeholder = false;
    $placeholders.each(function (){ 
        var c_sign = meta_sign ( 
            ($(this).data('fx_entity_meta') || {}).placeholder
        );
        if (c_sign === needle_sign) {
            $found_placeholder = $(this);
            return false;
        }
    });
    return $found_placeholder;
};

fx_front.prototype.get_area_node = function($ib_node) {
    return $ib_node.parent().closest('.fx_area');
};

fx_front.prototype.get_page_id = function() {
    return $('body').data('fx_page_id');
};

fx_front.prototype.active_dialogs = {};

fx_front.prototype.push_dialog_once = function(dialog) {
    if (this.active_dialogs[dialog]) {
        return false;
    }
    this.active_dialogs[dialog] = 1;
    return 1;
};

fx_front.prototype.pop_dialog = function(dialog) {
    this.active_dialogs[dialog]--;
};

/**
 * Function to show controller selection dialog
 */

fx_front.prototype.add_infoblock_select_controller = function($node, $rel_node, rel_position) {
    
    var dialog = 'infoblock_select_controller';
    if (!this.push_dialog_once(dialog)) {
        return;
    }
    
    var $area_node = $node.closest('.fx_area'),
        container_infoblock = $node.closest('.fx_infoblock').not('body').data('fx_infoblock'),
        area_meta = $fx.front.get_area_meta($area_node),
        place_params = {};
    
    if ($rel_node && $rel_node.length) {
        var $stub = $('<div class="fx_infoblock_stub"></div>');
        rel_position === 'after' ? $rel_node.after($stub) : $rel_node.before($stub);
        $fx.front.select_item($stub[0]);
        place_params = $fx.front.get_infoblock_place_params($stub);
    } else {
        $fx.front.select_item($area_node[0]);
    }
    
    var form_data = {
        entity:'infoblock',
        action:'select_controller',
        page_id:$fx.front.get_page_id(),
        container_infoblock_id: container_infoblock ? container_infoblock.id : null,
        area:area_meta,
        fx_admin:true
    };
    
    form_data = $.extend(place_params, form_data);
    
    $fx.front_panel.load_form(form_data, {
        view:'vertical',
        oncancel:function() {
            $('.fx_infoblock_stub').remove();
            $fx.front.deselect_item();
            $fx.front.pop_dialog(dialog);
        },
        onfinish: function(data) {
            $fx.front.pop_dialog(dialog);
            if (data.preset_id) {
                var $ib_node = $(data.html);
                $fx.front.append_ib_node($area_node, $ib_node);
                $fx.front.hilight($ib_node);
                $ib_node.trigger('fx_infoblock_loaded');
                $ib_node.css('opacity', $fx.front.disabled_infoblock_opacity).animate({opacity: 1},250);
                
                var preset_params = $fx.front.get_infoblock_place_params($ib_node);

                $ib_node.data('fx_preset_params', preset_params);
                
                $ib_node.on('fx_after_hide_adder_placeholder', function() {
                    $fx.front.outline_block_off($ib_node);
                    $ib_node.remove();
                    $fx.front.hilight();
                });
                
                $ib_node.find('.fx_adder_variant').first().click();
            } else {
                var $c_ib_node = $('<div class="fx_infoblock fx_infoblock_fake fx_infoblock_placeholder" />');
                $fx.front.append_ib_node($area_node, $c_ib_node);
                $c_ib_node.data('fx_infoblock', {id:'fake'});
                $fx.front.show_infoblock_settings_form(data, $c_ib_node);
            }
            $('.fx_infoblock_stub', $area_node).remove();
        }
    });
};

fx_front.prototype.append_ib_node = function ($area_node, $ib_node) {
    // try to replace stub
    var $stub = $('.fx_infoblock_stub', $area_node);
    if ($stub.length) {
        $stub.before($ib_node);
        $stub.addClass('fx_infoblock_stub_hidden');
        return;
    }

    // try to find last infoblock inside area
    // and add new after it
    var $last_ib = null;
    $('.fx_infoblock', $area_node).each(function () {
        if ($(this).closest('.fx_area').get(0) !== $area_node.get(0)) {
            return;
        }
        $last_ib = $(this);
    });
    if ($last_ib) {
        $last_ib.after($ib_node);
        return;
    }
    var $marker = $('.fx_area_marker', $area_node);
    if ($marker.length > 0) {
        $marker.after($ib_node);
        return;
    }

    if ($area_node.hasClass('fx_hidden_placeholded')) {
        $area_node.removeClass('fx_hidden_placeholded').html('');
    }
    $area_node.append($ib_node);
};

fx_front.prototype.is_styled_inline = function($n) {
    return $n.hasClass('fx-styled-inline') 
            && 
            (
                $n.hasClass('theme--floxim--basic--section') || 
                $n.hasClass('theme--floxim--basic--layout')
            );
};

fx_front.prototype.is_selectable = function(node) {
    var n = $(node);
    
    if (n.hasClass('fx_unselectable')) {
        return false;
    }
    
    
    var check_event = $.Event('fx_check_is_selectable');
    n.trigger(check_event);
    if (check_event.result === false) {
        return false;
    }
    
    switch($fx.front.mode) {
        case 'view': default:
            return false;
        case 'design':
            if (n.hasClass('fx_infoblock')) {
                return true;
            }
            return false;
        case 'edit':
            if ($fx.front.is_styled_inline(n)) {
                return true;
            }
            if (n.hasClass('fx_infoblock')) {
                return true;
            }
            // adder placeholders and fields inside are not selectable (mainly for keyboard navigation)
            var $placeholder = n.closest('.fx_entity_adder_placeholder');
            if ($placeholder.length > 0 && !$placeholder.hasClass('fx_entity_adder_placeholder_active')) {
                return false;
            }
            
            // select an entity to show "edit - delete - move" buttons
            if (n.hasClass('fx_entity')) {
                return true;
            }
            
            // text fields and variables in attributes
            if ( n.hasClass('fx_template_var') || n.hasClass('fx_template_var_in_att') ) {
                if ($fx.front.is_var_bound_to_entity(n)) {
                    return false;
                }
                return true;
            }
            return false;
    }
};

fx_front.prototype.is_var_bound_to_entity = function($node) {
    if ($node.hasClass('fx_var_bound_to_entity')) {
        return true;
    }
    if (!$node.is(':visible')) {
        return false;
    }
    if ($node.is('.fx_entity')) {
        return false;
    }
    
    var $entity = $node.closest('.fx_entity');
    
    if ($entity.length === 0) {
        return false;
    }
    
    var $node_ib = $node.closest('.fx_infoblock'),
        $entity_ib = $entity.closest('.fx_infoblock');
    
    if (!$node_ib[0] || !$entity_ib[0] || $node_ib[0] !== $entity_ib[0]) {
        return false;
    }
    
    if ($('.fx_template_var:visible, .fx_template_var_in_att:visible', $entity).length === 1) {
        $node.addClass('fx_var_bound_to_entity');
        return true;
    }
    
    if (
        !$node.is(':visible') || 
        ($node.is('.fx_template_var') && $('.fx_template_var', $entity).length > 1)
    ) {
        return false;
    }
    
    var distance = 35;
    var eo = $entity.offset();
    var no = $node.offset();
    
    if (Math.abs(eo.top - no.top) > distance) {
        return false;
    }
    if (Math.abs(eo.left - no.left) > distance) {
        return false;
    }
    
    // No Math.abs() here because if field is larger than container, we assume them to be bound
    if ( $entity.outerWidth() - $node.outerWidth() > distance) {
        return false;
    }
    if ( $entity.outerHeight() - $node.outerHeight()  > distance) {
        return false;
    }
    $node.addClass('fx_var_bound_to_entity');
    return true;
};

fx_front.prototype.get_selectable_up = function(rel_node) {
    if (!rel_node) {
        rel_node = this.get_selected_item();
    }
    if (!rel_node) {
        return null;
    }
    var selectable_up = null;
    var parents = $(rel_node).parents();
    for (var i = 0; i < parents.length; i++) {
        var c_parent = parents.get(i);
        if (this.is_selectable(c_parent)) {
            selectable_up = c_parent;
            break;
        }
    }
    return selectable_up;
};

fx_front.prototype.fix = function() {
    $('body').css('opacity', '0.99');
    setTimeout(function(){$('body').css('opacity', 1);}, 5);
};

fx_front.prototype.get_select_path_panel = function() {
    var $panel = $('.fx-admin-panel'),
        $path_panel = $('.fx_select_path_panel', $panel);
    if ($path_panel.length) {
        return $path_panel;
    }
    $path_panel = $(
        '<div class="fx_select_path_panel">'+
            '<div class="fx_select_path_panel__path"></div>'+
            '<div class="fx_select_path_panel__cancel"></div>'+
        '</div>'
    );
    $path_panel.find('.fx_select_path_panel__cancel').click(function() {
        fx_eip.cancel();
    });
    $panel.append($path_panel);
    return $path_panel;
};

fx_front.prototype.draw_select_path = function($node) {
    var $path_panel = $fx.front.get_select_path_panel(),
        $path_container = $path_panel.find('.fx_select_path_panel__path');
        
    $path_container.html('');
    var $path = $($node.parents().get().reverse()).add($node);
    
    if ($node.is('.fx_entity')) {
        $path = $path.add( $fx.front.get_entity_bound_vars($node) );
    }
    
    var path_items = [],
        block_found = false,
        entity_found = false;
    $.each( $path.get().reverse(), function() {
        if (this.nodeName === 'BODY') {
            return;
        }
        
        // limit path to three items
        if (block_found && entity_found) {
            //return false;
        }
        
        var $cp = $(this);
        var is_active = $cp.closest($node).length > 0;
        
        if ($cp.data('fx_var')) {
            var data = $cp.data('fx_var');
            path_items.push({
                type: data.var_type === 'content' ? 'Поле' : 'Надпись',
                label:data.label || data.id,
                node:$cp,
                is_active:is_active
            });
        } 
        
        $.each($cp.data(), function(data_key, data) {
            if (!data_key.match(/^fx_template_var_/)) {
                return;
            }
            if (data.type === 'image' && data.att === 'src') {
                path_items.push({
                    type:'Поле',
                    label:data.label || data.name,
                    node:$cp
                });
            }
        });
        
        if ($cp.is('.fx_entity')) {
            entity_found = true;
            path_items.push({
                type:'Элемент',
                label:$cp.data('fx_entity_name'),
                node:$cp,
                controls:[
                    'follow', 'edit', 'unpublish', 'delete'
                ],
                is_active:is_active
            });
        }
        if ($cp.is('.fx_infoblock')) {
            var ib_meta = $cp.data('fx_infoblock');
            
            var controller_parts = ib_meta.controller.split(':'),
                controller = controller_parts[0],
                action = controller_parts[1],
                icon = 'ib-' + (action.match(/list_/) ? action.replace(/_/, '-') : 'widget');
                
                
            
            
            block_found = true;
            path_items.push({
                type:'Блок',
                icon: icon,
                controller:controller,
                label: ib_meta.name || '#'+ib_meta.id,
                node:$cp,
                controls:[
                    'settings', 'delete'
                ],
                is_active:is_active
            });
        }
        if ( $fx.front.is_styled_inline($cp) && !$cp.is('.fx_infoblock') && !$cp.is('.fx_entity')) {
            /*
            var container_data = $cp.data('fx_container'),
                container_name = container_data.name;
            */
            var container_name = '';
            
            if ($cp.is('.fx_area')) {
                var area_meta = $cp.data('fx_area');
                if (area_meta.name) {
                    container_name = area_meta.name;
                }
            } else if ($cp.is('.theme--floxim--basic--layout')) {
                container_name = 'Страница';
            }
            path_items.push({
                type:'Контейнер',
                label: container_name,
                node:$cp,
                controls:[
                    
                ],
                is_active:is_active
            });
        }
    });
    if (path_items.length === 0) {
        return;
    }
    path_items[0].is_active = true;
    $path_panel.show();
    this.add_select_path_items(path_items.reverse());
};

fx_front.prototype.add_select_path_items = function(items) {
    var $path_panel = $fx.front.get_select_path_panel(),
        $path_container = $path_panel.find('.fx_select_path_panel__path');
    
    $.each(items, function() {
        var bl = 'fx_select_path_item',
            item = this;
        var $item = $('<div class="'+bl+ (item.is_active ? ' '+ bl + "-active" : '')+(item.icon ? ' '+bl+'_with-icon' : '')+'">'+
                        '<div class="'+bl+'__title">'+
                            (item.icon ? '<div class="'+bl+'__icon fx_icon fx_icon-type-'+item.icon+'"></div>' : '')+
                            '<div class="'+bl+'__type">'+item.type+'</div>'+
                            '<div class="'+bl+'__label">'+item.label+'</div>'+
                        '</div>'+
                    '</div>');
        if (item.controls && false) {
            var $controls = $('<div class="'+bl+'__controls"></div>');
            $item.append($controls);
            $.each(item.controls, function() {
                $controls.append('<div class="fx_icon fx_icon-type-'+this+'"></div>');
            });
        }
        if (!item.is_active) {
            $item.click(function() {
                $fx.front.select_item(item.node[0]);
            });
        } else {
            $item.click(function() {
                $fx.front.scrollTo(item.node);
            });
        }
        $path_container.append($item);
    });
};

fx_front.prototype.select_item = function(node) {
    var c_selected = this.get_selected_item();
    if (c_selected === node) {
        return;
    }
    
    this.deselect_item();
    
    this.selected_item = node;
    var $node = $(node);
    this.draw_select_path($node);
    $node.addClass('fx_selected').trigger('fx_select');
    $node.parents().add($node).addClass('fx_has_selection');
    
    $fx.front.outline_block_off($node);
    $fx.front.outline_block_off($node.find('.fx_hilight_hover'));
    $fx.front.outline_block($node, 'selected');
    
    if (!this.node_panel_disabled) {
        this.make_node_panel($node);
    }
    
    if ($node.is('.fx_entity')) {
        $fx.front.select_content_entity($node);
    }
    
    
    if ($node.is('.fx_template_var, .fx_template_var_in_att')) {
        $node.edit_in_place();
        
        var $closest_entity = $node.closest('.fx_entity');
        if ($closest_entity.length && $closest_entity[0] !== $node[0]) {
            $fx.front.select_content_entity($closest_entity, $node);
        }
    }
    
    if ($node.is('.fx_infoblock')) {
        $fx.front.select_infoblock($node);
    }
    
    /*
    var scrolling = false;
    setTimeout(function() {
        if (!scrolling && !$node.hasClass('fx_var_editable') && !$node.hasClass('theme--floxim--basic--layout')) {
            $fx.front.scrollTo($node, true, function() {
                scrolling = false;
            });
            scrolling = true;
        }
    }, 150);
    */
   
    $fx.front.disable_hilight();
    $('html').on('keydown.fx_selected', function(e) {
        if (
            $fx.front_panel.has_active_panels()
        ) {
            return;
        }
        // Escape
        if (e.which === 27) {
            if (e.isDefaultPrevented && e.isDefaultPrevented()) {
                return;
            }
            $fx.front.deselect_item();
            return;
        }
        if (! (e.ctrlKey && (e.which === 38 || e.which === 40)) && e.which !== 9) {
            return;
        }
        if ($node.hasClass('fx_var_editable') && e.target !== $node[0]) {
            return;
        }
        if ($(e.target).is(':input')) {
            return;
        }
        
        var $selectable = $('.fx_hilight');
        var c_index = $selectable.index(node);
        var is_left = (e.which === 38 || (e.which === 9 && e.shiftKey));
        var ii = is_left ? -1 : 1;
        var ie = is_left ? 0 : $selectable.length;
        for (var i = c_index + ii; i !== ie + ii; i+= ii ) {
            var $ci = $selectable.eq(i);
            if ($fx.front.is_selectable($ci)) {
                $fx.front.select_item($ci);
                return false;
            }
        }
    });
    
    $node.closest('.fx_entity_hidden').addClass('fx_entity_hidden-selected');
    
    if ($node.is('.fx-styled-inline')) {
        $fx.front.select_styled($node);
    }
};

fx_front.prototype.select_styled = function($node) {
    var node_panel = this.node_panel.get($node);
    var $ib = $node.closest('.fx_infoblock'),
        ib_meta = $ib.data('fx_infoblock');
    
    var style_id = $node.attr('class').match(/_style-id_([^\s]+)/);
    
    if (!style_id) {
        return;
    }
    style_id = style_id[1];
    
    var template_params = $ib.data('fx_template_params'),
        param_key = null,
        param_meta = null;
        
    if (!template_params) {
        return;
    }
    
    $.each(template_params, function(n, tps) {
        var k = tps[0],
            tp = tps[1];
        
        if (!tp.style_id || tp.style_id !== style_id) {
            return;
        }
        param_key = k;
        param_meta = tp;
        return false;
    });
    
    if (!param_key || !param_meta) {
        return;
    }
    
    node_panel.add_button(
        {
            keyword:'container',
            type:'icon',
            title:'Стиль'
        }, 
        function() {
            var fields = [param_meta];
            $fx.front.prepare_infoblock_visual_fields([fields]).then(function(res) {
                fields = res[0];
                $fx.front_panel.show_form({
                    header: 'Правим стиль',
                    fields: fields,
                    form_buttons: ['cancel']
                }, {
                    view:'horizontal',
                    onsubmit: function(e) {
                        //update_data($(e.target));
                        var $f = $(e.target),
                            vals = $f.find('.fx-field-group').formToHash();
                        
                        $fx.post({
                            entity:'layout',
                            action:'save_inline_style',
                            prop:param_key,
                            value: vals,
                            visual_id: ib_meta.visual_id
                        }, function(res) {
                            $fx.front_panel.hide();
                            $fx.front.reload_infoblock($ib);
                        });
                        return false;
                    }
                });
            });
        }
    );
};


fx_front.prototype.select_container = function($node) {
    if ($node.hasClass('fx_entity_adder_placeholder')) {
        return;
    }
    var node_panel = this.node_panel.get($node);
    var $ib = $node.closest('.fx_infoblock'),
        ib_meta = $ib.data('fx_infoblock'),
        container_meta = $node.data('fx_container');

    var form_handler = null;
    
    node_panel.add_button(
        {
            keyword:'container',
            type:'icon',
            title:'Размеры, отступы, фон...'
        }, 
        function() {
            $fx.front_panel.load_form({
                entity:'infoblock',
                action:'container_settings',
                container_meta: JSON.stringify(container_meta),
                content_parent_props: JSON.stringify ( $fx.front.get_content_parent_props($node) ),
                id:ib_meta.id,
                visual_id:ib_meta.visual_id,
                page_id: $fx.front.get_page_id(),
                fx_admin:true
            }, {
                view:'horizontal',
                onfinish:function() {
                    $fx.front.reload_infoblock($('.fx_infoblock_'+ib_meta.id));
                },
                onready:function($form) {
                    form_handler = new $fx.container.form_handler($form, $node, '');
                },
                oncancel: function() {
                    form_handler.reset_block();
                }
            });
        }
    );
};

fx_front.prototype.get_modifiers = function($node, name) {
    var rex = new RegExp('^'+name+'_(.+?)(_(.+))?$'),
        classes = $node.attr('class').split(' '),
        res = {};
    for (var i = 0; i < classes.length; i++) {
        var match = classes[i].match(rex);
        if (match) {
            res[match[1]] = match[3] === undefined ? true : match[3];
        }
    }
    return res;
};

fx_front.prototype.set_modifiers = function($node, name, mods) {
    var old = $fx.front.get_modifiers($node, name),
        cl = function(prop, value) {
            return value === false ? '' : name+'_'+prop+(value === true ? '' : '_'+value);
        };
    var add_classes = [],
        drop_classes = [],
        event_map = {};
    $.each(mods, function(prop, value) {
        if (old[prop] === value) {
            return;
        }
        event_map[prop] = {
            'new':value,
            'old':false
        };
        if (old[prop]) {
            drop_classes.push(cl(prop, old[prop]));
            event_map[prop]['old'] = old[prop];
        }
        add_classes.push(cl(prop, value));
    });
    if (drop_classes.length === 0 && add_classes.length === 0) {
        return;
    }
    $node.removeClass(drop_classes.join(' ')).addClass(add_classes.join(' '));
    var e = $.Event('fx_set_modifiers');
    e.modifiers = event_map;
    e.node_name = name;
    $node.trigger(e);
    return event_map;
};

fx_front.prototype.make_node_panel = function($node) {
    this.node_panel.create($node);
};

fx_front.prototype.recount_node_panel = function() {
    this.node_panel.recount();
};

fx_front.prototype.get_selected_item = function() {
    return this.selected_item;
};

fx_front.prototype.deselect_item = function() {
    var selected_item = this.get_selected_item();
    if (selected_item) {
        var $node = $(selected_item);
        
        var desel_event = $.Event('fx_deselect');
        $node.trigger(desel_event);
        if (desel_event.isDefaultPrevented()) {
            return;
        }
        
        $node.parents().add($node).removeClass('fx_has_selection');
        
        $node.off('.fx_recount_outlines');
        
        $node.off('.fx_catch_mouseout');
        $fx.front.enable_hilight();
        $node.
                removeClass('fx_selected').
                removeClass('fx_hilight_hover').
                unbind('remove.deselect_removed');
        
        $fx.front.outline_block_off($node);
        
        $node.closest('.fx_entity_hidden-selected').removeClass('fx_entity_hidden-selected');
        this.node_panel.remove($node);
    }
    this.get_select_path_panel().hide();
    this.selected_item = null;
    $('html').off('.fx_selected');
    if (this.mouseover_node && !this.hilight_disabled) {
        $(this.mouseover_node).trigger('mouseover');
    }
};

fx_front.prototype.select_level_up = function() {
    var item_up = $fx.front.get_selectable_up();
    if (item_up) {
        $fx.front.select_item(item_up);
    }
};

fx_front.prototype.node_is_empty = function($n){
    var res = /^\s*$/.test($n.text())
                && !$n.is('img') 
                && $('img', $n).length === 0;
    if (res && !$n.is('.fx_area')) {
        
        var rect = $n[0].getBoundingClientRect();
        if (rect.width > 20 && rect.height > 20 && $n.html() !== '') {
            res = false;
        }
    }
    return res;
};


var fx_data_att_rex = /^data-(fx.+)$/;

function drop_node_fx_data_atts(node) {
    var drop = [];
    for (var i = 0; i < node.attributes.length; i++) {
        var att = node.attributes[i].name,
            m = att.match(fx_data_att_rex);
        if (!m) {
            continue;
        }
        $(node).data(m[1]);
        drop.push(att);
    }
    for (var i = 0; i < drop.length; i++) {
        node.removeAttribute(drop[i]);
    }
}

fx_front.prototype.collapse_hidden = function($container) {
    var $hide_empty = $container.descendant_or_self('.fx-hide-empty');
    $hide_empty = $($hide_empty.get().reverse());
    $hide_empty.each(function() {
        var $el = $(this),
            has_visible_children = false;
        $el.children().each(function() {
            if (!$(this).hasClass('fx_view_hidden')) {
                has_visible_children = true;
                return false;
            }
        });
        if (!has_visible_children) {
            $el.addClass('fx_view_hidden');
        }
    });
};

fx_front.prototype.hilight = function(container) {
    
    
    container = container || $('html');
    
    $('*[data-has_var_in_att="1"]', container).addClass('fx_template_var_in_att');
    
    fx_eip.collect_nodes(container);
    
    $fx.front.collect_adder_placeholders(container);
    
    var fx_selector = '.fx_template_var, .fx_area, .fx_template_var_in_att, .fx_entity, .fx_infoblock, '+
                        '.fx_hidden_placeholded, .fx_adder_placeholder_container, .fx-container';
    var items = $(fx_selector, container).not('.fx_unselectable');
    if (container.not('.fx_unselectable').is(fx_selector)) {
        items = items.add(container);
    }
    
    $fx.front.collapse_hidden(container);
    
    $('.fx_has_inline_adder', container)
        .off('.fx_recount_adders_entity')
        .off('.fx_recount_adders_infoblock')
        .removeClass('fx_has_inline_adder');
    
    items.
        off('.fx_recount_outlines').
        off('.fx_recount_adders').
        removeClass('fx_hilight').
        removeClass('fx_hilight_empty').
        removeClass('fx_hilight_empty_inline').
        removeClass('fx_var_bound_to_entity').
        removeClass('fx_no_hilight').
        //removeClass('fx_has_inline_adder').
        removeClass('fx_clearfix');
        //.removeClass('fx_placeholded_collection');
    if ($fx.front.mode === 'view' || container.is('html')) {
        $('.fx_inline_adder').remove();
        //items.removeClass('fx_accept_neighbours');
    }
    $('.fx_hilight_hover').removeClass('fx_hilight_hover');

    items.filter('.fx_hidden_placeholded').removeClass('fx_hidden_placeholded').each(function() {
        var $mark = $(this).find('.fx_hidden_placeholder_mark');
        if ($mark.length) {
            $mark.remove();
        } else {
            $(this).html('');
        }
    });
    
    
    var mode = $fx.front.mode;
    
    var noimg = '/vendor/Floxim/Floxim/Admin/style/images/no.png';
    items.filter('img').each(function() {
        var $img = $(this);
        var src = $img.attr('src');
        if (src.slice(src.length - noimg.length) === noimg) {
            if (mode === 'view') {
                $img.hide($img.hasClass('fx_image_placeholded') ? 200 : 0);
            } else {
                $img.show(200);
            }
            $img.addClass('fx_image_placeholded');
        }
    });
        
    if (mode === 'view') {
        $('.fx_infoblock_hidden').hide();
        $('.fx_entity_hidden').each(function() {
            $fx.front.outline_block_off($(this));
        });
        return;
    }
    
    $('.fx_infoblock_hidden').show();
    items = $(items.get().reverse());
    
    items.each(function(index, item) {
        
        // defined in the beginning of this file
        if (drop_fx_data_atts) {
            drop_node_fx_data_atts(item);
        }

        
        var i = $(item);
        var meta = i.data('fx_controller_meta') || {};
        
        if (meta.accept_content) {
            i.addClass('fx_accept_content');
        }
        
        i.addClass('fx_hilight');

        var hidden_placeholder = meta.hidden_placeholder;
        if ( ($fx.front.node_is_empty(i) || i.is('.fx_infoblock_hidden') ) ) {
            if ( i.hasClass('fx_template_var') ) {
                var var_meta = i.data('fx_var');
                hidden_placeholder = var_meta.placeholder || var_meta.label || var_meta.id;
                if (
                    var_meta.type === 'html' 
                    && !var_meta.linebreaks 
                    //&& var_meta.linebreaks !== ''
                ) {
                    hidden_placeholder = '<p>'+hidden_placeholder+'</p>';
                }
            } else if (
                i.is('.fx_infoblock, .fx_adder_placeholder_container')
                && !hidden_placeholder 
                && $('.fx_entity', i).length === 0
                && !i.is('.fx_entity')
            ) {
                hidden_placeholder = $fx.lang('This block is empty');
            }
        }
        if (hidden_placeholder) {
            var $adder_placeholder = null;
            var mark_tag = 'div',
                mark_colspan = null;
                
            var $placeholder_container = i.descendant_or_self('.fx_adder_placeholder_container'),
                $adder_placeholders = $placeholder_container.data('fx_contained_placeholders');
            if ($adder_placeholders && $adder_placeholders.length) {
                $adder_placeholder = $adder_placeholders.first();
            }
            if ($adder_placeholder) {
                //var $placeholded = $adder_placeholder.parent();
                var $placeholded = $placeholder_container;
                mark_tag = $adder_placeholder[0].nodeName;
                $placeholded.addClass('fx_placeholded_collection');
                if (mark_tag === 'TR') {
                    mark_colspan = $adder_placeholder.children().length;
                }
            } else {
                $placeholded = i;
            }
            if (!$placeholded.hasClass('fx_hidden_placeholded')) {
                $placeholded.addClass('fx_hidden_placeholded');
                var $children = $placeholded.children();
                if ( ($children.length || $adder_placeholder) && !$placeholded.hasClass('fx_template_var') ) {
                    if (mark_tag === 'TR') {
                        hidden_placeholder = '<td colspan="'+mark_colspan+'">'+hidden_placeholder+'</td>';
                    }
                    
                    var $hidden_placeholder = $('<'+mark_tag+' class="fx_hidden_placeholder_mark">'+hidden_placeholder+'</'+mark_tag+'>');
                    if ($children.length) {
                        $children.first().before($hidden_placeholder);
                    } else {
                        $placeholded.append($hidden_placeholder);
                    }
                } else {
                    $placeholded.html(hidden_placeholder);
                }
            }
        }
            
        if (i.is('.fx_area') && $fx.front.node_is_empty(i) && $('.fx_infoblock', i).length === 0)  {
            $fx.front.hilight_area_empty(i, 'add');
        }
    });

    container.descendant_or_self('.fx_adder_placeholder_container').each(function() {
        $fx.front.create_inline_entity_adder($(this));
    });
    
    if (mode !== 'view') {
        items.filter('.fx_area').each(function(index, i) {
            var $area = $(i);
            $fx.front.create_inline_infoblock_adder( $area);
        });
    }
    $('.fx_hilight_outline .fx_hilight').addClass('fx_hilight_outline');
};

//window.fx_no_hide=1;

fx_front.prototype.collect_adder_placeholders = function($container) {
    $container.find('.fx_entity_adder_placeholder').each(function(){
        var $placeholder = $(this),
            $parent = $placeholder.parent(),
            $placeholders = null;
        
        // this placeholder is already removed from dom on prev step
        if (!$parent.length) {
            return;
        }
        
        // entity and ib are on the same node
        if ($placeholder.is('.fx_infoblock')) {
            $parent = $('<div class="fx_pseudo_parent"></div>');
            $parent
                .addClass( $placeholder.attr('class') )
                .removeClass('fx_template_var fx_entity fx_sortable fx_entity_adder_placeholder fx_template_var_in_att')
                .data('fx_infoblock', $placeholder.data('fx_infoblock'));
            $placeholder.before($parent);
            $placeholders = $placeholder;
        } else {
            $placeholders = $parent.find('>.fx_entity_adder_placeholder');
        }
        var $existing_entity = $parent.find('>.fx_entity:not(.fx_entity_adder_placeholder)');
        
        if ( 
            (
                !$parent.is('.fx_no_add') 
                && !$existing_entity.is('.fx_infoblock.fx_no_add')
            ) 
            || $existing_entity.length === 0
        ) {
            $parent.find('>.fx_entity').addClass('fx_accept_neighbours');
            $parent.data('fx_contained_placeholders', $placeholders);
            $parent.addClass('fx_adder_placeholder_container');
            $placeholders.each(function() {
               this.$fx_placeholder_parent =  $parent;
            });
        }
        $placeholders.remove();
    });
};

fx_front.prototype.hilight_area_empty = function($area, scenario) {
    var a_meta = $area.data('fx_area'),
        placeholder_text = null;
    
    if (!a_meta) {
        console.log('no ameta', $area);
        console.trace();
        return;
    }
    var $area_placeholder = $('<span class="fx_area_placeholder"></span>');
    
    switch (scenario) {
        case 'add':
            placeholder_text = 'Area %s is empty, you can add some blocks here.';
                $area_placeholder.on(
                'click', 
                'a', 
                function() {
                    $fx.front.add_infoblock_select_controller($area);
                    return false;
                }
            );
            break;
        case 'place':
            placeholder_text = '%s: <a>put the block here</a>.';
            $area_placeholder.on(
                'click', 
                'a',
                function() {
                    $area_placeholder.remove();
                    $area.removeClass('fx_hidden_placeholded');
                    var $cutted_ib = $('.fx_infoblock_cutted');
                    $fx.front.place_block($cutted_ib, $area, 'into');
                }
            );
            break;
        case 'show':
            placeholder_text = 'Область &laquo;%s&raquo;:<br /> тут скоро будут блоки!'
            break;
    }
    placeholder_text = $fx.lang(placeholder_text)
        .replace(
            /\%s/, 
            a_meta.name ? a_meta.name : a_meta.id
        );
    
    $area_placeholder.html(placeholder_text);
    
    $area.find('>.fx_area_placeholder').remove();
    $area.append($area_placeholder);
    
    $area.addClass('fx_area_placeholded');
    $area.toggleClass('fx_hidden_placeholded', scenario !== 'show');

};

fx_front.prototype.get_list_orientation = function($entities) {
    var is_x = true,
        is_y = true,
        prev_rect = null,
        $clone = null;
    
    var $entities_visible = $entities.filter(':visible');
    
    if ($entities_visible.length === 0) {
        return null;
    }
    
    if ($entities_visible.length === 1) {
        var $c_entity = $entities_visible;
        if (!$c_entity.is('.fx_entity')) {
            //return null;
        }
        //$clone = $c_entity.clone(false);
        var tag = $c_entity[0].nodeName;
        $clone = $('<'+tag
                    +' class="'+$c_entity.attr('class')+'" '
                    +' style="width:250px; height:50px; opacity:0;">'+
                    '</'+tag+'>');
        $c_entity.after($clone);
        $entities_visible = $entities_visible.add($clone);
    } else {
        var $entities_sorted = $([]),
            $children = $entities_visible.first().parent().children();
        
        $children.each ( function() {
            if ( $(this).is($entities_visible) ) {
                $entities_sorted = $entities_sorted.add( $(this) );
            }
        });
        $entities_visible = $entities_sorted;
        
    }
    $entities_visible.each(function()  {
        var c_rect = this.getBoundingClientRect();
        if (prev_rect === null) {
            prev_rect = c_rect;
            return;
        }
        if (c_rect.left < prev_rect.right) {
            is_x = false;
        }
        if (c_rect.top < prev_rect.bottom) {
            is_y = false;
        }
        if (!is_x && !is_y) {
            return false;
        }
        prev_rect = c_rect;
    });
    var axis = is_x ? 'x' : is_y ? 'y' : null;
    if ($clone) {
        $clone.remove();
    }
    return axis;
};


fx_front.prototype.is_jquery_overriden = function() {
    // if jquery is overriden by template script (another version is used)
    // we will attach click listeners to each hilightable node
    // it is slower, but we can be relatively sure 
    // that the event will not be prevented by client script (our listener is attached later)
    return window.jQuery !== window.$fxj;
};

fx_front.prototype.load = function ( mode ) {
    if (typeof mode === 'undefined') {
        mode = $.cookie('fx_front_mode') || 'view';
    }
    var panel_height = $fx.front.get_panel_height();
    var $overlay = $('<div class="fx_switch_overlay"></div>').appendTo($('body'));
    $overlay.css({
        'z-index':100000,
        background:'#FFF',
        opacity:0.3,
        position:'fixed',
        top:panel_height,
        left:0,
        width:$(window).width(),
        height:$(window).height() - panel_height
    });
    
    $('body').removeClass('fx_mode_'+this.mode).addClass('fx_mode_'+mode);
    
    var $mode_buttons = $('.fx_front_mode');
    $mode_buttons.removeClass('fx_menu_item-active');
    $mode_buttons.filter('*[data-key="'+mode+'"]').addClass('fx_menu_item-active');
    
    this.mode = mode;
    $.cookie('fx_front_mode', mode, {path:'/'});
    
    $fx.front.outline_all_off();
    
    $fx.front.deselect_item();
    
    // remove floxim handlers
    if ($fx.front.is_jquery_overriden()) {
        $('.fx_hilight').unbind('.fx_front');
    }
    $('html').off('.fx_front');
    
    $fx.front.hilight();
    
    $fx.buttons.draw_buttons($fx.buttons_map.page);
    
    if (mode === 'view') {
        this.set_mode_view();
    } else {
        $('html').on('mousedown.fx_front', function(e) {
            $fx.front.last_mousedown_node = e.target;
        });
        $('html').on('click.fx_front', $fx.front.handle_click);
        $('html').on('mouseover.fx_front', '.fx_hilight', $fx.front.handle_mouseover);
        
        if (mode === 'edit') {
            this.set_mode_edit();
        } else {
            this.set_mode_design();
        }
        this.get_front_overlay().css('top', 0);
    }
      
    if ( $fx.settings.additional_text ) {
        $fx.draw_additional_text( $fx.settings.additional_text );
    }
    
    if ($fx.settings.additional_panel) {
        $fx.draw_additional_panel($fx.settings.additional_panel);
    }
    if (this.mouseover_node) {
        $(this.mouseover_node).trigger('mouseover');
    }
    $('html').trigger('fx_set_front_mode', this.mode);
    return new Promise(function(resolve) {
        $overlay.stop().animate(
            {
                opacity:0
            }, 
            600,
            null,
            function() {
                $overlay.remove();
                resolve();
            }
        );
    });
};

fx_front.prototype.redraw_form_field = function($field, new_json) {
    var $new_field = $fx.form.draw_field(new_json, $field.parent());
    $field.before($new_field);
    $field.remove();
    $fx.front.add_field_config_controls($new_field);
};

fx_front.prototype.add_field_config_controls = function($field) {
    var $controls = $(
        '<div class="fx_field_config_controls">'+
            '<div class="fx_icon fx_icon-type-edit"></div>'+
        '</div>'
    );
    $field.children().first().before($controls);
};

fx_front.prototype.make_content_form_editable = function($form) {
    return;
    var $header = $('.fx_admin_form__header', $form),
        $control = $('<div class="fx_form_config_start_icon fx_icon fx_icon-type-settings"></div>');
    
    $header.append($control);
    
    $control.click(function() {
        var config_class = 'fx_admin_form_configurable',
            $fields = $form.find('.field').not('.field_hidden'),
            $sortables = $('.fx_admin_form__body, .fx-field-group__fields', $form);
            
        if (!$form.hasClass(config_class)) {
            $form.addClass(config_class);
            $fields.each(function() {
                $fx.front.add_field_config_controls($(this));
            });
            $form.on('click.fx_field_config', '.fx_icon-type-edit', function(e) {
                var $label = $(this),
                    $field = $label.closest('.field'),
                    meta = $field.data('field_meta');

                $fx.front_panel.load_form({
                    entity:'component',
                    action:'edit',
                    menu_id:'component-'+meta.component_id,
                    params: [
                        meta.component_id,
                        'edit_field',
                        meta.field_id
                    ],
                    field_context:meta,  
                    form_context:'front',
                    fx_admin:true
                }, {
                    onfinish:function(data) {
                        if (data.new_json) {
                            $fx.front.redraw_form_field($field, data.new_json);
                        }
                    }
                });
            });
            var get_prev_id = function($field) {
                var $prev = $field.prev('.field'),
                    prev_id = null;
                    
                if ($prev.length > 0) {
                    var prev_meta = $prev.data('field_meta');
                    prev_id = prev_meta.field_id;
                }
                return prev_id;
            };
            $sortables.sortable({
                items: '>.field',
                axis:'y',
                tolerance:'pointer',
                start:function(e,ui) {
                    var $field = ui.item;
                    $field.data('prev_id', get_prev_id($field));
                    $form.addClass('fx-form_sorting-in-progress');
                },
                stop:function(e, ui) {
                    var $field = ui.item,
                        field_meta = $field.data('field_meta'),
                        prev_id = get_prev_id($field),
                        init_prev_id = $field.data('prev_id');
                    $field.data('prev_id', null);
                    
                    if (prev_id === init_prev_id) {
                        // field stayed on the same position
                        return;
                    }
                    var post_data = {
                        entity:'field',
                        id:field_meta.field_id,
                        action:'move',
                        sort_params: {
                            mode:'relative'
                        },
                        params: $.extend(
                            {}, 
                            field_meta//, 
                            //{group_id:group_id}
                        ),
                        move_after:prev_id
                    };
                    
                    $fx.post(post_data, function(res) {
                        $form.removeClass('fx-form_sorting-in-progress');
                    });
                }
            });
        } else {
            $form.removeClass(config_class).off('.fx_field_config');
            $sortables.sortable('destroy');
            $('.fx_field_config_controls', $form).remove();
        }
    });
};

fx_front.prototype.bind_content_form = function($form, content_type_id, content_id) {
    if (typeof content_id === 'undefined') {
        content_id = null;
    }
    
    var bound_hash = content_type_id+'-'+content_id;
    $form.attr('data-fx_bound', bound_hash);
    
    $form.on('change input', function(e) {
        
        var $inp = $(e.target);
        
        if ($inp.is('.fx_wysiwyg') && !e.isTrigger) {
            return;
        }
        
        var $bound = $inp.closest('[data-fx_bound]');
        
        
        if (!$bound.length || $bound.attr('data-fx_bound') !== bound_hash) {
            return;
        }
        if ($inp.is('.redactor-editor')) {
            $inp = $inp.parent().find('textarea[name]').first();
        }
        var inp_name = $inp.attr('name');
        
        if (!inp_name) {
            return;
        }
        
        var field_name = inp_name.match(/([^\[]+?)\]$/);
        
        field_name = field_name ? field_name[1] : inp_name;
        
        
        if (!field_name) {
            return;
        }
        fx_eip.set_value(content_type_id, content_id, field_name, $inp.val());
    });
};

fx_front.prototype.show_edit_form = function(params) {
    params = params || {};
    
    fx_eip.fix();
    
    var entity_id = params.content_id;
        
    var entity_values = fx_eip.get_values(entity_id);
    
    fx_eip.stop();
    
    params = $.extend(
        true,
        params, 
        {
            entity:'content',
            action:'add_edit',
            entity_values: entity_values
        }
    );

    $fx.front_panel.load_form(
        params, 
        {
            is_fluid: true,
            onready: function($form) {
                $fx.front.make_content_form_editable($form);
                fx_eip.stop();
                var response = $form.data('fx_response');
                $fx.front.bind_content_form($form, response.content_type_id, entity_id);
            },
            onsubmit: params.onsubmit || function() {},
            onfinish: function(res) {
                fx_eip.stop();
                if (params.onfinish) {
                    params.onfinish(res);
                }
                $fx.front_panel.hide();
            },
            oncancel: function() {
                if (params.oncancel) {
                    params.oncancel();
                }
            }
        }
    );
};

fx_front.prototype.get_edit_closure = function($entity, params) {
    params = params || {};
    return function() {
        var entity_meta = $entity.data('fx_entity'),
            ce_id = entity_meta[0],
            $ib_node = $entity.closest('.fx_infoblock'),
            entity_meta_props = $entity.data('fx_entity_meta'),
            placeholder_data = entity_meta_props ? entity_meta_props.placeholder : null,
            $stored_selected_node = $fx.front.get_selected_item();
    
        params.placeholder_linker = entity_meta_props ? entity_meta_props.placeholder_linker : null;
        
        params.content_type = entity_meta[1];
            
        if (ce_id) {
            params.content_id = entity_meta[0];
        } else if (placeholder_data) {
            params.entity_values = $.extend({}, placeholder_data);
            ['before','after'].forEach(function(v) {
                var k = '__move_'+v;
                if (typeof placeholder_data[k] !== 'undefined') {
                    params[k] = placeholder_data[k];
                    delete params.entity_values[k];
                }
            });
        }
        var preset_params = $ib_node.data('fx_preset_params');
        if (preset_params) {
            params.preset_params = JSON.stringify(preset_params);
        }
        
        if (!params.oncancel) {
            params.oncancel = function() {
                $entity.data('fx_has_full_form', false);
                $fx.front.deselect_item();
                $fx.front.select_item($stored_selected_node);
            };
        }
        
        if (!params.onfinish) {
            params.onfinish = function(res) {
                var ib_reload_params = {};
                if (res.real_infoblock_id) {
                    ib_reload_params.real_infoblock_id = res.real_infoblock_id;
                }
                $fx.front.reload_infoblock($ib_node, null, ib_reload_params);
            };
        }
        
        $fx.front.disable_node_panel();

        if ($entity[0] !== $fx.front.get_selected_item()) {
            $fx.front.select_item($entity);
        }
        
        $fx.front.show_edit_form(params);
    };
};

fx_front.prototype.is_equal_rect = function($node_a, $node_b) {
    var a = $node_a[0].getBoundingClientRect(),
        b = $node_b[0].getBoundingClientRect(),
        treshold = 10,
        res = true;
    $.each(['top', 'right', 'bottom', 'left'], function() {
        if (Math.abs( a[this] - b[this]) > treshold) {
            res = false;
            return false;
        }
    });
    return res;
};

fx_front.prototype.select_content_entity = function($entity, $from_field) {
    // if true, child field is actually selected
    $from_field = $from_field || false;
    
    var entity_meta = $entity.data('fx_entity'),
        $ib_node = $entity.closest('.fx_infoblock');

    $('html').one('fx_deselect', function(e) {
        $fx.front.outline_block_off($entity);
    });
    if ($from_field) {
        $fx.front.outline_block($entity, 'selected_light');
        return;
    }
    
    var panel_params = {};
    if ($from_field) {
        panel_params.offset = 7;
    }
    
    var entity_panel = $fx.front.node_panel.create($entity, panel_params);
    
    var ce_id = entity_meta[2] || entity_meta[0];
    
    
    if (ce_id) {
        var $entity_label = entity_panel.add_label( $entity.data('fx_entity_name') );
        $entity_label.addClass('fx_node_panel__item-button-label');
        
        $entity_label.click(function() {
            var $test = $('.floxim--ui--box--value__value', $entity).first();
            $fx.front.select_item($test);
        });
        
        entity_panel.add_button(
            {
                keyword:'edit',
                type:'icon'
            }, 
            $fx.front.get_edit_closure($entity)
        );

        entity_panel.add_button('delete', function() {
            var ce_type = entity_meta[3] || entity_meta[1];
            $fx.front_panel.load_form({
                entity:'content',
                action:'delete_save',
                content_type:ce_type,
                content_id:ce_id,
                page_id:$fx.front.get_page_id(),
                fx_admin:true
            }, {
                style:'alert',
                onfinish: function() {
                    fx_eip.stop();
                    $fx.front.reload_layout();
                }
            });
        });
        
        var publish_action = $entity.is('.fx_entity_hidden') ? 'publish' : 'unpublish';
        
        function icon_set($b, published) {
            if (published) {
                $b
                    .removeClass('fx_icon-type-publish')
                    .addClass('fx_icon-type-unpublish');
            } else {
                $b
                    .removeClass('fx_icon-type-unpublish')
                    .addClass('fx_icon-type-publish');
            }
        }
        
        var $publish_button =  entity_panel.add_button(
            publish_action,
            function(e) {
                var $b = $(e.target);
                var $publish_inp = $('input[type="checkbox"][name="is_published"]', entity_panel.$panel);
                if ($b.hasClass('fx_icon-type-publish')) {
                    icon_set($b, true);
                    $publish_inp[0].checked = true;
                    $publish_inp[0].setAttribute('checked', 'checked');
                } else {
                    icon_set($b, false);
                    $publish_inp[0].checked = false;
                    $publish_inp[0].removeAttribute('checked');
                }
            }
        );
        setTimeout(function() {
            var $ch = $('.field_name__is_published input[type="checkbox"]', entity_panel.$panel);
            if ($ch.length) {
                icon_set($publish_button.find('.fx_icon'), $ch[0].checked);
            }
        }, 10);
        
        var entity_url = $entity.data('fx_url');
        if (entity_url) {
            $fx.front.add_follow_button(entity_url);
        }
    }
    
    var $sortable_entities = $fx.front.get_sortable_entities($entity.parent());
    if ( $sortable_entities && ce_id ) {
        function get_next_id($entity) {
            var $next_entity = $entity.nextAll('.fx_entity').first(),
                next_id = null;
            if ($next_entity.length > 0) {
                var next_data = $next_entity.data('fx_entity');
                next_id = next_data[2] || next_data[0];
            }
            return next_id;
        }
        var current_next_id = get_next_id($entity);
        $fx.sortable.create({
            node:$entity,
            entities:$sortable_entities,
            onstart: function() {
                fx_eip.stop();
                $entity.trigger('fx_start_sorting');
                $fx.front.outline_block_off($entity);
                $fx.front.disable_node_panel();
                $fx.front.disable_hilight();
                $fx.front.outline_block_off( $($fx.front.get_selected_item()) );
                $fx.front.outline_block_off($entity.find('.fx_hilight_hover'));
            },
            onstop: function() {
                var data = $entity.data('fx_entity'),
                    id = data[2] || data[0],
                    type = data[3] || data[1];
            
                $entity.trigger('fx_stop_sorting');

                var next_id = get_next_id($entity);
                if (next_id === current_next_id) {
                    $fx.front.enable_node_panel();
                    $fx.front.enable_hilight();
                    return;
                }
                
                $fx.post({
                    entity:'content',
                    action:'move',
                    content_id:id,
                    content_type:type,
                    next_id:next_id
                }, function(res) {
                    $fx.front.reload_infoblock($ib_node);
                    $fx.front.enable_node_panel();
                    $fx.front.enable_hilight();
                });
                
            }
        });
    }
    
    if ($entity.is('.fx_template_var, .fx_template_var_in_att')) {
        $entity.edit_in_place();
    }
    
    if (!$from_field) {
        var $bound_to_edit = $fx.front.get_entity_bound_vars($entity);
        $bound_to_edit.edit_in_place();
    }
};

fx_front.prototype.get_entity_bound_vars = function($entity) {
    var $bound_to_edit = $([]),
        entity = $entity[0];
        
    $('.fx_template_var, .fx_template_var_in_att', entity).each(function() {
        var $bound = $(this);
        if (!$fx.front.is_var_bound_to_entity($bound)) {
            return;
        }
        if ($bound.closest('.fx_entity')[0] === entity) {
            $bound_to_edit = $bound_to_edit.add($bound);
        }
    });
    return $bound_to_edit;
};

fx_front.prototype.cached_style_variants = {};


fx_front.prototype.prepare_infoblock_visual_fields = function(all_props, callback) {
    function extend_with_variants(prop, variants) {
        if (prop.is_inline) {
            
            var c_value = prop.value,
                res = $.extend(true, {}, prop, variants);
            
            if (prop.style_context_params) {
                var filter_name = prop.block.replace(/\-\-/g, '_')+'_style_filter';
                if (window[filter_name]) {
                    var filter_res = window[filter_name](res.fields, prop.style_context_params, c_value);
                    if (typeof filter_res !== 'undefined') {
                        res.fields = filter_res;
                    }
                }
            }
            
            
            
            for (var i = 0; i < res.fields.length; i++) {
                var c_field = res.fields[i];
                if (typeof c_value[c_field.name] !== 'undefined') {
                    c_field.value = c_value[c_field.name];
                } else if (typeof c_field.default !== 'undefined') {
                    c_field.value = c_field.default;
                } else {
                    //c_field.value = undefined;
                    delete c_field.value;
                }
            }

            $.extend(
                res.tweaker, 
                {
                    style_id: prop.style_id,
                    style_class: prop.block + '_style_'+prop.mod_value
                }
            );
            $.extend(prop, res);
        } else {
            prop.type = 'livesearch';
            prop.values = variants;
            prop.allow_empty = false;
        }
    }
    
    return new Promise(
        function(resolve, reject) {
            var style_props = [];
            $.each(all_props, function(prop_type, props){
                if (!props) {
                    return;
                }
                $.each( props, function(prop_index) {
                    var is_arr = this instanceof Array,
                        prop = is_arr ? this[1] : this;
                        
                    if (prop.type === 'style') {
                        var c_block = prop.block;
                        if ($fx.front.cached_style_variants[c_block]) {
                            extend_with_variants(prop, $fx.front.cached_style_variants[c_block]);
                        } else {
                            style_props.push(prop);
                        }
                    }
                });
            });
            
            if (style_props.length === 0) {
                resolve(all_props);
                return;
            }

            $fx.post({
                entity:'layout',
                action:'get_style_variants',
                blocks: style_props
            }, function(res) {
                for (var i = 0; i < res.variants.length; i++) {
                    
                    var prop = style_props[i],
                        sp = $.extend(true, {}, prop),
                        variants = res.variants[i];
                    extend_with_variants(prop, variants);
                    $fx.front.cached_style_variants[prop.block] = variants;
                }
                resolve(all_props);
            });
        }
    );
};

fx_front.prototype.extract_infoblock_visual_fields = function($ib_node, $form) {
    
    if ($ib_node.is('.fx_infoblock_placeholder')) {
        return new Promise(function(resolve) {
            resolve();
        });
    }
    
    var types = ['template', 'wrapper'];
    
    var all_props = {};
    $.each(types, function(index, type) {
        var source_props = $ib_node.data('fx_'+type+'_params') || null;
        
        all_props[type] = [];
        if (!source_props) {
            return;
        }
        for (var i = 0; i < source_props.length; i++) {
            var c_prop = source_props[i];
            all_props[type].push(
                [
                    c_prop[0], 
                    $.extend(true, {}, c_prop[1])
                ]
            );
        }
    });
    
    var promise = this.prepare_infoblock_visual_fields(all_props);
    return promise.then(function(all_props) {
        $.each(all_props, function(type, props) {
            var field_class = 'fx_infoblock_'+type+'_param_field';
            $('.'+field_class, $form).remove();
            if (!props) {
                return;
            }
            var $rel_field = $(':input[name="visual['+type+']"]', $form).closest('.field'),
                cc = 'fx-template-visual-fields',
                cc_items = cc+'__items', 
                $container = $rel_field.parent().find('.'+cc_items);
                
            if ($container.length === 0) {
                $rel_field.after(
                    $(
                        '<div class="'+cc+' '+cc+'_type_'+type+'">'+
                            '<div class="'+cc_items+'"></div>'+
                        '</div>'
                    )
                );
                $container = $rel_field.parent().find('.'+cc_items);
            } else {
                $container.html('');
            }
            
            $.each(props, function() {
                var prop_name = this[0],
                    prop_data = this[1],
                    field_name = 'visual['+type+'_visual]['+prop_name.replace(/\./, '][')+']',
                    meta = $.extend(
                        true, 
                        prop_data, 
                        {
                            name:field_name,
                            view_context:'panel'
                        }
                    );
        
                if (meta.type === 'group' && meta.fields) {
                    for (var i = 0; i < meta.fields.length; i++) {
                        var cf = meta.fields[i];
                        cf.name = field_name+'['+cf.name+']';
                    }
                }

                if (meta.parent) {
                    if (typeof meta.parent === 'string') {
                        var obj = {};
                        obj[meta.parent] = '!=0';
                        meta.parent = obj;
                    }
                    var real_parent = {};
                    $.each(meta.parent, function(index, rule) {
                        real_parent['visual['+type+'_visual]['+index+']'] = rule;
                    });
                    meta.parent = real_parent;
                }
                //var $field = $fx.form.draw_field(meta, $rel_field, 'after');
                var $field = $fx.form.draw_field(meta, $container);
                $field.addClass(field_class);

                // show tab label if the tab contained no visible fields
                var $c_tab = $rel_field.closest('.fx_tab_data');
                if ($c_tab.length) {
                    var tab_key = $c_tab.attr('class').match(/fx_tab_data-key-(.+)$/)[1];
                    var $tab_label = $form.find('.fx_tab_label[data-key="'+tab_key+'"]');
                    $tab_label.show();
                }
                $rel_field = $field;
            });
        });
        $form.trigger('fx_infoblock_visual_fields_updated').resize();
    });
};

fx_front.prototype.hilight_empty_infoblock_areas = function($ib, mode) {
    var $areas = $('.fx_area_placeholded', $ib),
        ib = $ib[0];
    
    $areas.each(function() {
        var $a = $(this),
            $a_ib = $a.closest('.fx_infoblock');
            
        if (!$a_ib.length || $a_ib[0] !== ib) {
            return;
        }
        $fx.front.hilight_area_empty($a, mode);
    });
};

fx_front.prototype.edit_template_variant = function(template_ls) {
    var fields = [],

        c_value = template_ls.getFullValue(),
        c_variant = null,
        $form = template_ls.$node.closest('form'),
        ib_data = $form.formToHash();
        
    if (c_value.basic_template) {
        c_variant = c_value;
    }
    
    
    var params = {
        header: (c_variant ? 'Редактируем шаблон &laquo;' + c_variant.name+'&raquo;' : 'Создаем шаблон')
    };
   
    if (c_variant) {
        fields.push({
            type:'hidden',
            name: 'target_id',
            value: c_variant.id
        });
        
        fields.push({
            type:'checkbox',
            name:'save_as_new',
            label:'Сохранить как новый?'
        });
        
        if (c_variant.count_using_blocks > 0) {
            fields.push({
                name:'using_blocks_group',
                type: 'group',
                label: 'Где еще используется ('+c_variant.count_using_blocks+')',
                loader: function() {
                    return new Promise(function(resolve) {
                        $fx.post(
                            {
                                entity:'infoblock',
                                action:'get_template_variant_using_blocks',
                                template_variant_id: c_variant.id,
                                infoblock_id: ib_data.id
                            },
                            function(res) {
                                resolve(res);
                            }
                        );
                    });
                }
            });
        }
        
        params.form_button = [{key:'delete', class:'delete', label:'Удалить'}, 'save'];
    }

    fields.push({
        type:'string',
        label:'Название',
        name:'name'
    });
    
    /*
    fields.push({
        type:'checkbox',
        //type:'hidden',
        label:'Защищен от редактирования?',
        name:'is_locked',
        value: (c_variant ? c_variant.is_locked : 0)
    });
    */
   
    var template_variant_params = template_ls.template_variant_params || [];
    
    for (var i = 0 ; i < template_variant_params.length; i++) {
        var c_param_field = template_variant_params[i];
        if (c_variant && c_variant[c_param_field.name]) {
            var c_param_value = c_variant[c_param_field.name];
            if (c_param_field.name === "avail_for_type") {
                var c_value_found = false;
                for (var j =0 ; j < c_param_field.values.length; j++) {
                    if (c_param_field.values[j].id === c_param_value) {
                        c_value_found = true;
                        break;
                    }
                }
                if (!c_value_found) {
                    c_param_field.values.push({
                        id: c_param_value,
                        name: c_variant.target_type_name
                    });
                }
            }
            c_param_field.value = c_param_value;
        }
        fields.push(c_param_field);
    }
    
    var $area_inp = $form.find('input[name="area"]'),
        area_meta = JSON.parse($area_inp.val()),
        c_width = 'any';
    
    if (area_meta.size) {
        var area_width = area_meta.size.match(/wide|narrow/);
        if (area_width) {
            c_width = area_width[0];
        }
    }
    
    fields.push({
        label: 'Подходит для области',
        type: 'radio_facet',
        name: 'size',
        values: [
            ['any', 'Любая'],
            ['wide', 'Широкая'],
            ['narrow', 'Узкая']
        ],
        value: c_value.size || c_width
    });
    
    if (template_ls.template_type === 'template') {
        var c_wrapper_ls = $form.find('input[name="visual[wrapper]"]').closest('.livesearch').data('livesearch');
        if (c_wrapper_ls) {
            var bound_wrapper_values = [
                    {id:'', name:'- нет -'}
                ],
                c_bound_wrapper = c_variant && c_variant.wrapper_variant_id,
                c_selected_wrapper = c_wrapper_ls.getValue();
            
            c_wrapper_ls.traversePresetValues(function(v) {
                if (!v.basic_template) {
                    return;
                }
                if (v.id == c_bound_wrapper || v.id == c_selected_wrapper) {
                    v = $.extend(true, {}, v);
                    bound_wrapper_values.push(v);
                }
            });
            
            if (bound_wrapper_values.length > 1) {
                fields.push({
                    type:'livesearch',
                    label:'Связать с шаблоном блока',
                    name:'wrapper_variant_id',
                    values: bound_wrapper_values,
                    value: c_bound_wrapper
                });
            }
        }
    }

    params.fields = fields;

    $fx.front_panel.show_form(
        params, 
        {
            //style:'alert',
            onload: function($form) {
                if (!c_variant){ 
                    return;
                }

                var c_name = c_variant.real_name;

                var $name = $form.find('input[name="name"]');

                function handle_save_mode() {
                    var data = $form.formToHash(),
                        $del = $form.find('.fx_button_key_delete'),
                        $using = $form.find('.field_name__using_blocks_group');
                        
                    
                    if (data.save_as_new*1 === 1) {
                        $del.hide();
                        $using.hide();
                        var prev_name = $name.val();
                        if (prev_name && prev_name !== c_name) {
                            c_name = prev_name;
                        }
                        if (c_name === c_variant.real_name) {
                            $name.val('');
                        }
                    } else {
                        $del.show();
                        $using.show();
                        $name.val(c_name);
                    }
                };
                $name.on('focus', function() {
                    $name.select();
                });
                handle_save_mode();
                $form.on('change', function(e) {
                    if (e.target.name === 'save_as_new') {
                        handle_save_mode();
                    }
                });
            },
            onsubmit: function(e) {
                var $vform = $(e.target);
                var data = $vform.formToHash();

                save_template_variant(data, template_ls).then(function() {
                    $fx.front_panel.hide();
                });
                return false;
            }
        }
    );
};

function save_template_variant(data, template_ls) {
    var c_value = template_ls.getFullValue(),
        ib_data = template_ls.$node.closest('form').formToHash(),
        type = template_ls.template_type;

    $.extend(
        data, 
        {
            entity:'infoblock',
            action: 'save_template_variant',
            basic_template: c_value.basic_template || c_value.id,
            params : ib_data.visual[type+'_visual'],
            controller: ib_data.controller,
            infoblock_id: ib_data.id,
            area: JSON.parse(ib_data.area),
            template_type: type
        }
    );
    
    return new Promise(function(resolve, reject) {
        $fx.post(
            data,
            function(res) {
                var template_field = res.template_field;
                template_ls.updatePresetValues(template_field.values);
                template_ls.setValue(res.template_value);
                resolve();
            }
        );
    });
}

function is_template_variant_locked(template_ls) {
    var $form = template_ls.$node.closest('form'),
        unlocked = $form.data('unlocked_template_variants') || {},
        value = template_ls.getFullValue();
        
    if (!value || !value.basic_template || unlocked[value.id]) {
        return false;
    }
    
    return value.count_using_blocks*1 > 0; // || value.is_locked*1 === 1
}

function handle_template_lock_state(template_ls) {
    
    var c_value = template_ls.getFullValue();
    
    if (!template_ls || !c_value) {
        return;
    }
    
    var count = c_value.count_using_blocks,
        is_locked = is_template_variant_locked(template_ls),
        $field = template_ls.$node.closest('.field'),
        cl = 'fx-template-visual-fields',
        $container = $field.closest('.fx_tab_data').find('.'+cl),
        opts_cl = cl+'__unlock-options',
        template_type = template_ls.template_type,
        $form = $field.closest('form'),
        c_value  = template_ls.getFullValue();

    if (is_locked && !$container.find('.'+opts_cl).length) {
        var count_word = (count % 10 === 1 && count % 100 !== 11 ? 'месте' : 'местах');
        var $opts = $(
            '<div class="'+opts_cl+'">'+
                '<div class="'+cl+'__unlock-description">'+
                    '<p>Этот шаблон используется <a class="'+cl+'__unlock-get-using-blocks">еще в '+count+' '+count_word+'</a>.</p>'+
                    '<p>Если хотите его отредактировать, выберите, где применить изменения.</p>'+
                '</div>'+
                '<div class="'+cl+'__unlock-actions">'+
                    '<div class="'+cl+'__unlock-option" data-action="lock">'+
                        '<div class="fx_icon fx_icon-type-unlocked"></div>'+
                        '<span>Везде</span>'+
                    '</div>'+
                    '<div class="'+cl+'__unlock-option" data-action="copy">'+
                        '<div class="fx_icon fx_icon-type-place"></div>'+
                        '<span>Только здесь</span>'+
                    '</div>'+
                '</div>'+
            '</div>'
        );
        $opts.on('click', '[data-action]', function(e) {
            var action = $(this).data('action');
            switch (action) {
                case 'copy':
                    var c_data = $form.data('last_data');
                            
                    // change template prop in current data to force visual props sending 
                    c_data.visual[template_type] = c_value.basic_template;
                    template_ls.setValue(c_value.basic_template);
                    break;
                case 'lock':
                    template_ls.$node.find('.monosearch__item-controls').show();
                    $container.toggleClass('fx-template-visual-fields_locked', false);
                    var unlocked = $form.data('unlocked_template_variants') || {};
                    unlocked[c_value.id] = true;
                    $form.data('unlocked_template_variants', unlocked);
                    break;
            }
        }).on('click', '.'+cl+'__unlock-get-using-blocks', function() {
            var ib_data = $form.formToHash();
                
            $fx.post(
                {
                    entity:'infoblock',
                    action:'get_template_variant_using_blocks',
                    template_variant_id: c_value.id,
                    infoblock_id: ib_data.id
                },
                function(res) {
                    $fx.front_panel.show_form(
                        {
                            header: 'Где еще используется шаблон',
                            fields: res.fields,
                            form_button: [{key:'cancel',label:'Понятно!'}]
                        },
                        {
                            style:'alert'
                        }
                    );
                }
            );
        });
        $container.append($opts);
    }
    $container.toggleClass('fx-template-visual-fields_locked', is_locked);
}

function add_template_variant_controls(template_ls) {
    var value = template_ls.getFullValue(),
        $form = template_ls.$node.closest('form');
    
    if (!value || value.id === '') {
        return;
    }
    var is_locked = is_template_variant_locked(template_ls),
        is_preset = !!value.basic_template,
        template_type = template_ls.template_type;

    if (is_preset && is_locked) {
        //return;
    }

    if (is_preset) {
        template_ls.addValueControl({
            icon: 'place',
            action: function(c_value) {
                var c_data = $form.data('last_data');

                // change template prop in current data to force visual props sending 
                c_data.visual[template_type] = c_value.basic_template;
                template_ls.setValue(c_value.basic_template);
            }
        });
    }

    template_ls.addValueControl({
        icon: is_preset ? 'edit' : 'add-round',
        action: function() {
            $fx.front.edit_template_variant(
                template_ls
            );
        }
    });

    if (is_locked) {
        template_ls.$node.find('.monosearch__item-controls').hide();
    }
}

fx_front.prototype.show_infoblock_settings_form = function(data, $ib_node, tab) {
    tab = tab || 'settings';
    var has_changes = false,
        is_new = $ib_node.is('.fx_infoblock_fake');

    $fx.front_panel.show_form(data, {
        view:'horizontal',
        active_tab: tab,
        onfinish:function(res, $form) {
            $ib_node = $form && $form.data('ib_node');
            if (!$ib_node || !$ib_node.length) {
                console.log('no ibnod');
                return;
            }
            if (!is_new) {
                $fx.front.reload_infoblock($ib_node);
                return;
            }
            
            if (!res.props || !res.props.infoblock_id) {
                return;
            }
            $fx.front.reload_infoblock(
                $ib_node,
                function($new_ib_node) {
                    setTimeout(function() {
                        var $adder = $new_ib_node.find('.fx_adder_variant');
                        if ($adder.length === 1) {
                            $adder.click();
                        }
                    }, 50);
                },
                {
                    real_infoblock_id:res.props.infoblock_id
                }
            );
        },
        onload: function($form) {
            $form.data('ib_node', $ib_node);
            $fx.front.hilight_empty_infoblock_areas($ib_node, 'show');
            
            var template_types = ['template', 'wrapper'],
                template_inputs = {};
            
            $.each(template_types, function(i, tt) {
                var $ls = $('input[name="visual['+tt+']"]', $form).closest('.livesearch'),
                    ls = $ls.data('livesearch');
                template_inputs[tt] = {
                    $input: $ls,
                    livesearch: ls
                };
                
                ls.template_type = tt;
                ls.bindValueControls(add_template_variant_controls);
            });
            
            return $fx.front.extract_infoblock_visual_fields($ib_node, $form)
            .then( function() {
                
                $.each(template_inputs, function(type, inputs) {
                    handle_template_lock_state(inputs.livesearch);
                });
                
                $form.data('last_data', $form.formToHash());
                var update_timeout = null;

                $form.on('change.fx_front', function(e) {
                    if (e.target.name === 'livesearch_input' || e.target.name === 'scope[type]') {
                        return;
                    }
                    
                    var new_data = $form.formToHash(),
                        c_data = $form.data('last_data');
                        
                    if (e.target.name === 'visual[template]') {
                        var c_template_data = $(e.target)
                                                .closest('.livesearch')
                                                .data('livesearch')
                                                .getFullValue();
                                        
                        if (c_template_data.wrapper_variant_id) {
                            new_data.visual.wrapper = c_template_data.wrapper_variant_id;
                            $form
                                .find('[name="visual[wrapper]"]')
                                .closest('.livesearch')
                                .data('livesearch')
                                .setValue(c_template_data.wrapper_variant_id, true);
                        }
                    }
                    
                    for (var i = 0; i < template_types.length; i++) {
                        var tt = template_types[i];

                        // поменяли шаблон
                        if (new_data.visual[tt] != c_data.visual[tt]) {
                            delete new_data.visual[tt+'_visual'];
                        }
                    }

                    var focused_name = $(document.activeElement).descendant_or_self(':input[name]').attr('name');

                    clearTimeout(update_timeout);
                    
                    update_timeout = setTimeout(
                        function() {
                            $form.data('last_data', new_data);
                            $fx.front.reload_infoblock(
                                $form.data('ib_node'), 
                                function($new_ib_node) {
                                    has_changes = true;
                                    $form.data('ib_node', $new_ib_node);
                                    $fx.front.extract_infoblock_visual_fields($new_ib_node, $form).then(function() {

                                        if (!$fx.front.get_selected_item()) {
                                            $fx.front.select_item($new_ib_node);
                                        }
                                        
                                        $.each(template_inputs, function(type, inputs) {
                                            handle_template_lock_state(inputs.livesearch);
                                        });
                                        
                                        if (focused_name) {
                                            var $focused_input = $form.find('[name="'+focused_name+'"]');
                                            if ($focused_input.attr('type') === 'hidden') {
                                                $focused_input = $focused_input.closest('[tabindex]');
                                            }
                                            $focused_input.focus();
                                        }
                                    });
                                    $fx.front.hilight_empty_infoblock_areas($new_ib_node, 'show');
                                }, 
                                {override_infoblock:new_data}
                            );
                        },
                        150
                    );
                });
                if (is_new) {
                    $form.trigger('change');
                }
                
            });
        },
        oncancel:function($form) {
            var $ib_node = $form.data('ib_node');
            if (is_new) {
                if ($ib_node) {
                    $fx.front.deselect_item();
                    var $area = $ib_node.closest('.fx_area');
                    $ib_node.remove();
                    if ($area.children().length === 0) {
                        $fx.front.hilight_area_empty($area, 'add');
                    }
                }
                return;
            }
            if (has_changes) {
                $fx.front.reload_infoblock($ib_node);
                return;
            }
            if ($ib_node && $ib_node.length) {
                $fx.front.hilight_empty_infoblock_areas($ib_node, 'add');
            }
        }
    });
};

fx_front.prototype.show_infoblock_settings = function($ib_node, tab) {
    var ib_meta = $ib_node.data('fx_infoblock') || {};

    var $area_node = $fx.front.get_area_node($ib_node);//ib_node.closest('.fx_area');
    var area_meta = $fx.front.get_area_meta($area_node);

    $fx.post(
        {
            entity:'infoblock',
            action:'select_settings',
            id:ib_meta.id,
            page_id: $fx.front.get_page_id(),
            area:area_meta
        }, 
        function(res) {
            $fx.front.show_infoblock_settings_form(res, $ib_node, tab);
        }
    );
};

fx_front.prototype.select_infoblock = function($node, panel) {
    if ($fx.front.mode === 'edit') {
        $node.edit_in_place();
    }
    
    if (!panel) {
        panel = $fx.front.node_panel.get();
    }
    
    if ($node.is('.fx_entity')) {
        var $block_label = panel.add_label( 'Блок' );
            $block_label.addClass('fx_node_panel__item-button-label');
    }

    panel.add_button('settings', function() {
        $fx.front.show_infoblock_settings($node);
    });
    
    panel.add_button('design', function() {
        $fx.front.show_infoblock_settings($node, 'design');
    });
    
    panel.add_button("container", function() {
        $fx.front.show_infoblock_settings($node, 'wrapper');
    });
    
    panel.add_button({
        type:'icon',
        keyword:'place',
        label:$fx.lang('Move')
    }, function() {
        $fx.front.start_placing_block($node);
    });
    
    panel.add_button('delete', function() {
        var ib_node = $fx.front.get_selected_item();
        if (!ib_node) {
            return;
        }
        var ib = $(ib_node).data('fx_infoblock');
        if (!ib) {
            return;
        }
        $fx.front_panel.load_form({
            entity:'infoblock',
            action:'delete_infoblock',
            id:ib.id,
            fx_admin:true
        }, {
            style:'alert',
            onfinish: function() {
                $fx.front.reload_layout();
            }
        });
    });
};

fx_front.prototype.start_placing_block = function($ib_node){
    $fx.front.deselect_item();
    $fx.front.disable_hilight();
    var $areas = $('.fx_area');
    
    $areas.filter('.fx_hidden_placeholded').each(function() {
        $fx.front.hilight_area_empty($(this), 'place');
    });
    
    $ib_node.addClass('fx_infoblock_cutted');
    $areas.each(function() {
        var $area = $(this);
        $fx.front.create_inline_infoblock_placer($area, $ib_node);
    });
    var ib_meta = $ib_node.data('fx_infoblock');
    var $panel = $(
        '<div class="fx_placer_panel">'+
            '<div class="fx_placer_panel__body">'+
                '<div class="fx_placer_panel__block fx_placed_block_symbol">'+
                    '<div class="fx_placed_block_symbol__icon"></div>'+
                    '<div class="fx_placed_block_symbol__title">'+
                        '<div class="fx_placed_block_symbol__type">'+
                            'Перемещаем блок'+
                        '</div>'+
                        '<div class="fx_placed_block_symbol__label">'+
                            ib_meta.name+
                        '</div>'+
                    '</div>'+
                '</div>'+
                '<div class="fx_placer_panel__description">'+
                    $fx.lang('placer_panel_description')+
                '</div>'+
            '</div>'+
            //'<div class="fx_closer">&times;</div>'+
            '<div class="fx_placer_panel__closer">'+
                '<div class="fx_button fx_button-class-cancel">'+$fx.lang('cancel')+'</div>'+
            '</div>'+
        '</div>');
    $('.fx-admin-panel').append($panel);
    
    $panel.find('.fx_placed_block_symbol').click(function() {
        $fx.front.scrollTo($ib_node);
    });
    $('.fx_placer_panel__closer', $panel).click(function() {
        $fx.front.stop_placing_block($ib_node);
    });
    $('body').on('keyup.fx_stop_placing_block', function(e) {
        if (e.which === 27) {
            $fx.front.stop_placing_block($ib_node);
        }
    });
};

fx_front.prototype.stop_placing_block = function($ib_node) {
    $ib_node.removeClass('fx_infoblock_cutted');
    $fx.front.destroy_all_infoblock_placers();
    $fx.front.enable_hilight();
    $('.fx_area.fx_hidden_placeholded').each(function() {
        $fx.front.hilight_area_empty($(this), 'add');
    });
    $('body').off('.fx_stop_placing_block');
    $('.fx_placer_panel').remove();
};

fx_front.prototype.get_infoblock_place_params = function($ib_node) {
    var params = {
        area:$ib_node.closest('.fx_area').data('fx_area').id
    };
    var $ib_container = $ib_node.parent().closest('.fx_infoblock_wrapper, .fx_infoblock');
    if ($ib_container.length === 0 || $ib_container.is('.fx_infoblock')) {
        $ib_container = $ib_node;
    }
    var $next_ib = $ib_container.next();
    if (!$next_ib.is('.fx_infoblock')) {
        $next_ib = $next_ib.find('.fx_infoblock').first();
    }
    if ($next_ib.length > 0) {
        var next_data = $next_ib.data('fx_infoblock');
        params.next_infoblock_id = next_data.id;
        params.next_visual_id = next_data.visual_id;
    }
    return params;
};

fx_front.prototype.place_block = function($ib_node, $rel_node, dir) {
    var $old_area = $ib_node.closest('.fx_area');
    if (dir === 'before') {
        $rel_node.before($ib_node);
    } else if (dir === 'after') {
        $rel_node.after($ib_node);
    } else if (dir === 'into') {
        $rel_node.append($ib_node);
    }
    // it was the only ib in it's area
    if ($('.fx_infoblock', $old_area).length === 0) {
        $fx.front.hilight_area_empty($old_area, 'place');
    }
    
    $fx.front.select_item($ib_node);
    
    var ib_data = $ib_node.data('fx_infoblock'),
        params = {
            entity:'infoblock',
            action:'move'
        };

    params.infoblock_id = ib_data.id;
    params.visual_id = ib_data.visual_id;
    
    params = $.extend(params, $fx.front.get_infoblock_place_params($ib_node));
    
    $fx.post(params, function(res) {
        $fx.front.reload_layout();
    });
    
    this.stop_placing_block($ib_node);
};

fx_front.prototype.set_mode_view = function () {
    
};

fx_front.prototype.get_sortable_entities = function($cp) {
    var sortable_items_selector = ' > .fx_entity.fx_sortable.fx_hilight';
    var $entities = $(sortable_items_selector, $cp);
    if ($entities.length < 2 || $cp.hasClass('fx_not_sortable')) {
        return false;
    }
    return $entities;
};

fx_front.prototype.set_mode_edit = function () {
    $fx.panel.one('fx.startsetmode', function() {
        $('html').off('.fx_edit_mode');
    });
};

fx_front.prototype.set_mode_design = function() {
    $fx.panel.one('fx.startsetmode', function() {
        $('html').off('.fx_design_mode');
    });
};

fx_front.prototype.disabled_infoblock_opacity = 0.8;

fx_front.prototype.disable_infoblock = function(infoblock_node) {
    $(infoblock_node)
        .on('click.fx_fake_click', function() {
            return false;
        })
        .animate({opacity:$fx.front.disabled_infoblock_opacity}, 150)
        .addClass('fx_infoblock_disabled');
};

fx_front.prototype.enable_infoblock = function(infoblock_node) {
    $(infoblock_node)
        .off('click.fx_fake_click')
        .css('opacity', '')
        .removeClass('fx_infoblock_disabled');
};

fx_front.prototype.get_content_parent_props = function($n) {
    var res = {},
        $pars = $n.parents('.fx-block');
    
    $.each($pars, function() {
        var mods = $fx.front.get_modifiers($(this), 'fx-block');
        if (mods.rw && !res.rw) {
            res.rw = mods.rw;
        }
        $.each(mods, function( k, v) {
            var p = k.match(/own-(.+)/);
            if (!p) {
                return;
            }
            p = p[1];
            if (typeof res[p] === 'undefined') {
                res[p] = v;
            }
        });
    } );
    return res;
};

fx_front.prototype.reload_infoblock = function(infoblock_node, callback, extra_data) {
    var $infoblock_node = $(infoblock_node),
        content_parent_props = $fx.front.get_content_parent_props($infoblock_node);

    infoblock_node = $infoblock_node[0];
    
    $fx.front.disable_infoblock(infoblock_node);
    
    var ib_parent = $infoblock_node.parent();
    var meta = $infoblock_node.data('fx_infoblock');
    var page_id = $fx.front.get_page_id();
    var post_data = {
        _ajax_base_url: $infoblock_node.data('fx_ajax_base_url') || document.location.href,
        content_parent_props: JSON.stringify(content_parent_props)
    };
    extra_data = extra_data || {};
    if ($infoblock_node.is('body') && extra_data.infoblock_is_layout === undefined) {
        extra_data.infoblock_is_layout = true;
    }
    
    $.extend(post_data, extra_data);
    
    if (!meta ) {
        console.log('nometa', infoblock_node);
        console.trace();
        return;
    }
    var selected = $infoblock_node.descendant_or_self('.fx_selected');
    var selected_selector = null;
    if(selected.length > 0) {
         selected_selector = selected.first().generate_selector(ib_parent);
    }
    var real_infoblock_id = (extra_data || {}).real_infoblock_id || meta.id || 'fake';
    
    var xhr = $.ajax({
        type:'post',
        data:post_data,
        url: document.baseURI+'~ib/'+real_infoblock_id+'@'+page_id,
        success:function(res) {
            $fx.front.c_hover = null;
            $infoblock_node.off('click.fx_fake_click');
            $fx.front.deselect_item();

            var is_layout = infoblock_node.nodeName === 'BODY';

            $('.fx_entity_hidden', $infoblock_node).each(function() {
                $(this).removeClass('fx_entity_hidden');
                $fx.front.outline_block_off($(this)); 
            });

            if (is_layout) {
                $fx.front.front_overlay = null;
                var inserted = false,
                    $res = $(res),
                    res_root = $res[0];


                $infoblock_node.trigger('fx_infoblock_unloaded');
                $infoblock_node.children().each(function() {
                    var $child = $(this);
                    if(!$child.hasClass('fx_overlay')) {
                        if (!inserted) {
                            $child.before( $res.html() );
                            inserted = true;
                        }
                        $child.remove();
                    }
                });
                var $new_infoblock_node = $('body');
                for (var i = 0; i < res_root.attributes.length; i++) {
                    var att = res_root.attributes[i],
                        data_prop = att.name.match(/data-(.+)/),
                        data_val;

                    $new_infoblock_node.attr(att.name, att.value);
                    if (!data_prop) {
                        continue;
                    }
                    data_prop = data_prop[1];
                    try {
                        data_val = JSON.parse(att.value);
                    } catch (e) {
                        data_val = att.value;
                    }
                    $new_infoblock_node.data(data_prop, data_val );
                }
                $new_infoblock_node
                    .removeClass('fx_infoblock_disabled')
                    .addClass('fx_mode_'+$fx.front.mode);
            } else {
                var $new_infoblock_node = $( $.trim(res) );
                if ($new_infoblock_node.length > 0) {
                    $new_infoblock_node = $new_infoblock_node.filter('.fx_infoblock').first();
                }
                $infoblock_node.hide();
                $infoblock_node.before($new_infoblock_node);
                $infoblock_node.trigger('fx_infoblock_unloaded', [$new_infoblock_node]);
                $infoblock_node.remove();
            }

            if (is_layout) {
                $fx.front.move_down_body();
            }
            $fx.front.hilight($new_infoblock_node);
           
            $new_infoblock_node[0].setAttribute('data-fx_block_is_pending', '1');
            
            var finish = function() {
                
                $new_infoblock_node[0].removeAttribute('data-fx_block_is_pending');
                $new_infoblock_node.trigger('fx_infoblock_loaded');
            
                $new_infoblock_node.css('opacity', $fx.front.disabled_infoblock_opacity).animate({opacity: 1},150);
                $('body').removeClass('fx_stop_outline');
                if (selected_selector) {
                    var sel_target = ib_parent.find(selected_selector);
                    if (sel_target.length > 0) {
                        sel_target = sel_target.get(0);
                        if (!$fx.front.is_selectable(sel_target)) {
                            sel_target = $fx.front.get_selectable_up(sel_target);
                        }
                        $fx.front.select_item(sel_target);
                    }
                }

                if (typeof callback === 'function') {
                    callback($new_infoblock_node);
                }
            };
            
            if (typeof this.pending_scripts_loaded  === 'object' && this.pending_scripts_loaded instanceof Promise) {
                this.pending_scripts_loaded.then(finish);
            } else {
                finish();
            }
       }
    });
    return xhr;
};

fx_front.prototype.is_fixed = function($node){
    var $parents = $node.parents();
    for (var i = $parents.length - 1; i >= 0; i--) {
        if ($parents.eq(i).css('position') === 'fixed') {
            return true;
        }
    }
    return false;
};

fx_front.prototype.scrollTo = function($node, if_invisible, callback) {
    
    // if the whole node is invisible, do nothing
    if (!$node.is(':visible')) {
        if (callback instanceof Function) {
            callback();
        }
        return;
    }
    // scroll only when part of the node is out of screen
    if (if_invisible === undefined) {
        if_invisible = false;
    }
    $node = $($node);
    if ($node.length === 0) {
        return;
    }
    if ($fx.front.is_fixed($node)) {
        return;
    }
    //var body_offset = parseInt($('body').css('margin-top'));
    var body_offset = parseInt($('body').css('padding-top'));
    var top_offset = $node.offset().top - body_offset - 50;
    var move = true;
    var st = $(document).scrollTop();
    if (if_invisible){
        move = false;
        if (st > top_offset) {
            if (!$node.hasClass('fx_area')) {
                move = true;
            }
        } else {
            var wh = $(window).height();
            if (st + wh < top_offset) {
                move = true;
            } else {
                var nh = $node.outerHeight();
                if (st + wh < top_offset + nh) {
                    move = true;
                }
            }
        }
    }
    
    if (move) {
        var distance = Math.abs(st - top_offset);
        var speed = distance*2;
        if (speed > 800) {
            speed = 800;
        }
        $('body')
            //.stop()
            .scrollTo(
                {
                    top:top_offset,
                    left:0
                },
                speed
            );
        
        if (callback instanceof Function) {
            setTimeout(callback, speed);
        }
    }
};

fx_front.prototype.reload_layout = function(callback) {
   $fx.front.reload_infoblock($('body').get(0), callback, {infoblock_is_layout:true});
};

fx_front.prototype.get_panel_height = function() {
    return $('.fx-admin-panel').outerHeight();
};

fx_front.prototype.move_down_body = function () {
    var panel_height = this.get_panel_height();
    //$('body').css('margin-top', '').css('margin-top','+='+panel_height+'px');
    $('body').css('padding-top', '').css('padding-top','+='+panel_height+'px');
    $('.fx_top_fixed').css('top', '+='+panel_height+'px').css('z-index', 2674);
};

fx_front.prototype.get_node_panel = function($node) {
    if (!$node) {
        $node = $($fx.front.get_selected_item());
    }
    return this.node_panel.get($node).$panel;
};

fx_front.prototype.create_button = function(button, callback) {
    if (typeof button === 'string') {
        button = {
            type:'icon',
            keyword:button
        };
    } else if (!callback) {
        callback = button.callback;
    }
    /*
    if (!callback && button.href) {
        callback = function() {
            document.location.href = button.href;
        };
    }
    */
   
    if (!button.type) {
        button.type = 'button';
    }
    
    var node_name = button.href ? 'a' : 'div';
    
    var $node = $('<'+node_name+' class="' + (button.in_dropdown ? '' : 'fx_node_panel__item fx_node_panel__item-type-'+button.type)+'"></'+node_name+'>');
    if (button.href) {
        $node.attr('href', button.href);
    }
    if (button.type === 'icon') {
        var $b = $('<div class="fx_icon fx_icon-type-'+button.keyword+'"></div>');
    } else {
        var $b = $('<div class="fx_button fx_button-active fx_button-in_node_panel">'+button.name+'</div>');
        if (button.dropdown) {
            $b.append('<div class="fx_button__arrow"></div>');
            $b.addClass('fx_button-has_dropdown');
            var $dropdown = $('<div class="fx_dropdown"></div>');
            $b.append($dropdown);
            for (var i = 0; i < button.dropdown.length; i++){
                var button_info = button.dropdown[i];
                button_info.name = button_info.name.replace(new RegExp( $fx.lang('Add')), '');
                button_info.in_dropdown = true;
                var $button = $fx.front.create_button(button.dropdown[i]);
                $('.fx_button__add_text', $button).removeClass('fx_button__add_text');
                $dropdown.append($button);
            }
        }
    }
    
    if (button.is_add) {
        $b.addClass('fx_button-add');
    }
    $node.append($b);
    
    if (button.type === 'icon' && button.label) {
        $node.append('<span class="fx_node_panel__item_label">'+button.label+'</span>');
    }
    
    if (button.title) {
        $node.attr('title', button.title);
    }
    
    if (!button.dropdown && callback) {
        $node.on('click', callback);
    }
    return $node;
};

fx_front.prototype.add_panel_button = function(button, callback) {
    var $p = this.get_node_panel();
    if (!$p || $p.length === 0) {
        return;
    }
    var $b = this.create_button(button, callback);
    $p.append($b).show();
    return $b;
};

fx_front.prototype.outline_panes = [];


fx_front.prototype.get_front_overlay = function() {
    if (!this.front_overlay) {
        this.front_overlay = $(
            '<div class="fx_front_overlay"></div>'
        );
        $('body').append(this.front_overlay);
    }
    return this.front_overlay;
};

fx_front.prototype.get_panel_z_index = function() {
    if (typeof this.panel_z_index === 'undefined') {
        this.panel_z_index = $('#fx_admin_control').css('z-index') - 10;
    }
    return this.panel_z_index;
};

fx_front.prototype.get_overlay_z_index = function($n) {
    var c_zindex = this.get_panel_z_index() + 1;
    $n.parents().each(function() {
        var $p = $(this);
        if ($p.css('position') === 'fixed') {
            c_zindex = $p.css('z-index');
        }
    });
    return c_zindex;
};


/**
 * Hide outline pane if the cursor comes very close to it to prevent mouseout unexpected by template js
 * @todo we should cache visible panes with their positions
 */
fx_front.prototype.hide_hover_outlines = function(pos) {
    var $panes = $('body>.fx_front_overlay .fx_outline_style_hover');
    var threshold = 1;
    $panes.each(function(i, p) {
        var $p = $(p),
            offset = $p.offset(),
            top = offset.top - threshold,
            left = offset.left - threshold,
            bottom = top + $p.height() + threshold*2,
            right = left + $p.width() + threshold*2;
        if (pos.top > top && pos.top < bottom && pos.left > left && pos.left < right) {
            //$p.css('outline', '3px solid #FF0');
            $p.css('visibility', 'hidden');
        } else {
            //$p.css('outline', 'none');
            $p.css('visibility', 'visible');
        }
    });
};

fx_front.prototype.outline_block = function(n, style, speed) {
    if (!style) {
        style = 'hover';
    }
    
    if (style === 'hover') {
        //return;
    }
    
    var styles = {
        hover: {
            size:1,
            recount:false
        },
        hidden: {
            size:2
        },
        selected: {
            size:2
        },
        selected_light: {
            size:1,
            offset_x:0, // 6 for visible
            offset_y:0
        }
    };
    
    var params = $.extend(
        {
            recount:true,
            size:2,
            offset_x:2,
            offset_y:0
        }, 
        styles[style]
    );
    
    if (!n || n.length === 0) {
        return;
    }
    if (n.hasClass('fx_hilight_outline')) {
        return;
    }
    if (n.hasClass('fx_entity_hidden') && style === 'hover') {
        return;
    }
    var panes = n.data('fx_outline_panes') || {};
    
    
    
    //if (style === 'selected' || style === 'hidden' || style === 'selected_light') {
    if (params.recount ) {
        var recount_outlines = function(e) {
            $fx.front.outline_block(n, style);
            $fx.front.recount_node_panel();
        };
        n.off('.fx_recount_outlines').on(
            'resize.fx_recount_outlines',// keydown.fx_recount_outlines', 
            recount_outlines
        );
    }
    
    if (!n.is(':visible')) {
        $.each(panes, function() {
            $(this).css('left', '-100000px');
        });
        return;
    }
    
    var o = n.offset();
    var overlay_offset = parseInt(this.get_front_overlay().css('top'));
    o.top -= overlay_offset > 0 ? overlay_offset : 0 ;
    var nw = n.outerWidth() + 1;
    var nh = n.outerHeight();
    //var size = style === 'hover' ? 1 : 2;
    var size = params.size;
    var pane_z_index = $fx.front.get_panel_z_index();
    var parents = n.parents();
    var pane_position = 'absolute';
    if (n.css('position') === 'fixed') {
        pane_position = 'fixed';
    }
    var fixed_found = false, overflow_found = false;
    for (var i = 0 ; i<parents.length; i++) {
        var $cp = parents.eq(i);
        if (pane_position !== 'fixed' && $cp.css('position') === 'fixed') {
            pane_position = 'fixed';
            if ($cp.css('z-index') !== undefined) {
                pane_z_index = $cp.css('z-index');
                n.data('fx_z_index', pane_z_index);
            }
            fixed_found = true;
        }
        if ($cp.css('overflow') === 'hidden') {
            var cph = $cp.outerHeight();
            if (cph < nh) {
                n.data('fx_visible_height', cph);
                nh = cph;
            }
            overflow_found = true;
        }
        if (fixed_found && overflow_found) {
            break;
        }
    };
    var doc_width = $(document).width(),
        doc_height = $(document).height(),
        front_overlay = $fx.front.get_front_overlay(),
        overlay_offset = parseInt(front_overlay.css('top'));
    
    var draw_lens = (style === 'selected_light') || (style === 'selected' && n.is('.fx_entity'));
    
    //draw_lens = false;
    
    if (draw_lens) {
        n.data('fx_has_lens', true);
        $.each(panes, function(index, pane) {
            var $pane = $(pane),
                $lens = $pane.data('lens');
            
            $pane.css({
                width:'1px',
                height:'1px',
                top:0
            });
            if ($lens) {
                $lens.css({
                    width:'1px',
                    height:'1px',
                    top:0
                });
            }
        });
    }
    
    function make_pane(box, type) {
        var c_left = box.left;
        var c_width = box.width;
        if (c_left < 0) {
            if (type === 'top' || type === 'bottom') {
                box.width += c_left;
            }
            c_left = 0;
            box.left = c_left;
        } else if (c_left >= doc_width) {
            c_left = doc_width - size - 1;
            box.left = c_left;
        }
        if (c_width + c_left >= doc_width) {
            box.width = (doc_width - c_left - size);
        }

        if (box.top >= doc_height) {
            box.top = doc_height - 3;
        }
        if ( (box.top + box.height) >= doc_height) {
            box.height = doc_height - box.top - 3;
        }
        if (pane_position === 'fixed') {
            box.top += overlay_offset;
        }
        var css = {};
        // add px size
        $.each(box, function(i, v) {
            css[i] = Math.round(v);
        });
        
        var $pane_node = panes[type];
        if (!$pane_node) {
            $pane_node = $('<div></div>').appendTo(front_overlay);
            panes[type] = $pane_node;
        }
        $pane_node.attr(
            'class', 
            'fx_outline_pane '+ 
            (pane_position === 'fixed' ? 'fx_outline_pane_fixed ' : '')+
            'fx_outline_pane_'+type+' fx_outline_style_'+style
        );
        css['z-index'] = pane_z_index;
        css['position'] = pane_position;
        $pane_node.css(css);
        
        if (draw_lens){
            var $lens = $pane_node.data('lens');
            if (!$lens || $lens.length === 0) {
                $lens = $('<div class="fx_lens fx_lens_'+type+'"></div>').appendTo(front_overlay);
                $pane_node.data('lens', $lens).addClass('fx_has_lens');
            }
            var lens_css = {
                'z-index':css['z-index'] - 1
            };
            var win_size = {
                width:$(document).width(),
                height: $(document).height()
            };
            
            switch(type) {
                case 'top':
                    lens_css.width = win_size.width;
                    lens_css.height = css.top;
                    lens_css.top = 0;
                    break;
                case 'bottom':
                    lens_css.width = win_size.width;
                    lens_css.height = win_size.height - css.top - size - parseInt(front_overlay.css('top'));
                    lens_css.top = css.top + size;
                    break;
                case 'left':
                    lens_css.width = css.left;
                    lens_css.top = css.top;
                    lens_css.height = css.height;
                    lens_css.left = 0;
                    break;
                case 'right':
                    lens_css.width = win_size.width - css.left;
                    lens_css.top = css.top;
                    lens_css.height = css.height;
                    lens_css.left = css.left;
                    break;
            }
            if (pane_position === 'fixed') {
                lens_css.position = 'fixed';
            }
            
            $lens.css(lens_css);
        }

        return $pane_node;
    }
    var offset_x = params.offset_x,
        offset_y = params.offset_y;
    
    if (pane_position==='fixed') {
        o.top -=$(window).scrollTop();
    }
    
    // if the node bottom is lower than doc height
    // don't enlarge the doc by showing the pane
    if (o.top + nh > doc_height) {
        nh -= o.top + nh - doc_height + 5;
    }
    
    var box_width = nw + offset_x*2,
        box_height = (nh + size*2 + offset_y*2);
    
    make_pane({
        top: o.top - size - offset_y,
        left:o.left - size - offset_y,
        //width:(nw + offset_x*2),
        width:box_width,
        height:size
    }, 'top');
    make_pane({
        top:o.top + nh + offset_y,
        left:o.left - size - offset_x,
        //width: nw + g_offset*2,
        width:box_width,
        height:size
    }, 'bottom');
    make_pane({
        top: (o.top - size - offset_y),
        left:o.left - size - offset_x,
        width:size ,
        //height: (nh + size*2 + g_offset*2)
        height:box_height
        
    }, 'left');
    make_pane({
        top:o.top - size - offset_y,
        left:o.left + nw + offset_x - size,
        width:size,
        height: box_height
    }, 'right');
    if (speed) {
        var $panes = $([]);
        for (var i in panes) {
            $panes = $panes.add(panes[i]);
        }
        $panes.css({opacity:0}).animate({opacity:1}, speed);
    }
    n.data('fx_outline_panes', panes);
    n.data('fx_outline_style', style);
    if (style === 'hover') {
        n.off('.fx_hide_hover_outlines').on('mousemove.fx_hide_hover_outlines', function(e) {
            $fx.front.hide_hover_outlines({top:e.pageY, left:e.pageX});
        });
    }
};

fx_front.prototype.outline_block_off = function(n, speed) {
    if (n.data('fx_has_lens')) {
        $('.fx_has_lens').each(function() {
            var $has_lens = $(this);
            $has_lens.removeClass('fx_has_lens').data('lens').remove();
            $has_lens.data('lens',null);
        });
        $('.fx_lens').remove();
        n.data('fx_has_lens', null);
    }
    n.off('.fx_hide_hover_outlines');
    if (n.hasClass('fx_hilight_outline')) {
        return;
    }
    var panes = n.data('fx_outline_panes');
    if (!panes) {
        return;
    }
    var $panes = $([]);
    for (var i in panes) {
        //panes[i].remove();
        $panes = $panes.add(panes[i]);
    }
    n.off('.fx_recount_outlines');
    $(window).off('.fx_recount_outlines');
    n.data('fx_outline_panes', null);
    
    if (speed === undefined) {
        $panes.each(function() {
            var $p = $(this);
            /*
            if ($p.data('lens')) {
                $p.data('lens').remove();
            }
            */
            $p.remove();
        });
    } else {
        $panes.stop().animate({opacity:0}, speed, null, function() {
            $panes.remove();
        });
    }
};

fx_front.prototype.outline_all_off = function() {
    $('.fx_outline_pane').remove();
};


fx_front.prototype.disable_node_panel = function() {
    this.node_panel.disable();
};

fx_front.prototype.enable_node_panel = function() {
    this.node_panel.enable();
};

fx_front.prototype.prepare_page_infoblock_form = function(settings) {
    if (!settings  || settings.id !== 'page_infoblocks') {
        return;
    }
    var $areas = $('.fx_area');
    var areas = {};
    $areas.each(function(){
        var area_meta = $(this).data('fx_area');
        areas[area_meta.id] = area_meta.name || area_meta.id;
    });
    areas.hidden_area = '(!!!) hidden';
    $.each(settings.fields[0].values, function() {
        var c_values = areas;
        if (!areas[this.area]) {
            var empty_area = {};
            empty_area[this.area] = '(!!!) '+this.area;
            c_values = $.extend({}, areas, empty_area);
        }
        this.area = {
            field : {
                name:'area['+this.id+']',
                type:'select',
                values: c_values,
                value : this.area
            }
        };
    });
};


fx_front.prototype.freeze_events = function(frozen_node) {
    var $ = window.jQuery,
        $node = $(frozen_node),
        frozen_handlers = [];
    
    function freeze_event_hanlder(handler, check_context) {
        if (handler.namespace.match(/^fx/) || handler.type.match(/^fx/)) {
            return;
        }
        if (!handler.type.match(/^(click|key|mouse)/)) {
            return;
        }
        if (handler._realHandler) {
            return;
        }
        frozen_handlers.push(handler);
        handler._realHandler = handler.handler;
        if (!check_context) {
            handler.handler = function(e) {};
            return;
        }
        handler.handler = function(e) {
            if ($(e.target).closest('.fx_overlay').length) {
                return handler._realHandler.apply(this, [e]);
            }
            /*
            if (frozen_node !== e.target && !$.contains(frozen_node, e.target)) {
                return handler._realHandler.apply(this, [e]);
            }
            */
        };
    }

    function freeze_node_events(node, check_context) {
        var events = $._data(node, 'events');
        if (!events) {
            return;
        }
        $.each( events, function (e_type, events) {
            $.each(events, function (e_index, e) {
                freeze_event_hanlder(e, check_context);
            });
        });
    }

    $.each($('*', $node).add($node), function (index, node) {
        freeze_node_events(node, false);
    });

    $.each($node.parents().add( document ), function (index, node) {
        freeze_node_events(node, true);
    });

    $node.data('frozen_handlers', frozen_handlers);
};

fx_front.prototype.unfreeze_events = function(frozen_node) {
    var $ = window.jQuery,
        $node = $(frozen_node);
    $.each($node.data('frozen_handlers'), function (index, h) {
        if (h._realHandler) {
            h.handler = h._realHandler;
            delete h._realHandler;
        }
    });
};

$('html').on('fx_before_adm_form_created', function(e, settings) {
    if ($fx.front) {
        $fx.front.prepare_page_infoblock_form(settings);
    }
});

$('html').on('click.fx_help', '.fx_item_help_block .level_expander', function() {
    var $c_row = $(this).closest('tr');
    var c_level = $c_row.attr('class').match(/help_level_(\d+)/)[1]*1;
    var $next = $c_row.nextAll('tr');
    for (var i = 0; i < $next.length; i++) {
        var $row = $next.eq(i);
        if ($row.hasClass('help_level_'+c_level)) {
            break;
        }
        if ($row.hasClass('help_level_'+(c_level+1))) {
            $row.toggle();
        }
    }
});

$('html').on('click.fx_help', '.fx_help .fx_help_expander', function() {
   var $exp = $(this);
   var $help = $exp.data('help_node');
   if (!$help) {
        var $help = $(this).parent().find('.fx_help_data');
        $('body').append($help);
        $exp.data('help_node', $help);
   }
   var offset = $exp.offset();
   //$help.css({top: offset.top+'px', left:offset.left+'px'});
   $help.css({
        position:'fixed', 
        top: '10px', 
        left:offset.left+'px', 
        'max-height': $(window).height() - 20 + 'px', 
        overflow:'auto'
   });
   if (!$help.is(':visible')) {
       $help.show();
       var z_index = ($help.css('z-index')+1);//+' !important';
       $exp.css({'z-index': z_index});
   } else {
       $help.hide();
       $exp.css('z-index', null);
   }
});


    
})($fxj);