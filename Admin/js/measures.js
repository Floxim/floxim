$fx.measures = function() {

};

$fx.measures.create = function($row, json) {
    var constructor = this[json.prop] || this;
    var item = new constructor();
    item.init($row, json);
};

$fx.measures.prototype = {
    init: function($row, params) {
        this.params = params;
        var el = this.el = $t.getBemElementFinder('fx-measures'),
            that = this;
    
        this.cl = $t.getBem('fx-measures');
        this.$preview = $( el( 'preview' ) , $row);
        this.$controls = $( el('controls'), $row);
        this.$value = $( el ('value') , $row);
        this.init_controls();

        var init_value = this.prepare_init_value(this.$value.val());
        /*
        if (!this.check_value(init_value)) {
            init_value = this.get_default_value();
        }
        */
        this.set_value( init_value );
        
        this.$lock = $( el('lock'), $row);
        this.lock = params.lock || 'none';
        
        this.$lock.addClass( this.get_lock_class() );
        
        this.$lock.click(function() {
            var c_index = that.lock_map.indexOf(that.lock),
                next_index = c_index + 1;
            if (next_index >= that.lock_map.length) {
                next_index = 0;
            }
            var prev_class = that.get_lock_class();
            
            that.lock = that.lock_map[ next_index ];
            
            var new_class = that.get_lock_class();
            
            that.$lock.removeClass(prev_class).addClass(new_class);
        });
    },
    
    get_lock_class: function() {
        return this.cl('lock', 'mode_'+this.lock)
    },
    
    lock_map: [
        'none',
        '1-3--2-4',
        'all'
    ],
    
    prepare_init_value: function(value) {
        if (!value) {
            return this.get_default_value();
        }
        var parts = value.split(this.value_separator),
            that  = this;
        parts = parts.map(function(v) {
            return v.replace(/[^\d\.\-]+/, '') + that.units;
        });
        if (parts.length === 1) {
            parts = [
                parts[0], parts[0], parts[0], parts[0]
            ];
        } else if (parts.length === 2) {
            parts = [
                parts[0], parts[1], parts[0], parts[1]
            ];
        }
        return parts.join(this.value_separator);
    },

    check_value: function(value) {
        var vals = this.get_values(value);
        return vals.length === 4;
    },

    set_value: function(val) {
        this.value = val;
        this.$value.val(val);
        this.append_controls_values();
        this.redraw_preview();
    },

    value_separator: ' ',

    units: 'rem',

    get_values: function(value) {
        var that = this;
        var rex = new RegExp( that.units + '$' );
        return  value
                .split(this.value_separator)
                .map(function(v) {
                    return v.replace( rex, '');
                });
    },
    
    get_locked_indexes: function(index) {
        if (this.lock === 'none') {
            return [];
        }
        var lock = this.lock === 'all' ? '1-2-3-4' : this.lock,
            lock_parts = lock.split('--');
        
        var named_index = index + 1,
            res = [];
        for (var i = 0; i < lock_parts.length; i++) {
            var c_part = lock_parts[i].split('-').map(function(v) {
                return v * 1;
            });
            if ( c_part.indexOf( named_index ) !== -1 ) {
                for (var j = 0; j < c_part.length; j++) {
                    var c_part_index = c_part[j];
                    if (c_part_index !== named_index ) {
                        res.push( c_part_index - 1 );
                    }
                }
            }
        }
        return res;
    },
    
    sync_locked: function(index) {
        var indexes = this.get_locked_indexes(index),
            vals = this.get_current_values(),
            changed = vals[index];
        for (var i = 0 ; i < indexes.length; i++) {
            if (indexes[i] !== index) {
                this.append_control_value(changed, indexes[i]);
            }
        }
    },
    
    recount_value: function(index) {
        this.sync_locked(index);
        var that = this;
        var value = this
                    .get_current_values()
                    .map(function(v) {
                        return v && v * 1 !== 0 ? v + that.units : 0;
                    })
                    .join(this.value_separator);

        this.$value.val(value).trigger('change');
    },

    get_current_values: function() {
        var res = [];
        for (var i = 0; i < 4; i++) {
            res.push( this.get_current_value(i) );
        }
        return res;
    },

    init_number_controls: function(params) {
        var that = this;
        this.inputs = [];
        var $all_controls = this.$controls.find( this.el('control') );
        $.each( $all_controls, function(index) {
            that.init_number_control($(this), params, index);
        });
        
        var active_class = 'fx-measures__control_active';
        
        $all_controls.on('mouseenter', function(e) {
            var $control = $(this),
                index = $all_controls.index( $control ),
                locked = that.get_locked_indexes(index);
                
            $control.addClass(active_class);
            for (var i = 0; i < locked.length; i++) {
                $all_controls.eq( locked[i] ).addClass(active_class);
            }
            
        }).on('mouseleave', function(e) {
            $all_controls.removeClass(active_class);
        });
    },

    init_number_control: function($c, params, index) {
        params = $.extend({
            min:0,
            max:10,
            step:0.5,
            focus:true
        }, params);
        var $inp = $(
                '<input class="' + this.cl('number-input')
                    + '" type="number" min="'+params.min+'" max="'+params.max+'" step="'+params.step+'" />'
            ),
            that = this;
            
        $fx_fields.handle_number_wheel($inp, {$target:$c});
        
        $inp.on('change input', function() {
            that.recount_value(index);
            return false;
        });
        $c.append($inp);
        if (params.focus) {
            $c.on('click', function() {
                $inp.focus().select();
            });
        }
        this.inputs.push($inp);
        return $inp;
    },
    append_controls_values: function() {
        var vals = this.get_values(this.value);
        for (var i = 0; i < vals.length; i++) {
            var $inp = this.inputs[i];
            if ($inp) {
                //$inp.val(vals[i]);
                this.append_control_value(vals[i], i);
            }
        }
    },
    get_current_value: function(i) {
        return this.inputs[i].val();
    },
    append_control_value: function(value, index) {
        this.inputs[index].val(value);
    },
    get_default_value: function() {
        return '0 0 0 0';
    }
};

