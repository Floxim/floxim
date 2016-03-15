/* global Function */

(function($) {

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
    
    $('html').on('keyup.fx_front_keyup', function(e) {
        if ($fx.front_panel.parse_stris_visible) {
            return;
        }

        switch (e.which) {
            // F2
            case 113:
                $fx.front.load($fx.front.mode === 'edit' ? 'view' : 'edit');
                break;
            /*
            // F4
            case 115:
                $fx.front.load($fx.front.mode === 'design' ? 'view' : 'design');
                break;
            */
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
        }
    });
    
    this.c_hover = null;
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
    var node = $(this);
    if (node.hasClass('fx_selected')) {
        e.fx_hilight_done = true;
        return;
    } 
    if (
        // @todo: fix make_content_editable for bound-to-entity vars
        //!$fx.front.is_selectable(this) || 
        node.closest('.fx_entity_adder_placeholder').length 
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
            if ($fx.front.c_hover !== node.get(0)) {
                return;
            }
            if (node.hasClass('fx_selected')) {
                return;
            }
            if ($fx.front.hilight_disabled) {
                return false;
            }
            $fx.front.last_hover_node = node[0];
            if (!node.hasClass('fx_hilight_hover') && node.closest('.fx_infoblock_disabled').length === 0) {
                $('.fx_hilight_hover').removeClass('fx_hilight_hover');
                node.addClass('fx_hilight_hover');
                if (node.closest('.fx_is_moving').length === 0) {
                    $fx.front.outline_block(node, 'hover', 300);
                }
                if (make_content_editable) {
                    $editable.addClass('fx_var_editable').attr('contenteditable', 'true');
                }
            }
        },
        is_hover_parent ? 300 : 30
    );
    node.one('mouseout.fx_front_mouseout', function() {
        $fx.front.c_hover = null;
        if (node.closest('.fx_selected').length > 0) {
            return false;
        }
        setTimeout(
            function() {
                if (node.closest('.fx_selected').length > 0) {
                    return false;
                }
                if ($fx.front.c_hover !== node[0]) {
                    node.removeClass('fx_hilight_hover');
                    $fx.front.outline_block_off(node, 100);
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
        var panel = $fx.front.node_panel.get(),
            is_link = !$link.is('.fx_click_handler'),
            button = {
                type:'icon',
                keyword:'follow'
            };
        if (is_link) {
            button.href = $link.attr('href');
        }
        var $button = panel.add_button(
            button, 
            null, 
            panel.$panel.find('>*:visible').first()
        );
        if (!is_link) {
            $button.click(function() {
                $link.data('fx_click_handler')();
            });
        }
    }
    return false;
};

fx_front.prototype.freeze = function() {
    this.disable_hilight();
    this.disable_select();
    this.disable_node_panel();
    this.is_frozen = true;
};

fx_front.prototype.unfreeze = function() {
    this.enable_node_panel();
    this.enable_select();
    if (!this.get_selected_item()) {
        this.enable_hilight();
    }
    this.is_frozen = false;
};

fx_front.prototype.disable_hilight = function() {
    this.hilight_disabled = true;
    $('.fx_front_overlay .fx_inline_adder-visible').each(function() {
        this.fx_hide_inline_adder();
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
    if (typeof meta.size === 'undefined') {
        // It would be nice to calculate
        var full_size = 1000;
        if ($area_node.outerWidth() < full_size*0.5) {
            meta.size = 'narrow';
        } else {
            meta.size = '';
        }
        $area_node.data('fx_area', meta);
    }
    return meta;
};

fx_front.prototype.show_adder_placeholder = function($placeholder, $rel_node, rel_position) {
    var $placeholder_parent = $placeholder[0].$fx_placeholder_parent;
    //var $hidden_block = $placeholder.parent().closest('.fx_hidden_placeholded');
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
        placeholder_meta = $placeholder.data('fx_entity_meta');
    
    var speed = 300;
    var null_size = {width:'',height:''};
    
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
        
        $placeholder.trigger('fx_before_show_adder_placeholder');
        
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
        
        $placeholder
          .css({width:0,height:0})
          .animate(
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
                $placeholder.trigger('fx_after_hide_adder_placeholder');
                $placeholder.remove();
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

/**
 * Function to show controller selection dialog
 */

fx_front.prototype.add_infoblock_select_controller = function($node, $rel_node, rel_position) {
    var $area_node = $node.closest('.fx_area'),
        container_infoblock = $node.closest('.fx_infoblock').not('body').data('fx_infoblock'),
        area_meta = $fx.front.get_area_meta($area_node),
        place_params = {};
    
    if ($rel_node && $rel_node.length) {
        var $stub = $(
            '<div class="fx_infoblock_stub">' + 
                //$fx.lang('Choose block type') +
            '</div>'
        );
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
        },
        onfinish: function(data) {
            
            if (data.preset_id) {
                var $ib_node = $(data.html);
                $fx.front.append_ib_node($area_node, $ib_node);
                $fx.front.hilight($ib_node);
                $ib_node.trigger('fx_infoblock_loaded');
                $ib_node.css('opacity', $fx.front.disabled_infoblock_opacity).animate({opacity: 1},250);
                
                $('.fx_infoblock_stub', $area_node).remove();
                
                var preset_params = $fx.front.get_infoblock_place_params($ib_node);

                $ib_node.data('fx_preset_params', preset_params);
                
                $ib_node.on('fx_after_hide_adder_placeholder', function() {
                    $fx.front.outline_block_off($ib_node);
                    $ib_node.remove();
                    $fx.front.hilight();
                });
                
                $ib_node.find('.fx_adder_variant').first().click();
            } else {
                return $fx.front.add_infoblock_select_settings(data);
            }
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

fx_front.prototype.add_infoblock_select_settings = function(data) {
    var $area_node = $($fx.front.get_selected_item()).closest('.fx_area');
    
    var infoblock_back = function () {
        $fx.front.add_infoblock_select_controller($area_node);
    };
    var cancel_adding = function() {
        $fx.front.deselect_item();
        $('.fx_infoblock_fake').remove();
        $fx.front.hilight();
    };
    $fx.front_panel.show_form(data, {
        view:'horizontal',
        onfinish:function(res) {
            $fx.front.reload_layout(
                function() {
                    if (!res.props || !res.props.infoblock_id) {
                        return;
                    }
                    var new_ib_node = $('.fx_infoblock_'+res.props.infoblock_id);
                    if (new_ib_node.length === 0) {
                        return;
                    }
                    
                    var new_cm = new_ib_node.data('fx_controller_meta');
                    if (new_cm && new_cm.accept_content) {
                        // @todo: init adder
                        return;
                    } else {
                        setTimeout(function() {
                            $fx.front.select_item(new_ib_node.get(0));
                        },100);
                    }
                }
            );
        },
        onready:function($form) {
            var back = $('.fx_admin_form__header a.back', $form);
            back.on('click.fx_front', function() {
                infoblock_back();
                cancel_adding();
            });
            
            // creating infoblock preview
            $fx.front.deselect_item();
            
            
            
            var add_fake_ib = function (callback) {
                var $c_ib_node = $('<div class="fx_infoblock fx_infoblock_fake" />');
                // if the closest infoblock is not layout,
                // we will reload it with 'add_new' param
                var $closest_ib = $area_node.closest('.fx_infoblock');
                var $cib_data = $closest_ib.data('fx_infoblock');
                if (
                    $closest_ib.length 
                    && $closest_ib[0].nodeName !== 'BODY'
                    && $cib_data.controller.match(/^widget_blockset/)
                ) {
                    $fx.front.reload_infoblock(
                        $closest_ib, 
                        function($new_ib_node) {
                            cancel_adding = function(){
                                $fx.front.reload_infoblock($new_ib_node);
                            };
                            callback($new_ib_node.find('.fx_infoblock_fake'));
                        }, 
                        {override_infoblock:{params:{add_new_infoblock:true}}}
                    );
                    return;
                }
                $fx.front.append_ib_node($area_node, $c_ib_node);
                $c_ib_node.data('fx_infoblock', {id:'fake'});
                $form.data('ib_node', $c_ib_node);
                $form.data('is_waiting', false);
                if (callback instanceof Function) {
                    callback($c_ib_node);
                }
            };
            var ib_loader = null, 
                is_waiting = false,
                c_data = null;
            
            $form.on('change.fx_front', function(e) {
                var new_data = $form.serialize();
                if (c_data === new_data) {
                    return;
                }
                c_data = new_data;
                if (is_waiting) {
                    if (ib_loader !== null) {
                        ib_loader.abort();
                    }
                }
                is_waiting = true;
                
                var $c_ib_node = $form.data('ib_node');
                
                ib_loader = $fx.front.reload_infoblock(
                    $c_ib_node, 
                    function($new_ib_node) {
                        var add_ib_to_form = function($new_ib_node) {
                            $form.data('ib_node', $new_ib_node);
                            is_waiting = false;
                            $fx.front.extract_infoblock_visual_fields($new_ib_node, $form);
                            $fx.front.select_item($new_ib_node.get(0));
                        };
                        if (!$new_ib_node || $new_ib_node.length === 0) {
                            add_fake_ib(add_ib_to_form);
                        } else {
                            add_ib_to_form($new_ib_node);
                        }
                    }, 
                    {override_infoblock:c_data}
                );
            });
            add_fake_ib(function($ib_node) {
                $form.data('ib_node', $ib_node);
                $form.change();
            });
        },
        oncancel:function($form) {
            cancel_adding();
            $('.fx_infoblock_stub').remove();
        }
    });
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
            '<div class="fx_button fx_button-class-cancel">'+$fx.lang('cancel')+'</div>'+
        '</div>'
    );
    $path_panel.find('.fx_button').click(function() {
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
            block_found = true;
            path_items.push({
                type:'Блок',
                label: ib_meta.name || '#'+ib_meta.id,
                node:$cp,
                controls:[
                    'settings', 'delete'
                ],
                is_active:is_active
            });
        }
        /*
        if ($cp.is('.fx_area')) {
            var area_meta = $cp.data('fx_area');
            path_items.push({
                type:'Область',
                label:area_meta.name || area_meta.id,
                node:$cp
            });
        }
        */
    });
    if (path_items.length === 0) {
        return;
    }
    path_items[0].is_active = true;
    $path_panel.show();
    $.each(path_items.reverse(), function() {
        var bl = 'fx_select_path_item',
            item = this;
        var $item = $('<div class="'+bl+ (item.is_active ? ' '+ bl + "-active" : '')+'">'+
                        '<div class="'+bl+'__title">'+
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
    
    var scrolling = false;
    setTimeout(function() {
        //if (!scrolling && !$node.hasClass('fx_edit_in_place')) {
        if (!scrolling && !$node.hasClass('fx_var_editable')) {
            $fx.front.scrollTo($node, true, function() {
                scrolling = false;
            });
            scrolling = true;
        }
    }, 150);
    
    $fx.front.disable_hilight();
    
    $('html').on('keydown.fx_selected', function(e) {
        if ($fx.front_panel.is_visible || $fx.front.hilight_disabled) {
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
    return res;
};

fx_front.prototype.hilight = function(container) {
    container = container || $('html');
    
    $('*[data-has_var_in_att="1"]', container).addClass('fx_template_var_in_att');
    
    $fx.front.collect_adder_placeholders(container);
    
    var fx_selector = '.fx_template_var, .fx_area, .fx_template_var_in_att, .fx_entity, .fx_infoblock, '+
                        '.fx_hidden_placeholded, .fx_adder_placeholder_container';
    var items = $(fx_selector, container).not('.fx_unselectable');
    if (container.not('.fx_unselectable').is(fx_selector)) {
        items = items.add(container);
    }
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
                .removeClass('fx_template_var fx_sortable fx_entity_adder_placeholder fx_template_var_in_att')
                .data('fx_infoblock', $placeholder.data('fx_infoblock'));
            $placeholder.before($parent);
            $placeholders = $placeholder;
        } else {
            $placeholders = $parent.find('>.fx_entity_adder_placeholder');
        }
        if (!$parent.is('.fx_no_add') || $parent.find('>.fx_entity:not(.fx_entity_adder_placeholder)').length === 0) {
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
    var a_meta = $area.data('fx_area');
    var area_placeholder = $fx.lang(
                                scenario === 'place' ? 
                                    '%s: <a>put the block here</a>.' : 
                                    'Area %s is empty, you can add some blocks here.'
                            ).replace(
                                /\%s/, 
                                a_meta.name ? a_meta.name : a_meta.id
                            ),
        $area_placeholder = $('<span class="fx_area_placeholder">'+area_placeholder+'</span>');

    if (scenario === 'place') {
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
    } else {
        $area_placeholder.on(
            'click', 
            'a', 
            function() {
                $fx.front.add_infoblock_select_controller($area);
                return false;
            }
        );
    }
    $area.find('>.fx_area_placeholder').remove();
    $area.append($area_placeholder);
    $area.addClass('fx_hidden_placeholded');
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
    });/*.animate({
        opacity:0.8
    }, 100);*/
    
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
    $overlay.stop().animate(
        {
            opacity:0
        }, 
        600,
        null,
        function() {
            $overlay.remove();
        }
    );
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

fx_front.prototype.get_edit_closure = function($entity, params) {
    params = params || {};
    return function() {
        var entity_meta = $entity.data('fx_entity'),
            ce_id = entity_meta[0],
            $ib_node = $entity.closest('.fx_infoblock'),
            edit_action_params = {
                entity:'content',
                action:'add_edit',
                content_type:entity_meta[1]
            },
            entity_meta_props = $entity.data('fx_entity_meta'),
            placeholder_data = entity_meta_props ? entity_meta_props.placeholder : null,
            placeholder_linker = entity_meta_props ? entity_meta_props.placeholder_linker : null,
            $stored_selected_node = $fx.front.get_selected_item();

        if (ce_id) {
            edit_action_params.content_id = entity_meta[0];
        } else if (placeholder_data) {
            edit_action_params = $.extend(edit_action_params, placeholder_data);
        }
        var preset_params = $ib_node.data('fx_preset_params');
        if (preset_params) {
            edit_action_params.preset_params = JSON.stringify(preset_params);
        }
        fx_eip.fix();
        
        var entity_values = fx_eip.get_values(ce_id);
        
        $fx.front.disable_node_panel();
        
        if ($entity[0] !== $fx.front.get_selected_item()) {
            $fx.front.select_item($entity);
        }
        
        fx_eip.stop();
        
        $fx.front_panel.load_form(
            $.extend(
                {}, 
                edit_action_params, 
                {
                    entity_values:entity_values,
                    placeholder_linker:placeholder_linker
                }
            ), 
            {
                onready: function($form) {
                    $fx.front.make_content_form_editable($form);
                    
                    fx_eip.stop();
                    var $att_var_nodes = $entity.descendant_or_self('*[data-has_var_in_att]');
                    $att_var_nodes.each(function() {
                        var $c_node = $(this),
                            c_data = $c_node.data();
                        $.each(c_data, function(data_key,data) {
                            if (!data_key.match(/fx_template_var_/)) {
                                return;
                            }
                            if (data.type === 'image' && data.var_type === 'content') {
                                var $target_field = $form.find('.field_name__'+data.name);
                                $target_field.on('fx_change_file', function(e) {
                                    if (e.upload_response) {
                                        fx_eip.append_value($c_node, data, e.upload_response.path, e.upload_response.formatted_value);
                                    }
                                });
                            }
                        });
                    });
                    var $var_nodes = $entity.descendant_or_self('*[data-fx_var]');
                    $var_nodes.each(function() {
                        var $var_node = $(this),
                            var_meta = $var_node.data('fx_var');
                        if (var_meta.var_type === 'visual') {
                            return;
                        }
                        var_meta.target_type = 'var';
                        $form.find('.field_name__'+var_meta.name).on('input keyup change', function(e) {
                            var $field = $(this),
                                $target = $(e.target),
                                val = null;
                            if ($target.is('.redactor_fx_wysiwyg')) {
                                val = $target.html();
                            } else if ($field.is('.field_datetime')) {
                                val = $field.find('.date_input').val();
                            } else {
                                val = $(e.target).val();
                            }
                            fx_eip.append_value($var_node, var_meta, val);
                        });
                    });
                },
                onfinish: function(res) {
                    fx_eip.stop();
                    var ib_reload_params = {};
                    if (res.real_infoblock_id) {
                        ib_reload_params.real_infoblock_id = res.real_infoblock_id;
                    }
                    $fx.front.reload_infoblock($ib_node, null, ib_reload_params);
                    $fx.front_panel.hide();
                },
                oncancel: function() {
                    if (params.oncancel) {
                        params.oncancel();
                    } else {
                        $entity.data('fx_has_full_form', false);
                        $fx.front.deselect_item();
                        $fx.front.select_item($stored_selected_node);
                    }
                }
            }
        );
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
        entity_meta_props = $entity.data('fx_entity_meta'),
        is_linker_placeholder = false,
        $ib_node = $entity.closest('.fx_infoblock');

    if (entity_meta_props) {
        is_linker_placeholder = entity_meta_props.placeholder_linker;
    }

    var entity = $entity[0];
    
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
        //panel_params.align = 'right';
    }
    
    var entity_panel = $fx.front.node_panel.create($entity, panel_params);
    
    if (!is_linker_placeholder) {
        //entity_panel.add_label($entity.data('fx_entity_name')+':');
    }
    
    var ce_id = entity_meta[2] || entity_meta[0];
    
    
    if (ce_id) {
        var $entity_label = entity_panel.add_label( $entity.data('fx_entity_name') );
        $entity_label.addClass('fx_node_panel__item-button-label');
        
        entity_panel.add_button(
            {
                keyword:'edit',
                type:'icon'//,
                //label:$fx.lang('do_edit')+' '+$entity.data('fx_entity_name').toLowerCase()
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
        /*
        var is_bound_to_ib = $fx.front.is_equal_rect($entity, $ib_node);
        if (is_bound_to_ib) {
            $fx.front.select_infoblock($ib_node, entity_panel);
        } else {
            
        }
        */
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
    
    
    /*
    function add_text_field_label(panel, $field_node) {
        var field_meta = $field_node.data('fx_var');
        if (!field_meta) {
            return;
        }
        if (field_meta.type === 'string' || field_meta.type === 'text' || field_meta.type === 'html') {
            panel.$panel.append('<div class="fx_node_panel_separator"></div>');
            panel.add_label(
                'Editing: '+field_meta.label
            );
        }
    }
    */
    
    if (!$from_field) {
        var $bound_to_edit = $fx.front.get_entity_bound_vars($entity);
        $bound_to_edit.edit_in_place();
    }/* else if ($from_field.is('.fx_template_var')) {
        var field_panel = $fx.front.node_panel.get($from_field);
        //add_text_field_label(field_panel, $from_field);
    }*/
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

fx_front.prototype.extract_infoblock_visual_fields = function($ib_node, $form) {
    var types = ['template', 'wrapper'];
    
    $.each(types, function(index, type) {
        var props = $ib_node.data('fx_'+type+'_params'),
            field_class = 'fx_infoblock_'+type+'_param_field';
        $('.'+field_class, $form).remove();
        if (!props) {
            return;
        }
        var $rel_field = $(':input[name="visual['+type+']"]', $form).closest('.field');
        $.each(props, function(prop_name, prop_data) {
            var meta = $.extend(
                {}, 
                prop_data, {
                    name:'visual['+type+'_visual]['+prop_name+']',
                    view_context:'panel'
                }
            );
    
            //console.log(meta);
    
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
            
            var $field = $fx.form.draw_field(meta, $rel_field, 'after');
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
};

fx_front.prototype.select_infoblock = function($node, panel) {
    if ($fx.front.mode === 'edit') {
        $node.edit_in_place();
    }
    
    if (!panel) {
        panel = $fx.front.node_panel.get();
    }
    /*
    var $label = panel.add_label( $fx.lang('Infoblock') );
    
    $label.addClass('fx_node_panel__item-button-label');
    */
    panel.add_button('settings', function() {
        var ib_node = $node[0],
            $ib_node = $node,
            ib = $ib_node.data('fx_infoblock');
        
        if (!ib) {
            return;
        }
        var $area_node = $fx.front.get_area_node($ib_node);//ib_node.closest('.fx_area');
        var area_meta = $fx.front.get_area_meta($area_node);
        
        var is_waiting = false, 
            ib_loader = null,
            has_changes = false;
            
        $fx.front_panel.load_form({
            entity:'infoblock',
            action:'select_settings',
            id:ib.id,
            visual_id:ib.visual_id,
            page_id: $fx.front.get_page_id(), //$('body').data('fx_page_id'),
            fx_admin:true,
            area:area_meta
        }, {
            view:'horizontal',
            onfinish:function() {
                $fx.front.reload_infoblock($('.fx_infoblock_'+ib.id));
            },
            onready:function($form) {
                $form.data('ib_node', ib_node);
                $fx.front.extract_infoblock_visual_fields($ib_node, $form);
                var c_data = $form.serialize();
                
                $form.on('change.fx_front', function(e) {
                    if (e.target.name === 'livesearch_input' || e.target.name === 'scope[type]') {
                        return;
                    }
                    var new_data = $form.serialize();
                    if (new_data === c_data) {
                        return;
                    }
                    c_data = new_data;
                    if (is_waiting) {
                        ib_loader.abort();
                    }
                    is_waiting = true;
                    ib_loader = $fx.front.reload_infoblock(
                        $form.data('ib_node'), 
                        function($new_ib_node) {
                            has_changes = true;
                            $form.data('ib_node', $new_ib_node);
                            $fx.front.extract_infoblock_visual_fields($new_ib_node, $form);
                            is_waiting = false;
                        }, 
                        {override_infoblock:c_data}
                    );
                });
            },
            oncancel:function($form) {
                if (has_changes) {
                    $fx.front.reload_infoblock($form.data('ib_node'));
                }
            }
        });
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

/*
fx_front.prototype._start_areas_sortable = function() {
    var $areas = $('.fx_area'),
        $ibs = $('.fx_infoblock').not('.fx_infoblock_fake').not('body');
    $ibs.each(function() {
        var $par = $(this).parent();
        if (!$par.hasClass('.fx_area')) {
            $areas = $areas.add($par);
        }
    });
    
    
    var $c_selected = $($fx.front.get_selected_item());
    
    $areas.each(function() {
        var $area = $(this);
        $area.addClass('fx_area_sortable');
        var sortable = new Sortable(this, {
            group:'fx_areas',
            scroll:true,
            scrollSensitivity: 100, // px, how near the mouse must be to an edge to start scrolling.
            scrollSpeed: 40,
            animation: 350,
            onStart: function() {
                
                $fx.front.outline_block_off($c_selected);
                $fx.front.disable_hilight();
                $fx.front.get_node_panel().hide();
                $areas.addClass('fx_area_target');
            },
            onEnd: function() {
                $areas.removeClass('fx_area_target');
                $fx.front.enable_hilight();
                $fx.front.select_item($c_selected);
            },
            ghostClass:'ui-sortable-helper'
        });
        $area.data('sortable', sortable);
    });
};


fx_front.prototype.start_areas_sortable = function() {
    var $iblocks = $('.fx_infoblock').not('.fx_infoblock_fake').not('body');
    $iblocks.each(function() {
        var $p = $(this).parent();
        if ($p.hasClass('fx_area_sortable')) {
            return;
        }
        $p.addClass('fx_area_sortable');
    });
    $('.fx_area').each(function () {
        var $area = $(this);
        if (!$area.hasClass('fx_area_sortable') && $('.fx_infoblock', $area).length === 0) {
            $area.addClass('fx_area_sortable');
        }
    });
    $('.fx_area_sortable').each(function(){
        var cp = $(this);
        cp.sortable({
            items:'>.fx_infoblock',
            connectWith:'.fx_area_sortable',
            placeholder: "fx_infoblock_placeholder",
            distance:35,
            start:function(e, ui) {
                $('.fx_area_sortable').addClass('fx_area_target');
                cp.trigger('fx_start_sort_infoblocks');
                cp.sortable('refreshPositions');
                var ph = ui.placeholder;
                var item = ui.item;
                ph.css({
                    'min-height':'30px',
                    height:item.outerHeight(),
                    overflow:'hidden'
                });
                var $c_selected = $($fx.front.get_selected_item());
                $fx.front.outline_block_off($c_selected);
                $fx.front.disable_hilight();
                $fx.front.get_node_panel().hide();
            },
            stop:function(e, ui) {
                $('.fx_area_sortable').removeClass('fx_area_target');
                cp.trigger('fx_stop_sort_infoblocks');
                var ce = ui.item;
                var ce_data = ce.data('fx_infoblock');
                $fx.front.outline_block_off(ce);
                $fx.front.outline_block(ce, 'selected');

                var params = {
                    entity:'infoblock',
                    action:'move',
                    area:ce.closest('.fx_area').data('fx_area').id
                };

                params.infoblock_id = ce_data.id;
                params.visual_id = ce_data.visual_id;

                var next_e = ce.next('.fx_infoblock');
                if (next_e.length > 0) {
                    var next_data = next_e.data('fx_infoblock');
                    params.next_infoblock_id = next_data.id;
                    params.next_visual_id = next_data.visual_id;
                }
                $fx.front.stop_areas_sortable();
                $fx.front.start_areas_sortable();
                $fx.post(params, function(res) {
                    $fx.front.reload_layout();
                });
            }
        });
    });
};
fx_front.prototype.stop_areas_sortable = function() {
    $('.fx_area_sortable').
            sortable('destroy').
            removeClass('fx_area_sortable');
};
                                
*/

fx_front.prototype.set_mode_design = function() {
    $fx.panel.one('fx.startsetmode', function() {
        $('html').off('.fx_design_mode');
    });
};

fx_front.prototype.disabled_infoblock_opacity = 0.4;

fx_front.prototype.disable_infoblock = function(infoblock_node) {
    // .css({opacity:'0.3'}).
    $(infoblock_node)
        .on('click.fx_fake_click', function() {
            return false;
        })
        .animate({opacity:$fx.front.disabled_infoblock_opacity}, 250)
        .addClass('fx_infoblock_disabled');
};

fx_front.prototype.enable_infoblock = function(infoblock_node) {
    $(infoblock_node)
        .off('click.fx_fake_click')
        .css('opacity', '')
        .removeClass('fx_infoblock_disabled');
};

fx_front.prototype.reload_infoblock = function(infoblock_node, callback, extra_data) {
    var $infoblock_node = $(infoblock_node);
    $fx.front.disable_infoblock(infoblock_node);
    
    var ib_parent = $infoblock_node.parent();
    var meta = $infoblock_node.data('fx_infoblock');
    var page_id = $fx.front.get_page_id(); //$('body').data('fx_page_id');
    var post_data = {
        _ajax_base_url: $infoblock_node.data('fx_ajax_base_url') || document.location.href
    };
    if (typeof extra_data !== 'undefined') {
        $.extend(post_data, extra_data);
    }
    if (!meta ) {
        console.log('nometa', infoblock_node);
        return;
    }
    var selected = $infoblock_node.descendant_or_self('.fx_selected');
    var selected_selector = null;
    if(selected.length > 0) {
         selected_selector = selected.first().generate_selector(ib_parent);
    }
    var real_infoblock_id = (extra_data || {}).real_infoblock_id || meta.id;
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
               var inserted = false;
               $infoblock_node.children().each(function() {
                   if(!$(this).hasClass('fx_overlay')) {
                       if (!inserted) {
                            $(this).before(res);
                            inserted = true;
                       }
                       $(this).remove();
                   }
               });
               var $new_infoblock_node = $('body');
               $new_infoblock_node.removeClass('fx_infoblock_disabled');
           } else {
               var $new_infoblock_node = $( $.trim(res) );
               if ($new_infoblock_node.length > 0) {
                   $new_infoblock_node = $new_infoblock_node.filter('.fx_infoblock').first();
               }
               $infoblock_node.hide();
               $infoblock_node.before($new_infoblock_node);
               $infoblock_node.remove();
           }
           
           if (is_layout) {
               $fx.front.move_down_body();
           }
           $fx.front.hilight($new_infoblock_node);
           $new_infoblock_node.trigger('fx_infoblock_loaded');
           $new_infoblock_node.css('opacity', $fx.front.disabled_infoblock_opacity).animate({opacity: 1},250);
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
    var body_offset = parseInt($('body').css('margin-top'));
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
            top_offset,
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
}

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
    var treshold = 1;
    $panes.each(function(i, p) {
        var $p = $(p),
            offset = $p.offset(),
            top = offset.top - treshold,
            left = offset.left - treshold,
            bottom = top + $p.height() + treshold*2,
            right = left + $p.width() + treshold*2;
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
            offset:0 // 6 for visible
        }
    };
    
    var params = $.extend(
        {
            recount:true,
            size:2,
            offset:2
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
    if (params.recount) {
        var recount_outlines = function(e) {
            $fx.front.outline_block(n, style);
            $fx.front.recount_node_panel();
        };
        n.off('.fx_recount_outlines').on('resize.fx_recount_outlines keydown.fx_recount_outlines', recount_outlines);
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
        doc_scroll = $(document).scrollTop(),
        front_overlay = $fx.front.get_front_overlay(),
        overlay_offset = parseInt(front_overlay.css('top'));
    
    var draw_lens = (style === 'selected_light') || (style === 'selected' && n.is('.fx_entity'));
    
    //draw_lens = false;
    
    if (draw_lens) {
        n.data('fx_has_lens', true);
        $.each(panes, function(index, pane) {
            var $lens = $(pane).data('lens');
            if ($lens) {
                $lens.css({
                    width:'1px',
                    height:'1px'
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
            box.left= c_left;
        }
        if (c_width + c_left >= doc_width) {
            box.width = (doc_width - c_left - size);
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
            //var $lens = $('<div class="fx_lens"></div>');
            var $lens = $pane_node.data('lens');
            if (!$lens || $lens.length === 0) {
                $lens = $('<div class="fx_lens fx_lens_'+type+'"></div>').appendTo(front_overlay);
                $pane_node.data('lens', $lens).addClass('fx_has_lens');
            }
            var lens_css = {
                'z-index':css['z-index'] - 1
            };
            var win_height = $(window).height(),
                body_height = $('body').height();
            
            var win_size = {
                width:$(document).width(),
                //height: Math.max(win_height, body_height) + $('body').offset().top
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
                //lens_css.top -= doc_scroll;
                lens_css.position = 'fixed';
            }
            
            $lens.css(lens_css);
            //$pane_node.hide();
        }
        return $pane_node;
    }
    var g_offset = params.offset;
    
    if (pane_position==='fixed') {
        o.top -=$(window).scrollTop();
    }
    
    // if the node bottom is lower than doc height
    // don't enlarge the doc by showing the pane
    if (o.top + nh > doc_height) {
        nh -= o.top + nh - doc_height + 5;
    }
    
    make_pane({
        top: o.top - size - g_offset,
        left:o.left - size - g_offset,
        width:(nw + g_offset*2),
        height:size
    }, 'top');
    make_pane({
        top:o.top + nh + g_offset,
        left:o.left - size - g_offset,
        width: nw + g_offset*2,
        height:size
    }, 'bottom');
    make_pane({
        top: (o.top - size - g_offset),
        left:o.left - size - g_offset,
        width:size ,
        height: (nh + size*2 + g_offset*2)
        
    }, 'left');
    make_pane({
        top:o.top - size - g_offset,
        left:o.left + nw + g_offset - size,
        width:size,
        height: (nh + size*2 + g_offset*2) 
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