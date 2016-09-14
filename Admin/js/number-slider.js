var number_slider = function($node, params) {    
    
    params = params || {};
    
    var min = params.min || 0,
        step = params.step || 1,
        max = params.max || step * 100,
        change = params.change || function() {},
        cl = 'fx-number-slider',
        is_multiple = params.multiple || false,
        that = this;

    this.$node = $node;
    
    $node.data('fx-number-slider-min', min);
    $node.data('fx-number-slider-max', max);
    
    $node.addClass(cl);
    
    this.points = [];
    
    $node.data('fx-number-slider', this);

    function round ( v ) {
        if (params.round === undefined) {
            return v;
        }
        var m = Math.pow(10, params.round);
        return Math.round( v * m) / m;
    };
    
    var box;
        //,
        //value = params.value === undefined ? min : params.value;
    
    function x_to_val(x) {
        var ratio = box.width / (max - min);
        return round( x / ratio + min );
    }
    
    this.e_to_val = function(e) {
        return x_to_val(normalize_x(e.pageX));
    };
    
    function val_to_x(val) {
        var ratio = box.width / (max - min);
        return (val - min ) * ratio;
    }
    
    this.add_point = function(value) {
        var $point = $('<div class="'+cl+'__point"></div>');
        this.points.push($point);
        $node.append($point);
        this.set(value, $point);
        return $point;
    };
    
    this.remove_point = function($point) {
        var p = $point[0];
        for (var i = 0; i < this.points.length; i++) {
            if (this.points[i][0] === p) {
                this.points.splice(i, 1);
                $point.remove();
                break;
            }
        }
    };
    
    var closest_point = function(x) {
        var diff = null,
            $res = null,
            val = x_to_val(x);
        for (var i = 0; i < that.points.length; i++) {
            var $p = that.points[i],
                c_diff = Math.abs($p.value - val);
            
            if (diff === null || c_diff < diff) {
                diff = c_diff;
                $res = $p;
            }
        }
        return $res;
    };
    
    var normalize_x = function(x) {
        if (x < box.left) {
            x = box.left;
        } else if (x > box.right) {
            x = box.right;
        }
        x = x - box.left;
        return x;
    };
    
    var move = function(x, $point) {
        $point.css('left', x);
        var value = x_to_val(x);
        $point.value = value;
        change(value);
        return false;
    };
    
    var $body = $('body');
    
    function recount_box() {
        box = $node[0].getBoundingClientRect();
    }
    
    //$node.on('click', function(e) {
    $node.on('mousedown', function(e) {
        if (that.disabled) {
            return;
        }
        recount_box();
        var x = normalize_x(e.pageX);
        
        var $point = closest_point(x);
        $point.css('transition', 'left 0.09s ease');
        
        move(x, $point);
        setTimeout(function() {
            $point.css('transition', '');
        }, 100);
        
        var h = function(e)  {
            var x = normalize_x(e.pageX);
            move(x, $point);
        };
        
        
        $body
            .on('mousemove', h)
            .one('mouseup', function() {
                $body.off('mousemove', h);
            });
    });
    
    this.set = function(val, $point) {
        $point = $point || this.points[0];
        
        $point.value = val;
        recount_box();
        
        if (box.width === 0) {
            var that = this;
            setTimeout(function() {
                that.set(val, $point);
            }, 50);
            return;
        }
        var x = val_to_x(val);
        $point.css('left', x);
    };
    
    this.get = function($point) {
        $point = $point || this.points[0];
        return $point.value;
    };

    this.disabled = false;

    this.disable = function() {
        this.disabled = true;
        $node.addClass(cl+'_disabled');
    };
    this.enable = function() {
        this.disabled = false;
        $node.removeClass(cl+'_disabled');
    };
    
    if (!is_multiple) {
        var val = params.value === undefined ? min : params.value;
        this.add_point(val);
    }
    //this.set( value );
};