/* padding */

$fx.measures.padding = function() {};

$fx.measures.padding.prototype = new $fx.measures();

$fx.measures.padding.prototype.redraw_preview = function() {};

$fx.measures.padding.prototype.init_controls = function() {
    this.init_number_controls(
        $.extend(
            {
                min:0,
                max:30,
                step:0.5
            }, 
            this.params
        )
    );
};

/* margin  */

$fx.measures.margin = function() {};

$fx.measures.margin.prototype = new $fx.measures();

$fx.measures.margin.prototype.redraw_preview = function() {};

$fx.measures.margin.prototype.init_controls = function() {
    this.init_number_controls(
        $.extend(
            {
                min:-30,
                max:30,
                step:0.5
            }, 
            this.params
        )
    );
};

/* corners */


$fx.measures.corners = function() {
    this.lock_map = [
        'none',
        '1-2--3-4',
        'all'
    ];
};

$fx.measures.corners.prototype = new $fx.measures();

$fx.measures.corners.prototype.units = 'px';

$fx.measures.corners.prototype.redraw_preview = function() {};

$fx.measures.corners.prototype.init_controls = function() {
    this.init_number_controls({
        min:0,
        max:50,
        step:1
    });
};

/* borders */


$fx.measures.borders = function() {
    /*
    this.lock_map = [
        'none',
        '1-2--3-4',
        'all'
    ];
    */
};

$fx.measures.borders.prototype = new $fx.measures();

$fx.measures.borders.prototype.units = 'px';

$fx.measures.borders.prototype.redraw_preview = function() {};

$fx.measures.borders.prototype.get_locked_indexes = function(index) {
    var  res = [];
    if (this.lock === 'none') {
        return res;
    }
    if (this.lock === 'all') {
        for ( var i = 0; i < 16; i += 4) {
            var ci = i + (index % 4);
            res.push(ci);
        }
        return res;
    }
    
    var offset = index % 4,
        pos = Math.floor(index / 4);
    
    if (offset === 0) { // radius
        res = pos < 2 ? [0, 4] : [8, 12];
    } else { // other props
        var n = pos % 2 === 0 ? 0 : 4;
        res = [offset + n, offset + n + 8];
    }
    return res;
};

$fx.measures.borders.prototype.init_controls = function() {
    this.inputs = [];
    var $all_controls = this.$controls.find( this.el('control') ),
        n = 0,
        that = this;
    $.each( $all_controls, function(index) {
        var $c = $(this);
        var $corner = that.init_number_control(
            $c, 
            {
                min:0,
                max:50,
                step:1,
                focus:false
            }, 
            n
        );
        n++;
        
        var $size = that.init_number_control(
            $c, 
            {
                min:0,
                max:50,
                step:1,
                focus:false
            }, 
            n
        );
        n++;
        
        var $color = $fx_fields.control({
                type:'palette',
                empty: false,
                opacity: true
            }),
            palette = $color.data('palette');
            
        
        
        $color.addClass('c_'+index);
        $c.append($color);
        var $color_ph = $('<div class="'+that.cl('color-placeholder')+'"><span></span></div>');
        $c.append($color_ph);
        
        var styles = ['solid', 'dotted', 'dashed'],
            style_values = [];
            
        for (var j = 0; j < styles.length; j++) {
            var style = styles[j],
                css = "display:inline-block;" +
                      "width:100px; "+
                      "border-bottom:1px "+style+" #000;"+
                      "position:relative;"+
                      "top:-5px;";
            style_values.push(
                {
                    id:style,
                    name: "<span style='"+css+"'></span>",
                    title: style
                }
            );
        }
        
        
        
        var $style = $fx_fields.livesearch({
            /*
            values: [
                {id:'solid', name:"Solid <span style='border-bottom:1px solid #000; display:inline-block;'></span>"},
                {id:'dotted',name:'Dotted'},
                {id:'dashed',name:'Dashed'}
            ],
            */
            values: style_values,
            value:'solid'
        }, 'input');
        
        that.inputs.push($style);
        
        $style.on(
            'change', 
            (function(n) {
                return function() {
                    that.recount_value(n);
                };
            })(n)
        );
        
        n++;
        
        
        
        that.inputs.push($color);
        
        $color.on(
            'change', 
            (function(n) {
                return function() {
                    that.recount_value(n);
                };
            })(n)
        );
        
        $style.css('margin-bottom', '10px');
        
        palette.$colors.children().first().before($style);
        
        $size.on('focus', function() {
            palette.show();
        });
        n++;
    });
};

