(function($) {
    
/**
 * Node panels manager
 */
window.fx_node_panels = function() {
    
    var that = this;
    
    this.create = function($node, params) {
        var panel = null;
        if ($node.is('.fx_var_bound_to_entity')) {
            panel = that.create($node.closest('.fx_entity'));
        } else {
            panel = $node.data('fx_node_panel');   
            if (!panel) {
                panel = new node_panel($node, params);
            }
        }
        if (panel && that.is_disabled) {
            panel.$panel.hide();
        }
        
        return panel;
    };
    
    this.get = function($node) {
        if (!$node) {
            $node = $( $fx.front.get_selected_item() );
        }
        return this.create($node);
    };
    
    this.remove = function($node) {
        var panel = $node.data('fx_node_panel');
        if (panel) {
            panel.remove();
        }
        if ($node.is('.fx_var_bound_to_entity')) {
            return that.remove($node.closest('.fx_entity'));
        }
    };
    
    this.recount = function() {
        if (this.is_disabled) {
            return;
        }
        $('.fx_node_panel')
            //.hide()
            .each(function() {
            $(this).data('panel').recount();
        });
    };
    
    this.is_disabled = false;
    
    this.disable = function() {
        this.is_disabled = true;
        $('.fx_node_panel').hide();
    };
    
    this.enable = function() {
        this.is_disabled = false;
        $('.fx_node_panel').show();
        this.recount();
    };
};

/**
 * One panel
 * @param {jQuery} $node jQuery wrapper around the node to create panel for
 */
function node_panel($node, params) {
    var that = this;
    
    this.$panel = null;
    this.$node = $node;
    this.params = $.extend({}, {
            offset:5,
            align:'left'
        }, params
    );
    
    this.init = function() {
        if (!$node || $node.length === 0) {
            return;
        }
        var $overlay = $fx.front.get_front_overlay();
        var $panel = $('<div class="fx_node_panel fx_overlay"></div>');
        $overlay.append($panel);
        $node.data('fx_node_panel', $panel);
        $panel.css({
            position:'absolute',
            visibility:'hidden',
            'z-index': $node.data('fx_z_index') || ($fx.front.get_panel_z_index() + 1)
        });
        setTimeout(this.recount, 10);
        $panel.on(
            'change.fx_front keyup.fx_front livesearch_value_loaded.fx_front click.fx_front focus.fx_front blur.fx_front', 
            //that.recount
            $fx.front.node_panel.recount
        );
        this.$panel = $panel;
        $node.on('fx_deselect.fx_remove_node_panel', function() {
            that.remove();
        });
        $panel.data('panel', this);
        $node.data('fx_node_panel', this);
    };
    
    this.remove = function() {
        $node.off('fx_deselect.fx_remove_node_panel');
        $node.data('fx_node_panel', null);
        that.$panel.hide();
        setTimeout(function(){
            that.$panel.remove();
        }, 50);
    };
    
    this.recount = function() {
        var $p = that.$panel;
        if ($fx.front.node_panel.is_disabled) {
            $p.hide();
            return;
        }
        
        
        var $p_items = $p.children(':visible').not('.fx_buttons_dropdown');
        if ($p_items.length === 0) {
            return;
        }
        
        var outer_offset = that.params.offset;
        
        // move to the left edge to get real width
        $p.css('left', 0);
        var p_width = $p.outerWidth();
        
        var css = {
            visibility:'visible',
            opacity:1
        };
        
        var p_height = $p.outerHeight();
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
        
        var $prev_panel = $p.prevAll('.fx_node_panel').first();
        
        
        //var $node = $($fx.front.get_selected_item());
        var $node = that.$node;
        var no = $node.offset();
        if ( that.params.align === 'left') {
            css.left = no.left - outer_offset;
        } else {
            css.left = no.left + $node.width() - p_width + outer_offset / 2;
        }
        if (css.left < 0) {
            css.left = 0;
        }
        var node_height = $node.data('fx_visible_height') || $node.outerHeight();
        var node_top = no.top;
        var node_bottom = node_top + node_height;
        var break_top = node_top - top_fix - p_height - outer_offset;
        var break_bottom = break_top + node_height + p_height;

        if (doc_scroll >= break_bottom) {
            // set panel beneath the node
            var offset_top = node_bottom + outer_offset ;
            css.top = offset_top;
            css.position = 'absolute';
            $p.removeClass('fx_node_panel_fixed');
        } else if (doc_scroll <= break_top) {
            // set panel above the node
            css.position = 'absolute';
            css.top = node_top - p_height - outer_offset;
            $p.removeClass('fx_node_panel_fixed');
        } else {
            var bottom_edge_visible = doc_scroll + $(window).height() > node_bottom + p_height;
            if (bottom_edge_visible) {
                css.top = node_bottom + outer_offset;
                css.position = 'absolute';
                $p.removeClass('fx_node_panel_fixed');
            } else {
                css.position = 'fixed';
                css.top = top_fix;
                //css.opacity = 0.7;
                $p.addClass('fx_node_panel_fixed');
            }
        }
        
        if ($fx.front.is_fixed($node)) {
            css.top -= doc_scroll;
            css.position = 'fixed';
        }
        
        var win_width = $(window).outerWidth(),
            p_gone = (css.left + p_width) - (win_width - 3);
        
        
        if (p_gone > 0) {
            css.left = css.left - p_gone;
        }
        
        
        $p.css(css);
        
        if ($prev_panel.length) {
            function get_size($n) {
                var size = $n.offset();
                size.right = size.left + $n.width();
                size.bottom = size.top + $n.height();
                return size;
            }
            var c_size = get_size($p),
                p_size = get_size($prev_panel);
            
            if (c_size.left <= p_size.right && c_size.right >= p_size.left) {
                if (c_size.top <= p_size.bottom && c_size.bottom >= p_size.top) {
                    var pos = $prev_panel.css('position');
                    var css = {
                        position:pos,
                        top: parseInt($prev_panel.css('top')),
                        left: parseInt($prev_panel.css('left')) + $prev_panel.width() + 10
                    };
                    p_gone = (css.left + p_width) - win_width;
                    // tried to place it from the right side of the previous panel
                    // but there's not enough space
                    if (p_gone > 0) {
                        $prev_panel.css('left', '-='+(p_gone+10)+'px');
                        css.left = css.left - p_gone - 10;
                    }
                    $p.css(css);
                }
            }
        }
    };
    
    this.add_label = function(data, $before_node) {
        var $item = $('<div class="fx_node_panel__item fx_node_panel__item-type-label"></div>');
        var $label = $('<div class="fx_node_panel__item_label"></div>');
        $label.append(data);
        $item.append($label);
        if (!$before_node) {
            this.$panel.append($item);
        } else {
            $before_node.before($item);
        }
        return $item;
    };
    
    this.add_button = function(button, callback, $before_node) {
        var $button = $fx.front.create_button(button, callback);
        if (!$before_node) {
            this.$panel.append($button);
        } else {
            $before_node.before($button);
        }
        return $button;
    };
    
    this.init();
}
})($fxj);