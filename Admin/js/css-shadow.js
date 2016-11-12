(function() {

function shadow($container, params) {
    this.$container = $container;
    this.$popup = $container.find(shadow.el('popup'));
    this.$input = $container.find(shadow.el('value'));
    this.$handler = $container.find(shadow.el('handler'));
    this.params = params;
    this.init();
}

shadow.cl = $t.getBem('fx-shadow-control');



var cls = function() {
    return ' class="'+shadow.cl.apply(null, arguments)+'" ';
};

shadow.el = $t.getBemElementFinder(shadow.cl());

shadow.parse_value = function(v) {
    if (v === 'none' || !v) {
        return [];
    }
    var parts = $fx.parse_css_value(v),
        res = [];

    function int(v) {
        return Math.round( parseFloat(v) );
    }
    
    for (var i = 0; i < parts.length; i++) {
        var p = parts[i],
            color = p[5] && p[6] && p[7] ? p[5] + ' ' + p[6] + ' ' + p[7] : '',
            level = {
                type: p[0],
                x: int(p[1]),
                y: int(p[2]),
                blur: int(p[3]),
                spread: int(p[4]),
                color: color
            };
        res.push(level);
    }
    
    return res;
};


shadow.prototype.init = function() {
    var raw_value = this.params.value,
        value = shadow.parse_value(raw_value),
        $levels = this.$popup.find(shadow.el('levels')),
        that = this;

    $('body').append(this.$popup);
    
    this.$popup.addClass('fx_overlay fx_admin_form '+shadow.cl());
    
    var first_open = true;
    
    function show_popup(){ 
        
        that.place_popup();
        
        that.closer = $fx.close_stack.push(
            function() {
                that.hide_popup();
            },
            that.$popup
        );
        if (first_open) {
            that.$container.parents().one('fx_destroy', function() {
                that.$popup.remove();
            });
            first_open = false;
        }
    }
    
    this.$handler.on('click', function() {
        show_popup();
        return false;
    });
    
    this.$popup.find( shadow.el('close') ).on('click', function(e) {
        that.closer(e);
        return false;
    });
    
    $levels.append(
        '<div' + cls('level')+'>'+
            '<div></div>'+
            '<div>Внутри?</div>'+
            '<div>Цвет</div>'+
            '<div>Рамер</div>'+
            '<div>Размытие</div>'+
            '<div>Расстояние</div>'+
            '<div>Угол</div>'+
        '</div>'
    );
    
    for (var i =0 ; i < value.length; i++) {
        var level = shadow_level.create(value[i]);
        level.draw($levels);
    }
    
    $levels.on('click', shadow.el('level-drop'), function(e) {
        setTimeout(
            function() {
                $(e.target).closest(shadow.el('level')).remove();
                that.update();
            },
            10
        );
    });
    
    this.$popup.on('click', shadow.el('add'), function() {
        var level = shadow_level.create();
        level.draw($levels);
        that.update();
    });
    
    $levels.on('change', function() {
        that.update();
    });
    that.update();
};

shadow.prototype.get_levels = function() {
    var res = [];
    var $levels = this.$popup.find( shadow.el('level') );
    $.each( $levels, function() {
        var level = $(this).data('shadow-level');
        if (level) {
            res.push(level);
        }
    });
    return res;
};

shadow.prototype.get_value = function() {
    var res = [];
    var levels = this.get_levels();
    for (var i = 0; i < levels.length; i++) {
        var level = levels[i];
        var v = level.get_value(),
            level_string =  v.type + ' ' + 
                            v.x +' '+
                            v.y +' '+
                            v.blur +' '+
                            v.spread + ' '+
                            v.color;
                    
        res.push(level_string);
    }
    if (res.length === 0) {
        return 'none';
    }
    return res.join(', ');
};

shadow.prototype.update = function() {
    
    var new_val = this.get_value() ;
    
    this.$input.val( new_val ).trigger('change');
    
    this.update_handle();
    
    if (this.$popup.is(':visible')) {
        this.place_popup();
    }
};

shadow.prototype.update_handle = function() {
    var levels = this.get_levels(),
        css = [];
    for (var i = 0; i < levels.length; i++) {
        css.push( levels[i].get_css() );
    }
    
    var res = css.join(', ');
    this.$handler.css('box-shadow', res);
};

shadow.prototype.place_popup = function() {
    this.$popup.css('left', 0).show();
    var rect = this.$handler[0].getBoundingClientRect(),
        popup_rect = this.$popup[0].getBoundingClientRect(),
        body_rect = document.body.getBoundingClientRect(),
        offset = body_rect.right - (rect.left + popup_rect.width);

    this.$popup.css({
        top: rect.top,
        left: rect.left + Math.min(offset, 0)
    });
};

shadow.prototype.hide_popup = function() {
    this.$popup.hide();
};

function shadow_level(value) {
    this.value = value || shadow_level.default_value();
};

shadow_level.create = function(v) {
    return new shadow_level(v);
};


shadow_level.default_value = function() {
    return shadow.parse_value('outer 2 2 10 2 main 0 0.5')[0];
};

shadow_level.prototype.get_angle = function() {
    
    var cx = 0,
        cy = 0,
        ex = this.value.x,
        ey = this.value.y;
    
    var dy = ey - cy;
    var dx = ex - cx;
    var res = Math.atan2(dy, dx); // range (-PI, PI]
    res *= 180 / Math.PI; // rads to degs, range (-180, 180]

    res += 90;
    return res;
};

shadow_level.prototype.get_distance = function() {
    var a2 = Math.pow(this.value.x, 2),
        b2 = Math.pow(this.value.y, 2);

    var res = Math.round(Math.sqrt( a2 + b2 ));
    
    return res;
};

shadow_level.prototype.draw = function($target) {
    this.$node = $(
        '<div' + cls('level', 'type_'+this.type) + '>'+
            '<div'+cls('level-controls')+'>'+
                '<span '+cls('level-drop')+'>&times</span>'+
            '</div>'+
            '<div'+cls('level-type')+'"></div>'+
            '<div'+cls('level-color')+'"></div>'+
            '<div'+cls('level-spread')+'"></div>'+
            '<div'+cls('level-blur')+'"></div>'+
            '<div'+cls('level-distance')+'"></div>'+
            '<div'+cls('level-angle')+'"></div>'+
        '</div>'
    );
    
    this.$node.data('shadow-level', this);
    
    var that = this;
    this.el = function() {
        var sel = shadow.el.apply(null, arguments);
        return that.$node.find(sel);
    };
    
    $target.append(this.$node);
    
    var $type = $fx_fields.control({
        type:'checkbox',
        value: this.value.type === 'inset'
    });
    
    this.el('level-type').append($type);
    
    this.$type = $type.find('input');
    
    
    var $color = $fx_fields.control({
        type: 'palette',
        empty: false,
        opacity: true,
        value: this.value.color
    });
    
    this.$color = $color;
    
    this.el('level-color').append($color);
    
    var $spread = $fx_fields.control({
        type: 'number',
        max: 99,
        value: this.value.spread
    });
    
    this.el('level-spread').append($spread);
    
    this.$spread = $spread;
    
    var $blur = $fx_fields.control({
        type: 'number',
        max: 99,
        value: this.value.blur
    });
    
    this.$blur = $blur;
    
    this.el('level-blur').append($blur);
    
    var $distance = $fx_fields.control({
        type: 'number',
        max: 99,
        value: that.get_distance()
    });
    
    this.$distance = $distance;
    
    this.el('level-distance').append($distance);
    
    var $angle = $fx_fields.control({
        type: 'angle',
        value: that.get_angle()
    });
    
    this.$angle = $angle.find('input');
    
    this.el('level-angle').append($angle);
};

shadow_level.prototype.get_value = function() {
    var res = {},
        distance = this.$distance.val(),
        angle = this.$angle.val() - 90,
        x = Math.cos(angle * Math.PI / 180) * distance,
        y = Math.sin(angle * Math.PI / 180) * distance;
    
    res.x = Math.round(x);
    res.y = Math.round(y);
    
    res.blur = this.$blur.val() * 1;
    res.spread = this.$spread.val() * 1;
            
    res.type = this.$type.val()*1 === 1 ? 'inset' : 'outer';
    
    res.color = this.$color.data('palette').val() || 'main 0 0.5';
    
    this.value = res;
    
    return res;
};

shadow_level.prototype.get_css = function() {
    /* offset-x | offset-y | blur-radius | spread-radius | color */
    // box-shadow: 2px 2px 2px 1px rgba(0, 0, 0, 0.2);
    var v = this.value,
        color = this.$color.data('palette').get_color();
    var css = (v.type === 'inset' ? 'inset ' : '') +
              v.x+'px '+v.y+'px '+v.blur+'px ' + v.spread + 'px '+color;
    
    return css;
};

window.fx_shadow_control = shadow;

})();