$fx.measures.borders.prototype.get_current_value = function(index) {
    var $inp = this.inputs[index];
    if ($inp.is('.fx-palette')) {
        var v = $inp.data('palette').val();
        return v ? v : 'transparent';
    }
    if ($inp.is('.livesearch')) {
        return $inp.data('livesearch').getValue();
    }
    return $inp.val() || '0';
};

$fx.measures.borders.prototype.recount_value = function(index) {
    this.sync_locked(index);
    var that = this;
    function unit(v) {
        return v && v * 1 !== 0 ? v + that.units : 0;
    }
    var value = this
                .get_current_values()
                .map(function(v, i) {
                    return (i % 4 <= 1) ? unit(v) : v;
                });
                
    var stack = [],
        res = [],
        i = 0;

    do {
        stack.push(value[i]);
        if (stack.length === 4) {
            res.push( stack.join(' '));
            stack = [];
        }
        i++;
    } while (i < value.length);
    
    res = res.join(', ');
    
    this.$value.val(res).trigger('change');
    this.redraw_preview();
};

$fx.measures.borders.prototype.redraw_preview = function() {
    var groups = this.get_group_values(this.$value.val()),
        corners = [
            'top-left',
            'top-right',
            'bottom-right',
            'bottom-left'
        ];
    
    for (var i = 0; i < groups.length; i++) {
        var g = groups[i],
            $c = this.$controls.find('.fx-measures__control').eq(i),
            $span = $c.find('.fx-measures__color-placeholder span'),
            radius = parseInt(g[0]),
            show_size = parseInt(g[1]) > 0 ? '2px' : '0px',
            style = g[2],
            color = $c.find('.fx-palette').data('palette').get_color(0),
            show_radius = radius > 0 ? Math.min( Math.max(5, radius), 15) : 0,
            corner = corners[i];
        
        this.$controls.css(
            'border-'+corner+'-radius',
            show_radius+'px'
        );

        $span.css(
            'border-'+ (i % 2 === 0 ? 'bottom' : 'left'),
            show_size+' '+style+' '+color
        );
    }
};

$fx.measures.borders.prototype.append_control_value = function(value, index) {
    var $inp = this.inputs[index];
    if ($inp.is('.fx-palette')) {
        $inp.data('palette').val(value);
    } else if ($inp.is('.livesearch')) {
        $inp.data('livesearch').setValue(value, true);
    } else {
        $inp.val(value);
    }
};

$fx.measures.borders.prototype.get_group_values = function(v) {
    
    var groups = v.split(/,\s*/),
        res = [];
    
    for (var i = 0; i < groups.length; i++) {
        var group = groups[i].split(' '),
            c_res = [];
        c_res.push (group.shift().replace(/px$/, '')); // radius
        c_res.push (group.shift().replace(/px$/, '')); // size
        c_res.push (group.shift()); // style
        c_res.push (group.join(' ')); // color
        res.push(c_res);
    }
    return res;
};

$fx.measures.borders.prototype.get_values = function(v) {
    var groups = this.get_group_values(v),
        res = [];

    for (var i = 0; i< groups.length; i++) {
        var g = groups[i];
        for (var j = 0; j < g.length; j++) {
            res.push(g[j]);
        }
    }
    return res;
    
    
    var groups = v.split(/,\s*/),
        res = [];
    
    for (var i = 0; i < groups.length; i++) {
        var group = groups[i].split(' ');
        res.push (group.shift()); // radius
        res.push (group.shift().replace(/px$/, '')); // size
        res.push (group.shift()); // style
        res.push (group.join(' ')); // color
    }
    return res;
};

$fx.measures.borders.prototype.check_value = function() {
    return true;
};

$fx.measures.borders.prototype.prepare_init_value = function(value) {
    if (!value || value === 'none') {
        value = '0 0 solid main 2 1, 0 0 solid main 2 1, 0 0 solid main 2 1, 0 0 solid main 2 1';
    }
    return value;
};

$fx.measures.borders.prototype.get_current_values = function() {
    var res = [];
    for (var i = 0; i < this.inputs.length; i++) {
        res.push( this.get_current_value(i) );
    }
    return res;
};