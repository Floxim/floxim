(function() {
    
window.condition_builder = function(params) {
    var that = this;
    var cl = 'fx-condition-builder';
    this.fields = params.fields || {};
    this.value = params.value || null;
    if (typeof this.value === 'string') {
        this.value = $.parseJSON(this.value);
    }
    this.$node = params.$node;
    this.name = params.name;
    this.context = params.context;
    
    this.$node.data('condition_builder', this);
    
    function get_date_value($control) {
        var res = $control.find('.date_input').val();
        return res;
    }
    
    var field_operators = {
        entity: [
            'is_in', 
            ['is_under_or_equals', {}],
            ['is_under', {}],
            'has_type'
        ],
        text: [
            'contains'
        ],
        string:[
            'contains',
            'equals'
        ],
        number: [
            ['equals', {value_type:'number'}],
            'greater', 
            'less'
        ],
        datetime: [
            ['greater', {value_type:'datetime', get_value: get_date_value}],
            ['less', {value_type:'datetime', get_value: get_date_value}]
        ],
        bool:['is_true'],
        image:['defined'],
        file:['defined'],
        select:[]
    };
    
    var select_livesearch = function(field_props, value) {
        var field = {
            is_multiple: 'true',
            params: {
                content_type: 'select_value',
                id_field: 'keyword',
                conditions: [
                    ['field_id', field_props.id]
                ]
            }
        };
        if (value && value.length) {
            field.value = value;
            field.ajax_preload = true;
        }
        var $control = $fx_fields.livesearch(field, 'input');
        $control.data('value_type_hash', 'livesearch_select_'+field_props.id);
        return $control;
    };
    
    field_operators.select.push( ['is_in', {value_type:select_livesearch}] );
    
    var operators = {
        contains: {name:'содержит'},
        equals: {name:'равно'},
        is_in: {name:'равно', allow_context:true, allow_expression:true},
        is_true: {
            name:'',
            value_type: function() {
                var $control = $fx_fields.control({
                    type:'radio_facet',
                    values: [
                        [1, 'да'],
                        [0, 'нет']
                    ],
                    value:1
                });
                $control.data('value_type_hash', 'is_true');
                return $control;
            }
        },
        has_type: {
            name:'имеет тип',
            value_type: function(field_props, value) {
                var field = {
                        is_multiple:true,
                        content_type:'component',
                        params: {
                            id_field:'keyword'
                        }
                    },
                    type_hash = 'livesearch_type';
                if (field_props.content_type) {
                    field.params.conditions = [ [null, field_props.content_type, 'is'] ];
                    type_hash += '_'+field_props.content_type;
                }
                if (value && value.length) {
                    field.value = value;
                    field.ajax_preload = true;
                }
                var $control = $fx_fields.livesearch(field, 'input');
                $control.data('value_type_hash', type_hash);
                return $control;
            },
            test: function(field_props) {
                return field_props.has_types;
            }
        },
        defined: {
            name:'задано',
            value_type: function() {
                return false;
            },
            invertable: false
        },
        greater: {name:'больше'},
        less: {name:'меньше'},
        is_under: {
            name: 'находится внутри',
            test: function(field_props) {
                return field_props.has_tree;
            },
            value_type: function(field_props, value) {
                var field = {
                    is_multiple:true,
                    content_type:'floxim.main.page'
                };
                if (value && value.length) {
                    field.value = value;
                    field.ajax_preload = true;
                }
                var $control = $fx_fields.livesearch(field, 'input');
                $control.data('value_type_hash', 'livesearch_page');
                return $control;
            }
        }
    };
    
    operators.is_under_or_equals = $.extend({}, operators.is_under, {name:'равно или внутри'});
    
    operators.is_in.value_type = function(field_props, value) {
        var params = {
                is_multiple: 'true',
                params: {}
            },
            type_hash = 'livesearch_page';
        
        // field is relation
        if (field_props.linking_entity_type) {
            params.params.relation_field_id = field_props.id;
            params.params.linking_entity_type = field_props.linking_entity_type;
            type_hash += '_rf_'+field_props.id;
        } else if (field_props.content_type) {
            params.params.content_type = field_props.content_type;
            if (field_props.content_type !== 'floxim.main.page') {
                type_hash += '_ct_'+field_props.content_type;
            }
        }
        if (value && value.length) {
            params.ajax_preload = true;
            params.value = value;
        }
        var $control = $fx_fields.livesearch(params, 'input');
        $control.data('value_type_hash', type_hash);
        return $control;
    };
    
    function getFieldOperators(field_type) {
        var ops = field_operators[field_type],
            res = [];
        if (!ops) {
            return res;
        }
        for (var i = 0; i < ops.length; i++) {
            var op_key = ops[i],
                op;
            if (typeof op_key === 'string') {
                op = operators[op_key];
            } else if (op_key instanceof Array) {
                var op_extra = op_key[1],
                    op_key = op_key[0];
                
                op = $.extend({}, operators[op_key], op_extra);
            }
            op.keyword = op_key;
            res.push(op);
        }
        return res;
    }
    
    function findOperator(keyword, ops) {
        for (var i = 0; i < ops.length; i++) {
            var op = ops[i];
            if (op.id === keyword) {
                return op;
            }
            if (op.children) {
                var child_res = findOperator(keyword, op.children);
                if (child_res) {
                    return child_res;
                }
            }
        }
    }
    
    this.recountValue = function() {
        var prev_value = this.$input.val();
        var value = this.getValue();
        var string_value = JSON.stringify(value);
        if (string_value !== prev_value) {
            this.$input.val(string_value).trigger('change');
        }
    };
    
    this.getValue = function() {
        var $items = this.$node.find('> .'+cl+'-cond, > .'+cl+'-group');
        var vals = this.getValues( $items );
        return vals[0];
    };
    
    this.getValues = function($items) {
        var values = [];
        $items.each(function() {
            var $i = $(this),
                val;
            if ($i.is('.'+cl+'-group')) {
                val = that.getGroupValue($i);
            } else {
                val = that.getConditionValue($i);
            }
            if (val !== null) {
                values.push( val );
            }
        });
        return values;
    };
    
    this.getGroupValue = function($group) {
        var values = this.getValues( this.getGroupItems($group) );
        if (values.length === 0) {
            return null;
        }
        if (values.length === 1) {
            return values[0];
        }
        var res = {
            type:'group',
            logic: $group.data('logic'),
            values: values
        };
        return res;
    };
    
    this.getConditionValue = function($cond) {
        var current_field = $cond.data('current_field');
        var current_op = $cond.data('current_operator');
        
        if (!current_field || !current_op) {
            return null;
        }
        var $value_control = $cond.data('current_value_control');
        var res = {
            type:current_op.id,
            field: current_field.keyword
        };
        if (current_field.real_keyword) {
            res.real_field = current_field.real_keyword;
        }
        var value = null;
        if ($value_control && $value_control.length) {
            var is_empty = false;
            if (current_op.get_value) {
                value = current_op.get_value($value_control);
                is_empty = !value;
            } else if ($value_control.is('.multisearch')) {
                value = $value_control.data('livesearch').getValues();
                is_empty = value.length === 0;
            } else if ($value_control.is('.monosearch')) {
                value = $value_control.data('livesearch').getValue();
                is_empty = !value;
            } else if ($value_control.is('.fx-radio-facet') ) {
                value = $('input[type="hidden"]', $value_control).val();
                is_empty = false;
            } else {
                value = $value_control.val();
                is_empty = value === '';
            }
            if (current_op.check_empty) {
                is_empty = current_op.check_empty(value);
            }
            if (is_empty) {
                return null;
            }
            res.value = value;
        }
        var $invertor_checkbox = $('.'+cl+'-cond__invertor input[type="checkbox"]', $cond);
        res.inverted = $invertor_checkbox.length && $invertor_checkbox.is(':checked');
        return res;
    };
    
    this.getFieldsSelect = function(fields, value) {
        fields = fields || this.fields;
        
        function convert_vals(fields) {
            var vals = [];
            for (var i = 0; i < fields.length; i++) {
                var f = fields[i];
                f = $.extend({}, f, {id:f.keyword});
                if (f.children) {
                    f.children = convert_vals(f.children);
                }
                vals.push( f );
            }
            return vals;
        }
        var vals = convert_vals(fields);
        var params = {
            type: 'livesearch',
            values: vals
        };
        if (value) {
            params.value = value;
        }
        var $input = $fx_fields.livesearch(params, 'input');
        return $input;
    };
    
    this.draw = function($node) {
        $node.html('');
    };
    
    this.getField = function(keyword, fields) {
        fields = fields || this.fields;
        for (var i = 0; i < fields.length; i++) {
            var f  = fields[i];
            if (f.keyword === keyword) {
                return f;
            }
            if (f.children) {
                var child_res = this.getField(keyword, f.children);
                if (child_res) {
                    return child_res;
                }
            }
        }
    };
    
    this.filterContext = function(op, field) {
        var op_type = op.id.replace(/.context$/, ''),
            res;
    
        function find_fields(fields, test) {
            var res = [];
            for (var i = 0; i < fields.length; i++) {
                var field = $.extend({}, fields[i]),
                    field_matched = test(field);
                
                field.children = field.children ? find_fields(field.children, test) : [];
                if (field.children.length || field_matched) {
                    res.push(field);
                    if (!field_matched) {
                        field.disabled = true;
                    }
                    if (field.children.length) {
                        field.collapsed = false;
                    }
                }
            }
            return res;
        }
        
        switch (op_type) {
            case 'is_in':
                var content_type = field.content_type;
                res = find_fields( that.context, function(field) {
                    var res = field.type === 'entity' && field.content_type === content_type;
                    return res;
                });
                break;
            default:
                res = that.context.slice(0);
                break;
        }
        
        return res;
    };
    
    this.drawValue = function($cond, op, field_props, value) {
        
        var $container = $('.'+cl+'-cond__value', $cond),
            value_type = op.value_type === undefined ? 'string' : op.value_type,
            $control,
            $current_control = $cond.data('current_value_control'),
            current_control_type = $current_control && $current_control.length ? $current_control.data('value_type_hash') : null;
        
        var op_type = op.id.match(/\.(.+)$/);
        if (op_type) {
            op_type = op_type[1];
            switch (op_type) {
                case 'context':
                    var filtered_context = that.filterContext(op, field_props);
                    $control = that.getFieldsSelect(filtered_context, value ? value.value : null);
                    break;
                case 'expression':
                    $control = $fx_fields.control({
                        type:'text'
                    });
                    break;
            }
        } else {
            if (value_type === false) {
                $container.html('');
                return;
            }
            if (typeof value_type === 'string') {
                var params = {
                    type: value_type
                };
                if (value !== undefined && value.value) {
                    params.value = value.value;
                }
                $control = $fx_fields.control(params);
                $control.data('value_type_hash', value_type);
            } else if (typeof value_type === 'function') {
                $control = value_type(field_props, value && value.value ? value.value : undefined);
            }
        }
        var new_control_type = $control ? $control.data('value_type_hash') : null;
        var need_redraw = !new_control_type 
                            || !current_control_type 
                            || (new_control_type !== current_control_type);
        
        if (need_redraw) {
            //console.log($current_control, $control);
            if ($current_control && $control) {
                var current_livesearch = $current_control.data('livesearch');
                var new_livesearch = $control.data('livesearch');
                //console.log(current_livesearch, new_livesearch );
                if (
                    current_livesearch && new_livesearch 
                    && current_livesearch.isMultiple && new_livesearch.isMultiple
                ) {
                    var old_value = current_livesearch.getValues();
                    if (old_value) {
                        new_livesearch.loadValues(old_value);
                    }
                }
            }
            $container.html('').append($control);
            $cond.data('current_value_control', $control);
        }
    };
    
    this.drawOperators = function($cond, current_field, value) {
        var $container = $('.'+cl+'-cond__operator', $cond);
        
        $container.html('');
        
        $cond.removeClass(cl+'-cond_has-op');
        
        if (!current_field) {
            return;
        }
        
        var ops = getFieldOperators(current_field.type),
            vals = [];
        if (ops.length === 0) {
            return;
        }
        for (var i = 0; i < ops.length; i++) {
            var op = $.extend({}, ops[i], {id:ops[i].keyword, children:[]});
            if (op.test && !op.test(current_field)) {
                continue;
            }
            op.collapsed = true;
            if (op.allow_context && that.context) {
                op.children.push(
                    {
                        id:op.keyword + '.context',
                        name:'контекст'
                    }
                );
            }
            if (false && op.allow_expression && that.context) {
                op.children.push(
                    {
                        id:op.keyword + '.expression',
                        name:'выражение'
                    }
                );
            }
            
            vals.push(op);
        }
        
        var op_value = value && value.type ? value.type : vals[0].id,
            op_value_exists = false;
        for (var i = 0; i < vals.length; i++) {
            if (vals[i].id === op_value) {
                op_value_exists = true;
                break;
            }
        }
        if (!op_value_exists) {
            op_value = vals[0].id;
            value = undefined;
        }
        
        var $control = $fx_fields.livesearch({
            type:'livesearch',
            values: vals,
            value: op_value
        },'input');
        
        $container.append($control);
        
        if (vals.length) {
            if (vals.length < 1 || vals[0].name !== '') {
                $cond.addClass(cl+'-cond_has-op');
                
            } 
            if (vals.length === 1 && vals[0].name !== '') {
                $container.append('<span class="'+cl+'-cond__op-name">'+vals[0].name+'</span>');
                $control.css('display', 'none');
            }
        }
        
        function redrawValue(value) {
            var op_keyword = $control.data('livesearch').getValue(),
                op = findOperator(op_keyword, vals);
            if (op) {
                $cond.data('current_operator', op);
                that.drawValue($cond, op, current_field, value);   
            }
        }
        
        $control.on('change', function() {
            redrawValue();
        });
        if (value !== undefined) {
            redrawValue(value);
        } else {
            $control.trigger('change');
        }
    };
    
    this.drawCondition = function(fields, value) {
        var ccl = cl+'-cond';
        var $cond = $(
            '<div class="'+ccl+'">'+
                '<span class="'+cl+'__split '+cl+'__split_cond" title="Создать группу"></span>'+
                '<span class="'+ccl+'__kill" title="Удалить условие">&times;</span>'+
                '<span class="'+ccl+'__field"></span>'+
                '<span class="'+ccl+'__invertor"></span>'+
                '<span class="'+ccl+'__operator"></span>'+
                '<span class="'+ccl+'__value"></span>'+
            '</div>');
        
        fields = fields || this.fields;
        var $field_control = this.getFieldsSelect(fields, value ? value.field : null);
        $cond.find('.'+ccl+'__field').append( $field_control );
        $field_control.on('change', function() {
            var field_keyword = $field_control.data('livesearch').getValue(),
                field = that.getField(field_keyword, fields);
            that.drawOperators($cond, field, value);
            $cond.data('current_field', field);
        });
        if (value) {
            $field_control.trigger('change');
        }
        var invertor_class = ccl+'__invertor';
        var $invertor = $('.'+invertor_class, $cond);
        var $invertor_checkbox = $fx_fields.control({
            type:'checkbox',
            label:'не',
            value: value && value.inverted
        });
        $invertor.append($invertor_checkbox);
        return $cond;
    };
    
    function getLogicName(logic) {
        return {
            OR:'или',
            AND:'и'
        }[logic];
    }
    
    this.drawGroup = function(logic) {
        logic = logic || 'AND';
        var gcl = cl+'-group';
        var $group = $(
            '<div class="'+gcl+'" data-logic="'+logic+'">'+
                '<span class="'+gcl+'__groupper"></span>'+
                '<div class="'+gcl+'__items"></div>'+
                '<span class="'+gcl+'__controls">'+
                    '<span class="'+cl+'__split '+cl+'__split_group" title="Создать группу"></span>'+
                    '<span class="'+gcl+'__add" title="Добавить условие">+</span>'+
                    '<span class="'+gcl+'__logic" title="Поменять логику">'+ getLogicName(logic) +'</span>'+
                '</span>'+
            '</div>'
        );
        return $group;
    };
    
    this.append = function($cond, $group) {
        $group.find('>.'+cl+'-group__items').append($cond);
    };
    
    this.splitItem = function($item, logic) {
        if (!logic) {
            var $upper_group = $item.closest('.'+cl+'-group');
            logic = this.getAlterLogic($upper_group.data('logic'));
        }
            
        var $group = this.drawGroup(logic);
        $item.before($group);
        this.append($item, $group);
        var $new_cond = this.drawCondition();
        this.append($new_cond, $group);
        this.recountGrouppers();
    };
    
    this.recountGroupper = function($group) {
        if ($group.length === 0) {
            return;
        }
        var $groupper = $('>.'+cl+'-group__groupper', $group),
            $items = that.getGroupItems($group),
            $first = $items.first(),
            $last = $items.last(),
            group_rect = $group[0].getBoundingClientRect(),
            top,
            bottom;
        
        $items.filter('.'+cl+'-group').each(function () {
            that.recountGroupper($(this));
        });
        
        if ($first.is('.'+cl+'-group')) {
            top = $first.data('group_center_offset');
        } else {
            var first_rect = $first[0].getBoundingClientRect();
            top = Math.round(first_rect.height/2);
        }
        var last_rect = $last[0].getBoundingClientRect();
        if ($last.is('.'+cl+'-group')) {
            bottom = (last_rect.top - group_rect.top) + $last.data('group_center_offset');
        } else {
            bottom = group_rect.height - Math.round ( last_rect.height / 2 );
        }
        
        var height = bottom - top,
            $controls = $('>.'+cl+'-group__controls', $group),
            controls_rect = $controls[0].getBoundingClientRect(),
            controls_top = Math.round ( top + (height / 2 ) - controls_rect.height / 2);
        
        $controls.css({
            top:controls_top
        });
        
        var group_center = controls_top + Math.round(controls_rect.height / 2);
        $group.data('group_center_offset', group_center);

        $groupper.css({
            top:top,
            height:height
        });
    };
    
    this.recountGrouppers = function() {
        var is_visible = this.$node.is(':visible');
        if (!is_visible) {
            var $prev_parent = this.$node.parent(),
                $prev_neighbour = this.$node.next();
            $(document.body).append(this.$node);
            this.$node.css({
                position:'fixed',
                top:0,
                left:0,
                'z-index':100000
            });
        }
        var $top_group = this.$node.find('>.'+cl+'-group');
        this.recountGroupper($top_group);
        if (!is_visible) {
            this.$node.attr('style', '');
            if ($prev_neighbour.length) {
                $prev_neighbour.before(this.$node);
            } else {
                $prev_parent.append(this.$node);
            }
        }
        
    };
    
    this.removeCondition = function($cond) {
        var $group = $cond.closest('.'+cl+'-group');
        $cond.remove();
        var $items = this.getGroupItems($group);
        if ( $items.length === 1 ) {
            $group.before($items);
            $group.remove();
        } else if ($items.length === 0) {
            this.redraw();
        }
        this.recountGrouppers();
    };
    
    this.getGroupItems = function($group) {
        return $group.find('>.'+cl+'-group__items > *');
    };
    
    this.getAlterLogic = function(logic) {
        return logic === 'AND' ? 'OR' : 'AND';
    };
    
    this.toggleGroupLogic = function($group) {
        var c_logic = $group.data('logic'),
            new_logic = this.getAlterLogic( c_logic );
        $group.data('logic', new_logic);
        $('>.'+cl+'-group__controls>.'+cl+'-group__logic', $group).text( getLogicName(new_logic) );
    };
    
    this.drawItems = function(items, $target) {
        for (var i = 0; i < items.length; i++) {
            var item = items[i],
                $node;
            if (item.type === 'group') {
                $node = this.drawGroup(item.logic);
                this.drawItems(item.values, $node);
            } else {
                $node = this.drawCondition(this.fields, item);
            }
            if ($node && $node.length) {
                if ($target.is('.'+cl+'-group')) {
                    this.append($node, $target);
                } else {
                    $target.append($node);
                }
            }
        }
    };
    
    this.init = function() {
        this.$input = $('<input type="hidden" name="'+this.name+'" />');
        this.$node.append(this.$input);
        if (!this.value) {
            var $initer = $('<a class="'+cl+'__initer">Добавить условие</a>');
            $initer.click(function() {
                var $init_cond = that.drawCondition();
                that.$node.append($init_cond);
                $initer.remove();
            });
            this.$node.append($initer);
            return;
        }
        this.$input.val( JSON.stringify(this.value) );
        this.drawItems([this.value], this.$node);
        setTimeout(function() {
            that.recountGrouppers();
        }, 100);
    };
    
    this.redraw = function(new_value) {
        this.value = new_value;
        this.$node.html('');
        this.init();
    };
    
    
    this.$node.addClass(cl);
    
    this.init();
    
    this.$node.on('click', '.'+cl+'__split', function() {
        var $split = $(this),
            $item = $split.closest('.'+cl+'-'+ ( $split.is('.'+cl+'__split_cond') ? 'cond' : 'group') );
        that.splitItem($item);
        return false;
    }).on('click', '.'+cl+'-group__add', function() {
        var $group = $(this).closest('.'+cl+'-group');
        var $cond = that.drawCondition();
        that.append($cond, $group);
        that.recountGrouppers();
        return false;
    }).on('click', '.'+cl+'-cond__kill', function() {
        var $cond = $(this).closest('.'+cl+'-cond');
        that.removeCondition($cond);
        that.recountValue();
        return false;
    }).on('click', '.'+cl+'-group__logic', function(){
        var $group = $(this).closest('.'+cl+'-group');
        that.toggleGroupLogic($group);
        that.recountValue();
        return false;
    }).on('change', function(e) {
        if (e.target === that.$input[0]) {
            return;
        }
        that.recountGrouppers();
        that.recountValue();
        return false;
    });
};

})(jQuery);