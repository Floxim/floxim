(function($) {
    
fx_front.prototype.get_own_infoblocks = function($node) {
    var infoblocks = [],
        area_node = $node.closest('.fx_area')[0];
    
    $('.fx_infoblock', $node).each(function() {
        if ($(this).closest('.fx_area')[0] === area_node) {
            infoblocks.push(this);
        }
    });
    return $(infoblocks);
};
    
fx_front.prototype.create_inline_infoblock_adder = function($node) {
    if ($node.is('.fx_hidden_placeholded')) {
        return;
    }
    
    var $button = $fx.front.create_inline_adder(
        $node, 
        $fx.front.get_own_infoblocks($node),
        '<span class="fx_adder_variant">'+$fx.lang('block')+'</span>',
        'infoblock'
    );

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

fx_front.prototype.destroy_inline_infoblock_adder = function($node) {
    var $infoblocks = $fx.front.get_own_infoblocks($node);
    $node.off('.fx_recount_adders_infoblock');
    $infoblocks.off('.fx_recount_adders_infoblock');
    var $b = $node.data('fx_inline_adder_infoblock');
    if ($b) {
        $b.remove();
    }
};

fx_front.prototype.create_inline_entity_adder = function($node) {
    var $placeholders = $node.data('fx_neighbour_placeholder');
    
    var $placeholder_mark = $node.is('.fx_hidden_placeholder_mark') ? $node : $('.fx_hidden_placeholder_mark', $node);
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
        var $entities = $('>.fx_entity', $node).not('.fx_entity_adder_placeholder');
        var $button = $fx.front.create_inline_adder(
            $node, 
            $entities,
            '',
            'entity'
        );
        var $title = $button.data('title_node');
    }
    
    var pl = $placeholders.length;
    $placeholders.each(function(index, item) {
        var $placeholder = $(this);
        var entity_name = $placeholder.data('fx_entity_name');
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
    }
};

fx_front.prototype.destroy_inline_entity_adder = function($node) {
    
};

fx_front.prototype.create_inline_adder = function($node, $entities, title, scope) {
    var bl = 'fx_inline_adder';
    var $existing_button = $node.data('fx_inline_adder');
    if ($existing_button && $existing_button.length && $existing_button.parent().length) {
        $existing_button.find('.fx_inline_adder__title').html(title);
        return $existing_button;
    }
    
    var is_sortable = $entities.filter('.fx_sortable, .fx_infoblock').length > 0;
    
    var $overlay = this.get_front_overlay();
    var $button = $(
        '<div class="'+bl+' '+bl+'-'+(!is_sortable ? 'not_sortable' : scope)+' fx_overlay">'+
            '<div class="'+bl+'__line"></div>'+
            '<div class="'+bl+'__plus"><span class="'+bl+'__plus_plus">+</span></div>'+
            '<div class="'+bl+'__title">'+title+'</div>'+
        '</div>'
    );
    
    var $title = $('.'+bl+'__title', $button),
        $plus = $('.'+bl+'__plus', $button),
        $line = $('.'+bl+'__line', $button);
    
    $button.data('title_node', $title);
    
    var z_index = $fx.front.get_overlay_z_index($node);
    
    //var $entities = $node.find(neighbour_selector).filter(':not(.fx_entity_adder_placeholder)');
    
    $overlay.append($button);
    $node.data('fx_inline_adder_'+scope, $button).addClass('fx_has_inline_adder fx_has_inline_adder_'+scope);
    var out_timeout = null,
        title_timeout = null,
        over_timeout = null;
    
    function hide_button($button) {
        if (window.fx_no_hide) {
            return;
        }
        if (!$button) {
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
        $button.removeClass(
            bl+'-outstanding '+bl+'-inverted '+bl+'-horizontal '+bl+'-vertical'+
            bl+'-hover '+bl+'-visible'
        ).attr('style', '');
        $plus.removeClass(bl+'__plus-with_variants');
        $('div', $button).attr('style', '');
    }
    
    // provide it for outside use (e.g. hide buttons on fx_select)
    $button[0].fx_hide_inline_adder = function() {
        hide_button($button);
    };
    
    function hide_button_timeout(time_offset) {
        if (time_offset === undefined) {
            time_offset = 500;
        }
        if (over_timeout !== null) {
            clearTimeout(over_timeout);
            return;
        }
        out_timeout = setTimeout(
            function() {hide_button($button);},
            time_offset
        );
    }
    
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
            } else {
                css.left = ($line.outerWidth() - $title.outerWidth()) / 2;
                css.top = plus_size * 0.7;
            }
        }
        var abs_top = parseInt(css.top) + parseInt($button.css('top'));
        if (abs_top < $fx.front.get_panel_height()) {
            css.top = 0;
        }
        $title.attr('style', '').css(css);
    }
    
    $button.on('mouseenter', function() {
        clearTimeout(out_timeout);
        clearTimeout(title_timeout);
        
        var $button_target = $button.data('rel_node');
        if (!$button_target) {
            $button_target = $node;
        }
        $button_target.trigger('fx_expand_inline_adder');
        
        $button.off('mouseleave.fx_adder_mouseout').on('mouseleave.fx_adder_mouseout', function() {
            if (!$button.hasClass(bl+'-hover')) {
                hide_button_timeout();
            }
        });
        
        $button.off('.fx_show_adder').on('click.fx_show_adder', function(e) {
            var $variants = $('.fx_adder_variant', $button);
            if ($variants.is(':visible')) {
                $variants.hide();
                $button.removeClass(bl+'-hover');
                $plus.removeClass(bl+'__plus-with_variants');
                return;
            }
            if ( $variants.length === 1) {
                $variants.first().click();
                return;
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
        if ($(e.target).closest('.fx_has_inline_adder_'+scope)[0] !== $node[0] ) {
            return;
        }
        if ($fx.front.hilight_disabled) {
            return;
        }
        
        clearTimeout(out_timeout);
        $node.one('mouseout.fx_recount_adders_'+scope, function() {
            hide_button_timeout();
        });
        
        if ($plus.is(':visible')) {
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
        
        $button
            .removeClass(bl+'-horizontal')
            .removeClass(bl+'-vertical')
            .addClass(bl+'-'+axis_class)
            .css('z-index', z_index);
    
        var offset = $node.offset();
        var css = {
            opacity:'0',
            left:offset.left// - $plus.outerWidth()
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
        //css.top -= 8;
        
        if (css.left < 0) {
            css.left = 0;
        }
        
        over_timeout = setTimeout(function() {
            if ($fx.front.hilight_disabled) {
                return;
            }
            $button.addClass(bl +'-visible');
            var button_node = $button[0];
            $('.'+bl+'-visible.'+bl+'-'+scope).each(function() {
                if (this !== button_node) {
                    //$(this).removeClass(bl+'-visible');
                    hide_button($(this));
                }
            });
            $button.data({
                rel_node:null,
                offset_top:null
            });
            $button.animate({opacity:1},100);
            if (is_sortable) {
                place_button(e, $(e.target).closest($entities));
                $entities.on('mousemove.fx_recount_adders_'+scope, function(e) {
                    place_button(e, $(this));
                });
            } else {
                //if (!$button.hasClass(bl+'-not_sortable')) {
                if (!$button.data('not_sortable_rendered')) {
                    //$button.addClass(bl+'-not_sortable');
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
            }
            over_timeout = null;
        }, 100);
        
        var right_edge = $(window).width() - 20;
        
        function get_margins($entity) {
            return {
                x: parseInt($entity.css('margin-left')) + parseInt($entity.css('margin-right')),
                y: parseInt($entity.css('margin-top')) + parseInt($entity.css('margin-bottom'))
            };
        }
        
        function place_button(e, $entity) {
            
            //var $entity = $(e.target).closest( neighbour_selector.replace(/^>/, '') );
            
            var entity_index = $entities.index($entity);
            
            if (!$entity.length || entity_index === -1) {
                return;
            }
            var e_top = e.pageY,
                e_left = e.pageX,
                offset = $entity.offset(),
                top = offset.top,
                left = offset.left,
                e_width = $entity.outerWidth(),
                e_height = $entity.outerHeight(),
                e_margins = get_margins($entity),  
                size = axis === 'x' ? e_width : e_height,
                diff = axis === 'x' ? (e_left - left) : (e_top - top),
                is_after = size / 2 < diff,
                dir = is_after ? 'after' : 'before',
                is_first = entity_index === 0,
                is_last = entity_index === $entities.length - 1;
            
            if ($button.data('rel_dir') === dir) {
                var $c_button_entity = $button.data('rel_node');
                if ($c_button_entity && $c_button_entity.length && $c_button_entity[0] === $entity[0]) {
                    return;
                }
            }
            
            $plus.attr('style', '');
            $line.attr('style', '');
            
            $button.data('rel_dir', dir);
        
            var neighbour_index = entity_index + (is_after ? 1 : (entity_index === 0 ? $entities.length : -1) ),
                $neighbour = $entities.eq(neighbour_index),
                neighbour_is_real = $neighbour.length > 0,
                neighbour_offset = $neighbour.offset(),
                distance = 0;
            
            
            function not_on_the_same_line(neighbour_offset) {
                return axis === 'x' && Math.abs(neighbour_offset.top - top) > e_height/2;
            }
            // use neighbour from the other side if there's no nodes on the correct side
            // or if the correct neighbour is not on the same line ("tiles" case)
            if (
                $neighbour.length === 0 || 
                not_on_the_same_line(neighbour_offset)
            ) {
                neighbour_index = entity_index + (!is_after ? 1 : (entity_index === 0 ? $entities.length : -1) ),
                $neighbour = $entities.eq(neighbour_index),
                neighbour_offset = $neighbour.offset();
                if ($neighbour.length && not_on_the_same_line(neighbour_offset)) {
                    $neighbour = $([]);
                }
            }
            
            if ($neighbour.length) {
                if (axis === 'x') {
                    var dims = [left, left+e_width, neighbour_offset.left, neighbour_offset.left + $neighbour.outerWidth() ];
                } else {
                    var dims = [top, top+e_height, neighbour_offset.top, neighbour_offset.top + $neighbour.outerHeight() ];
                }
                dims.sort(function(a,b){return a-b;});
                distance = neighbour_is_real ? dims[2] - dims[1] : 0;
            }
            
            var outstand_treshold = scope === 'infoblock' ? 20 : 35,
                is_outstanding = (e_width + e_margins.x) < outstand_treshold || 
                                 (e_height + e_margins.y) < outstand_treshold;
            
            if ($neighbour.length && neighbour_is_real && !is_outstanding) {
                var n_margins = get_margins($neighbour);
                is_outstanding = ($neighbour.outerWidth() + n_margins.x) < outstand_treshold ||
                                 ($neighbour.outerHeight() + n_margins.y) < outstand_treshold;
            }
            
            $button.toggleClass('fx_inline_adder-outstanding', is_outstanding);
            
            var b_size = $plus.outerWidth();
            
            if (axis === 'y') {
                if (is_after) {
                    top += size + distance/2;
                } else {
                    top -= distance/2;
                }
                
                top -= b_size/2;
                
                var n_width = $neighbour.length ? $neighbour.outerWidth() : e_width;
                
                var line_width = Math.max(e_width, n_width);
                
                
                if (n_width > e_width && $neighbour.length) {
                    left -= offset.left - neighbour_offset.left;
                }
               
                $line.css({
                    width:line_width+'px',
                    top: Math.round(b_size/2) - 1 +'px'
                });
                
                if (is_outstanding) {
                    $plus.css({
                        left: '-'+b_size+'px'
                    });
                } else {
                    var plus_left = Math.round(line_width/2 - b_size/2),
                        plus_top = 0;
                    if (scope === 'entity' && (is_last && is_after || is_first && !is_after) ) {
                        var $ib_button = $entity.closest('.fx_has_inline_adder_infoblock').data('fx_inline_adder_infoblock');
                        if ($ib_button) {
                            var ib_box = $ib_button.offset();
                            
                            if (Math.abs(ib_box.top - top) < 20 ) {
                                plus_left += 40;
                                plus_top = is_after ? -15 : 15;
                            }
                        }
                    }
                    $plus.css({
                        left: plus_left+'px',
                        top: plus_top+'px'
                    });
                }
                
            } else {
                
                if (is_after) {
                    left += size + distance/2;
                } else {
                    left -= distance/2;
                }
                
                left -= b_size/2;
                
                var n_height = $neighbour.length ? $neighbour.outerHeight() : e_height;
                
                var line_height = Math.max(e_height, n_height);
                
                $line.css({
                    height:line_height+'px',
                    left: Math.round(b_size/2) - 1 +'px'
                });
                
                if (is_outstanding) {
                    $plus.css({
                        top: '-'+b_size+'px'
                    });
                } else {
                    $plus.css({
                        top: Math.round(line_height/2 - b_size/2)+'px'
                    });
                }
            }
            $button.data('rel_node', $entity);
            $button.data('rel_axis', axis);
            
            if (is_fixed) {
                top -= $(window).scrollTop();
            }
            
            // !!! panel height
            if (top < 140 && axis === 'x' && is_outstanding) {
                $plus.css('top', '+='+(line_height + b_size ) );
                $button.addClass(bl+'-inverted');
            } else if (left < 140 && axis === 'y' && is_outstanding) {
                $plus.css('left', '+='+(line_width + b_size ) );
                if ( parseInt($plus.css('left')) + left > right_edge) {
                    $plus.css('left', right_edge - b_size - left);
                }
                $button.addClass(bl+'-inverted');
            } else {
                $button.removeClass(bl+'-inverted');
            }
            
            if (left < 0) {
                left = 0;
            } else if ( (left + $plus.outerWidth()/2) > right_edge) {
                left = right_edge - $plus.outerWidth()/2;
            }
            
            top = Math.round(top);
            left = Math.round(left);
            
            var stored_left = $button.data('offset_left'),
                stored_top = $button.data('offset_top');
            
            if (
                !stored_left || 
                !stored_top || 
                Math.abs(stored_left - left) > 2 || 
                Math.abs(stored_top - top) > 2
            ) {
                $button.
                    data('offset_left', left).
                    data('offset_top', top).
                    css({
                        top:top,
                        left:left
                    });
                if ($plus.hasClass(bl+'__plus-with_variants')) {
                    place_title();
                }
            }
        }
    }
    
    $visible_node.off('.fx_recount_adders_'+scope).on('mouseover.fx_recount_adders_'+scope, function(e) {
        handle_mouseover(e, $visible_node);
    });
    
    return $button;
};

})($fxj);