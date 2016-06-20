(function($) {
    
//window.fx_no_hide=1;
    
fx_front.prototype.get_own_infoblocks = function($node) {
    var infoblocks = [],
        area_node = $node.closest('.fx_area')[0];
    
    //console.log(area_node, $('.fx_infoblock', $node));
    $('.fx_infoblock', $node).each(function() {
        var ib = this,
            $ib = $(this);
        
        $ib.parents().each(function() {
            var $par = $(this);
            if ($par.is('.fx_infoblock_wrapper')) {
                ib = this;
            } else if ($par.is('.fx_area')) {
                if (this === area_node) {
                    infoblocks.push(ib);
                }
                return false;
            }
        });
    });
    return $(infoblocks);
};
    
fx_front.prototype.create_inline_infoblock_adder = function($node) {
    if ($node.is('.fx_hidden_placeholded')) {
        return;
    }
    if ($node.closest('.fx_area').is('.fx_area_no_add')) {
        return;
    }
    
    var $button = $fx.front.create_inline_adder(
        $node, 
        $fx.front.get_own_infoblocks($node),
        '<span class="fx_adder_variant">'+$fx.lang('block')+'</span>',
        'infoblock'
    );

    
    $button.on('fx_place_adder', function() {
        var $ib = $button.data('rel_node');
        if (!$ib || !$ib.length) {
            $button.attr('title', $fx.lang('Add new block'));
            return;
        }
        var area = $ib.closest('.fx_area').data('fx_area');
        if (area) {
            $button.attr('title', $fx.lang('Add block to the %s area', area.name || area.id));
        }
    });

    $('.fx_adder_variant', $button).on('click', function() {
        $fx.front.add_infoblock_select_controller($node, $button.data('rel_node'), $button.data('rel_dir'));
        return false;
    });
    
    // Recreate adders when infoblock is reloaded
    $node
        .off('.ib_reload_handle_adders')
        .on('fx_infoblock_loaded.ib_reload_handle_adders', function(e) {
            var $ib = $(e.target),
                $area = $ib.closest('.fx_area'); // .fx_has_inline_adder_infoblock ?
                
            $fx.front.destroy_inline_infoblock_adder($area);
            $fx.front.create_inline_infoblock_adder($area);
        });
};

fx_front.prototype.create_inline_infoblock_placer = function($node, $ib_node) {
    if ($node.is('.fx_hidden_placeholded')) {
        return;
    }
    
    var $button = $fx.front.create_inline_adder(
        $node, 
        $fx.front.get_own_infoblocks($node),
        '<span class="fx_adder_variant"></span>',
        'infoblock_placer'
    );
    
    

    $('.fx_adder_variant', $button).on('click', function(e) {
        var $rel = $button.data('rel_node'), 
            dir = $button.data('rel_dir');
        $fx.front.place_block($ib_node, $rel, dir);
        return false;
    });
};

fx_front.prototype.destroy_inline_infoblock_adder = function($node) {
    var $infoblocks = $fx.front.get_own_infoblocks($node);
    $node.off('.fx_recount_adders_infoblock');
    $infoblocks.off('.fx_recount_adders_infoblock');
    var $b = $node.data('fx_inline_adder_infoblock');
    if ($b) {
        $b.remove();
    }
};

fx_front.prototype.destroy_all_infoblock_placers = function() {
    $('.fx_area').each(function() {
        var $node = $(this);
        var $infoblocks = $fx.front.get_own_infoblocks($node);
        $node.off('.fx_recount_adders_infoblock_placer');
        $infoblocks.off('.fx_recount_adders_infoblock_placer');
        var $b = $node.data('fx_inline_adder_infoblock_placer');
        if ($b) {
            $b.remove();
        }
    });
};

fx_front.prototype.create_inline_entity_adder = function($node) {
    var $placeholders = $node.data('fx_contained_placeholders');
    if (!$placeholders) {
        return;
    }
    var $placeholder_mark = $node.is('.fx_hidden_placeholder_mark') ? $node : $('.fx_hidden_placeholder_mark', $node);
    
    if ($placeholder_mark.closest('.fx_adder_placeholder_container')[0] !== $node[0]) {
        $placeholder_mark = $([]);
    }
    
    var $placeholder_mark_td = $('td', $placeholder_mark); 
    
    if ($placeholder_mark.length) {
        var add_text = '. '+ $fx.lang('You can add %s here') + '.';
        add_text = add_text.replace(/\%s/, '<span class="fx_adder_variants"></span>');
        if ($placeholder_mark_td.length) {
            $placeholder_mark_td.append(add_text);
        } else {
            $placeholder_mark.append(add_text);
        }
        var $text_variants = $('.fx_adder_variants', $placeholder_mark);
    } else {
        var $entities = $('>.fx_entity', $node).not('.fx_entity_adder_placeholder'),
            button_scope = $node.is('.columns') ? 'infoblock' : 'entity';

        var $button = $fx.front.create_inline_adder(
            $node, 
            $entities,
            '',
            button_scope
        );
        var $title = $button.data('title_node');
    }
    
    var pl = $placeholders.length,
        title_parts = [];
    
    $placeholders.each(function(index, item) {
        var $placeholder = $(this),
            entity_meta = $placeholder.data('fx_entity_meta'),
            entity_name = entity_meta ? entity_meta.placeholder_name : $placeholder.data('fx_entity_name');
            
        title_parts.push(entity_name);
        
        var $c_title = $('<div class="fx_adder_variant">'+entity_name+'</div>');
        $c_title.data('placeholder', $placeholder);
        if ($placeholder_mark.length) {
            var $text_variant = $('<span class="fx_adder_variant">'+entity_name.toLowerCase()+'</span>');
            $text_variant.data('placeholder', $placeholder);
            $text_variants.append($text_variant);
            if (index === pl - 2) {
                $text_variants.append(' '+$fx.lang('or')+' ');
            } else if (index !== pl - 1) {
                $text_variants.append(', ');
            }
        } else {
            $title.append($c_title);
        }
    });
    
    if ($placeholder_mark.length) {
        $placeholder_mark.off('click').on('click', '.fx_adder_variant', function(e) {
            var $placeholder = $(this).data('placeholder');
            $fx.front.show_adder_placeholder($placeholder);
            return false;
        });
    } else {
        $button.off('click').on('click', '.fx_adder_variant', function(e) {
            var $c_title = $(this);
            var $placeholder = $c_title.data('placeholder');
            $fx.front.show_adder_placeholder($placeholder, $button.data('rel_node'), $button.data('rel_dir'));
            return false;
        });
        $button.attr('title', $fx.lang('Add')+' '+title_parts.join(', '));
    }
};

fx_front.prototype.destroy_inline_entity_adder = function($node) {
    
};

fx_front.prototype.recount_adder_overlaps = function() {
    var bl = 'fx_inline_adder',
        $all = $('.'+bl+'_placed'),
        tolerance = 30;
    
    $all.removeClass(bl+'_overlapper '+bl+'_overlapped');
    
    for (var i = 0; i < $all.length; i++) {
        var $a = $all.eq(i),
            a = $a.data('props');
        
        if (!a || $a.is('.'+bl+'_overlapped')) {
            continue;
        }
        for (var j = i+1; j < $all.length; j++) {
            var $b = $all.eq(j),
                b = $b.data('props');
                
            if (!b || $b.is('.'+bl+'_overlapped')) {
                continue;
            }
        
            if (a.is_vertical !== b.is_vertical) {
                continue;
            }
            if ( Math.abs(a.cx - b.cx) > tolerance ) {
                continue;
            }
            if (Math.abs(a.cy - b.cy) > tolerance) {
                continue;
            }
            $a.addClass(bl+'_overlapper');
            $b.addClass(bl+'_overlapped');
        }
    }
    
};

fx_front.prototype.disable_adders = function(scope) {
    if (!this.disabled_adders) {
        this.disabled_adders = {};
    }
    this.disabled_adders[scope] = true;
};

fx_front.prototype.enable_adders = function(scope) {
    if (!this.disabled_adders) {
        return;
    }
    this.disabled_adders[scope] = false;
};

fx_front.prototype.is_adder_disabled = function(scope) {
    return this.disabled_adders && this.disabled_adders[scope];
};

window.counters = {
    bind:0,
    release:0
};

fx_front.prototype.create_inline_adder = function($node, $entities, title, scope) {
    var bl = 'fx_inline_adder';
    var $existing_button = $node.data('fx_inline_adder_'+scope);
    if ($existing_button && $existing_button.length && $existing_button.parent().length) {
        $existing_button.find('.fx_inline_adder__title').html(title);
        return $existing_button;
    }
    
    var is_sortable = scope === 'infoblock' || scope === 'infoblock_placer' || $entities.filter('.fx_sortable').length > 0;
    
    var $overlay = this.get_front_overlay(),
        $adder_overlay = $('.fx_inline_adder_overlay', $overlay);
    
    if ($adder_overlay.length === 0) {
        var z_index = $fx.front.get_overlay_z_index($node);
        
        $adder_overlay = $('<div class="fx_inline_adder_overlay" style="z-index:'+z_index+'"></div>');
        $overlay.append($adder_overlay);
        
    }
    
    var $button = $(
        '<div class="'+bl+' '+bl+'-'+(!is_sortable ? 'not_sortable' : scope)+' fx_overlay">'+
            '<div class="'+bl+'__line"></div>'+
            '<div class="'+bl+'__title">'+title+'</div>'+
            '<div class="'+bl+'__plus"><span class="'+bl+'__plus_plus"></span></div>'+
        '</div>'
    );
    
    var $title = $('.'+bl+'__title', $button),
        $plus = $('.'+bl+'__plus', $button),
        $line = $('.'+bl+'__line', $button);
    
    $button.data('title_node', $title);
    
    $adder_overlay.append($button);
    $node.data('fx_inline_adder_'+scope, $button).addClass('fx_has_inline_adder fx_has_inline_adder_'+scope);
    var out_timeout = null,
        title_timeout = null,
        over_timeout = null;
    
    function hide_button($button) {
        if (window.fx_no_hide) {
            return;
        }
        if (!$button || !$button.is('.'+bl+'-visible')) {
            return;
        }
        var $button_target = $button.data('rel_node');
        if (!$button_target) {
            $button_target = $node;
        }
        $button_target.trigger('fx_collapse_inline_adder');
        $('html').off('.fx_hide_adder_button_'+scope);
        $button.data({
            rel_node:null,
            rel_dir:null,
            offset_left:null,
            offset_top:null
        });
        $button.removeClass(bl+'-visible');
        //setTimeout(function() {
            $button.removeClass(
                bl+'-outstanding '+bl+'-inverted '+bl+'-horizontal '+bl+'-vertical'+
                bl+'-hover '+bl+'-visible '+bl+'-overlapped '+bl+'_placed '+bl+'_overlapper '+bl+'_overlapped'
            ).attr('style', '');
            $plus.removeClass(bl+'__plus-with_variants');
            $('div', $button).attr('style', '');
            $title.hide();
        //},200);
    }
    
    function hide_button_timeout(time_offset) {
        if (time_offset === undefined) {
            time_offset = 500;
        }
        if (over_timeout !== null) {
            clearTimeout(over_timeout);
            return;
        }
        out_timeout = setTimeout(
            function() {
                hide_button($button);
            },
            time_offset
        );
        return out_timeout;
    }
    
    // provide it for outside use (e.g. hide buttons on fx_select)
    $button[0].fx_hide_inline_adder = function(time_offset) {
        hide_button_timeout(time_offset);
    };
    
    function place_title() {
        var css = {display:'block'};
        var plus_size = $plus.outerWidth(); // it seems to be square...
            
        if (!is_sortable) {
            css.left = 0;
            css.top = $plus.outerHeight();
        } else if ($button.hasClass(bl+'-outstanding')) {

            if ($button.hasClass(bl+'-vertical')) {
                css.left = '-' + ( $title.outerWidth() / 2 - plus_size / 2 ) + 'px';
                if ($button.hasClass(bl+'-inverted')) {
                    css.top = $line.outerHeight() + plus_size*0.7;
                } else {
                    css.top = '-' + ($title.outerHeight() + plus_size*0.7) + 'px';
                }
            } else {
                css.top = '-' + ( $title.outerHeight() / 2 - plus_size / 2 ) + 'px';
                if ($button.hasClass(bl+'-inverted')) {
                    css.left = ($line.outerWidth() + plus_size*0.7) + 'px';
                } else {
                    css.left = '-'+( $title.outerWidth() + plus_size*0.7 )+'px';
                }
            }
            if ( parseInt($button.css('left')) + parseInt(css.left) < 0) {
                css.left = 0;
            }

        } else {
            if ($button.hasClass(bl+'-vertical')) {
                css.left = plus_size * 0.7;
                css.top = ($line.outerHeight() - $title.outerHeight()) / 2;
                if (parseInt($button.css('left')) + $title.outerWidth() > $(window).width()) {
                    css.left -= ($title.outerWidth() + plus_size*0.5);
                }
                css.left = 5;
            } else {
                css.left = ($line.outerWidth() - $title.outerWidth()) / 2;
                //css.top = plus_size * 0.7;
                css.top = 5;
            }
        }
        var abs_top = parseInt(css.top) + parseInt($button.css('top'));
        if (abs_top < $fx.front.get_panel_height()) {
            css.top = 0;
        }
        $title.attr('style', '').css(css);
    }
    
    function hide_title() {
        //$variants.hide();
        $title.hide();
        $button.removeClass(bl+'-hover');
        $plus.removeClass(bl+'__plus-with_variants');
    }
    
    function handle_mouseleave(e) {
         var $leave_to = $(e.toElement),
             $leave_to_button = $leave_to.closest('.'+bl);

        if ( $leave_to_button.length > 0 ) {
            $leave_to_button.one('mouseleave', function(e) {
                handle_mouseleave(e);
            });
            return;
        }
        
        hide_button_timeout();
    }
    /*
    if (!is_sortable) {
        $button.on('mouseleave', function(e) {
            handle_mouseleave(e);
        });
    }
    */
    $button.on('mouseenter', function() {
        clearTimeout(out_timeout);
        clearTimeout(title_timeout);
        
        var $button_target = $button.data('rel_node');
        if (!$button_target) {
            $button_target = $node;
        }
        $button_target.trigger('fx_expand_inline_adder');
        
        $button.off('.fx_show_adder').on('click.fx_show_adder', function(e) {
            var $variants = $('.fx_adder_variant', $button);
            if ($variants.is(':visible')) {
                hide_title();
                return;
            }
            if ( $variants.length === 1) {
                $variants.first().click();
                return false;
            } 
            if ($variants.length === 0) {
                return;
            }
            $variants.show();
            clearTimeout(out_timeout);
            $button.off('.fx_adder_mouseout');
            $button.addClass(bl+'-hover');
            $plus.addClass(bl+'__plus-with_variants');
            
            place_title();
            return false;
        });
        $('html').on('click.fx_hide_adder_button_'+scope, function(e){
            var $target_button = $(e.target).closest('.'+bl);
            if ($target_button.length === 0) {
                hide_button_timeout(0);
                return false;
            }
        });
        $('html').on('keyup.fx_hide_adder_button_'+scope, function(e){
            if (e.which === 27) {
                hide_button_timeout(0);
            }
        });
    });
    
    // if closest infoblock was hidden while rendering, use it as mouse event target
    var $visible_node = $node;
    
    function handle_mouseover (e, $node) {
        // the nested adder case
        if ($fx.front.is_adder_disabled(scope)) {
            return;
        }
        
        clearTimeout(out_timeout);
        
        var e_scope = 'mouseleave.fx_recount_adders_'+scope;
        
        $node.off(e_scope).on(e_scope, handle_mouseleave);
        
        
        if ($button.is('.'+bl+'-visible')) {
            return;
        }
        
        var axis = $fx.front.get_list_orientation($entities);
        if (axis === null) {
            if ($entities.length > 0 && $entities.first().outerWidth() > $node.outerWidth() / 2) {
                axis = 'y';
            } else {
                axis = 'x';
            }
        }
            
        var axis_class = axis === 'y' ? 'horizontal' : 'vertical';
        
        var offset = $node.offset();
        var css = {
            left:offset.left
        };
        
        var is_fixed = $fx.front.is_fixed($node);
        
        if (is_fixed) {
            $button.css('position', 'fixed');
        }
        if (is_fixed) {
            css.position = 'fixed';
            css.top = offset.top - $(window).scrollTop();
        } else {
            css.top = offset.top;
        }
        
        if (css.left < 0) {
            css.left = 0;
        }
        
        over_timeout = setTimeout(function() {
            if ($fx.front.is_adder_disabled(scope)) {
                return;
            }
            $button.addClass(bl +'-visible');
            
            $button.data({
                rel_node:null,
                offset_top:null
            });
            if (is_sortable) {
                place_button(e, $(e.target).closest($entities));
                $entities.on('mousemove.fx_recount_adders_'+scope, function(e) {
                    place_button(e, $(this));
                });
            } else {
                if (!$button.data('not_sortable_rendered')) {
                    $button.data('not_sortable_rendered', true);
                    var $variants = $('.fx_adder_variant', $title),
                        plus_label_text = $fx.lang('Add');
                    
                    if ($variants.length <= 1) {
                        plus_label_text += ' '+$variants.first().text();
                    } else {
                        plus_label_text += '...';
                    }
                    
                    var $plus_label = $('<div class="'+bl+'__plus_label">'+plus_label_text+'</div>');
                    $plus.append($plus_label);
                }
                css.top -= $plus.height();
                $button.css(css);
                place_button();
                $node.on('mousemove.fx_recount_adders_'+scope+' mouseover.fx_recount_adders_'+scope, function(e) {
                    place_button();
                });
            }
            over_timeout = null;
        }, 100);
        
        var plus_size = 16,
            left_edge = plus_size,
            right_edge = $(window).width() - plus_size,
            top_edge = $fx.front.get_panel_height() + plus_size,
            bottom_edge = $(document).height() - plus_size;
        
        function place_button(e, $entity) {
            
            if (!is_sortable) {
                var node_offset = $node.offset(),
                    panel_height = $('.fx-admin-panel').height(),
                    button_height = $plus.height(),
                    scroll_top = $(window).scrollTop();
                if (panel_height + scroll_top >= node_offset.top) {
                    $button.css({
                        position:'fixed',
                        top:panel_height
                    });
                } else {
                    $button.css({
                        position:'absolute',
                        top:node_offset.top - button_height
                    });
                }
                return;
            }
            
            if ($entity.closest('.fx_is_moving').length > 0) {
                hide_button($button);
                return;
            }
            
            var entity_index = $entities.index($entity);
            
            if (!$entity.length || entity_index === -1) {
                return;
            }
            
            var e_top = e.pageY,
                e_left = e.pageX,
                offset = $entity.offset(),
                top = offset.top,
                left = offset.left,
                width = $entity.outerWidth(),
                height = $entity.outerHeight(),
                is_vertical = axis === 'x', // axis shows how entites are placed!
                size = axis === 'x' ? width : height,
                diff = axis === 'x' ? (e_left - left) : (e_top - top),
                is_after = size / 2 < diff,
                dir = is_after ? 'after' : 'before',
                was_placed = $button.hasClass(bl+'_placed');
               
            var opacity = 1;
            if (size > 20) {
                var show_distance = Math.max(Math.min(150, size/2), size/4),
                    show_offset = show_distance - (is_after ? size - diff : diff);
                
                if (show_offset > 0) {
                    opacity = Math.min(1, Math.sqrt(show_offset/show_distance)*1.5);
                } else {
                    opacity = 0;
                }
            }
            
            $plus.css('opacity', opacity);
            
            var is_placed = (opacity > 0.01);
            
            if (is_placed !== was_placed) {
                $button.removeClass(bl+'_overlapped '+bl+'_overlapper');
            }
            
            if ($button.data('rel_dir') === dir) {
                var $c_button_entity = $button.data('rel_node');
                if ($c_button_entity && $c_button_entity.length && $c_button_entity[0] === $entity[0]) {
                    if (is_placed !== was_placed) {
                        $button.toggleClass(bl+'_placed', is_placed);
                        $fx.front.recount_adder_overlaps();
                    }
                    return;
                }
            }
            
            $plus.attr('style', '').css('opacity', opacity);
            
            $button
                .data('rel_dir', dir)
                .data('rel_node', $entity);
            
            // should it really be here?
            $button
                .removeClass(bl+'-horizontal '+bl+'-vertical '+bl+'-dir_before '+bl+'-dir_after')
                .addClass(bl+'-'+axis_class+' '+bl+'-dir_'+dir);
        
            $button.toggleClass(bl+'_placed', is_placed);
                
            var css = {},
                is_outstanding = false;
            
            if (is_vertical) {
                css.height = height;
                css.top = top;
                css.left = is_after ? left + width : left;
                if (css.left < left_edge) {
                    css.left = plus_size;
                    is_outstanding = true;
                } else if (css.left > right_edge) {
                    css.left = right_edge;
                    is_outstanding = true;
                }
            } else {
                css.width = width;
                css.top = is_after ? top + height : top;
                css.left = left;
                if (css.top < top_edge) {
                    css.top = top_edge;
                    is_outstanding = true;
                } else if (css.top > bottom_edge) {
                    css.top = bottom_edge;
                    is_outstanding = true;
                }
            }
            $button.toggleClass(bl + '-outstanding', is_outstanding);

            var c_width = is_vertical ? 0 : width,
                c_height = is_vertical ? height : 0;
            
            $button
                .css(css)
                .data('props', {
                    is_vertical:is_vertical,
                    cx: css.left + (c_width / 2),
                    cy: css.top + (c_height / 2)
                });
            $fx.front.recount_adder_overlaps();
            $button.trigger('fx_place_adder');
        }
    }
    
    $visible_node.off('.fx_recount_adders_'+scope).on('mouseover.fx_recount_adders_'+scope, function(e) {
        handle_mouseover(e, $visible_node);
    });
    
    return $button;
};

})($fxj);