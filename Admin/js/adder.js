(function($) {
    
fx_front.prototype.create_inline_infoblock_adder = function($node) {
    var $button = $fx.front.create_inline_adder($node, '>.fx_infoblock', 'block');
    $button.on('click', function() {
        $fx.front.add_infoblock_select_controller($node, $button.data('rel_node'), $button.data('rel_dir'));
    });
};

fx_front.prototype.create_inline_entity_adder = function($node) {
    var $placeholders = $node.data('fx_neighbour_placeholder');
    var $button = $fx.front.create_inline_adder($node, '>.fx_entity', '');
    var $title = $button.data('title_node');
    
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
    }
    
    var pl = $placeholders.length;
    $placeholders.each(function(index, item) {
        var $placeholder = $(this);
        var entity_name = $placeholder.data('fx_entity_name');
        var $c_title = $('<div class="fx_adder_variant">'+entity_name+'</div>');
        $c_title.data('placeholder', $placeholder);
        $title.append($c_title);
        if ($placeholder_mark.length) {
            var $text_variant = $('<span class="fx_adder_variant">'+entity_name.toLowerCase()+'</span>');
            $text_variant.data('placeholder', $placeholder);
            $text_variants.append($text_variant);
            if (index === pl - 2) {
                $text_variants.append(' '+$fx.lang('or')+' ');
            } else if (index !== pl - 1) {
                $text_variants.append(', ');
            }
        }
    });
    
    $button.off('click').on('click', '.fx_adder_variant', function(e) {
        var $c_title = $(this);
        var $placeholder = $c_title.data('placeholder');
        $fx.front.show_adder_placeholder($placeholder, $button.data('rel_node'), $button.data('rel_dir'));
        return false;
    });
    
    if ($placeholder_mark.length) {
        $placeholder_mark.off('click').on('click', '.fx_adder_variant', function(e) {
            var $placeholder = $(this).data('placeholder');
            $fx.front.show_adder_placeholder($placeholder);
            return false;
        });
    }
};

fx_front.prototype.create_inline_adder = function($node, neighbour_selector, title) {
    var bl = 'fx_inline_adder';
    var $existing_button = $node.data('fx_inline_adder');
    if ($existing_button && $existing_button.length && $existing_button.parent().length) {
        $existing_button.find('.fx_inline_adder__title').html(title);
        return $existing_button;
    }
    
    var $overlay = this.get_front_overlay();
    var $button = $(
        '<div class="'+bl+' fx_overlay">'+
            '<div class="'+bl+'__line"></div>'+
            '<div class="'+bl+'__plus"><span>+</span></div>'+
            '<div class="'+bl+'__title">'+title+'</div>'+
        '</div>'
    );
    
    var $title = $('.'+bl+'__title', $button),
        $plus = $('.'+bl+'__plus', $button),
        $line = $('.'+bl+'__line', $button);

    $button.data('title_node', $title);
    
    var z_index = $fx.front.get_overlay_z_index($node);
    
    var $entities = $node.find(neighbour_selector).filter(':not(.fx_entity_adder_placeholder)');
    
    $overlay.append($button);
    $node.data('fx_inline_adder', $button).addClass('fx_has_inline_adder');
    var out_timeout = null,
        title_timeout = null,
        over_timeout = null;
    
    function hide_button($button) {
        if (!$button) {
            return;
        }
        var $button_target = $button.data('rel_node');
        if (!$button_target) {
            $button_target = $node;
        }
        $button_target.trigger('fx_collapse_inline_adder');
        $('html').off('.fx_hide_adder_button');
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
        $('div', $button).attr('style', '');
        console.trace();
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
            if ( $variants.length === 1) {
                $variants.first().click();
                return;
            } else if ($variants.length === 0) {
                return;
            }
            clearTimeout(out_timeout);
            $button.off('.fx_adder_mouseout');
            $button.addClass(bl+'-hover');
            var css = {display:'block'};
            var plus_size = $plus.outerWidth(); // it seems to be square...
            
            if ($button.hasClass(bl+'-vertical')) {
                css.left = '-' + ( $title.outerWidth() / 2 - plus_size / 2 ) + 'px';
                if ($button.hasClass(bl+'-inverted')) {
                    css.top = $line.outerHeight();
                } else {
                    css.top = '-' + $title.outerHeight() + 'px';
                }
            } else {
                css.top = '-' + ( $title.outerHeight() / 2 - plus_size / 2 ) + 'px';
                if ($button.hasClass(bl+'-inverted')) {
                    css.left = $line.outerWidth() + 'px';
                } else {
                    css.left = '-'+( $title.outerWidth() )+'px';
                }
            }
            $title.attr('style', '').css(css);
            return false;
        });
        $('html').on('click.fx_hide_adder_button', function(e){
            var $target_button = $(e.target).closest('.'+bl);
            if ($target_button.length === 0) {
                hide_button_timeout(0);
                return false;
            }
        });
        $('html').on('keyup.fx_hide_adder_button', function(e){
            if (e.which === 27) {
                hide_button_timeout(0);
            }
        });
    });
    
    // if closest infoblock was hidden while rendering, use it as mouse event target
    var $visible_node = $node;
    
    function handle_mouseover (e, $node) {
        if ($(e.target).closest('.fx_has_inline_adder')[0] !== $node[0] ) {
            return;
        }
        if ($fx.front.hilight_disabled) {
            return;
        }
        
        clearTimeout(out_timeout);
        $node.one('mouseout.fx_recount_adders', function() {
            hide_button_timeout();
        });
        
        if ($plus.is(':visible')) {
            return;
        }
        
        if ($entities.first().is('.fx_infoblock')) {
            var axis = 'y';
        } else {
            var axis = $fx.front.get_list_orientation($entities);
            if (axis === null) { // && $entities.filter(':visible').length < 2) {
                if ($entities.length > 0 && $entities.first().outerWidth() > $node.outerWidth() / 2) {
                    axis = 'y';
                } else {
                    axis = 'x';
                }
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
            left:offset.left - $plus.outerWidth()
        };
        
        var is_fixed = $fx.front.is_fixed($node);
        console.log(is_fixed, $node, $button);
        if (is_fixed) {
            $button.css('position', 'fixed');
        }
        if (is_fixed) {
            css.position = 'fixed';
            css.top = offset.top - $(window).scrollTop();
        } else {
            css.top = offset.top;
        }
        css.top -= 8;
        
        if (css.left < 0) {
            css.left = 0;
        }
        
        over_timeout = setTimeout(function() {
            if ($fx.front.hilight_disabled) {
                return;
            }
            $button.addClass(bl+'-visible');
            var button_node = $button[0];
            $('.'+bl+'-visible').each(function() {
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
            if ($entities.filter('.fx_sortable, .fx_infoblock').length > 0) {
                place_button(e, true);
                $node.on('mousemove.fx_recount_adders', place_button);
            } else {
                //place_button(e, false);
                $button.css(css);
                console.log('css', css, $button.attr('class'));
            }
            over_timeout = null;
        }, 100);
        
        var right_edge = $(window).width() - 20;
        
        
        
        function place_button(e, sortable) {
            
            var $entity = $(e.target).closest( neighbour_selector.replace(/^>/, '') );
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
                size = axis === 'x' ? e_width : e_height,
                diff = axis === 'x' ? (e_left - left) : (e_top - top),
                is_after = size / 2 < diff,
                dir = is_after ? 'after' : 'before';
            
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
                neighbour_offset = $neighbour.offset(),
                distance = 0;
            
            // use neighbour from the other side if there's no nodes on the correct side
            // or if the correct neighbour is not on the same line ("tiles" case)
            if ($neighbour.length === 0 || (axis === 'x' && Math.abs(neighbour_offset.top - top) > e_height/2)) {
                neighbour_index = entity_index + (!is_after ? 1 : (entity_index === 0 ? $entities.length : -1) ),
                $neighbour = $entities.eq(neighbour_index),
                neighbour_offset = $neighbour.offset();
            }
            
            if ($neighbour.length) {
                if (axis === 'x') {
                    var dims = [left, left+e_width, neighbour_offset.left, neighbour_offset.left + $neighbour.outerWidth() ];
                } else {
                    var dims = [top, top+e_height, neighbour_offset.top, neighbour_offset.top + $neighbour.outerHeight() ];
                }
                dims.sort(function(a,b){return a-b;});
                distance = dims[2] - dims[1];
            }
            
            var outstand_treshold = 100,
                is_outstanding = e_width < outstand_treshold || e_height < outstand_treshold;
            
            if ($neighbour.length && !is_outstanding) {
                is_outstanding = $neighbour.outerWidth() < outstand_treshold 
                                    || $neighbour.outerHeight() < outstand_treshold;
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
                
                $line.css({
                    width:line_width+'px',
                    top: Math.round(b_size/2) - 1 +'px'
                });
                
                if (is_outstanding) {
                    $plus.css({
                        left: '-'+b_size+'px'
                    });
                } else {
                    $plus.css({
                        left: Math.round(line_width/2 - b_size/2)+'px'
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
            if (top < 140 && axis === 'x') {
                $plus.css('top', '+='+(line_height + b_size ) );
                $button.addClass(bl+'-inverted');
            } else if (left < 140 && axis === 'y') {
                $plus.css('left', '+='+(line_width + b_size ) );
                $button.addClass(bl+'-inverted');
            } else {
                $button.removeClass(bl+'-inverted');
            }
            if (left < 0) {
                left = 0;
            } else if (left > right_edge) {
                left = right_edge;
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
            } else {
                console.log('r2');
            }
        }
    }
    
    $visible_node.off('.fx_recount_adders').on('mouseover.fx_recount_adders', function(e) {
        handle_mouseover(e, $visible_node);
    });
    
    return $button;
};


/** --------- v2 ------ */

/*
 
     
var adder = function() {
    var adder = this,
        $button, $node, $entities,
        $variants, $line, $plus,
        bl = 'fx_inline_adder',
        out_timeout, title_timeout, over_timeout;
    
    this.create = function(neighbour_selector, title) {
        var $existing_button = $node.data('fx_inline_adder');
        if ($existing_button && $existing_button.length && $existing_button.parent().length) {
            $existing_button.find('.'+bl+'__title').html(title);
            return $existing_button;
        }

        var $overlay = $fx.front.get_front_overlay();
        $button = $(
            '<div class="'+bl+' fx_overlay">'+
                '<div class="'+bl+'__line"></div>'+
                '<div class="'+bl+'__plus"><span>+</span></div>'+
                '<div class="'+bl+'__variants">'+title+'</div>'+
            '</div>'
        );

        $variants = $('.'+bl+'__variants', $button),
        $plus = $('.'+bl+'__plus', $button),
        $line = $('.'+bl+'__line', $button);

        $button.data('title_node', $variants);

        $button.css('z-index', $fx.front.get_overlay_z_index($node));

        $entities = $node.find(neighbour_selector).filter(':not(.fx_entity_adder_placeholder)');
        
        $overlay.append($button);
        $node.data('fx_inline_adder', $button).addClass('fx_has_inline_adder');
    };
    
    this.hide_button = function() {
        var $button_target = $button.data('rel_node');
        if (!$button_target) {
            $button_target = $node;
        }
        $button_target.trigger('fx_collapse_inline_adder');
        $('html').off('.fx_hide_adder_button');
        $button.data({
            rel_node:null,
            rel_dir:null,
            offset_left:null,
            offset_top:null
        });
        $button.removeClass(
            bl+'-outstanding '+bl+'-inverted '+bl+'-horizontal '+bl+'-vertical'+
            bl+'-hover '+bl+'-visible'
        );
        $('div', $button).attr('style', '');
    };
    
    this.hide_button_timeout = function(time_offset) {
        if (time_offset === undefined) {
            time_offset = 50000;
        }
        if (over_timeout !== null) {
            clearTimeout(over_timeout);
            return;
        }
        out_timeout = setTimeout(
            adder.hide_button,
            time_offset
        );
    };
    
    this.show_variants = function() {
        var $variant_items = $('.fx_adder_variant', $button);
        if ( $variant_items.length === 1) {
            $variant_items.first().click();
            return;
        } 
        if ($variant_items.length === 0) {
            return;
        }
        clearTimeout(out_timeout);
        $button.off('.fx_adder_mouseout');
        $button.addClass(bl+'-hover');
        var css = {};
        var plus_size = $plus.outerWidth(); // it seems to be square...

        if ($button.hasClass(bl+'-vertical')) {
            css.left = '-' + ( $variants.outerWidth() / 2 - plus_size / 2 ) + 'px';
            if ($button.hasClass(bl+'-inverted')) {
                css.top = $line.outerHeight();
            } else {
                css.top = '-' + $variants.outerHeight() + 'px';
            }
        } else {
            css.top = '-' + ( $variants.outerHeight() / 2 - plus_size / 2 ) + 'px';
            if ($button.hasClass(bl+'-inverted')) {
                css.left = $line.outerWidth() + 'px';
            } else {
                css.left = '-'+( $variants.outerWidth() )+'px';
            }
        }
        $variants.attr('style', '').css(css);
        return false;
    };
    
    this.show_button = function() {
        
    };
    
    this.place_button = function(e) {
        
    };
};

 
 */

})($fxj);