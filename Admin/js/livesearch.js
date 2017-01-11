(function($) {
     
window.fx_livesearch = function (node, params) {
    
    this.$node = $(node);
    
    params || {};
    
    params = $.extend({
        allow_new: false,
        skip_ids: [],
        plain_values: [],
        allow_select_doubles: false,
        preset_values: params.preset_values || params.values || []
    }, params);
    
    if (typeof params.allow_empty === 'undefined') {
        params.allow_empty = this.is_multiple || params.preset_values.length === 0;
    }
    
    $.extend(this, params);
    
    var bl = this.is_multiple ? 'multisearch' : 'monosearch';
    
    if (!this.allow_empty) {
        this.$node.addClass(bl+'_no-empty');
    }
    
    this.$container = this.$node.find('.'+bl+'__container');
    
    this.$node.data('livesearch', this);
    
    if (this.preset_values.length) {
        this.count_show = this.preset_values.length;
    }
    
    var f_postfix = params.name_postfix ? '['+params.name_postfix+']' : '';
    this.inputNameTpl = this.name ? this.name+'[prototype]'+f_postfix : false;
    
    this.$input = this.$node.find('.'+bl+'__input');
    
    this.inpNames = {};
    var livesearch = this;
    
    this.getInputName = function(value) {
        if (this.is_multiple) {
            if (value && this.inpNames[value]) {
                return this.inpNames[value];
            }
            var name = this.inputNameTpl ? this.inputNameTpl.replace(/prototype[0-9]?/, '') : false;
            return name;
        }
        return this.name;
    };
    
    this.getSuggestParams = function() {
        var data = {};
        if (this.params.content_type) {
            data.content_type = this.params.content_type;
        }
        data.params = this.params;
        var params = {
            url:'/~ajax/floxim.main.content:livesearch/',
            data:data,
            count_show:this.count_show
        };
        if (this.params.send_form) {
            var $form = this.$node.closest('form');
            if (typeof $form.formToHash !== 'undefined') {
                //data.form_data = $form.formSerialize();
                data.form_data  = $form.formToHash();
            }
        }
        if (this.conditions) {
            params.data.conditions = this.conditions;
        }
        var vals = this.getValues();
        params.skip_ids = [];
        if (this.skip_ids) {
            for (var i = 0; i < this.skip_ids.length; i++){
                params.skip_ids.push(this.skip_ids[i]);
            }
        }
        if (this.is_multiple && vals.length > 0 && !this.allow_select_doubles) {
            for (var i = 0; i < vals.length; i++) {
                params.skip_ids.push(vals[i]);
            }
        }
        return params;
    };
    
    this.getValues = function() {
        var vals = [];
        this.$node.find('.'+bl+'__item input[type="hidden"]').each(function() {
            var v = $(this).val();
            if (v || livesearch.preset_values.length) {
                vals.push(v);
            }
        });
        return vals;
    };
    
    this.getValue = function() {
        if (this.is_multiple) {
            return null;
        }
        var vals = this.getValues();
        if (vals.length > 0){
            return vals[0];
        }
        return null;
    };
    
    this.disable = function() {
        this.$node.addClass(bl+'_disabled');
        this.disabled = true;
    };
    
    this.enable = function() {
        this.$node.removeClass(bl+'_disabled');
        this.disabled = false;
    };
    
    this.traversePresetValues = function(cb, vals) {
        vals = vals || this.preset_values;
        for (var i = 0; i < vals.length; i++) {
            var c_value = vals[i];
            if (cb(c_value) === false) {
                return false;
            }
            if (c_value.children && c_value.children.length) {
                var sub_res = this.traversePresetValues(cb, c_value.children);
                if (sub_res === false) {
                    return false;
                }
            }
        }
    };
    
    this.selectFirstValue = function() {
        this.traversePresetValues(function(v) {
            if (!v.disabled) {
                livesearch.setValue(v.id);
                return false;
            }
        });
    };
    
    this.updatePresetValues = function(values) {
        var c_value = this.getValue();
        values = fx_livesearch.vals_to_obj(values);
        this.preset_values = values;
        this.Suggest.preset_values = values;
        var set_old_res = this.setValue(c_value);
        if (!set_old_res && values.length > 0) {
            //this.setValue(values[0].id);
            this.setFirstValue();
        }
    };

    this.getFullValue = function() {
        if (this.is_multiple || !this.preset_values) {
            return;
        }
        var val = this.getValue();
       var res = null;
       this.traversePresetValues(function(v) {
           if (v.id == val) {
               res = v;
               return false;
           }
       });
       return res;
    };
    
    this.Select = function($n) {
        var value = $n.data('value');
        var path = $n.data('path');
        if (!path) {
            var $groups = $n.parent().parents('.search_group'),
                path = [];
            $groups.each(function() {
                path.push( $(this).find(">.search_item").data('name') );
            });
            path = path.reverse();
        }
        if (livesearch.is_multiple) {
            
            livesearch.addValue(value, path);
            
            livesearch.$input.val('').focus().trigger('keyup');
            livesearch.recountInputWidth();
        } else {
            livesearch.$container.removeClass('livesearch__container_focused');
            if (!value.custom) {
                livesearch.$container.attr('tabindex', '0').focus();
            }
            livesearch.addValue(value, path);
            if (value.custom) {
                livesearch.$container.find('.search_item__custom-value input:visible').first().focus();
            }
        }
    };
    
    this.selectNext = function() {
        var current_value = this.getValue(),
            first_value = null,
            found_value = null,
            found_next = null;
    
        this.traversePresetValues(function(v) {
            if (v.disabled) {
                return;
            }
            if (first_value === null) {
                first_value = v;
            }
            if (v.id == current_value) {
                found_value = true;
                return;
            }
            if (found_value) {
                found_next = v;
                return false;
            }
        });
        if (found_value && !found_next) {
            found_next = first_value;
        }
        if (found_next) {
            this.setValue(found_next.id);
        }
    };
    
    this.selectPrev = function() {
        var current_value = this.getValue(),
            last_value = null,
            is_first = true;
        
        this.traversePresetValues(function(v) {
            if (v.disabled) {
                return;
            }
            if (v.id == current_value) {
                if (!is_first) {
                    return false;
                }
            }
            last_value = v;
            is_first = false;
        });
        
        this.setValue(last_value.id);
    };
    
    this.focusNextInput = function() {
        return;
    };
    
    this.addSilent = false;
    
    this.loadValues = function(ids) {
        if (typeof ids === 'string') {
            //ids = [ ids * 1];
            ids = [ids];
        }
        if (!(ids instanceof Array) || ids.length === 0) {
            return;
        }
        var params = this.getSuggestParams();
        params.data.ids = ids;
        params.data.term = null;
        params.data.limit = null;
        $.ajax({
            url:params.url,
            type:'post',
            dataType:'json',
            data:params.data,
            success:function(res){
                livesearch.addSilent = true;
                livesearch.$node.css('visibility', 'hidden');
                if (!res.results) {
                    console.log(res, params.data);
                    return;
                }
                $.each(res.results, function(index, item) {
                    livesearch.addValue(
                        $.extend({}, item, {input_name:livesearch.inpNames[item.id]})
                    );
                });
                livesearch.addSilent = false;
                livesearch.$node.trigger('livesearch_value_loaded');
                livesearch.$node.css('visibility', '');
            }
        });
    };
    
    
    // recount axis prop for sortable
    // if there is only one row, use "x", otherwise - "false"
    this.updateSortableAxis = function() {
        if (!this.is_multiple) {
            return;
        }
        var $container = this.$container;
        var $items = $container.children('.'+bl+'__item');
        var axis = false;
        if (!$items || !$items.length) {
            return;
        }
        if ($items.first().offset().top === $items.last().offset().top) {
            axis = 'x';
        }
        setTimeout(function() {
            //$container.sortable('option', 'axis', axis);
        }, 150);
    };
    
    this.addValues = function(values) {
        $.each(values, function(index, val) {
            this.addValue(val);
        });
    };
    
    this.hasValue = function(val_id) {
        var vals = this.getValues();
        if (val_id === undefined) {
            return vals.length > 0;
        }
        for (var i = 0; i < vals.length; i++) {
            if (vals[i] === val_id) {
                return true;
            }
        }
        return false;
    };
    
    this.findPresetValue = function(id) {
        var res = false;
        this.traversePresetValues(function(v) {
            if (v.id == id) {
                res = v;
                return false;
            }
        });
        return res;
    };
    
    this.setValues = function(ids, silent) {
        if (silent) {
            var was_silent = this.addSilent;
            this.addSilent = true;
        }
        
        var vals = {};
        this.traversePresetValues(function(v) {
            if (ids.indexOf(v.id) !== -1) {
                vals[v.id] = v;
            }
        });
        
        for (var i = 0; i < ids.length; i++) {
            var id = ids[i];
            if ( typeof vals[id] !== 'undefined') {
                this.addValue(vals[id]);
            }
        }
        
        if (silent) {
            this.addSilent = was_silent;
        }
    };
    
    this.setValue = function (id, silent) {
        if (this.is_multiple && this.preset_values.length && id instanceof Array) {
            return this.setValues(id, silent);
        }
        if (this.is_multiple || !this.preset_values.length) {
            return;
        }
        var res = false,
            custom_value = null;
        
        if (typeof id === 'object' && id.id) {
            id = id.id;
        }
        if (silent) {
            var was_silent = this.addSilent;
            this.addSilent = true;
        }
        this.traversePresetValues(function(v) {
            if (v.id == id) {
                res = v;
                livesearch.addValue(v);
                return false;
            }
            if (v.custom) {
                if (v.type === 'number' && !/^[\d\,\.\-]+$/.test(id+'')) {
                    return;
                }
                custom_value = v;
            }
        });
        if (custom_value) {
            res = custom_value;
            custom_value.value = id;
            livesearch.addValue(custom_value);
        }
        if (silent) {
            this.addSilent = was_silent;
        }
        return res;
    };
    
    // now adding all together
    this.addValue = function(value, path) {
        var     id = value.id,
                name = value.name,
                input_name = value.input_name;
        
        path = path || value.path || [];
        
        if ( (!id || id*1 === 0) && !name) {
            return;
        }
        if (id && (!this.allow_select_doubles && this.hasValue(id)) && this.is_multiple) {
            return;
        }
        if (!input_name) {
            input_name = this.getInputName(id);
        }
        if (!this.is_multiple) {
            var current_value = this.getValue();
            if (current_value !== null) {
                // remove old value
                this.removeValue(this.getValueNode(), true);
            }
        }
        
        var res_value = id;
        if ( !this.preset_values.length && (!id || (id*1 === 0) ) ) {
            id = false;
            input_name = input_name+'[title]';
            res_value = name;
        }
        
        if (value.custom ) {
            res_value = value.value;
        }
        
        var path_separator = ' <span class="'+bl+'__item-path-separator">&#9657;</span> ';
        var path_separator = ' <span class="'+bl+'__item-path-separator">&gt;</span> ';

        var $node = $(
            '<div class="'+bl+'__item'+ (!id ? ' '+bl+'__item_empty' : '')+'">'+
                '<input class="'+bl+'__item-value" type="hidden" '+
                    (input_name ? ' name="'+input_name+'" ' : '') +
                    ' value="'+res_value+'" />'+
                ( 
                    path.length 
                    ? '<span class="'+bl+'__item-path">'+ 
                            path.join(path_separator)+ path_separator +
                       '</span>' 
                    : ''
                ) +
                '<span class="'+bl+'__item-title">'+(name || '')+'</span>'+
                (this.is_multiple ? '<span class="'+bl+'__item-killer">&times;</span>' : '')+
            '</div>'
        );
    
        if (value.custom) {
            var $custom_control = this.Suggest.drawCustomControl(value, $node.find('.'+bl+'__item-title')),
                $item_input = $node.find('.'+bl+'__item-value');
            
            $custom_control.on(
                'input change',
                function(e) {
                    var $t = $(e.target),
                        v = $t.val();
                    $item_input.val(v).trigger('change');
                }
            );
        }
        
        this.$input.before( $node );
        
        this.updateSortableAxis();
        if (!this.is_multiple) {
            this.disableAdd();
            this.Suggest.currentId = id;
        } else {
            //this.Suggest.setRequestParams(this.getSuggestParams());
        }
        var e = $.Event('livesearch_value_added');
        e.id = id;
        e.value = value;
        e.value_name = name;
        e.$value_node = $node;
        e.is_preset = !!this.inpNames[id];
        this.$node.trigger(e);
        
        if (!this.addSilent) {
            $('input', $node).trigger('change');
        }
        this.Suggest.hideBox();
    };
    
    this.bindValueControls = function(callback) {
        this.$node.on('livesearch_value_added', function() {
            callback(livesearch);
        });
        callback(this);
    };
    
    this.addValueControl = function(params) {
        var $item = this.$node.find('.'+bl+'__item'),
            $controls = $item.find('.'+bl+'__item-controls');
        if ($controls.length === 0) {
            $controls = $('<span class="'+bl+'__item-controls"></span>');
            $item.find('.'+bl+'__item-title').after($controls);
        }
        var html = '<span class="'+bl+'__item-control';
        if (params.icon) {
            html += ' fx_icon fx_icon-type-'+params.icon;
        }
        html += '"></span>';
        var $control = $(html);
        $controls.append($control);
        if (params.action) {
            $control.click(function() {
                var c_value = livesearch.getFullValue();
                params.action(c_value, livesearch);
                livesearch.$input.trigger('suggest_blur');
                livesearch.Suggest.hideBox();
                return false;
            });
        }
        return $control;
    };
    
    
    this.addDisabled = false;
    this.disableAdd = function() {
        this.addDisabled = true;
        if (this.allow_empty) {
            this.$input.attr('tabindex', '-1');
        }
        this.Suggest.disabled = true;
        if (!this.is_multiple) {
            this.$node.addClass(bl+'_has-value');
            this.$container.attr('tabindex', '0');
        }
    };
    
    this.enableAdd = function() {
        this.addDisabled = false;
        this.Suggest.disabled = false;
        this.$node.removeClass(bl+'_has-value');
        this.recountInputWidth();
    };
    
    this.lastRemovedValue = null;
    
    this.removeValue = function(n, silent) {
        if (!n) {
            return;
        }
        this.lastRemovedValue = n.find('input').val();
        n.remove();
        this.enableAdd();
        //this.Suggest.setRequestParams(this.getSuggestParams());
        if (!silent) {
            this.$node.trigger('change');
        }
        this.updateSortableAxis();
        //this.recountInputWidth();
    };
    
    this.getValueNode = function() {
        if (this.is_multiple) {
            return false;
        }
        var item_node = livesearch.$node.find('.'+bl+'__item').first();
        if (item_node.length === 0) {
            return false;
        }
        return item_node;
    };
    
    this.hideValue = function() {
        var $item_node = this.getValueNode();
        if (!$item_node || !$item_node.length) {
            return;
        }
        if (!this.is_multiple) {
            this.$input.css('width', this.$container.width());
            this.$container.attr('tabindex', null);
        }
        if (this.allow_empty) {
            $item_node.hide();
        }
        var c_text = $item_node.find('.'+bl+'__item-title').text();
        this.$input.val(c_text);
        this.enableAdd();
    };
    
    this.showValue = function() {
        var item_node = this.getValueNode();
        if (item_node) {
            item_node.show();
            this.disableAdd();
        }
    };
    
    this.recountInputWidth = function() {
        if (!this.is_multiple) {
            //return;
        }
        var v = livesearch.$input.val();
        v = v.replace(/\s/g, '&nbsp;');
        livesearch.$proto_node.css({
            font:livesearch.$input.css('font'),
            padding:livesearch.$input.css('padding')
        });
        livesearch.$proto_node.html(v);
        var extra_right = 10; // 20
        var width = livesearch.$proto_node.outerWidth() + extra_right;
        livesearch.$input.css({width:width+'px'});
    };
    
    this.Init = function() {
        this.Suggest = new fx_suggest({
            input:this.$input,
            requestParamsFilter:function() {
                return livesearch.getSuggestParams();
            },
            resultType:'json',
            onSelect:this.Select,
            offsetNode: this.is_multiple ? this.$input : this.$container,
            minTermLength:0,
            preset_values: this.preset_values
        });
        
        
        this.$input.on('fx_livesearch_request_start', function() {
            livesearch.$container.find('.livesearch__control').hide();
            livesearch.$container.append('<div class="livesearch__control livesearch__control_spinner"></div>');
        });
        
        this.$input.on('fx_livesearch_request_end', function(){
            $('.livesearch__control_spinner', livesearch.$container).remove();
            livesearch.$container.find('.livesearch__control').show();
        });
        
        this.$input.on  ('change', function() {
            return false;
        });
        
        if (this.is_multiple && $.sortable) {
            setTimeout(function() {
                livesearch.$node.find('.'+bl+'__container').sortable({
                    items:'.'+bl+'__item',
                    stop:function () {
                        livesearch.$node.trigger('change');
                    }
                });
            }, 100);
        }
        
        this.$node.on('click', '.'+bl+'__item-killer', function() {
            livesearch.removeValue($(this).closest('.'+bl+'__item'));
        });
        
        this.$node.on('set_value', function(e) {
            var res = livesearch.setValue(e.value);
            if (res) {
                return false;
            }
        });
        
        this.$node.find('.livesearch__control_arrow').click(function() {
            livesearch.$input.focus();
            livesearch.Suggest.Search('', {immediate:true});
            return false;
        });
        
        if (!this.is_multiple) {
            var cont = this.$container[0];
            this.$container.on('focus', function(e) {
                if (e.target !== cont)  {
                    return;
                }
                if (!livesearch.hasValue()) {
                    livesearch.$input.focus();
                }
            }).on('keydown', function(e) {
                if (e.target !== cont) {
                    return;
                }
                // enter, space or down arrow
                if (e.which === 13 || e.which === 32 || e.which === 40) {
                    livesearch.$input.focus();
                    return false;
                }
                if (livesearch.preset_values) {
                    if (e.which === 39) { // next
                        livesearch.selectNext();
                        return false;
                    }
                    if (e.which === 37) { // prev
                        livesearch.selectPrev();
                        return false;
                    }
                }
                
            }).on('keypress', function(e) {
                if (e.target !== cont) {
                    return;
                }
                var char = String.fromCharCode(e.keyCode || e.charCode);
                // user starts typing on focused container
                if (char && !/^\s$/.test(char)) {
                    livesearch.$input.focus().val(char);
                    return false;
                }
            });
        }
        
        this.$node.on('keydown', '.'+bl+'__input', function(e) {
            var v = $(this).val();
            // backspace - delete prev item
            if (e.which === 8 && v === '' && livesearch.is_multiple) {
                livesearch.$node.find('.'+bl+'__item-killer').last().click();
                livesearch.Suggest.hideBox();
            }
            if (e.which === 27) {
                if (!livesearch.is_multiple) {
                    if (livesearch.getValue() === null) {
                        livesearch.Suggest.hideBox();
                    } else {
                        $(this).trigger('blur');
                    }
                    if (livesearch.hasValue()) {
                        livesearch.$container.focus();
                    }
                } else {
                    livesearch.Suggest.hideBox();
                }
                return false;
            }
            if (e.which === 90 && e.ctrlKey && livesearch.lastRemovedValue) {
                livesearch.loadValues(livesearch.lastRemovedValue);
                livesearch.lastRemovedValue = null;
                livesearch.Suggest.hideBox();
            }
        });
        
        this.$proto_node = $('<span class="fx_livesearch_proto_node"></span>');
        this.$proto_node.css({
            position:'fixed',
            top:'-1000px',
            left:'-1000px',
            opacity:0
        });
        $('body').append(this.$proto_node);
        this.$input.on('keypress keyup keydown', function() {
            livesearch.recountInputWidth();
        });
        
        this.$node.on('focus', '.'+bl+'__input', function(e) {
            livesearch.$container.addClass('livesearch__container_focused');
            if (livesearch.is_multiple) {
                return;
            }
            if (livesearch.hasValue()) {
                var item_node = livesearch.getValueNode();
                if (item_node && item_node.hasClass(bl+'__item_empty')) {
                    var item_title = item_node.find('input[type="hidden"]').val();
                    livesearch.$input.val(item_title);
                }
                livesearch.hideValue();
                $(this).select();
                livesearch.Suggest.lockTerm = livesearch.$input.val();
                livesearch.Suggest.Search('', {immediate:true});
            }
        });

        this.$container.click(function(e) {
            if ($(e.target).closest('.search_item__custom-value').length) {
                return;
            }
            if (!livesearch.$input.is(':focus')) {
                livesearch.$input.focus();
            }
            return;
        });
        
        this.$input.on('suggest_blur', function() {
            var $input = $(this),
                input_value = $input.val();
            
            livesearch.$container.removeClass('livesearch__container_focused');
            
            if (!livesearch.is_multiple) {
                if (input_value && livesearch.allow_new && false) {
                    livesearch.addValue({id:false, name:input_value});
                } else if (input_value !== '' || !livesearch.allow_empty) {
                    livesearch.showValue();
                } else {
                    livesearch.removeValue( livesearch.getValueNode() );
                    $input.val('');
                }
                return false;
            }
            if (input_value && livesearch.allow_new) {
                livesearch.addValue({id:false, name:input_value});
            } else {
                livesearch.removeValue( livesearch.getValueNode() );
                $input.val('');
            }
            return false;
        });
        
        if (!this.is_multiple) {
            if (!this.preset_values.length) {
                if (this.value && typeof this.value === 'object') {
                    this.addValue(this.value);
                }
            } else {
                var val_set = false;
                if (this.value) {
                    val_set = this.setValue(this.value);
                }
                if (!this.allow_empty && !val_set) {
                    this.selectFirstValue();
                }
            }
        } else {
            var vals = this.value || [],
                ids = [],
                has_raw = false;
            
            for (var i = 0; i < vals.length; i++) {
                var v = vals[i];
                if (!this.preset_values.length) {
                    if (typeof v !== 'object') {

                        has_raw = true;
                        ids.push(v);
                    } else if (v.id) {
                        ids.push(v.id);
                    }
                } else {
                    var preset_value = this.findPresetValue(v);
                    if (preset_value !== false) {
                        this.addValue(preset_value);
                    }
                }
            }

            if (has_raw) {
                this.loadValues( ids );
            }
        };
        
        if (this.disabled) {
            this.disable();
        }
    };
    
    this.destroy = function() {
        this.Suggest.destroy();
        this.$proto_node.remove();
    };
    
    this.Init();
};

window.fx_livesearch.vals_to_obj = function(vals, path) {
    var res = [];
    if (path === undefined) {
        path = [];
    }

    for (var i = 0; i < vals.length; i++) {
        var val = vals[i],
            res_val = val;

        if (val instanceof Array && val.length >= 2) {
            res_val = {
                id:val[0]
            };
            if (typeof val[1] === 'string' || typeof val[1] === 'number') {
                res_val.name = val[1] + '';
            } else if (typeof val[1] === 'object') {
                res_val = $.extend({}, res_val, val[1]);
            }
            if (val.length > 2) {
                res_val =  $.extend({}, res_val, val[2]);
            }
        }
        if (!res_val.path) {
            res_val.path = path.slice(0); //$.extend(true, {}, path);
            res_val.path_is_auto = true;
        }
        path.push(res_val.name);
        if (res_val.children && res_val.children.length) {
            res_val.children = this.vals_to_obj(res_val.children, path);
        }
        path.pop();
        res.push(res_val);
    }
    return res;
};

window.fx_livesearch.create = function(json, template) {
    template = template || 'form_row';
        json.params = json.params || {};
        if (!json.type) {
            json.type = 'livesearch';
        }
        
        if (json.content_type && !json.params.content_type) {
            json.params.content_type = json.content_type;
        }
        
        
        if (json.fontpicker) {
            window.fx_font_preview.init_stylesheet();
            json.values = window.fx_font_preview.get_livesearch_values( json.fontpicker );
        }
        
        if (json.values) {
            var preset_vals = json.values;//,
                //custom = false;
            if ( ! (json.values instanceof Array) ) {
                preset_vals = [];
                $.each(json.values, function(k, v) {
                    preset_vals.push([k, v]);
                });
            }
            json.preset_values = fx_livesearch.vals_to_obj(preset_vals);
        }
        
        var $ls = $t.jQuery(template, json);
        if (json.values && json.values.length === 0) {
            $ls.hide();
        }
        if (json.fontpicker) {
            var $input = $ls.find('.livesearch__input');
            function add_input_style(value) {
                if (!value) {
                    return;
                }
                var $v = $(value);
                $input.addClass($v.attr('class'));
                $input.attr('style', $v.attr('style'));
            }
            if (json.value && json.value.name) {
                add_input_style( json.value.name );
            }
            
            $ls.on('livesearch_value_added', function(e) {
                add_input_style(e.value_name);
            });
        }
        return $ls;
};

})(jQuery);