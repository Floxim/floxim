(function($) {
    
$fx.sortable = {
    create: function(params) {
        
        params = $.extend({
            clone:true,
            animate:true,
            axis:'auto'
        }, params);
        
        var $overlay = $fx.front.get_front_overlay(),
            $node = params.node,
            offset = $node.offset(),
            $sorter = $('<div class="fx_overlay fx_sorter"><div class="fx_icon fx_icon-type-move"></div></div>'),
            z_index = $fx.front.get_panel_z_index(),
            button_offset = $node.height()/2 - 20,
            click_offset = 0;
        
        $overlay.append($sorter);
        $sorter.css({
            top:offset.top + button_offset,
            left:offset.left - 45,
            'z-index':z_index
        });

        $('html').one('fx_deselect', function() {
            $sorter.remove();
        });
        
        var $placeholder = null,
            placeholder = null,
            map = [],
            is_moving = false,
            prev_node = null,
            next_node = null,
            recount_map = function() {
                map = [];
                $.each(params.entities, function() {
                    if (this === $node[0]) {
                        return;
                    }
                    var $e = $(this),
                        rect = this.getBoundingClientRect();

                    rect.$node = $e;

                    map.push(rect);
                });
            };
        
        function get_intersection(box) {
            var res = {
                square:0,
                $node:null
            };
            
            $.each(map, function() {
                if (this.top > box.bottom || this.bottom < box.top) {
                    return;
                }
                if (this.left > box.right || this.right < box.left) {
                    return;
                }
                
                var overlap_x = Math.min(box.right, this.right) - Math.max(box.left, this.left),
                    overlap_y = Math.min(box.bottom, this.bottom) - Math.max(box.top, this.top),
                    square = overlap_x * overlap_y;
                    
                if (square > res.square) {
                    res.$node = this.$node;
                    res.square = square;
                    res.rel_square = square / Math.min( this.width * this.height, box.width * box.height );
                    var node = this.$node[0],
                        edge_x, 
                        edge_y;
                    
                    if (node === next_node) {
                        edge_x = box.right;
                        edge_y = box.bottom;
                    } else if (node === prev_node) {
                        edge_x = box.left;
                        edge_y = box.top;
                    } else {
                        edge_x = (box.left + box.width/2);
                        edge_y = (box.top + box.height/2);
                    }
                    res.x =  (edge_x - (this.left + this.width/2) ) / this.width;
                    res.y =  (edge_y - (this.top + this.height/2) ) / this.height;
                }
            });
            return res;
        }
        
        
        $sorter.draggable({
            scrollSensitivity:80,
            start: function(e) {
                click_offset = {
                    x:$sorter.width() - e.offsetX,
                    y:$sorter.height() - e.offsetY
                };
                if (params.onstart) {
                    params.onstart();
                }
                recount_map();
                
                if (!params.clone) {
                    $placeholder = $('<div class="fx_sort_placeholder"></div>');
                    $placeholder.css({
                        width:Math.floor($node.width()) - 1,
                        height: $node.height(),
                        background:'#FF9',
                        display:$node.css('display') === 'inline' ? 'inline-block' : 'block',
                        float:$node.css('float')
                    });
                } else {
                    $placeholder = $($node[0].cloneNode(true));
                    $placeholder.css({
                        outline:'3px solid #EE0',
                        position:'relative',
                        'z-index':z_index - 2
                    });
                }
                
                placeholder = $placeholder[0];
                $node.css({
                    position:'absolute',
                    'z-index':z_index-1,
                    width:$node.width(),
                    opacity:0.3,
                    overflow:'hidden',
                    outline:'2px dotted #000'
                });
                $placeholder.insertBefore($node);
                
                prev_node = $placeholder.prev()[0];
                next_node = $node.next()[0];
                $sorter.css('opacity', '0');
            },
            drag: function(e) {

                $node.offset({
                    top:e.pageY - button_offset - click_offset.y,
                    left:e.pageX + click_offset.x
                });
                if (is_moving) {
                    return;
                }
                
                var box = $node[0].getBoundingClientRect();
                var intersect = get_intersection(box);
                if ( intersect.$node &&  intersect.rel_square > 0.5) {
                    
                    var old_rect = placeholder.getBoundingClientRect();
                    
                    if (intersect.x < 0) {
                        if (next_node === intersect.$node[0]) {
                            return;
                        }
                        $placeholder.insertBefore(intersect.$node);
                    } else {
                        if (prev_node === intersect.$node[0]) {
                            return;
                        }
                        $placeholder.insertAfter(intersect.$node);
                    }
                    
                    var duration = 10;
                    if (params.animate) {
                        duration = 200;
                    
                        var new_rect = placeholder.getBoundingClientRect();

                        placeholder.style.transition = 'none';

                        placeholder.style.transform = 'translate3d(' +
                               (old_rect.left - new_rect.left) + 'px,' +
                               (old_rect.top - new_rect.top) + 'px,0)';

                        setTimeout(function() {
                            placeholder.style.transition = 'all '+duration+'ms';
                            placeholder.style.transform = 'translate3d(0px,0px,0)';
                        }, 10);
                    }
                    
                    prev_node = $placeholder.prev()[0];
                    next_node = $placeholder.next()[0];
                    
                    is_moving = true;
                    
                    setTimeout(function() {
                        recount_map();
                        is_moving = false;
                    }, duration);
                }
            },
            stop: function() {
                $node.insertBefore($placeholder);
                $node.attr('style', '');
                $placeholder.remove();
                $sorter.remove();
                
                if (params.onstop) {
                    params.onstop();
                }
            }
        });
    }
};
    
})($fxj);