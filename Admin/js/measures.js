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
        var el = this.el = $t.getBemElementFinder('fx-measures');
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
    },

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

    recount_value: function() {
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
        $.each( this.$controls.find( this.el('control') ), function() {
            that.init_number_control($(this), params);
        });
    },

    init_number_control: function($c, params) {
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
        $inp.on('change input', function() {
            that.recount_value();
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
    get_default_value: function() {
        return '0 0 0 0';
    }
};

/* padding */

$fx.measures.padding = function() {};

$fx.measures.padding.prototype = new $fx.measures();

$fx.measures.padding.prototype.redraw_preview = function() {};

$fx.measures.padding.prototype.init_controls = function() {
    this.init_number_controls({
        min:0,
        max:30,
        step:0.5
    });
};

/* margin  */

$fx.measures.margin = function() {};

$fx.measures.margin.prototype = new $fx.measures();

$fx.measures.margin.prototype.redraw_preview = function() {};

$fx.measures.margin.prototype.init_controls = function() {
    this.init_number_controls({
        min:-30,
        max:30,
        step:0.5
    });
};

/* corners */

$fx.measures.corners = function() {};

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