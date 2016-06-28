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

        var init_value = this.$value.val();
        if (!this.check_value(init_value)) {
            init_value = this.get_default_value();
        }
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

    check_value: function(value) {
        var vals = this.get_values(value);
        return vals.length === 4;
    },

    set_value: function(val) {
        this.value = val;
        this.redraw_preview();
        this.append_controls_values();
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
    
    sync_locked: function(index) {
        if (this.lock === 'none') {
            return;
        }
        var lock = this.lock === 'all' ? '1-2-3-4' : this.lock,
            lock_parts = lock.split('--');
        
        var vals = this.get_current_values(),
            changed = vals[index],
            named_index = index + 1;
        for (var i = 0; i < lock_parts.length; i++) {
            var c_part = lock_parts[i].split('-').map(function(v) {
                return v * 1;
            });
            if ( c_part.indexOf( named_index ) !== -1 ) {
                for (var j = 0; j < c_part.length; j++) {
                    var c_part_index = c_part[j];
                    if (c_part_index !== named_index ) {
                        this.append_control_value(changed, c_part_index - 1);
                    }
                }
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
        $.each( this.$controls.find( this.el('control') ), function(index) {
            that.init_number_control($(this), params, index);
        });
    },

    init_number_control: function($c, params, index) {
        params = $.extend({
            min:0,
            max:10,
            step:0.5
        }, params);
        var $inp = $(
                '<input class="' + this.cl('number-input')
                    + '" type="number" min="'+params.min+'" max="'+params.max+'" step="'+params.step+'" />'
            ),
            that = this;
            
        $fx_fields.handle_number_wheel($inp);
        
        $inp.on('change input', function() {
            that.recount_value(index);
            return false;
        });
        $c.append($inp);
        this.inputs.push($inp);
    },
    append_controls_values: function() {
        var vals = this.get_values(this.value);
        for (var i = 0; i < vals.length; i++) {
            this.inputs[i].val(vals[i]);
        }
    },
    get_current_value: function(i) {
        return this.inputs[i].val();
    },
    append_control_value: function(value, index) {
        console.log(index, value);
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