(function($) {

window.fx_front = function () {
    this.mode = '';
    $('html').on('mouseover', function(e) {
        $fx.front.mouseover_node = e.target;
    });
    var more_menu = new fx_more_menu($fx.settings.more_menu); 
    more_menu.load();
           
    this.mode_menu = new fx_mode_menu();
    this.mode_menu.load();
    
    this.move_down_body();
    
    this.mode_selectable_selector = null;
    
    $('#fx_admin_page_modes').on('click', 'a', function() {
        var mode = $(this).attr('href').match(/\.([^\.]+)$/)[1];
        $fx.front.load(mode);
        return false;
    });
    
    $('html').on('keyup', function(e) {
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
                if ($(e.target).closest('.fx_var_editable').length > 0) {
                    break;
                }
                $('.fx_admin_button_delete', $panel).first().trigger('click');
                break;
        }
    });
    
    this.c_hover = null;
    
   $('html').on('fx_select', function(e) {
        var n = $(e.target);
        if ($fx.front.mode === 'edit') {
            if (n.is('.fx_essence')) {
                $fx.front.select_content_essence(n);
            }
            if (n.is('.fx_template_var, .fx_template_var_in_att')) {
                n.edit_in_place();
            }
        }
        if (n.is('.fx_infoblock')) {
            $fx.front.select_infoblock(n);
        }
        $fx.front.redraw_add_button(n);
        return false;
    });
};

// this code should fix firefox problem with loosing focus on contenteditable links ("a" tag)
// not used now
fx_front.prototype.handle_link_keydown = function(e) {
    var sel  = window.getSelection();
    var link = this;
    var KEY_PGUP = 33;
    var KEY_PGDN = 34;
    var KEY_END = 35;
    var KEY_DOWN = 40;
    var KEY_RIGHT = 39;
    var KEY_HOME = 36;

    if (e.which !== KEY_PGDN && e.which !== KEY_PGUP && e.which !== KEY_END && e.which !== KEY_DOWN && e.which !== KEY_RIGHT && e.which !== KEY_HOME) {
        return;
    }
    var right_out = sel.anchorNode.length - sel.anchorOffset === 1
                                            && !sel.anchorNode.nextSibling
                                            && sel.anchorNode.parentNode === this;
    if (e.which !== KEY_RIGHT || right_out) {
        var range = document.createRange();
        if (e.which === KEY_HOME) {
            range.setStart(link, 0);
            range.collapse(true);
        } else {
            range.selectNodeContents(link);
            range.collapse(false);
        }
        var sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(range);
        return false;
    }
};

fx_front.prototype.unselectable_selectors = [];
fx_front.prototype.make_unselectable = function (selector) {
    this.unselectable_selectors.push(selector);
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
        if (!$fx.front.is_selectable(this)) {
            return;
        }
        for ( var sel_index = 0; sel_index < $fx.front.unselectable_selectors.length; i++) {
            if (node.is($fx.front.unselectable_selectors[sel_index])) {
                return;
            }
        }
        $fx.front.outline_block_off($($fx.front.c_hover));
        $fx.front.c_hover = this;
        var $editable = $(e.target).closest('.fx_template_var');
        $target = $(e.target);
        var make_content_editable = $editable.length > 0 
                                    && $editable.data('fx_var').type !== 'datetime'
                                    && $fx.front.mode === 'edit' 
                                    && !($editable.get(0).nodeName === 'A' && e.ctrlKey);
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
                $('.fx_hilight_hover').removeClass('fx_hilight_hover');
                node.addClass('fx_hilight_hover');
                $fx.front.outline_block(node);
                if (make_content_editable) {
                    $editable.addClass('fx_var_editable').attr('contenteditable', 'true');
                }
            }, 
            $fx.front.c_hover ? 100 : 10
        );
        node.one('mouseout', function() {
            $fx.front.c_hover = null;
            if (node.closest('.fx_selected').length > 0) {
                return false;
            }
            setTimeout(
                function() {
                    if ($fx.front.c_hover !== node.get(0)) {
                        node.removeClass('fx_hilight_hover');
                        $fx.front.outline_block_off(node);
                        $editable.removeClass('fx_var_editable').attr('contenteditable', null);
                    }
                },
                100
            );
        });
        e.fx_hilight_done = true;
        return;
        return false;
    };

