(function($) {

window.fx_front = function () {
    this.mode = '';
    $('html').on('mouseover.fx_front', function(e) {
        $fx.front.mouseover_node = e.target;
    });
           
    this.node_panel = new fx_node_panels();
    
    this.move_down_body();
    
    this.mode_selectable_selector = null;
    
    this.image_stub = "/vendor/Floxim/Floxim/Admin/style/images/no.png";
    
    $('#fx_admin_front_menu').on('click.fx_mode_click', '.fx_front_mode', function() {
        var mode = $(this).data('key');
        $fx.front.load(mode);
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
            case 115:
                $fx.front.load($fx.front.mode === 'design' ? 'view' : 'design');
                break;
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
                $fx.front.outline_block(node, 'hover', 300);
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
    if ($fx.front.mode === 'view' || $fx.front.select_disabled) {
        return;
    }
    var target = $(e.target);
    if (target.closest('.fx_overlay, .redactor-dropdown, #redactor-modal-box').length > 0) {
        return;
    }
    var closest_selectable = null;
    if ($fx.front.is_selectable(target)) {
        closest_selectable = target;
    } else {
        closest_selectable = $fx.front.get_selectable_up(target);
    }
    // nothing to choose
    if (!closest_selectable) {
        // the cases when the target was beyond the primary tree
        // as with jqueryui-datepicker at redrawing
        if (target.closest('html').length === 0) {
            return;
        }
        // remove the selection and end processing
        $fx.front.deselect_item();
        return;
    }

    // move between pages via links to squeezed control,
    // and even saves the current mode
    var clicked_link = target.closest('a');
    if (clicked_link.length > 0 && e.ctrlKey && clicked_link.attr('href')) {
        clicked_link.add(clicked_link.parents()).attr('contenteditable', 'false');
        document.location.href = clicked_link.attr('href');
        return false;
    }


    e.stopImmediatePropagation();
    
    if (target.attr('onclick')) {
        target.attr('onclick', null);
    }
    
    // catch only contenteditable
    if ($(closest_selectable).hasClass('fx_selected')) {
        e.preventDefault();
        return;
    }
    $fx.front.select_item(closest_selectable);
    var $link = $(target).closest('a[href]');
    if ($link.length && $link.closest('.fx_entity_adder_placeholder').length === 0) {
        var panel = $fx.front.node_panel.get();
        /*
        var $click = $(
            '<div class="fx_follow_the_link">'+
                '<a href="'+$link.attr('href')+'" class="fx_icon fx_icon-type-follow"></a>'+
            '</div>'
        );
        panel.add_label($click, panel.$panel.children().filter(':visible').first());
        */
       panel.add_button(
            {
                type:'icon', 
                keyword:'follow',
                href:$link.attr('href')
            }, 
            null, 
            panel.$panel.find('>*:visible').first()
        );
    }
    return false;
};

fx_front.prototype.disable_hilight = function() {
    this.hilight_disabled = true;
    $('.fx_front_overlay .fx_inline_adder-visible').each(function() {
        this.fx_hide_inline_adder();
    });
};

fx_front.prototype.enable_hilight = function(){
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
    
    var $hidden_block = $placeholder.parent().closest('.fx_hidden_placeholded');
    var block_was_hidden = $hidden_block.length > 0;
    var $block_mark = $([]);
    if (block_was_hidden) {
        $hidden_block.removeClass('fx_hidden_placeholded');
        $block_mark = $hidden_block.children('.fx_hidden_placeholder_mark');
        //$hidden_block.data('fx_hidden_placeholder_mark', $block_mark[0].outerHTML);
        //$block_mark.remove();
        $hidden_block.children().show();
    }
    
    // store node right before placeholder to place placeholder back on cancel
    var $placeholder_pre = $placeholder.prev().first(),
        placeholder_meta = $placeholder.data('fx_entity_meta');
    
    var speed = 200;
    var null_size = {width:'',height:''};
    
    function get_size() {
        // decrease size by one pixel to suppress rouning effect in chrome 
        // when real size is defined in percents
        return {
            height: $placeholder.height() - 1,
            width: $placeholder.width() - 1
        };
    }
    
    
    function show_placeholder() {
        $placeholder.addClass('fx_entity_adder_placeholder_active');
        
        $('.fx_hilight_hover').each(function() {
            $fx.front.outline_block_off($(this));
        });
        $fx.front.disable_hilight();
        
        var is_linker_placeholder = placeholder_meta.placeholder_linker;
                
        if (is_linker_placeholder) {
            $('.fx_unselectable', $placeholder).removeClass('fx_unselectable');
        }
        
        $fx.front.hilight($placeholder);
        var target_size = get_size();
        
        $block_mark.slideUp(speed);
        
        $placeholder
          .css({width:0,height:0})
          .animate(
            target_size,
            speed,
            null,
            function() {
                $placeholder.css(null_size);
                var $placeholder_fields = $('.fx_template_var, .fx_template_var_in_att', $placeholder),
                    $placeholder_focus = $placeholder,
                    field_found = false;
                if (is_linker_placeholder) {
                    $placeholder.addClass('fx_linker_placeholder');
                    $('.fx_hilight', $placeholder).removeClass('fx_hilight').addClass('fx_unselectable');
                } else {
                    for (var i = 0; i < $placeholder_fields.length; i++) {
                        var $c_field = $placeholder_fields.eq(i);
                        if ($fx.front.is_selectable($c_field)) {
                            if (!$c_field.is(":visible")) {
                                continue;
                            }
                            // found name field
                            if ( ($c_field.data('fx_var') || {}).name === 'name') {
                                $placeholder_focus = $c_field;
                                break;
                            }
                            if (!field_found){
                                $placeholder_focus = $c_field;
                                field_found = true;
                            }
                        }
                    }
                }
                $fx.front.select_item($placeholder_focus);
            }
        );
    };
    
    function hide_placeholder() {
        $fx.front.disable_hilight();
        $placeholder
          .css(get_size())
          .animate(
            null_size, 
            speed,
            null,
            function() {
                $placeholder.removeClass('fx_entity_adder_placeholder_active').css(null_size);
                
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
                $fx.front.enable_hilight();
            }
        );
        $block_mark.slideDown(speed);
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
    }

    show_placeholder();
    
    $fx.front.scrollTo($placeholder, true);
    
    
    $placeholder.off('fx_deselect').on('fx_deselect', function(e) {
        setTimeout(function() {
            var $c_selected_placeholder = 
                    $($fx.front.get_selected_item())
                    .closest('.fx_entity_adder_placeholder');
            
            // don't hide placeholder the selected node is inside it
            if (
                $c_selected_placeholder.length 
                && $c_selected_placeholder[0] === $placeholder[0]
            ) {
                return;
            }
            
            // don't hide placeholder if we are reloading infoblock
            var $ib = $placeholder.closest('.fx_infoblock');
            if ($ib.is('.fx_infoblock_disabled')) {
                return;
            }
            hide_placeholder();
        }, 100);
    });
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

//fx_front.prototype.get_adder_closure = function(meta, $infoblock_node, $current_node) {

// generate function to open "add smth" form in panel
fx_front.prototype.get_panel_adder_closure = function(meta) {
    return function(e) {
        var form_data = {
           entity:'content',
           action:'add_edit',
           content_type:meta.type,
           infoblock_id:meta.infoblock_id,
           parent_id:meta.parent_id
        };
        var $current_node = $($fx.front.get_selected_item());
        var $ib_node = $current_node.closest('.fx_infoblock');
        var entity_meta = $current_node && $current_node.data('fx_entity');
        if (entity_meta) {
            var $button = $(e.target);
            var curr_node_id = entity_meta[0];
            if ($button.hasClass('fx_before')) {
                form_data.__move_before = curr_node_id;
            } else if ($button.hasClass('fx_after')) {
                form_data.__move_after = curr_node_id;
            }
        }
        $fx.front_panel.load_form(
            form_data, 
            {
            //view:'cols',
            onfinish:function() {
                $fx.front.reload_infoblock($ib_node);
            }
        });
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

fx_front.prototype.redraw_add_button = function($node) {
    if (!$node || $node.is('.fx_entity_adder_placeholder') || !$node.is('.fx_infoblock, .fx_area, .fx_entity')) {
        return;
    }
    var get_neighbour_buttons = function(between_text) {
        between_text = '<span class="fx_button__add_text">'+between_text+'</span>';
        if (!$node.is('.fx_entity') || !$node.is('.fx_sortable')) {
            return between_text;
        }
        var res = ' <a class="fx_button__extra fx_before" title="'+$fx.lang('Before')+'">&#9668;</a>';
        if (typeof  between_text !== 'undefined') {
            res += ' ' + between_text+' ';
        }
        res += ' <a class="fx_button__extra fx_after" title="'+$fx.lang('After')+'">&#9658;</a>';
        return res;
    };
    var mode = $fx.front.mode,
        buttons = [],
        $ib_node = $node.closest('.fx_infoblock'),
        ib_accept = ($ib_node.data('fx_controller_meta') || {}).accept_content;
    
    if ($node.is('.fx_entity') && mode === 'edit') {
        
        /*
        var is_top_entity = $fx.front.is_top_entity($node),
            $placeholder = $('>.fx_entity_adder_placeholder', $node.parent());
        
        if ($placeholder.length) {
            var placeholder_meta = $placeholder.data('fx_entity_meta') || {},
                placeholder_name = placeholder_meta.placeholder_name;
            buttons.push({
                name:  get_neighbour_buttons( $fx.lang('Add') + ' ' + placeholder_name.toLowerCase() ),
                callback: $fx.front.get_placeholder_adder_closure($placeholder)
            });
        }
        
        for (var i = 0; is_top_entity && ib_accept && i < ib_accept.length; i++) {
            var c_meta = ib_accept[i];
            if ($fx.front.find_placeholder_by_meta(c_meta, $placeholder)) {
                continue;
            }
            buttons.push({
                name: get_neighbour_buttons( c_meta.title ),
                callback: $fx.front.get_panel_adder_closure(c_meta)
            });
        };
        */
        
        var extra_accept = ($node.data('fx_entity_meta') || {}).accept_content || [];
        $.each(extra_accept, function () {
            buttons.push({
                name: this.title,
                callback: $fx.front.get_panel_adder_closure(this)
            });
        });
    } else if (ib_accept && mode === 'edit') {
        $.each(ib_accept, function () {
            var $placeholder  = $fx.front.find_placeholder_by_meta(this, $node.find('.fx_entity_adder_placeholder'));
            buttons.push({
                name: this.title,
                callback: $placeholder ? 
                                $fx.front.get_placeholder_adder_closure($placeholder) :
                                $fx.front.get_panel_adder_closure(this)
            });
        });
    }
    
    var $c_area = $node.closest('.fx_area');
    
    if (mode === 'design' && $c_area.length) {
        var area_meta = $fx.front.get_area_meta($c_area);
        if (area_meta) {
            buttons.push({
                name:$fx.lang('Add block to')+' '+(area_meta.name || area_meta.id),
                callback: function() {
                    $fx.front.add_infoblock_select_controller($c_area);
                }
            });
        }
    }
    if (buttons.length === 0) {
        return;
    }
    if (buttons.length === 1) {
        buttons[0].is_add = true;
        $fx.front.add_panel_button(buttons[0]);
        return;
    }
    $fx.front.add_panel_button({
        name:$fx.lang('Add'),
        dropdown:buttons,
        callback:function() {
            
        }
    });
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
    var $area_node = $node.closest('.fx_area');
    var container_infoblock = $node.closest('.fx_infoblock').not('body').data('fx_infoblock');
    var area_meta = $fx.front.get_area_meta($area_node);
    
    if ($rel_node && $rel_node.length) {
        var $stub = $('<div class="fx_infoblock_stub">Choose block type</div>');
        rel_position === 'after' ? $rel_node.after($stub) : $rel_node.before($stub);
        $fx.front.select_item($stub[0]);
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
    
    if ($rel_node && $rel_node.data('fx_infoblock')) {
        form_data.rel_infoblock_id = $rel_node.data('fx_infoblock').id;
        form_data.rel_position = rel_position;
    }
    
    $fx.front_panel.load_form(form_data, {
        view:'vertical',
        oncancel:function() {
            $('.fx_infoblock_stub').remove();
        },
        onfinish: $fx.front.add_infoblock_select_settings
    });
};

fx_front.prototype.add_infoblock_select_settings = function(data) {
    var $area_node = $($fx.front.get_selected_item()).closest('.fx_area');
    
    var infoblock_back = function () {
        $fx.front.add_infoblock_select_controller($area_node);
    };
    var cancel_adding = function() {
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
                        var first_meta = new_cm.accept_content[0];
                        var adder_closure = $fx.front.get_panel_adder_closure(first_meta);
                        $fx.front.load('edit');
                        $fx.front.select_item(new_ib_node.get(0));
                        adder_closure();
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
            
            var append_ib_node = function ($area_node, $ib_node) {
                
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
                append_ib_node($area_node, $c_ib_node);
                $c_ib_node.data('fx_infoblock', {id:'fake'});
                $form.data('ib_node', $c_ib_node);
                $form.data('is_waiting', false);
                if (callback instanceof Function) {
                    callback($c_ib_node);
                }
            };
            var ib_loader = null, 
                is_waiting = false;
            $form.on('change.fx_front', function(e) {
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
                            $fx.front.select_item($new_ib_node.get(0));
                        };
                        if (!$new_ib_node || $new_ib_node.length === 0) {
                            add_fake_ib(add_ib_to_form);
                        } else {
                            add_ib_to_form($new_ib_node);
                        }
                    }, 
                    {override_infoblock:$form.serialize()}
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
    
    var check_event = $.Event('fx_check_is_selectable')
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
            if (n.hasClass('fx_area')) {
                if ($('.fx_infoblock', n).length === 0) {
                    return true;
                }
            }
            return false;
        case 'edit':
            
            // adder placeholders and fields inside are not selectable (mainly for keyboard navigation)
            var $placeholder = n.closest('.fx_entity_adder_placeholder');
            if ($placeholder.length > 0 && !$placeholder.hasClass('fx_entity_adder_placeholder_active')) {
                return false;
            }
            
            // select an entity to show "edit - delete - move" buttons
            //if (n.hasClass('fx_entity') || n.hasClass('fx_accept_content')) {
            if (n.hasClass('fx_entity')) {
                return true;
            }
            
            // select a block to show "add" button
            var c_meta = n.data('fx_controller_meta');
            //if (c_meta && (c_meta.accept_content || c_meta.fields)) {
            if (
                c_meta && (
                    c_meta.fields ||
                    c_meta.hidden ||
                    $fx.front.node_is_empty(n)
                )
            ) {
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
    var $entity = $node.closest('.fx_entity');
    
    if ($entity.length === 0) {
        return false;
    }
    
    if ($('.fx_template_var, .fx_template_var_in_att', $entity).length === 1) {
        $node.addClass('fx_var_bound_to_entity');
        return true;
    }
    
    if (!$node.is(':visible') || $('.fx_template_var', $entity).length > 1) {
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

fx_front.prototype.select_item = function(node) {
    var c_selected = this.get_selected_item();
    if (c_selected === node) {
        return;
    }
    this.deselect_item();
    this.selected_item = node;
    var $node = $(node);
    node = $node[0];
    $fx.front.outline_block_off($node);
    $fx.front.outline_block_off($node.find('.fx_hilight_hover'));
    $fx.front.outline_block($node, 'selected');
    
    if (!this.node_panel_disabled) {
        this.make_node_panel($node);
    }
    
    var closest_ib_node = $node.closest('.fx_infoblock')[0];
   
    $fx.front.freeze_events(closest_ib_node);
    $node.one('fx_deselect', function() {
        $fx.front.unfreeze_events(closest_ib_node);
    });
   
    if (!$node.is('.fx_entity_adder_placeholder')) {
        var selectable_up = this.get_selectable_up();
        if (selectable_up && !$(selectable_up).hasClass('fx_entity')) {
            //$fx.buttons.bind('select_block', $fx.front.select_level_up);
            //$fx.front.add_panel_button('select_block', $fx.front.select_level_up);
        }
    }
    
    $node.addClass('fx_selected').trigger('fx_select');
    
    if ($fx.front.mode === 'edit') {
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
    }
    if ($node.is('.fx_infoblock')) {
        $fx.front.select_infoblock($node);
    }
    $fx.front.redraw_add_button($node);
    
    var scrolling = false;
    setTimeout(function() {
        if (!scrolling && !$node.hasClass('fx_edit_in_place')) {
            $fx.front.scrollTo($node, true, function() {
                scrolling = false;
            });
            scrolling = true;
        }
    }, 150);
    
    // if you delete the selected node from the tree pull deselect_item()
    $(node).bind('remove.deselect_removed', function(e) {
        $fx.front.deselect_item();
    });
    
    $fx.front.disable_hilight();
    
    $('html').on('keydown.fx_selected', function(e) {
        if ($fx.front_panel.is_visible) {
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
        $node.off('.fx_recount_outlines');
        
        $node.off('.fx_catch_mouseout');
        $fx.front.enable_hilight();
        $node.
                removeClass('fx_selected').
                removeClass('fx_hilight_hover').
                trigger('fx_deselect').
                unbind('remove.deselect_removed');
        
        $fx.front.outline_block_off($node);
        this.node_panel.remove($node);
    }
    this.selected_item = null;
    $fx.buttons.unbind('select_block');
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
    var fx_selector = '.fx_template_var, .fx_area, .fx_template_var_in_att, .fx_entity, .fx_infoblock, .fx_hidden_placeholded';
    var items = $(fx_selector, container).not('.fx_unselectable');
    if (container.not('.fx_unselectable').is(fx_selector)) {
        items = items.add(container);
    }
    items.
        off('.fx_recount_outlines').
        off('.fx_recount_adders').
        removeClass('fx_hilight').
        removeClass('fx_hilight_empty').
        removeClass('fx_hilight_empty_inline').
        removeClass('fx_var_bound_to_entity').
        removeClass('fx_no_hilight').
        removeClass('fx_has_inline_adder').
        removeClass('fx_clearfix').
        removeClass('fx_placeholded_collection');
    if ($fx.front.mode !== 'edit' || container.is('html')) {
        $('.fx_inline_adder').remove();
        items.removeClass('fx_accept_neighbours');
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
        
        if (i.hasClass('fx_entity_hidden')) {
            $fx.front.outline_block(i, 'hidden');
        }

        if (meta.accept_content) {
            i.addClass('fx_accept_content');
        }
        
        var is_selectable = $fx.front.is_selectable(item);
        
        if (is_selectable || i.hasClass('fx_var_bound_to_entity')) {
            i.addClass('fx_hilight');
            // we add .fx_clearfix class to the nodes which are not floated but have floated children
            // so forcing them to have real size
            if (!i.css('float').match(/left|right/) && !i.css('display').match(/^inline/)) {
                i.children().each(function() {
                    if ($(this).css('float').match(/left|right/)) {
                        i.addClass('fx_clearfix');
                        return false;
                    }
                });
            }
            var hidden_placeholder = meta.hidden_placeholder;
            if ($fx.front.node_is_empty(i) || i.is('.fx_infoblock_hidden')) {
                if ( i.hasClass('fx_template_var') ) {
                    var var_meta = i.data('fx_var');
                    hidden_placeholder = var_meta.label ? var_meta.label : var_meta.id; //i.data('fx_var').label;
                    if (var_meta.type === 'html' && !var_meta.linebreaks) {
                        hidden_placeholder = '<p>'+hidden_placeholder+'</p>';
                    }
                } else if (i.hasClass('fx_infoblock') && !hidden_placeholder) {
                    var ib_meta = i.data('fx_infoblock');
                    hidden_placeholder = $fx.lang('This block is empty');
                }
            }
            var is_hidden = false;
            if (hidden_placeholder) {
                var $adder_placeholder = i.find('.fx_entity_adder_placeholder').first();
                var mark_tag = 'div',
                    mark_colspan = null;
                if ($adder_placeholder.length) {
                    var $placeholded = $adder_placeholder.parent();
                    mark_tag = $adder_placeholder[0].nodeName;
                    $placeholded.addClass('fx_placeholded_collection');
                    if (mark_tag === 'TR') {
                        mark_colspan = $adder_placeholder.children().length;
                    }
                } else {
                    $placeholded = i;
                }
                $placeholded.addClass('fx_hidden_placeholded');
                var $children = $placeholded.children();
                if ($children.length) {
                    if (mark_tag === 'TR') {
                        hidden_placeholder = '<td colspan="'+mark_colspan+'">'+hidden_placeholder+'</td>';
                    }
                    var $hidden_placeholder = $('<'+mark_tag+' class="fx_hidden_placeholder_mark">'+hidden_placeholder+'</'+mark_tag+'>');
                    $children.first().before($hidden_placeholder);
                } else {
                    $placeholded.html(hidden_placeholder);
                }
            } else if (i.width() === 0 || i.height() === 0) {
                if ($fx.front.node_is_empty(i)) {
                    is_hidden = true;
                    var $parents = i.parents();
                    for (var j = 0; j < $parents.length; j++ ) {
                        if ($parents.eq(j).css('display') === 'none') {
                            is_hidden = false;
                            break;
                        }
                    }
                }
            }
            
            
            if (is_hidden || (i.is('.fx_area') && $fx.front.node_is_empty(i)) ) {
                if (i.hasClass('fx_area')) {
                    var a_meta = i.data('fx_area');
                    var area_placeholder = $fx.lang('Area %s is empty, you can add some blocks here.');
                    area_placeholder = area_placeholder.replace(/\%s/, a_meta.name ? a_meta.name : a_meta.id);
                    i.html(area_placeholder);
                    i.addClass('fx_hidden_placeholded');
                } else {
                    i.addClass('fx_hilight_empty');
                    if (i.css('display') === 'inline') {
                        i.addClass('fx_hilight_empty_inline');
                    }
                    i.parents().filter('.fx_hilight_empty').removeClass('fx_hilight_empty');
                }
            }
        }
    });
    
    if (mode === 'edit') {
        items.filter('.fx_entity').each(function(index, entity) { 
            var i = $(entity);
            if (i.hasClass('fx_accept_neighbours')) {
                return;
            }
            var $entity_parent = i.parent();
            var $placeholder = $entity_parent.find('>.fx_entity_adder_placeholder');
            if ($placeholder.length > 0) {
                $entity_parent.find('>.fx_entity').addClass('fx_accept_neighbours');
                $entity_parent.data('fx_neighbour_placeholder', $placeholder);
                $fx.front.create_inline_entity_adder($entity_parent);
            }
        });
    }  else if (mode === 'design') {
        items.filter('.fx_area').each(function(index, i) {
            $fx.front.create_inline_infoblock_adder( $(i) );
        });
    }
    
    if ($fx.front.is_jquery_overriden()) {
        $('.fx_hilight').bind('click.fx_front', $fx.front.handle_click);
    }
    $('.fx_hilight_outline .fx_hilight').addClass('fx_hilight_outline');
};

fx_front.prototype.get_list_orientation = function($entities) {
    
    var is_x = true;
    var is_y = true;
    var c_x = -1,
        c_y = -1,
        treshold = 2;
    
    var $entities_visible = $entities.filter(':visible');
    
    if ($entities_visible.length < 2) {
        return null;
    }
    
    $entities_visible.each(function()  {
        var $entity = $(this);
        var o  = $entity.offset();
        if (c_y - o.top > treshold ) {
            is_y = false;
        }
        if (c_x - o.left > treshold) {
            is_x = false;
        }
        c_x = o.left + $entity.width();
        c_y = o.top + $entity.height();
    });
    var axis = is_x ? 'x' : is_y ? 'y' : null;
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
};

fx_front.prototype.select_content_entity = function($entity, $from_field) {
    // if true, child field is actually selected
    $from_field = $from_field || false;
    
    var entity_meta = $entity.data('fx_entity'),
        entity_meta_props = $entity.data('fx_entity_meta'),
        is_linker_placeholder = false;
    if (entity_meta_props) {
        var placeholder_data = entity_meta_props.placeholder;
        is_linker_placeholder = entity_meta_props.placeholder_linker;
    }

    var ib_node = $entity.closest('.fx_infoblock').get(0);
    var entity = $entity[0];
    
    if ($from_field) {
        $fx.front.outline_block($entity, 'selected_light');
    }
    //$fx.front.make_node_panel($entity);
    
    
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
    
    var edit_action_params = {
        entity:'content',
        action:'add_edit',
        content_type:entity_meta[1]
    };
    
    if (ce_id) {
        edit_action_params.content_id = entity_meta[0];
    } else if (placeholder_data) {
        edit_action_params = $.extend(edit_action_params, placeholder_data);
    }
    
    
    if (!is_linker_placeholder) {
        entity_panel.add_button(
            {
                keyword:'edit',
                type:'icon',
                label:'Edit '+$entity.data('fx_entity_name').toLowerCase()
            }, 
            function() {
                $fx.front.disable_node_panel();
                $fx.front.select_item(entity);
                $fx.front_panel.load_form(
                    edit_action_params, 
                    {
                        //view:'cols',
                        onfinish: function() {
                            $fx.front.reload_infoblock(ib_node);
                        },
                        oncancel: function() {

                        }
                    }
                );
            }
        );
    }
    
    if (ce_id){
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
                onfinish: function() {
                    $fx.front.reload_layout();
                }
            });
        });
    }
    
    if (!is_linker_placeholder) {
        var publish_action = $entity.is('.fx_entity_hidden, .fx_entity_adder_placeholder') ? 'publish' : 'unpublish';
        entity_panel.add_button(
            publish_action,
            function(e) {
                var $b = $(e.target);
                var $publish_inp = $('.field_name__is_published input[type="checkbox"]', entity_panel.$panel);
                if ($b.hasClass('fx_icon-type-publish')) {
                    $b
                        .removeClass('fx_icon-type-publish')
                        .addClass('fx_icon-type-unpublish');
                    $publish_inp[0].checked = true;
                } else {
                    $b
                        .removeClass('fx_icon-type-unpublish')
                        .addClass('fx_icon-type-publish');
                    $publish_inp[0].checked = false;
                }
            }
        );
    }
    
    if (
        $fx.front.get_sortable_entities(
            $entity.parent()
        )
        && ce_id
    ) {
        entity_panel.add_button('move', function() {
            var $b = $(this);
            if ($b.hasClass('fx_admin_button_move_active')) {
                $b.removeClass('fx_admin_button_move_active');
                $fx.front.stop_entities_sortable($entity.parent());
                $fx.front.deselect_item();
                $fx.front.select_item($entity);
            } else {
                $b.addClass('fx_admin_button_move_active');
                var eip = $entity.data('edit_in_place');
                if (eip) {
                    eip.stop();
                    eip.restore();
                }
                $fx.front.start_entities_sortable($entity.parent());
            }
        });
        //var $sorter = '<div class="fx_icon fx_icon-type-move fx_inline_sorter"></div>';
        //$entity.append($sorter);
    }
    
    if ($entity.is('.fx_template_var, .fx_template_var_in_att')) {
        $entity.edit_in_place();
    }
    
    
    
    function add_text_field_label(panel, $field_node) {
        var field_meta = $field_node.data('fx_var');
        if (!field_meta) {
            return;
        }
        if (field_meta.type === 'string' || field_meta.type === 'text' || field_meta.type === 'html') {
            /*
            panel.$panel.append('<div class="fx_node_panel_separator"></div>');
            panel.add_label(
                'Editing: '+field_meta.label
            );
            */
        }
    }
    
    if (!$from_field) {
        var $bound_to_edit = $([]);
        $('.fx_var_bound_to_entity', $entity).each(function() {
            var $bound = $(this);
            if ($bound.closest('.fx_entity')[0] === entity) {
                add_text_field_label(entity_panel, $bound);
                $bound_to_edit = $bound_to_edit.add($bound);
            }
        });
        $bound_to_edit.edit_in_place();
    } else if ($from_field.is('.fx_template_var')) {
        var field_panel = $fx.front.node_panel.get($from_field);
        add_text_field_label(field_panel, $from_field);
    }
    $('html').one('fx_deselect', function(e) {
        $fx.front.stop_entities_sortable();
        $fx.front.outline_block_off($entity);
    });
    
};

fx_front.prototype.select_infoblock = function(n) {
    
    if ($fx.front.mode === 'edit') {
        n.edit_in_place();
    }
    
    if ($fx.front.mode !== 'design') {
        return;
    }
    $fx.front.add_panel_button('settings', function() {
        var ib_node = n;
        var ib = $(ib_node).data('fx_infoblock');
        if (!ib) {
            return;
        }
        var area_node = $fx.front.get_area_node(ib_node);//ib_node.closest('.fx_area');
        var area_meta = $fx.front.get_area_meta(area_node);
        
        var is_waiting = false, 
            ib_loader = null;
            
        $fx.front_panel.load_form({
            entity:'infoblock',
            action:'select_settings',
            id:ib.id,
            visual_id:ib.visual_id,
            page_id: $fx.front.get_page_id(), //$('body').data('fx_page_id'),
            fx_admin:true,
            //area_size:area_size
            area:area_meta
        }, {
            view:'horizontal',
            onfinish:function() {
                $fx.front.reload_infoblock($('.fx_infoblock_'+ib.id));
            },
            onready:function($form) {
                $form.data('ib_node', ib_node);
                $form.on('change.fx_front', function(e) {
                    if (e.target.name === 'livesearch_input') {
                        return;
                    }
                    if (is_waiting) {
                        ib_loader.abort();
                    }
                    is_waiting = true;
                    ib_loader = $fx.front.reload_infoblock(
                        $form.data('ib_node'), 
                        function($new_ib_node) {
                            $form.data('ib_node', $new_ib_node);
                            is_waiting = false;
                        }, 
                        {override_infoblock:$form.serialize()}
                    );
                });
            },
            oncancel:function($form) {
                $fx.front.reload_infoblock($form.data('ib_node'));
            }
        });
    });
    
    $fx.front.add_panel_button('delete', function() {
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
            onfinish: function() {
                $fx.front.reload_layout();
            }
        });
    });
    
    $fx.front.start_areas_sortable();
    
    $('html').one('fx_deselect', function() {
        $fx.buttons.unbind('settings');    
        $fx.buttons.unbind('delete');    
        $fx.front.stop_areas_sortable();
    });
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

fx_front.prototype.start_entities_sortable = function($cp) {
    var $entities = $fx.front.get_sortable_entities($cp);
    var placeholder_class = "fx_entity_placeholder";
    if ($entities.first().css('display') === 'inline') {
        placeholder_class += ' fx_entity_placeholder_inline';
    }
    $cp.addClass('fx_entity_container_sortable');
    
    var axis = $fx.front.get_list_orientation($entities);
    
    var sort_params = {
        axis:axis,
        items:$entities,
        placeholder: placeholder_class,
        forcePlaceholderSize : true,
        distance:3,
        helper:'clone',
        start:function(e, ui) {
            // dummy block with black border
            var ph = ui.placeholder;
            
            // original dragged item
            // it is hidden by this moment
            var item = ui.item;
            
            // item's clone wich is being dragged
            var helper = ui.helper;
            
            // we are to set helper's offset same as the original item had before jqueryui hide it
            // so we show the item, copy its offset and hide again
            item.show();
            helper.offset(item.offset());
            item.hide();
            
            // then put the item to the end
            // to preserve real number of items for :nth-child selector
            item.parent().append(item);
            
            ph.css({
                // -1px to fix sub-pixel size (like 33.333% of 100px)
                width: (helper.outerWidth() - 1) +'px',
                height:helper.outerHeight()+'px',
                'box-sizing':'border-box'
            });
            ph.attr('class', ph.attr('class')+ ' '+item.attr('class'));
            var $c_selected = $($fx.front.get_selected_item());
            $fx.front.outline_block_off($c_selected);
            $fx.front.disable_hilight();
            $fx.front.disable_node_panel();
        },
        stop:function(e, ui) {
            var ce = ui.item.closest('.fx_entity');
            var ce_data = ce.data('fx_entity');
            var ce_id = ce_data[2] || ce_data[0];
            var ce_type = ce_data[3] || ce_data[1];

            var next_e = ce.nextAll('.fx_entity').first();
            var next_id = null;
            if (next_e.length > 0) {
                var next_data = next_e.data('fx_entity');
                next_id = next_data[2] || next_data[0];
            }
            //$fx.front.disable_infoblock($cp.closest('.fx_infoblock'));
            $fx.post({
                entity:'content',
                action:'move',
                content_id:ce_id,
                content_type:ce_type,
                next_id:next_id
            }, function(res) {
                $fx.front.reload_infoblock($cp.closest('.fx_infoblock'));
                $fx.front.enable_node_panel();
            });
            //$fx.front.get_node_panel().show();
        }
    };
    $cp.sortable(sort_params);
};

fx_front.prototype.stop_entities_sortable = function(container) {
    if (!container) {
        container = $('.fx_entity_container_sortable');
    }
    if (!container.hasClass('fx_entity_container_sortable')) {
        return;
    }
    container.removeClass('fx_entity_container_sortable');
    container.sortable('destroy');
};

fx_front.prototype.set_mode_edit = function () {
    $fx.panel.one('fx.startsetmode', function() {
        $('html').off('.fx_edit_mode');
    });
};

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
    
    //console.log('sas', $areas);
};

fx_front.prototype._stop_areas_sortable = function() {
    $('.fx_area_sortable').each(function() {
        var $area = $(this);
        console.log($area.data('sortable'));
        $area.data('sortable').destroy();
        $area.removeClass('fx_area_sortable');
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
        //console.log('sorting', cp.attr('class'));
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
                /*
                ph.animate({
                    'height':'100px'
                }, 1000);
                item.css({overflow:'hidden'}).animate({
                    'height':'100px'
                }, 1000);
                */
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

fx_front.prototype.set_mode_design = function() {
    $fx.panel.one('fx.startsetmode', function() {
        $('html').off('.fx_design_mode');
    });
};

fx_front.prototype.disabled_infoblock_opacity = 0.8;

fx_front.prototype.disable_infoblock = function(infoblock_node) {
    // .css({opacity:'0.3'}).
    $(infoblock_node)
        .on('click.fx_fake_click', function() {
            return false;
        })
        .animate({opacity:$fx.front.disabled_infoblock_opacity}, 250)
        .addClass('fx_infoblock_disabled');
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
    //$('body').stop();
    var xhr = $.ajax({
        type:'post',
        data:post_data,
       url:'/~ib/'+meta.id+'@'+page_id,
       success:function(res) {
           $fx.front.c_hover = null;
           $infoblock_node.off('click.fx_fake_click');//.css({opacity:''});
           
           //$fx.front.outline_all_off();
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
               $infoblock_node.hide();
               $infoblock_node.before($new_infoblock_node);
               $infoblock_node.remove();
           }
           
           if (is_layout) {
               $fx.front.move_down_body();
           }
           $fx.front.hilight();
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

fx_front.prototype.move_down_body = function () {
    var panel_height = $('.fx-admin-panel').outerHeight();
    $('body').css('margin-top', '').css('margin-top','+='+panel_height+'px');
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
    if (!callback && button.href) {
        callback = function() {
            document.location.href = button.href;
        };
    }
    
    if (!button.type) {
        button.type = 'button';
    }
    
    var $node = $('<div class="' + (button.in_dropdown ? '' : 'fx_node_panel__item fx_node_panel__item-type-'+button.type)+'"></div>');
    
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
    
    if (!button.dropdown) {
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
    
    var styles = {
        hover: {
            size:2,
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
            offset:6
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
    var doc_width = $(document).width();
    var doc_scroll = $(document).scrollTop();
    var front_overlay = $fx.front.get_front_overlay();
    var overlay_offset = parseInt(front_overlay.css('top'));
    
    var draw_lens = (style === 'selected_light') || (style === 'selected' && n.is('.fx_entity'));
    
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
                'z-index':css['z-index']
            };
            var win_height = $(window).height(),
                body_height = $('body').height();
            
            var win_size = {
                width:$(document).width(),
                //height:$(document).height()
                // $('body').height()
                height: Math.max(win_height, body_height) + $('body').offset().top
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
    //alert('stop');
    n.off('.fx_hide_hover_outlines');
    if (n.hasClass('fx_hilight_outline')) {
        return;
    }
    if (n.hasClass('fx_entity_hidden') && $fx.front.mode === 'edit') {
        $fx.front.outline_block(n, 'hidden');
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