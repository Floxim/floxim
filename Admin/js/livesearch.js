(function($) {
     
window.fx_livesearch = function (node) {
    var $node = $(node);
    
    this.$node = $node;
    
    this.isMultiple = $node.data('is_multiple') === 'Y';
    this.allowEmpty = !this.isMultiple && $node.data('allow_empty') !== 'N';
    
    var bl = this.isMultiple ? 'multisearch' : 'monosearch';
    
    if (!this.allowEmpty) {
        $node.addClass(bl+'_no-empty');
    }
    
    this.$container = this.$node.find('.'+bl+'__container');
    
    $node.data('livesearch', this);
    var data_params = $node.data('params');
    this.params = data_params || {};
    
    if (data_params) {
        this.datatype = data_params.content_type;
        this.count_show = data_params.count_show;
        this.conditions = data_params.conditions;
        this.preset_values = data_params.preset_values;
        this.ajax_preload = data_params.ajax_preload;
        this.plain_values = data_params.plain_values || [];
        this.skip_ids = data_params.skip_ids || [];
        this.allow_new = data_params.allow_new || true;
        this.allow_select_doubles = data_params.allow_select_doubles;
    } else {
        this.datatype = $node.data('content_type');
        this.count_show = $node.data('count_show');
        this.preset_values = $node.data('preset_values');
    }
    if (!this.preset_values) {
        this.preset_values=[];
    }
    if (this.preset_values.length) {
        this.count_show = this.preset_values.length;
    }
    this.inputNameTpl = $node.data('prototype_name');
    
    this.$input = $node.find('.'+bl+'__input');
    
    
    this.inpNames = {};
    var livesearch = this;
    
    this.getInputName = function(value) {
        if (this.isMultiple) {
            if (value && this.inpNames[value]) {
                return this.inpNames[value];
            }
            var name = this.inputNameTpl.replace(/prototype[0-9]?/, '');
            return name;
        }
        return this.inputName;
    };
    
    this.getSuggestParams = function() {
        var data = {};
        if (this.datatype) {
            data.content_type = this.datatype;
        }
        data.params = this.params;
        var params = {
            url:'/~ajax/floxim.main.content:livesearch/',
            data:data,
            count_show:this.count_show
        };
        if (this.params.send_form) {
            var $form = this.$node.closest('form');
            if (typeof $form.formSerialize !== 'undefined') {
                data.form_data = $form.formSerialize();
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
        if (this.isMultiple && vals.length > 0 && !this.allow_select_doubles) {
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
            if (v) {
                vals.push(v);
            }
        });
        return vals;
    };
    
    this.getValue = function() {
        if (this.isMultiple) {
            return null;
        }
        var vals = this.getValues();
        if (vals.length > 0){
            return vals[0];
        }
        return null;
    };
    
    this.Select = function(n) {
        var value = n.data('value');
        if (n.hasClass('add_item')){
            return;
        }
        var $groups = n.parent().parents('.search_group'),
            path = [];
        $groups.each(function() {
            path.push( $(this).find(">.search_item").data('name') );
        });
        path = path.reverse();
        livesearch.addValue(value, path);
        if (livesearch.isMultiple) {
            livesearch.$input.val('').focus().trigger('keyup');
            livesearch.recountInputWidth();
        } else {
            livesearch.$container.removeClass('livesearch__container_focused');
            livesearch.$container.focus();
        }
    };
    
    this.focusNextInput = function() {
        return;
    };
    
    this.addSilent = false;
    
    this.loadValues = function(ids) {
        if (typeof ids === 'string') {
            ids = [ ids * 1];
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
                /*
                res.results.sort(function(a, b) {
                    if (ids.indexOf(a.id) < ids.indexOf(b.id) )
                        return -1;
                    if (ids.indexOf(a.id) > ids.indexOf(b.id) )
                        return 1;
                    return 0;  
                }); 
                */
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
        if (!this.isMultiple) {
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
    
    // now adding all together
    this.addValue = function(value, path) {
        var     id = value.id,
                name = value.name,
                input_name = value.input_name;
        
        path = path || [];
        
        if ( (!id || id*1 === 0) && !name) {
            return;
        }
        if (id && (!this.allow_select_doubles && this.hasValue(id)) && this.isMultiple) {
            return;
        }
        if (!input_name) {
            input_name = this.getInputName(id);
        }
        if (!this.isMultiple && this.getValues().length > 0) {
            this.removeValue(this.getValueNode(), true);
            //return;
        }
        
        var res_value = id;
        if (!id || (id*1 === 0) ) {
            id = false;
            input_name = input_name+'[title]';
            res_value = name;
        }
        
        var path_separator = ' <span class="'+bl+'__item-path-separator">&#9657;</span> ';

        var node = $('<div class="'+bl+'__item'+ (!id ? ' '+bl+'__item_empty' : '')+'">'+
            '<input type="hidden" name="'+input_name+'" value="'+res_value+'" />'+
            ( path.length ? '<span class="'+bl+'__item-path">'+ path.join(path_separator)+ path_separator +'</span>' : '')+
            '<span class="'+bl+'__item-title">'+name+'</span>'+
            (this.isMultiple ? '<span class="'+bl+'__item-killer">&times;</span>' : '')+
            '</div>');
        this.$input.before( node );
        this.updateSortableAxis();
        if (!this.isMultiple) {
            this.disableAdd();
            this.Suggest.currentId = id;
        } else {
            //this.Suggest.setRequestParams(this.getSuggestParams());
        }
        if (!this.addSilent) {
            var e = $.Event('livesearch_value_added');
            e.id = id;
            e.value = value;
            e.value_name = name;
            e.value_node = node;
            e.is_preset = !!this.inpNames[id];
            this.$node.trigger(e);

            $('input', node).trigger('change');
        }
        this.Suggest.hideBox();
    };
    
    this.addDisabled = false;
    this.disableAdd = function() {
        this.addDisabled = true;
        if (this.allowEmpty) {
            this.$input.css({
                width:'1px',
                position:'absolute',
                left:'-10000px'
            }).attr('tabindex', '-1');
        }
        this.Suggest.disabled = true;
        if (!this.isMultiple) {
            this.$node.addClass(bl+'_has-value');
            this.$container.attr('tabindex', '0');
        }
    };
    
    this.enableAdd = function() {
        this.addDisabled = false;
        this.$input.attr('style', '');
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
        this.recountInputWidth();
    };
    
    this.getValueNode = function() {
        if (this.isMultiple) {
            return false;
        }
        var item_node = livesearch.$node.find('.'+bl+'__item').first();
        if (item_node.length === 0) {
            return false;
        }
        return item_node;
    };
    
    this.hideValue = function() {
        var item_node = this.getValueNode();
        if (item_node) {
            if (!this.isMultiple) {
                this.$input.css('width', this.$container.width());
                this.$container.attr('tabindex', null);
            }
            if (this.allowEmpty) {
                item_node.hide();
            }
            var c_text = item_node.find('.'+bl+'__item-title').text();
            this.$input.val(c_text);
            this.enableAdd();
        }
    };
    
    this.showValue = function() {
        var item_node = this.getValueNode();
        if (item_node) {
            item_node.show();
            this.disableAdd();
        }
    };
    
    this.recountInputWidth = function() {
        if (!this.isMultiple) {
            //return;
        }
        var v = livesearch.$input.val();
        v = v.replace(/\s/g, '&nbsp;');
        livesearch.$proto_node.css({
            font:livesearch.$input.css('font'),
            padding:livesearch.$input.css('padding')
        });
        livesearch.$proto_node.html(v);
        var width = livesearch.$proto_node.outerWidth() + 20;
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
            offsetNode: this.isMultiple ? this.$input : this.$container,
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
        
        var inputs = this.$node.find('.preset_value');
        if (!this.isMultiple) {
            this.inputName = inputs.first().attr('name');
        }
        
        if (this.isMultiple && $.sortable) {
            setTimeout(function() {
                livesearch.$node.find('.'+bl+'__container').sortable({
                    items:'.'+bl+'__item',
                    stop:function () {
                        livesearch.$node.trigger('change');
                    }
                });
            }, 100);
        }
        inputs.each(function() {
            var $inp = $(this),
                id = $inp.val(),
                name = $inp.data('name'),
                path = $inp.data('path');
                
            livesearch.inpNames[id] = this.name;
            livesearch.addValue({id:id, name:name, input_name:this.name}, path);
            $(this).remove();
        });

        this.$node.on('click', '.'+bl+'__item-killer', function() {
            livesearch.removeValue($(this).closest('.'+bl+'__item'));
        });
        
        this.$node.find('.livesearch__control_arrow').click(function() {
            livesearch.$input.focus();
            livesearch.Suggest.Search('', {immediate:true});
            return false;
        });
        
        if (!this.isMultiple) {
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
            if (e.which === 8 && v === '' && livesearch.isMultiple) {
                livesearch.$node.find('.'+bl+'__item-killer').last().click();
                livesearch.Suggest.hideBox();
            }
            if (e.which === 27) {
                if (!livesearch.isMultiple) {
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
        this.$input.on('keypress keyup keydown focus', function() {
            livesearch.recountInputWidth();
        });
        
        this.$node.on('focus', '.'+bl+'__input', function() {
            livesearch.$container.addClass('livesearch__container_focused');
            if (livesearch.isMultiple) {
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
            if (!livesearch.$input.is(':focus')) {
                livesearch.$input.focus();
            }
            return;
        });
        
        this.$input.on('suggest_blur', function() {
            var $input = $(this),
                input_value = $input.val();
            
            livesearch.$container.removeClass('livesearch__container_focused');
            
            if (!livesearch.isMultiple) {
                if (input_value !== '' || !livesearch.allowEmpty) {
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
    };
    
    this.destroy = function() {
        this.Suggest.destroy();
        this.$proto_node.remove();
    };
    
    this.Init();
};

})(jQuery);