fx_front.prototype.handle_click = function(e) {
    if ($fx.front.mode === 'view' || $fx.front.select_disabled) {
        return;
    }
    var target = $(e.target);
    if (target.closest('.fx_overlay, .redactor_dropdown, #redactor_modal').length > 0) {
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
        document.location.href = clicked_link.attr('href')+document.location.hash;
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
        return false;
    }
    $fx.front.select_item(closest_selectable);
    return false;

};

fx_front.prototype.disable_hilight = function() {
    this.hilight_disabled = true;
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

fx_front.prototype.get_adder_closure = function(c_cnt) {
    var ib = $($fx.front.get_selected_item()).closest('.fx_infoblock');
    return function() {
        $fx.front.select_item(ib.get(0));

        $fx.front_panel.load_form({
           essence:'content',
           action:'add_edit',
           content_type:c_cnt.type,
           infoblock_id:c_cnt.infoblock_id,
           parent_id:c_cnt.parent_id
        }, 
            {
            view:'cols',
            onfinish:function() {
                $fx.front.reload_infoblock(ib);
            },
            oncancel:function() {

            }
        });
    };
};

fx_front.prototype.redraw_add_button = function(node) {
    $fx.buttons.unbind('add');
    var mode = $fx.front.mode;
    var buttons = [];
    if (!node) {
        return;
    }
    if (!node.is('.fx_infoblock, .fx_area')) {
        return;
    }
    var ib = node.closest('.fx_infoblock');
    var adders = [];
    var cm = ib.data('fx_controller_meta');
    if (cm && cm.accept_content) {
        for (var i = 0; i < cm.accept_content.length; i++) {
            var c_cnt = cm.accept_content[i];
            var cb_closure = $fx.front.get_adder_closure(c_cnt);
            adders.push(cb_closure);
            if (mode === 'edit') {
                buttons.push({
                    is_add:true,
                    name:c_cnt.title,
                    callback:cb_closure
                });
            }
        }
    }
    ib.data('content_adders', adders);
    
    var $c_area = node.closest('.fx_area');
    
    if (mode === 'design' && $c_area.length) {//node.is('.fx_area')) {
        var area_meta = $fx.front.get_area_meta($c_area);
        if (area_meta) {
            buttons.push({
                name:$fx.lang('Add block to')+' '+(area_meta.name || area_meta.id),
                is_add:true,
                callback: function() {
                    $fx.front.add_infoblock_select_controller($c_area);
                }
            });
        }
    }
    for (var i = 0; i < buttons.length; i++) {
        $fx.front.add_panel_button(buttons[i]);
    }
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

fx_front.prototype.add_infoblock_select_controller = function($node) {
    var $area_node = $node.closest('.fx_area');
    var container_infoblock = $node.closest('.fx_infoblock').not('body').data('fx_infoblock');
    var area_meta = $fx.front.get_area_meta($area_node);
    
    $fx.front.select_item($area_node.get(0));

    $fx.front_panel.load_form({
        essence:'infoblock',
        action:'select_controller',
        page_id:$fx.front.get_page_id(),
        container_infoblock_id: container_infoblock ? container_infoblock.id : null,
        area:area_meta,
        fx_admin:true
    }, {
        view:'vertical',
        onfinish: $fx.front.add_infoblock_select_settings
    });
};

fx_front.prototype.add_infoblock_select_settings = function(data) {
    var $area_node = $($fx.front.get_selected_item());
    
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
                    
                    var adders = new_ib_node.data('content_adders');
                    if (!adders || adders.length === 0 ){
                        $fx.front.select_item(new_ib_node.get(0));
                        return;
                    }
                    $fx.front.load('edit');
                    adders[0]();
                    setTimeout(function() {
                        $fx.front.select_item(new_ib_node.get(0));
                    },100);
                }
            );
        },
        onready:function($form) {
           var back = $('.form_header a.back', $form);
            back.on('click', function() {
                infoblock_back();
                cancel_adding();
            });

            // creating infoblock preview
            $fx.front.deselect_item();
            
            var append_ib_node = function ($area_node, $ib_node) {
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
            $form.on('change', function(e) {
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
        }
    });
};

