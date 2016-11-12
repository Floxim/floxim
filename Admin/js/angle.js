(function() {

var cl = 'fx-angle-control';

function angle_control ($node, params) {
    this.$node = $node;
    this.$input = $node.find('.'+cl+'__input');
    this.$point = $node.find('.'+cl+'__point');
    
    params = angle_control.add_defaults(params);
    
    
    var value = (params.value || 0) * 1;
    
    var that = this;
    
    $fx_fields.handle_number_wheel(
        this.$input,
        {
            $target: this.$node,
            focus: false
        }
    );
    
    var move_by_event = function(e, snap) {
        var box = that.$node[0].getBoundingClientRect(),
            ex = e.clientX,
            ey = e.clientY,
            cx = box.left + box.width / 2,
            cy = box.top + box.height / 2;
        
        var res = Math.atan2(ey - cy, ex - cx) * 180 / Math.PI + 90;
        if (snap) {
            res = 45 * Math.round( res / 45);
        }
        set_value(res);
    };
    
    var place_point = function(angle) {
        that.$point.css('transform', 'rotate('+angle+'deg)');
    };
    
    var normalize = function(val) {
        val = (val * 1) % 360;
        if (val < 0) {
            val += 360;
        }
        return Math.round(val);
    };
    
    var tick = function(dir, shifted) {
        var diff = params.step * (shifted ? 10 : 1),
            res = value + diff * (dir === 'up' ? 1 : -1);
        set_value ( res );
    };
    
    
    var set_value = function(v) {
        var normed = normalize(v);
        place_point( normed );
        if (normed === value) {
            return;
        }
        value = normed;
        that.$input.val(value).trigger('change');
    };
    
    this.$input.on('keydown', function(e) {
        if (e.which === 38) {
            tick('up', e.shiftKey);
            return false;
        }
        if (e.which === 40) {
            tick('down', e.shiftKey);
            return false;
        }
    });
    
    this.$input.on('input change', function() {
        set_value(that.$input.val());
    });
    
    var $body = $('body');
    
    function disableSelection(){
	if (window.getSelection) {
            window.getSelection().removeAllRanges();
        } else {
            document.selection.empty();
        }
	return false;
    }
    
    this.$node.on('mousedown', function(e) {
        var $target = $(e.target);
        if ( $target.closest(that.$input).length) {
            return;
        }
        
        if ($target.closest( that.$point).length === 0) {
            move_by_event(e, true);
        }
        
        var h = function(e) {
                disableSelection();
                that.$node.focus();
                move_by_event(e);
            },
            up_handle = function() {
                clearTimeout(move_timeout);
            },
            move_timeout = setTimeout(
                function() {
                    that.$input.attr('disabled', 'disabled');
                    $body
                        .off('mouseup', up_handle)
                        .on('mousemove', h)
                        .one('mouseup', function() {
                            $body.off('mousemove', h);
                            that.$input.attr('disabled',null).focus();
                            return false;
                        });
                },
                100
            );
        $body.one('mouseup', up_handle);
    });
    
    set_value(value);
}

angle_control.add_defaults = function(_c) {
    return $.extend(
        true,
        {
            min:-1,
            max:360,
            step:1,
            value: _c.default || 0
        },
        _c
    );
};

window.fx_angle_control = angle_control;

})();