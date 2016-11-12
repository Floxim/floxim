$fx.colorset = function($field, params) {
    var cl = 'fx-colorset',
        that = this;
    
    this.cl = $t.getBem(cl);
    this.el = $t.getBemElementFinder(cl);
    
    this.label = params.label;
    this.name = params.name;
    
    this.$node = $('.'+cl, $field);
    
    this.$node.data('fx-colorset', this);
    
    this.find = function(sel, $n) {
        if ($n === undefined) {
            $n = that.$node;
        }
        return $n.find( that.el(sel) );
    };
    
    if (typeof params.value === 'string') {
        var color = tinycolor(params.value).toHsv();
        params.value = {
            hue: color.h,
            saturation: color.s
        };
    }
    
    var val = $.extend(
        {
            hue:0,
            saturation:1,
            tweaked: {},
            vals: []
        },
        params.value
    );
    
    if (val.tweaked instanceof Array) {
        var tweaked_obj = {};
        for (var i = 0; i < val.tweaked.length; i++) {
            if (val.tweaked[i]) {
                tweaked_obj[i] = true;
            }
        }
        val.tweaked = tweaked_obj;
    }
    
    this.luminance_map = params.luminance_map || params.neutral ? [
        0.00,
        0.12,
        0.25,
        0.55,
        0.85,
        1.00
    ] : [
        0.06,
        0.10,
        0.2,
        0.5,
        0.65,
        0.8
    ];
    
    var saturation_range = params.saturation || params.neutral ? [0, 0.15] : [0.15, 1];
    
    this.saturation = Math.min(val.saturation, saturation_range[1]);
    this.hue = val.hue;
    
    this.tweaked = val.tweaked;
    
    this.hue_slider = this.draw_hue( 
        this.find( 'common hue' ), 
        0, 
        359 
    );
    
    var $colors = this.get_color_nodes();
    
    this.count_colors = $colors.length;
    
    $.each(val.tweaked, function(index, is_tweaked) {
        if (!is_tweaked) {
            return;
        }
        var c_value = val.vals[index],
            $c_color = $colors.eq(index);
        
        $c_color.data('value', c_value);
    });
    
    this.saturation_slider = this.draw_saturation( 
        this.find('common saturation'), 
        saturation_range[0],
        saturation_range[1]
    );
    setTimeout(function() {
        that.recount_colors();
    },50);
    
    this.$node.on('click', '.'+cl+'__color', function() {
        that.show_color_tweaker($(this));
    });
};