fx_front.prototype.is_selectable = function(node) {
    var n = $(node);
    
    if (n.hasClass('fx_unselectable')) {
        return false;
    }
    
    
    switch($fx.front.mode) {
        case 'view': default:
            return false;
        case 'design':
            return n.hasClass('fx_area') || n.hasClass('fx_infoblock');
        case 'edit':
            if (n.hasClass('fx_essence') || n.hasClass('fx_accept_content')) {
                return true;
            }
            var c_meta = n.data('fx_controller_meta');
            if (c_meta && (c_meta.accept_content || c_meta.fields)) {
                return true;
            }
            if ( n.hasClass('fx_template_var') || n.hasClass('fx_template_var_in_att') ) {
                if ($fx.front.is_var_bound_to_essence(n)) {
                    return false;
                }
                return true;
            }
            return false;
    }
};

fx_front.prototype.is_var_bound_to_essence = function($node) {
    if (!$node.is(':visible')) {
        return false;
    }
    if ($node.hasClass('fx_var_bound_to_essence')) {
        return true;
    }
    var $essence = $node.closest('.fx_essence');
    
    if ($essence.length === 0) {
        return false;
    }
    
    if ($('.fx_template_var, .fx_template_var_in_att', $essence).length === 1) {
        $node.addClass('fx_var_bound_to_essence');
        return true;
    }
    
    var distance = 35;
    var eo = $essence.offset();
    var no = $node.offset();
    
    if (Math.abs(eo.top - no.top) > distance) {
        return false;
    }
    if (Math.abs(eo.left - no.left) > distance) {
        return false;
    }
    if (Math.abs($node.outerWidth() - $essence.outerWidth()) > distance) {
        return false;
    }
    if (Math.abs($node.outerHeight() - $essence.outerHeight()) > distance) {
        return false;
    }
    $node.addClass('fx_var_bound_to_essence');
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
    $fx.front.outline_block($node, 'selected');
    
    if (!this.node_panel_disabled) {
        this.make_node_panel($node);
    }
    
    $node.on('mouseout.fx_catch_mouseout', function (e) {
       e.stopImmediatePropagation();
    });
    
    var selectable_up = this.get_selectable_up();
    if (selectable_up) {
        $fx.buttons.bind('select_block', $fx.front.select_level_up);
        $fx.front.add_panel_button('select_block', $fx.front.select_level_up);
    } else {
        $fx.buttons.unbind('select_block', $fx.front.select_level_up);
    }
    
    $node.addClass('fx_selected').trigger('fx_select');
    
    
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
        
        var $selectable = $('.fx_hilight');
        var c_index = $selectable.index(node);
        var is_left = (e.which === 38 || (e.which === 9 && e.shiftKey));
        var ii = is_left ? -1 : 1;
        var ie = is_left ? 0 : $selectable.length;
        for (var i = c_index + ii; i !== ie + ii; i+= ii ) {
            var $ci = $selectable.eq(i);
            if ($fx.front.is_selectable($ci)) {
                $fx.front.select_item($ci);
                return;
            }
        }
    });
};

fx_front.prototype.make_node_panel = function($node) {
    if (!$node || $node.length === 0) {
        return;
    }
    var $overlay = this.get_front_overlay();
    var $panel = $('<div class="fx_node_panel fx_overlay"></div>');
    $overlay.append($panel);
    $node.data('fx_node_panel', $panel);
    var o = $node.offset();
    $panel.css({
        position:'absolute',
        visibility:'hidden',
        left:o.left - 4 + 'px',
        top: o.top - $panel.outerHeight() - 4 + 'px',
        'z-index': $node.data('fx_z_index') || (this.get_panel_z_index() + 1)
    });
    setTimeout(function() {
        $fx.front.recount_node_panel();
    }, 10);
    $(window).on('scroll', function() {$fx.front.recount_node_panel();});
    $panel.on('change keyup livesearch_value_loaded click', function () {
        setTimeout(function() {$fx.front.recount_node_panel();}, 10);
    });
    
};

