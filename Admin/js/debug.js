(function($) {
$('html').on('click', '.fx_debug_collapser>span', function(e) {
    var $node = $(this.parentNode.nextSibling);
    
    // no control - toggle just current level
    if (!e.ctrlKey){
        $node.toggle();
        return;
    }
    // control pressed - toggle this level and all children
    if ($node.is(':visible')) {
        $node.hide();
        $('.fx_debug_collapse', $node).hide();
    } else{
        $node.show();
        $('.fx_debug_collapse', $node).show();
    }
    
});

window.fx_debug_draw = function(data, $target) {
    
    var entry = {
            index: {}
        },
        types = ["integer","double","string","array","boolean","null","resource","_link"],
        key_types = ['array', 'public', 'protected', 'private'];

    function get_type(data) {
        if (typeof data === 'string') {
            return 'string';
        }
        var type_id = data[0];
        if (typeof type_id === 'string') {
            return 'object';
        }
        var type = types[type_id];
        return type;
    }
    
    function collect_linked(data, path) {
        var type = get_type(data);
        
        path = path || [];
        
        if (type === '_link') {
            // set recursive marker
            data[2] = false;
            for (var i = 0; i < path.length; i++) {
                if (path[i] === data[1]) {
                    data[2] = true;
                    break;
                }
            }
            return;
        }
        if (type !== 'array' && type !== 'object') {
            return;
        }
        
        var alias = data[2];
        if (alias !== undefined) {
            entry.index[alias] = data;
            path.push(alias);
        }
        for (var i = 0; i < data[1].length; i++) {
            var sub = data[1][i][1];
            collect_linked(sub, path);
        }
        if (alias !== undefined) {
            path.pop();
        }
    }
    
    collect_linked(data);
    
    function get_subitems_counter(data) {
        var type = get_type(data);
        if (type !== 'array' && type !== 'object') {
            return null;
        }
        if (type === 'object') {
            var classname = data[0];
            if (!classname.match(/(Collection|Registry)$/)) {
                return null;
            }
            var res = 0,
                subs = data[1];
            for (var i = 0; i < subs.length; i++) {
                var sub = subs[i],
                    sub_index_type = sub[0][0];
                if (key_types[sub_index_type] === 'array') {
                    res++;
                }
            }
            return res;
        }
        // plain array
        return data[1].length;
    }
    
    function make_expandable($node, data, $title) {
        
        var type = get_type(data);
        if (type === '_link') {
            var link_id = data[1],
                is_recursive = data[2];
        
            data = entry.index[link_id];
            type = 'object';
            if (is_recursive) {
                $node.addClass('fx-debug__expandable_recursive');
            }
        }
        
        var is_expandable = (type === 'array' || type === 'object') && data[1].length > 0;
        if (!is_expandable) {
            return;
        }
        $node.addClass('fx-debug__expandable fx-debug__expandable_collapsed');
        $node.data('debug_data', $.extend({}, data));
        $title.addClass('fx-debug__expander');
    }
    
    function draw_item(item, $target) {
        var $n = $('<div class="fx-debug__item"></div>');
        if (typeof item === 'string') {
            $n.addClass('fx-debug__item_type_string');
            if (item.slice(0,5) === '%raw%') {
                $n.html(item.slice(5));
            } else {
                $n.text(item);
            }
            
        } else {
            var type = get_type(item);
            
            if (type === '_link') {
                item = entry.index[item[1]];
                type = 'object';
            }
            
            $n.addClass('fx-debug__item_type_'+type);
            
            switch (type) {
                case 'null':
                    $n.text('null');
                    break;
                case 'integer': case 'double': case 'boolean': case 'resource':
                    $n.text(item[1]);
                    break;
                case 'array': case 'object':
                    var $title = $('<div class="fx-debug__item-title"></div>');
                    $title.text( type === 'array' ? 'Array' : item[0] );
                    var sub_count = get_subitems_counter(item);
                    if (sub_count !== null) {
                        $title.append('<span class="fx-debug__child-counter">'+sub_count+'</span>');
                        if (sub_count === 0) {
                            $n.addClass('fx-debug__item_empty');
                        }
                    }
                    $n.append($title);
                    if ($target.hasClass('fx-debug__data-entry')) {
                        make_expandable($n, item, $title);
                    }
                    break;
            }
            //$n.text(type);
        }
        $target.append($n);
    }
    
    function draw_children(data, $target) {
        if ($target.data('debug_children')) {
            return;
        }
        if (!data || !data[1]) {
            return;
        }
        var subs = data[1],
            type = get_type(data),
            is_array = type === 'array',
            $children = $('<div class="fx-debug__children"></div>');
        
        $target.append($children);
        
        var prev_key_type = null;
        
        for (var i = 0; i < subs.length; i++) {
            var sub = subs[i],
                sub_key = sub[0],
                sub_val = sub[1],
                key_type = is_array ? 'array' : key_types[sub_key[0]],
                key_value = '<span class="fx-debug__key-name">'+ (is_array ? sub_key : sub_key[1] ) +'</span>',
                $child = $('<div class="fx-debug__child"></div>'),
                $title = $('<div class="fx-debug__child-title"></div>'),
                $key = $('<div class="fx-debug__key"></div>'),
                $val = $('<div class="fx-debug__value"></div>');
        
            $key.addClass('fx-debug__key_type_'+key_type);
            if (key_type === 'array') {
                if (type === 'object') {
                    /*
                    key_value = '<span class="fx-debug__arr-bracket">[</span>' 
                                + key_value + 
                            '<span class="fx-debug__arr-bracket">]</span>';
                    */
                   key_value = '<span class="fx-debug__prop-marker">@</span>' + key_value;
                }
            } else {
                key_value = '<span class="fx-debug__prop-marker">*</span>' + key_value;
            }
            $key.html(key_value);
            
            draw_item(sub_val, $val);
                
            $title.append($key);
            $title.append($val);
            
            $child.append($title);
            
            make_expandable($child, sub_val, $title);
            
            $children.append($child);
            if (prev_key_type === 'array' && key_type !== 'array') {
                $child.addClass('fx-debug__child_first-prop');
            }
            prev_key_type = key_type;
        }
        $target.data('debug_children', $children);
    }
    
    entry.toggle_item = function($item) {
        var data = $item.data('debug_data'),
            col_class = 'fx-debug__expandable_collapsed';
        if (!$item.hasClass(col_class)) {
            $item.addClass(col_class);
            return;
        }
        $item.removeClass(col_class);
        draw_children(data, $item);
    };
    
    draw_item(data, $target);
    $target.data('debug_entry', entry);
};

function fx_debug_init($container) {
    $('.fx-debug__data-entry', $container).each(function() {
        
        
        var $entry = $(this),
            hash = $entry.data('hash'),
            ready_class = 'fx-debug__data-entry_ready';
            
        if ($entry.hasClass(ready_class)) {
            return;
        }
        
        var data = window.fx_debug_data[hash];
        if (typeof data !== 'string' && (!data || data[0] === undefined)) {
            console.log('invalid', data, typeof data);
            return;
        }
        $entry.html('');
        fx_debug_draw(data, $(this));
        $entry.addClass(ready_class).attr('style', '');
    });
    if (window.Clipboard) {
        var $cb = $('<div class="fx-debug-clipboard-button">copy</div>');
        $('body').append($cb);
        $cb.css({
            position:'absolute',
            top:90,
            right:0,
            'z-index':100000,
            display:'none'
        });
        $cb.attr('data-clipboard-target', '.fx-debug__item_active');
        var clipboard = new Clipboard($cb[0]),
            active_class = 'fx-debug__item_active';
        clipboard.on('success', function() {
            $cb.hide();
            $('.'+active_class).removeClass(active_class);
        });
    }
    $container
        .off('.fx_debug')
        .on(
            'click.fx_debug', 
            '.fx-debug__expandable .fx-debug__expander', 
            function() {
                var $item = $(this).closest('.fx-debug__expandable'),
                    $entry = $item.closest('.fx-debug__data-entry');
                $entry.data('debug_entry').toggle_item( $item );
                return false;
            }
        );
    if (window.Clipboard) {
        $container.on('click.fx_debug', '.fx-debug__item_type_string', function(e) {
            var $node = $(e.target);
            if ($node.hasClass(active_class)) {
                $node.removeClass(active_class);
                $cb.css('display', 'none');
            } else {
                $('.'+active_class).removeClass(active_class);
                $node.addClass(active_class);
                var node_box = e.target.getBoundingClientRect(),
                    $entry = $node.closest('.fx_debug_entry'),
                    c_box = $entry[0].getBoundingClientRect();
                    
                $entry.append($cb);
                
                $cb.css( {
                    top:node_box.top - c_box.top,
                    left: Math.min(node_box.right - c_box.left, (c_box.right - c_box.left - 60)),
                    display:'inline-block'
                });
            }
            return false;
        });
    }
}

$(function() {
    fx_debug_init($('body'));
    $('html').on('fx_render', function(e) {
        fx_debug_init( $(e.target ) );
    });
});

})($fxj);