$fx.colorset.prototype = {
    
    show_color_tweaker: function($color) {
        var c_value = $color.data('value'),
            box = $color[0].getBoundingClientRect(),
            $popup = $(
                '<div class="fx_overlay fx_admin_form fx-colorset__popup">'+
                    //'<input type="text" value="'+c_value+'" />'+
                '</div>'
            ),
            index = this.get_color_nodes().index($color),
            that = this,
            is_tweaked = typeof this.tweaked[index] !== 'undefined' && this.tweaked[index];
    
        var $rgb = $fx_fields.input({
                type:'text',
                value: c_value
            }),
            $inp = $rgb.find('input'),
            $preview = $('<span class="fx-colorset__popup-preview"></span>');
            
        $inp.after($preview);
        
        $preview.css('background-color', c_value);
        
        $popup.append($rgb);
        
        if (is_tweaked) {
            var $cancel = $fx_fields.button(
                {
                    label:'Восстановить',
                    class:'delete'
                }, 
                'input'
            );
            $popup.append($cancel);
            $cancel.click(function() {
                delete that.tweaked[index];
                that.recount_colors();
                closer();
            });
        }
        
        var $submit = $fx_fields.button(
            {
                label:'Заменить цвет'
            }, 
            'input'
        );
        $popup.append($submit);
        
        $('body').append($popup);
        $popup.css({
            top: box.bottom,
            left: box.left
        });
        var $inp = $popup.find('input').first();
        
        $inp.focus();
        
        function get_color() {
            var cv = $inp.val(),
                color = tinycolor(cv);
            if (!color.isValid()) {
                return false;
            } 
            return color.toHexString();
        }
        
        $inp.on('input', function() {
            var color = get_color();
            $submit.toggleClass('fx_button-disabled', !color);
            if (color) {
                $preview.css('background-color', color);
            }
        })
        .on('keydown', function(e) {
            if (e.which === 13) {
                save();
                return false;
            }
        });
        
        var closer = $fx.close_stack.push(
            function() {
                $popup.remove();
            },
            $popup
        );

        
        function save() {
            var color = get_color();
            if (!color) {
                return;
            }
            $color.data('value', color);
            that.tweaked[index] = true;
            that.recount_colors();
            closer();
        }

        $submit.click(save);
    },
    
    normalize_hue: function (val) {
        if (val < 0) {
            return 360 + val;
        }
        if (val > 360) {
            return val - 360;
        }
        return val;
    },
    
    draw_hue: function($node, min, max) {
        $node.attr('title', 'Тон');
        var that = this;
        var slider = new number_slider(
            $node, {
                min:min,
                max:max,
                step:1,
                value:this.hue,
                change:function(val) {
                    that.hue = val;
                    that.draw_saturation_bg(that.saturation_slider.$node, val);
                    that.recount_colors();
                }
            }
        );
        this.draw_hue_bg($node,this.saturation);
        return slider;
    },
    
    draw_hue_bg: function($node, saturation) {
        var min = $node.data('fx-number-slider-min'),
            max = $node.data('fx-number-slider-max');
        
        var s = saturation,
            l = 0.5;
        
        var count = 15,
            step = (max - min) / count,
            parts = [];

        for (var i = 0; i <= count; i++) {
            var c_hue = min + (i * step);
            parts.push(
                tinycolor( 'hsl(' + this.normalize_hue(c_hue) + ', '+s+', '+l+')' ).toRgbString()
            );
        }

        $node.css(
            {
                'background-image': 'linear-gradient(to right, ' + parts.join(',') +')'
            }
        );
    },
    
    draw_saturation: function($node, min, max) {
        $node.attr('title', 'Насыщенность');
        var that = this,
            slider = new number_slider(
                $node, {
                    min:min,
                    max:max,
                    step:1,
                    value:this.saturation,
                    change:function(val) {
                        that.saturation = val;
                        that.draw_hue_bg(that.hue_slider.$node, val);
                        that.recount_colors();
                    }
                }
            );
        this.draw_saturation_bg($node, this.hue);
        return slider;
    },
    
    draw_saturation_bg: function($node, hue) {
        var min = $node.data('fx-number-slider-min'),
            max = $node.data('fx-number-slider-max');
        
        var l = 0.5,
            parts = [];
        
        $.each([min, max], function(n, saturation) {
            parts.push(
                tinycolor( 'hsl(' + hue + ', '+saturation+', '+l+')' ).toRgbString()
            );
        });
        
        $node.css(
            {
                'background-image': 'linear-gradient(to right, ' + parts.join(',') +')'
            }
        );
    },
    
    get_color_nodes: function() {
        return this.find('color');
    },
    
    recount_colors: function() {
        var hue = this.hue,
            saturation = this.saturation;
    
        //var method = 'lightness',
        var method = 'luminance',
            colors = this['colors_by_'+method](hue, saturation),
            $colors = this.get_color_nodes();
    
        console.log(this.tweaked);
        
        for (var i = 0; i < colors.length; i++) {
            var color = colors[i],
                $c = $colors.eq(i),
                is_tweaked = typeof this.tweaked[i] !== 'undefined' && this.tweaked[i],
                hex = is_tweaked ? $c.data('value') : color.toHexString();
        
            $c
                .css('background', hex)
                .attr('title', hex.toUpperCase() + ' | '+this.name+'_'+i)
                .data('value', hex)
                .toggleClass('fx-colorset__color_tweaked', is_tweaked);
        }
        this.save();
    },
    
    colors_by_luminance:  function(hue, saturation) {
        var res = [],
            l_map = this.luminance_map,
            lightness = 0;
        
        for (var i = 0; i < this.count_colors; i++) {
            var target_lum = l_map[i],
                color, 
                lum = 0,
                counter = 0;
            
            do {
                color = tinycolor('hsl(' + hue +', '+saturation+', '+lightness+')');
                lum = color.getLuminance(),
                lightness += 0.003;
                counter++;
            } while (
                target_lum - lum > 0.005
                && counter < 1000
            );
            res.push( color );
        }
        return res;
    },
    
    colors_by_lightness: function(hue, saturation) {
        var res = [],
            range = this.name === 'color_main' ? [0, 1] : [0.3, 0.9],
            step = this.round( (range[1] - range[0]) / (this.count_colors - 1) , 3);
        
        for (var i = 0; i < this.count_colors; i++) {
            var lightness = range[0] + step * i,
                color = tinycolor('hsl(' + hue +', '+saturation+', '+lightness+')');
            res.push( color );
        }
        return res;
    },
    
    round : function( v, num) {
        num = num || 0;
        var m = Math.pow(10, num);
        return Math.round( v * m) / m;
    },
    
    save: function() {
        var res = {
            hue: this.round(this.hue),
            saturation: this.round ( this.saturation , 3 ),
            vals: []
        };
        this.get_color_nodes().each(function() {
            res.vals.push( $(this).data('value') );
        });
        
        res.tweaked = this.tweaked;
        this.find('value').val( JSON.stringify(res) ).trigger('change');
    },
    
    stage_class: 'fx-colorset-stage',
    
    get_stage: function() {
        var cl = this.stage_class;
        var $stage = $('.'+cl);
        if ( $stage.length === 0) {
            $stage = $('<div class="'+cl+'"></div>');
            $('body').append($stage);
        }
        return $stage;
    },
    
    redraw_stage: function() {
        var $stage = this.get_stage(),
            cl = this.stage_class,
            $sets = $('.fx-colorset'),
            map = [],
            that = this;
        
        $sets.each(function() {
            var c_set = $(this).data('fx-colorset'),
                $colors = c_set.find('color'),
                colors = [];
            
            $colors.each(function(n) {
                colors.push ({
                    value:$(this).data('value'),
                    name: n + 1
                });
            });
            
            map.push( {
                name:c_set.name,
                colors: colors
            });
        });
        
        var html = '';
        for (var i = 0; i < map.length; i++) {
            var colors = map[i].colors;
            html += '<div class="'+cl+'__row">';
            for (var j = 0; j < colors.length; j++) {
                html += '<div class="'+cl+'__cell" style="background-color:'+colors[j].value+';" title="'+map[i].name+' '+j+'">';
                
                html += that.draw_stage_lines(map, j);
                
                html += '</div>';
                
            }
            html += '</div>';
        }
        $stage.html(html);
    },
    
    draw_stage_lines: function(map, color_num) {
        var res = '';
        for (var i = 0; i < map.length; i++) {
            var set = map[i],
                colors = set.colors;
            for (var j = 0 ; j < colors.length; j++) {
                if (color_num < 2 && j < 2 || color_num >=2 && j >= 2) {
                    continue;
                }
                var color = colors[j];
                res += '<div class="'+this.stage_class+'__line" style="color:'+color.value+';" title="'+set.name + ' ' + color.name+'">';
                res += 'мелко' + ' <b>крупно</b>';
                res += '</div>';
            }
        }
        return res;
    }
};