fx_front.prototype.recount_node_panel = function() {
    var $p = this.get_node_panel();
    if (!$p) {
        return;
    }
    var $p_items = $p.children();
    if ($p_items.length === 0) {
        return;
    }
    $p.css({
        width:'2000px',
        visibility:'hidden'
    });
    var po = $p.offset();
    var p_left = po.left;
    var $lpi = $p_items.last();
    $lpi.css('margin-right', '3px');
    var p_right = $lpi.offset().left + $lpi.outerWidth() + parseInt($lpi.css('margin-right'));
    var p_width = p_right - p_left;
    var p_height = $p.outerHeight();
    var css = {
        width:p_right - p_left + 'px',
        visibility:'visible',
        opacity:1
    };
    var top_fix = 0;
    var $top_fixed_nodes = $('#fx_admin_panel, .fx_top_fixed');
    var doc_scroll = $(document).scrollTop();
    var screen_half = $('body').outerWidth() / 2;
    $top_fixed_nodes.each(function (index, item) {
        var $i = $(item);
        var i_top = $i.offset().top - doc_scroll;
        var i_bottom = i_top + $i.outerHeight();
        if (i_bottom > top_fix && $i.outerWidth() > screen_half) {
            top_fix = i_bottom;
        }
    });
    
    var $node = $($fx.front.get_selected_item());
    var no = $node.offset();
    css.left = no.left - 4;
    var node_height = $node.data('fx_visible_height') || $node.outerHeight();
    var node_top = no.top;
    var node_bottom = node_top + node_height;
    var break_top = node_top - top_fix - p_height - 4;
    var break_bottom = break_top + node_height + p_height;
    
    if (doc_scroll >= break_bottom) {
        // set panel beneath the node
        var offset_top = node_bottom + 4 ;
        css.top = offset_top + 'px';
        css.position = 'absolute';
        $p.removeClass('fx_node_panel_fixed');
    } else if (doc_scroll <= break_top) {
        // set panel above the node
        css.position = 'absolute';
        css.top = node_top - p_height - 4 + 'px';
        $p.removeClass('fx_node_panel_fixed');
    } else {
        var bottom_edge_visible = doc_scroll + $(window).height() > node_bottom + p_height;
        if (bottom_edge_visible) {
            css.top = node_bottom + 4 + 'px';
            css.position = 'absolute';
            $p.removeClass('fx_node_panel_fixed');
        } else {
            css.position = 'fixed';
            css.top = top_fix+'px';
            //css.opacity = 0.7;
            $p.addClass('fx_node_panel_fixed');
        }
    }
    var p_gone = (css.left + p_width) - $(window).outerWidth() + 10;
    
    if (p_gone > 0) {
        css.left = css.left - p_gone;
    }
    $p.css(css);
};

fx_front.prototype.get_selected_item = function() {
    return this.selected_item;
};

fx_front.prototype.deselect_item = function() {
    var selected_item = this.get_selected_item();
    if (selected_item) {
        var $node = $(selected_item);
        $node.off('.recount_outlines');
        
        $node.off('.fx_catch_mouseout');
        $fx.front.enable_hilight();
        $node.
                removeClass('fx_selected').
                removeClass('fx_hilight_hover').
                trigger('fx_deselect').
                unbind('remove.deselect_removed');
        
        $fx.front.outline_block_off($node);
        var $panel = $node.data('fx_node_panel');
        if ($panel) {
            $panel.remove();
        }
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
    return  $n.text().match(/^\s*$/) 
                && !$n.is('img') 
                && $('img', $n).length === 0;
};

fx_front.prototype.hilight = function() {
    var items = $('.fx_template_var, .fx_area, .fx_template_var_in_att, .fx_essence, .fx_infoblock').not('.fx_unselectable');
    items.
        removeClass('fx_hilight').
        removeClass('fx_hilight_empty').
        removeClass('fx_hilight_empty_inline').
        removeClass('fx_var_bound_to_essence').
        removeClass('fx_no_hilight').
        removeClass('fx_clearfix');
    $('.fx_hilight_hover').removeClass('fx_hilight_hover');
    items.filter('.fx_hidden_placeholded').removeClass('fx_hidden_placeholded').html('');
    
    if ($fx.front.mode === 'view') {
        $('.fx_infoblock_hidden').hide();
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
        
        var is_selectable = $fx.front.is_selectable(item);
        
        if (is_selectable || i.hasClass('fx_var_bound_to_essence')) {
            i.addClass('fx_hilight');
            if (!i.css('float').match(/left|right/) && !i.css('display').match(/^inline/)) {
                i.addClass('fx_clearfix');
            }
            var hidden_placeholder = meta.hidden_placeholder;
            if ($fx.front.node_is_empty(i) ) {
                if ( i.hasClass('fx_template_var') ) {
                    var var_meta = i.data('fx_var');
                    hidden_placeholder = var_meta.label ? var_meta.label : var_meta.id; //i.data('fx_var').label;
                } else if (i.hasClass('fx_infoblock') && !hidden_placeholder) {
                    var ib_meta = i.data('fx_infoblock');
                    hidden_placeholder = $fx.lang('Infoblock #'+ ib_meta.id + ' is empty');
                }
            }
            var is_hidden = false;
            if (hidden_placeholder) {
                i.html(hidden_placeholder);
                i.addClass('fx_hidden_placeholded');
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
            
            if (is_hidden){
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
    if ($fx.front.is_jquery_overriden()) {
        $('.fx_hilight').bind('click.fx_front', $fx.front.handle_click);
    }
    $('.fx_hilight_outline .fx_hilight').addClass('fx_hilight_outline');
};

fx_front.prototype.is_jquery_overriden = function() {
    // if jquery is overriden by template script (another version is used)
    // we will attach click listeners to each hilightable node
    // it is slower, but we can be relatively sure 
    // that the event will not be prevented by client script (our listener is attached later)
    return window.jQuery !== window.$fxj;
};

fx_front.prototype.load = function ( mode ) {
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
    
    $fx.main_menu.set_active_item('site');
        
    
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
      
    this.mode_menu.set_active(this.mode);
            
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

fx_front.prototype.select_content_essence = function($essence) {
    var essence_meta = $essence.data('fx_essence');
    var ib_node = $essence.closest('.fx_infoblock').get(0);
    var essence = $essence[0];
    $fx.front.add_panel_button('edit', function() {
        $fx.front.select_item(essence);
        $fx.front_panel.load_form(
            {
                essence:'content',
                action:'add_edit',
                content_id: essence_meta[0],
                content_type:essence_meta[1]
            }, 
            {
                view:'cols',
                onfinish: function() {
                    $fx.front.reload_infoblock(ib_node);
                },
                oncancel: function() {
                    
                }
            }
        );
    });
    
    $fx.front.add_panel_button('delete', function() {
       if (confirm($fx.lang("Are you sure?"))) {
           
           $fx.front.disable_infoblock(ib_node);
           var ce_type = essence_meta[3] || essence_meta[1];
           var ce_id = essence_meta[2] || essence_meta[0];
           $fx.post({
               essence:'content',
               action:'delete_save',
               content_type:ce_type,
               content_id:ce_id,
               page_id:$fx.front.get_page_id() //$('body').data('fx_page_id')
           }, function () {
               $fx.front.reload_infoblock(ib_node);
           });
       }
    });
    
    if (
        $fx.front.get_sortable_essences(
            $essence.parent()
        )
    ) {
        $fx.front.add_panel_button('move', function() {
            var $b = $(this);
            if ($b.hasClass('fx_admin_button_move_active')) {
                $b.removeClass('fx_admin_button_move_active');
                $fx.front.stop_essences_sortable($essence.parent());
                $fx.front.deselect_item();
                $fx.front.select_item($essence);
            } else {
                $b.addClass('fx_admin_button_move_active');
                var eip = $essence.data('edit_in_place');
                if (eip) {
                    eip.stop();
                    eip.restore();
                }
                $fx.front.start_essences_sortable($essence.parent());
            }
        });
    }
    
    //
    var meta_extra = $essence.data('fx_essence_meta');
    if (meta_extra && meta_extra.accept_content) {
        $.each(meta_extra.accept_content, function () {
            $fx.front.add_panel_button({
                name: this.title,
                is_add:true,
                callback: $fx.front.get_adder_closure(meta_extra.accept_content[0])
            });
        });
    }
    
    var $bound_to_edit = $([]);
    $('.fx_var_bound_to_essence', $essence).each(function() {
        var $bound = $(this);
        if ($bound.closest('.fx_essence')[0] === essence) {
            $bound_to_edit = $bound_to_edit.add($bound);
        }
    });
    $bound_to_edit.edit_in_place();
    $('html').one('fx_deselect', function(e) {
        $fx.front.stop_essences_sortable();
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
        
        $fx.front_panel.load_form({
            essence:'infoblock',
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
                $form.on('change', function(e) {
                    if (e.target.name === 'livesearch_input') {
                        return;
                    }
                    if ($form.data('is_waiting')) {
                        return;
                    }
                    $form.data('is_waiting', true);
                    $fx.front.reload_infoblock(
                        $form.data('ib_node'), 
                        function($new_ib_node) {
                            $form.data('ib_node', $new_ib_node);
                            $form.data('is_waiting', false);
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
            essence:'infoblock',
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

fx_front.prototype.get_sortable_essences = function($cp) {
    var sortable_items_selector = ' > .fx_essence.fx_sortable.fx_hilight';
    var $essences = $(sortable_items_selector, $cp);
    if ($essences.length < 2 || $cp.hasClass('fx_not_sortable')) {
        return false;
    }
    return $essences;
}

fx_front.prototype.start_essences_sortable = function($cp) {
    var $essences = $fx.front.get_sortable_essences($cp);
    var placeholder_class = "fx_essence_placeholder";
    if ($essences.first().css('display') === 'inline') {
        placeholder_class += ' fx_essence_placeholder_inline';
    }
    $cp.addClass('fx_essence_container_sortable');
    
    var is_x = true;
    var is_y = true;
    var c_x = null;
    var c_y = null;
    $essences.each(function()  {
        var o  = $(this).offset();
        if (c_x === null){
            c_x = o.left;
        } else if (o.left !== c_x) {
            is_y = false;
        }
        if (c_y === null){
            c_y = o.top;
        } else if (o.top !== c_y) {
            is_x = false;
        }
    });
    var axis = is_x ? 'x' : is_y ? 'y' : null;
    
    var sort_params = {
        axis:axis,
        items:$essences,
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
                width:helper.outerWidth()+'px',
                height:helper.outerHeight()+'px',
                'box-sizing':'border-box'
            });
            ph.attr('class', ph.attr('class')+ ' '+item.attr('class'));
            var $c_selected = $($fx.front.get_selected_item());
            $fx.front.outline_block_off($c_selected);
            $fx.front.disable_hilight();
            $fx.front.get_node_panel().hide();
        },
        stop:function(e, ui) {
            var ce = ui.item.closest('.fx_essence');
            var ce_data = ce.data('fx_essence');
            var ce_id = ce_data[2] || ce_data[0];
            var ce_type = ce_data[3] || ce_data[1];

            var next_e = ce.nextAll('.fx_essence').first();
            var next_id = null;
            if (next_e.length > 0) {
                var next_data = next_e.data('fx_essence');
                next_id = next_data[2] || next_data[0];
            }
            $fx.front.disable_infoblock($cp.closest('.fx_infoblock'));
            $fx.post({
                essence:'content',
                action:'move',
                content_id:ce_id,
                content_type:ce_type,
                next_id:next_id
            }, function(res) {
                $fx.front.reload_infoblock($cp.closest('.fx_infoblock'));
            });
            $fx.front.get_node_panel().show();
        }
    };
    $cp.sortable(sort_params);
}

fx_front.prototype.stop_essences_sortable = function(container) {
    if (!container) {
        container = $('.fx_essence_container_sortable');
    }
    if (!container.hasClass('fx_essence_container_sortable')) {
        return;
    }
    container.removeClass('fx_essence_container_sortable');
    container.sortable('destroy');
};

fx_front.prototype.set_mode_edit = function () {
    $fx.panel.one('fx.startsetmode', function() {
        $('html').off('.fx_edit_mode');
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
                ph.animate({
                    'height':'100px'
                }, 1000);
                item.css({overflow:'hidden'}).animate({
                    'height':'100px'
                }, 1000);
                $c_selected = $($fx.front.get_selected_item());
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
                    essence:'infoblock',
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

fx_front.prototype.disable_infoblock = function(infoblock_node) {
    $(infoblock_node).css({opacity:'0.3'}).on('click.fx_fake_click', function() {
        return false;
    });
};

fx_front.prototype.reload_infoblock = function(infoblock_node, callback, extra_data) {
    
    var $infoblock_node = $(infoblock_node);
    $fx.front.disable_infoblock(infoblock_node);
    var ib_parent = $infoblock_node.parent();
    var meta = $infoblock_node.data('fx_infoblock');
    var page_id = $fx.front.get_page_id(); //$('body').data('fx_page_id');
    var post_data = {c_url:document.location.href};
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
           $infoblock_node.off('click.fx_fake_click').css({opacity:''});
           
           $fx.front.outline_all_off();
           $fx.front.deselect_item();
           
           var is_layout = infoblock_node.nodeName === 'BODY';

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
           } else {
               var $new_infoblock_node = $( $.trim(res) );
               $infoblock_node.hide();
               $infoblock_node.before($new_infoblock_node);
               $infoblock_node.remove();
           }
           
           $fx.front.hilight();
           $new_infoblock_node.trigger('fx_infoblock_loaded');
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
           
           if (is_layout) {
               $fx.front.move_down_body();
           }
           
           if (typeof callback === 'function') {
               callback($new_infoblock_node);
           }
           
       }
    });
    return xhr;
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
    $parents = $node.parents();
    for (var i = $parents.length - 1; i >= 0; i--) {
        if ($parents.eq(i).css('position') === 'fixed') {
            return;
        }
    }
    var body_offset = parseInt($('body').css('margin-top'));
    var top_offset = $node.offset().top - body_offset - 40;
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
        $('body').stop().scrollTo(
            top_offset,
            speed
            //800
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
    $('body').css('margin-top', '').css('margin-top','+=34px');
    $('.fx_top_fixed').css('top', '+=34px').css('z-index', 2674);
};

fx_front.prototype.get_node_panel = function() {
    return $($($fx.front.get_selected_item()).data('fx_node_panel'));
};

fx_front.prototype.add_panel_button = function(button, callback) {
    var $p = this.get_node_panel();
    if (!$p || $p.length === 0) {
        return;
    }
    if (typeof button !== 'string') {
        if (!callback) {
            callback = button.callback;
        }
        var $b = $('<div class="fx_admin_button_text fx_admin_button"><span>'+button.name+'</span></div>');
        //return;
    } else {
        var button_code = button;
        var $b = $('<div class="fx_admin_button_'+button_code+' fx_admin_button"></div>');
    }
    
    if (button.is_add) {
        $b.addClass('fx_add_button');
    }
    
    $b.click(callback);
    $p.append($b).show();
    return $b;
};

fx_front.prototype.outline_panes = [];


fx_front.prototype.get_front_overlay = function() {
    if (!this.front_overlay) {
        this.front_overlay = $(
            '<div class="fx_front_overlay" style="position:absolute; top:0px; left:0px;"></div>'
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

fx_front.prototype.outline_block = function(n, style) {
    if (!style) {
        style = 'hover';
    }
    if (!n || n.length === 0) {
        return;
    }
    if (n.hasClass('fx_hilight_outline')) {
        return;
    }
    // already hilighted 
    if (n.data('fx_outline_panes') && n.data('fx_outline_style') === style) {
        return;
    }
    
    if (style === 'selected') {
        var recount_outlines = function() {
            $fx.front.outline_block_off(n);
            $fx.front.outline_block(n, 'selected');
            $fx.front.recount_node_panel();
        };
        n.off('.recount_outlines').on('resize.recount_outlines keydown.recount_outlines', recount_outlines);
    }
    var o = n.offset();
    var overlay_offset = parseInt(this.get_front_overlay().css('top'));
    o.top -= overlay_offset > 0 ? overlay_offset : 0 ;
    var nw = n.outerWidth() + 1;
    var nh = n.outerHeight();
    var size = style === 'hover' ? 2 : 2;
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
    var front_overlay = $fx.front.get_front_overlay();
    var overlay_offset = parseInt(front_overlay.css('top'));
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
            box.width = (doc_width - c_left);
        }
        if (pane_position === 'fixed') {
            box.top += overlay_offset;
        }
        var css = {};
        // add px size
        $.each(box, function(i, v) {
            css[i] = Math.round(v)+'px';
        });
        var m = $(
            '<div class="fx_outline_pane '+ (pane_position === 'fixed' ? 'fx_outline_pane_fixed ' : '')+
                'fx_outline_pane_'+type+' fx_outline_style_'+style+'" />'
        );
        css['z-index'] = pane_z_index;
        css['position'] = pane_position;
        m.css(css);
        /*
        m.data('pane_props', $.extend(box, {
            type:type,
            vertical: type === 'left' || type === 'right'
        }));
        */
        front_overlay.append(m);
        return m;
    }
    var panes = {};
    var top_left_offset = 0;
    var top_top_offset = 0;
    var bottom_right_offset = 0;
    var bottom_bottom_offset = 0;
    if (n.css('display') === 'inline' && n.text() !== '') {
        var m_before = $('<span style="display:inline-block;width:1px; height:1px;background:#F00;"></span>');
        m_before.insertBefore(n.get(0).firstChild);
        var mbo = m_before.offset();
        // compare the height for cases where the test pixel is on the previous line one,
        // because it breaks, and the actual text - no
        if (mbo.top > o.top && (mbo.left - parseInt(n.css('padding-left')) - o.left) > 10) {
            top_left_offset = (mbo.left - o.left);
            top_top_offset = mbo.top - o.top + size*2 + 1;
            panes.top_left = make_pane({
                top:o.top - size,
                left:mbo.left - size,
                height: (mbo.top - o.top) + size*2,
                width:size
            }, 'left');
            panes.top_top = make_pane({
                top:mbo.top +size*2,
                left:o.left,
                width:mbo.left - o.left,
                height:size
            }, 'top');
        }
        m_before.remove();
        var m_after = $('<span style="display:inline-block;width:1px; height:1px;vertical-align: top; background:#FFF;"></spans>');
        n.append(m_after);
        var mao = m_after.offset();
        /// catch correctly cases where span-tester takes a string
        if (n.outerHeight() > nh) {
            mao.top = o.top;
            mao.left = o.left + nw;
        }
        
        if ( (o.left+nw) - (mao.left + parseInt(n.css('padding-right')) ) > 10) {
            bottom_right_offset = nw - (mao.left - o.left);
            bottom_bottom_offset = o.top+nh-mao.top;
            panes.bottom_right = make_pane({
                top:mao.top,
                left:mao.left,
                width:size,
                height:bottom_bottom_offset
            }, 'right');
            panes.bottom_bottom = make_pane({
                top:mao.top,
                left:mao.left,
                width: bottom_right_offset,
                height:size
            }, 'bottom');
        }
        
        m_after.remove();
    }
    if (pane_position==='fixed') {
        o.top -=$(window).scrollTop();
    }
    panes.top = make_pane({
        top: o.top - size,
        left: (o.left + top_left_offset),
        width:(nw-top_left_offset ),
        height:size
    }, 'top');
    panes.bottom = make_pane({
        top:o.top + nh,
        left:o.left,
        width: nw - bottom_right_offset,
        height:size
    }, 'bottom');
    panes.left = make_pane({
        top: (o.top - size + top_top_offset),
        left:o.left - size ,
        width:size,
        height: (nh + size*2 - top_top_offset)
        
    }, 'left');
    panes.right = make_pane({
        top:o.top - size ,
        left:o.left + nw ,
        width:size,
        height: (nh + size*2 - bottom_bottom_offset) 
    }, 'right');
    n.data('fx_outline_panes', panes);
    n.data('fx_outline_style', style);
};

fx_front.prototype.outline_block_off = function(n) {
    if (n.hasClass('fx_hilight_outline')) {
        return;
    }
    var panes = n.data('fx_outline_panes');
    if (!panes) {
        return;
    }
    for (var i in panes) {
        panes[i].remove();
    }
    n.data('fx_outline_panes', null);
    n.off('.recount_outlines');
    $(window).off('.recount_outlines');
};

fx_front.prototype.outline_all_off = function() {
    $('.fx_outline_pane').remove();
};


fx_front.prototype.disable_node_panel = function() {
    this.node_panel_disabled = true;
    var $p = this.get_node_panel();
    if ($p) {
        $p.hide();
    }
};

fx_front.prototype.enable_node_panel = function() {
    this.node_panel_disabled = false;
    var $p = this.get_node_panel();
    if ($p) {
        $p.show();
        $fx.front.recount_node_panel();
    }
};

fx_front.prototype.prepare_page_infoblock_form = function(settings) {
    if (settings.id !== 'page_infoblocks') {
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

$('html').on('fx_before_adm_form_created', function(e, settings) {
    if ($fx.front) {
        $fx.front.prepare_page_infoblock_form(settings);
    }
});

$('html').on('click', '.fx_item_help_block .level_expander', function() {
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

$('html').on('click', '.fx_help .fx_help_expander', function() {
   var $exp = $(this);
   var $help = $exp.data('help_node');
   if (!$help) {
        var $help = $(this).parent().find('.fx_help_data');
        $('body').append($help);
        $exp.data('help_node', $help);
   }
   var offset = $exp.offset();
   $help.css({top: offset.top+'px', left:offset.left+'px'});
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