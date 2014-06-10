(function($) {
     
window.fx_livesearch = function (node) {
    this.n = $(node);
    var n = this.n;
    n.data('livesearch', this);
    var data_params = n.data('params');
    if (data_params) {
        this.datatype = data_params.content_type;
        this.count_show = data_params.count_show;
        this.conditions = data_params.conditions;
    } else {
        this.datatype = n.data('content_type');
        this.count_show = n.data('count_show');
    }
    this.inputNameTpl = n.data('prototype_name');
    
    this.inputContainer = n.find('.livesearch_input');
    this.input = this.inputContainer.find('input');
    this.isMultiple = n.data('is_multiple') === 'Y';
    
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
        var params = {
            url: '/floxim/index.php',
            data:{
                essence:'content',
                action:'livesearch',
                content_type:this.datatype,
                fx_admin:'true'
            },
			count_show:this.count_show
        };
        if (this.conditions) {
            params.data.conditions = this.conditions;
        }
        var vals = this.getValues();
        if (vals.length > 0) {
            params.data.skip_ids = vals;
        }
        return params;
    };
    
    this.getValues = function() {
        var vals = [];
        this.n.find('.livesearch_item input[type="hidden"]').each(function() {
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
        var id = n.data('id');
        var name = n.data('name');
        var value = n.data('value');
        if (n.hasClass('add_item')){
            return;
        }
        livesearch.addValue(value);//(id, name);
        if (livesearch.isMultiple) {
            livesearch.input.val('').focus().trigger('keyup');
        } else {
            livesearch.focusNextInput();
        }
    };
    
    this.focusNextInput = function() {
        var next_inp_index = $(":visible:input").index(livesearch.Suggest.input.get(0))*1 + 1;
        var next_inp = $(":visible:input:eq(" + next_inp_index + ")");
        next_inp.focus();
    };
    
    this.addSilent = false;
    
    this.loadValues = function(ids) {
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
                res.results.sort(function(a, b) {
                    if (ids.indexOf(a.id) < ids.indexOf(b.id) )
                        return -1;
                    if (ids.indexOf(a.id) > ids.indexOf(b.id) )
                        return 1;
                    return 0;  
                });  
                $.each(res.results, function(index, item) {
                    livesearch.addValue(
                        $.extend({}, item, {input_name:livesearch.inpNames[item.id]})
                        //item.id, item.name, livesearch.inpNames[item.id]
                    );
                });
                livesearch.addSilent = false;
                livesearch.n.trigger('livesearch_value_loaded');
            }
        });
    };
    
    this.addValues = function(values) {
        $.each(values, function(index, val) {
            this.addValue(val); //val.id, val.name, val.input_name);
        });
    };
    
    this.hasValue = function(val_id) {
        var vals = this.getValues();
        for (var i = 0; i < vals.length; i++) {
            if (vals[i] === val_id) {
                return true;
            }
        }
        return false;
    };
    
    // old style:
    //this.addValue = function(id, name, input_name) {
    
    // now adding all together
    this.addValue = function(value) {
        var     id = value.id,
                name = value.name,
                input_name = value.input_name;
        
        if ( (!id || id*1 === 0) && !name) {
            return;
        }

        if (id && this.hasValue(id)) {
            return;
        }
        if (!input_name) {
            input_name = this.getInputName(id);
        }
        if (!this.isMultiple && this.getValues().length > 0) {
            this.removeValue(this.getValueNode());
            //return;
        }

        var res_value = id;
        if (!id || (id*1 === 0) ) {
            id = false;
            input_name = input_name+'[title]';
            res_value = name;
        }

        var node = $('<li class="livesearch_item'+ (!id ? ' livesearch_item_empty' : '')+'">'+
            (this.isMultiple ? '<span class="killer">&times;</span>' : '')+
            '<input type="hidden" name="'+input_name+'" value="'+res_value+'" />'+
            '<span class="title">'+name+'</span>'+
            '</li>');
        this.inputContainer.before( node );
        if (!this.isMultiple) {
            this.disableAdd();
        } else {
            this.Suggest.setRequestParams(this.getSuggestParams());
        }
        if (!this.addSilent) {
            var e = $.Event('livesearch_value_added');
            e.id = id;
            e.value = value;
            e.value_name = name;
            e.value_node = node;
            e.is_preset = !!this.inpNames[id];
            this.n.trigger(e);

            $('input', node).trigger('change');
        }
        this.Suggest.hideBox();
    };
    
    this.addDisabled = false;
    this.disableAdd = function() {
            this.addDisabled = true;
            this.inputContainer.css({width:'1px',position:'absolute',left:'-1000px'});
            this.Suggest.disabled = true;
            if (!this.isMultiple) {
                this.n.addClass('livesearch_has_value');
            }
    };
    
    this.enableAdd = function() {
            this.addDisabled = false;
            this.inputContainer.attr('style', '');
            this.Suggest.disabled = false;
            this.n.removeClass('livesearch_has_value');
    };
    
    this.lastRemovedValue = null;
    
    this.removeValue = function(n) {
            this.lastRemovedValue = n.find('input').val();
            n.remove();
            this.enableAdd();
            this.Suggest.setRequestParams(this.getSuggestParams());
            this.n.trigger('change');
    };
    
    this.getValueNode = function() {
            if (this.isMultiple) {
                return false;
            }
            var item_node = livesearch.n.find('.livesearch_item').first();
            if (item_node.length === 0) {
                return false;
            }
            return item_node;
    };
    
    this.hideValue = function() {
            var item_node = this.getValueNode();
            if (item_node) {
                item_node.hide();
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
    
    this.Init = function() {
        this.Suggest = new fx_suggest({
            input:n.find('input[name="livesearch_input"]'),
			requestParams:this.getSuggestParams(),
            resultType:'json',
            onSelect:this.Select,
            offsetNode:n.find('.livesearch_items'),
            minTermLength:0
        });
        var inputs = n.find('.preset_value');
        if (!this.isMultiple) {
            this.inputName = inputs.first().attr('name');
        }
        inputs.each(function() {
            var id = $(this).val();
            livesearch.inpNames[id] = this.name;
            livesearch.addValue({id:id, name:$(this).data('name'), input_name:this.name});
            $(this).remove();
        });

        this.n.on('click', '.killer', function() {
            livesearch.removeValue($(this).closest('.livesearch_item'));
        });
        this.n.on('keydown', '.livesearch_input input', function(e) {
            var v = $(this).val();
            if (e.which === 8 && v === '') {
                n.find('.killer').last().click();
                livesearch.Suggest.hideBox();
            }
            if (e.which === 27 && !livesearch.isMultiple) {
                $(this).trigger('blur');
                return false;
            }
            if (e.which === 90 && e.ctrlKey && livesearch.lastRemovedValue) {
                livesearch.loadValues(livesearch.lastRemovedValue);
                livesearch.lastRemovedValue = null;
                livesearch.Suggest.hideBox();
            }
        });
        this.n.on('keyup', '.livesearch_input input', function(e) {
            var v = $(this).val();
            if (v === $(this).data('last_counted_val')) {
                return;
            }
            $(this).data('last_counted_val', v);
            if (v.length === 0) {
                $(this).css({width:'3px'});
                return;
            }
            var proto_html = '<span style="position:absolute; left: -1000px; top: -1000px; display:none;';
            styles = ['font-size', 'font-style', 'font-weight', 'font-family', 'line-height', 'text-transform', 'letter-spacing'];
            for (var i = 0 ; i < styles.length; i++) {
                proto_html += ' '+styles[i]+':'+$(this).css(styles[i])+';';
            }
            proto_html += '">'+$(this).val()+'</span>';
            var proto = $(proto_html);
            $('body').append(proto);
            var width = proto.width()*1 + 15;
            $(this).css({width:width+'px'});

            proto.remove();
        });

        function edit_item(item_node) {
            // @todo
        }

        this.n.on('focus', '.livesearch_input input', function() {
            if (livesearch.isMultiple) {
                return;
            }
            var item_node = livesearch.getValueNode();
            if (!item_node) {
                return;
            }
            if (item_node.hasClass('livesearch_item_empty')) {
                var item_title = item_node.find('input[type="hidden"]').val();
                livesearch.input.val(item_title);
            }
            livesearch.hideValue();
            $(this).select().trigger('keyup');
        });

        this.n.on('click', '.livesearch_items', function(e) {
            livesearch.input.focus();
            return;
        });

        this.n.on('click', '.livesearch_item', function(e) {
            var item_node = $(this);
            if (e.ctrlKey) {
                edit_item(item_node);
                return false;
            }
        });
        if (this.isMultiple) {
            this.n.find('.livesearch_items').sortable({
                items:'.livesearch_item',
                stop:function () {
                    n.trigger('change');
                }
            });
        }

        this.n.on('suggest_blur', '.livesearch_input input', function() {
            var c_val = $(this).val();
            if (c_val === '') {
                livesearch.showValue();
                return;
            }
            livesearch.addValue({id:false, name:c_val});
            livesearch.focusNextInput();
        });
    };
    
    this.Init();
};

window.fx_suggest = function(params) {
    // default
    this.defaults={
        requestParams: {
            url: null,
            data: {},
            count_show: 20,
            limit: null
        }
    };

    /**
     * Set request params use default settings
     *
     * @param params
     */
    this.setRequestParams = function(params) {
        this.requestParams = $.extend({}, this.defaults.requestParams, params);
        // calc limit
        this.requestParams.limit=this.requestParams.count_show*2;
    };

    this.input = params.input;
    this.requestParams = this.setRequestParams(params.requestParams);
    this.onSelect = params.onSelect;
    this.minTermLength = typeof params.minTermLength == 'undefined' ? 1 : params.minTermLength;
    this.resultType = params.resultType || 'html';
    this.offsetNode = params.offsetNode || this.input;
    this.boxVisible = false;
    if (!fx_suggest.cache) {
        fx_suggest.cache = {};
    }
    
    var Suggest = this;
    
    this.Init = function() {
        if (!this.input) {
            return;
        }
        this.input.attr('autocomplete', 'off');
        this.input.keyup( function(e) {
            switch (e.which) {
                // up & down & enter
                case 38: case 40: case 13:
                    return false;
                    break;
                // anything
                default:
                    var term = Suggest.getTerm();
                    if (term != '' || e.which == 8) {
                        Suggest.Search(term);
                    }
                    break;
            }
        });
        this.input.keydown( function(e) {
            switch (e.which) {
                // escape
                case 27:
                    if (Suggest.boxVisible) {
                        Suggest.hideBox();
                        return false;
                    }
                    break;
                // enter
                case 13:
                    var csi = Suggest.box.find('.search_item_active');
                    if (csi.length == 1) {
                        Suggest.onSelect(csi);
                        Suggest.hideBox();
                        return false;
                    } else {
                        Suggest.triggerHide()
                        Suggest.hideBox();
                        return false;
                    }
                    break;
                // up
                case 38:
                    Suggest.moveSelection('up');
                    return false;
                    break
                // down
                case 40:
                    if (Suggest.getTerm() == '' && Suggest.minTermLength == 0 && !Suggest.boxVisible) {
                        Suggest.Search('');
                        return false;
                    }
                    Suggest.moveSelection('down');
                    return false;
                    break;
            }
        });
        setTimeout(function() {
            Suggest.createBox();
        }, 50);
    };

    this.getTerm = function() {
        return this.input.val().replace(/^\s|\s$/, '');
    };
    
    this.lastTerm = null;
    
    this.disabled = false;
    
    this.Search = function(term) {
        if (this.disabled) {
            return;
        }
        if (term.length < this.minTermLength) {
            this.hideBox();
            this.lastTerm = term;
            return;
        }
        if (term == this.lastTerm) {
            return;
        }
        this.lastTerm = term;
        // the timeout for fast printing
        setTimeout( function() {
            // query time to change
            if (term != Suggest.getTerm()) {
                return;
            }
            Suggest.getResults(term);
        }, 200);
    }
    
    this.getResults = function(term) {
        var request_params = {
            dataType:Suggest.resultType,
			type: 'POST'
        };
        var url, cache_key_data;
        url = this.requestParams.url;
        request_params.url = url;
        var data = this.requestParams.data;
        data.term = term;
        data.limit = this.requestParams.limit;
        cache_key_data = url+$.param(data);
        request_params.data = data;
        
        if (typeof fx_suggest.cache[cache_key_data] != 'undefined') {
            var res = fx_suggest.cache[cache_key_data];
            if (res) {
                Suggest.showBox();
                Suggest.box.html(res);
            } else {
                Suggest.hideBox(false);
            }
            return;
        }
        
        
        request_params.success = function(res) {
            // query has changed while they were loading Majesty
            if (term != Suggest.getTerm()) {
                return;
            }
            //Suggest.showBox();
            if (Suggest.resultType != 'html') {
                res = Suggest.renderResults(res);
            }
            if (res) {
                Suggest.showBox();
                Suggest.box.html(res);
            } else {
                Suggest.hideBox(false);
            }
            fx_suggest.cache[cache_key_data] = res;
        };
        
        $.ajax(request_params);
    };
    
    this.renderResults = function(res) {
        var html = '';
        $.each(res.results, function(index, item) {
            html += '<div class="search_item" ';
            for (var prop in item) {
                html += 'data-'+prop+'="'+item[prop]+'" ';
            }
            html += "data-value='"+$.toJSON(item)+"' ";
            html  += '>'+item.name+'</div>';
        });
        return html;
    };
    
    this.showBox = function() {
        this.boxVisible = true;
        var node = this.offsetNode;
        this.box.show();
        if (this.isFixed()) {
            this.box.css('position', 'fixed');
        }
        this.box.offset({
                top:node.offset().top + node.height() + 4,
                left:node.offset().left
        });
        
        var tmp_box = this.box;
        setTimeout (
            function () {
                tmp_box.offset({
                        top:tmp_box.offset().top+1,
                });
            }
        , 1)
        this.box.css({
            width:node.width()-10+'px'
        });
        this.skipBlur = false;
        $('html').on('mousedown.suggest_clickout', this.clickOut);
    }
    
    
    
    this.hideBox = function(clear_input) {
        if (typeof clear_input == 'undefined') {
            clear_input = true;
        }
        if (!this.box) {
            return;
        }
        this.boxVisible = false;
        this.box.hide();
        this.lastTerm = null;
        if (clear_input) {
            this.input.val('');
        }
        $('html').off('mousedown.suggest_clickout');
    }
    
    this.clickOut = function(e) {
        var dom_box = Suggest.box.get(0);
        var dom_inp = Suggest.input.get(0);
        
        var n = e.target;
        while (n) {
            if (n == dom_inp || n == dom_box) {
                Suggest.skipBlur = true;
                return;
            }
            n = n.parentNode;
        }
        Suggest.triggerHide();
        Suggest.hideBox();
    }
    
    this.triggerHide = function() {
        var e = $.Event('suggest_blur');
        this.input.trigger(e);
    };
    
    this.isFixed = function() {
        var $parents = this.offsetNode.parents();
        for (var i = 0; i < $parents.length; i++) {
            if ($parents.eq(i).css('position') === 'fixed') {
                return true;
            }
        }
        return false;
    };
    
    this.createBox = function() {
        
        this.box = $('<div class="fx_suggest_box fx_overlay"></div>');
        $('body').append(this.box);
        
        this.box.on('click', '.search_item', function() {
            Suggest.onSelect($(this));
            Suggest.hideBox();
            return false;
        });
        
        this.input.blur(function(e) {
            setTimeout(function() {
                if(Suggest.skipBlur) {
                    Suggest.skipBlur = false;
                    return;
                }
                Suggest.triggerHide()
                Suggest.hideBox();
            },100);
        });
        
        this.input.focus(function(){
            $(this).trigger('keyup');    
        });
        
        this.box.on('mouseover', '.search_item', function() {
            Suggest.Select($(this));
        });
    }
    
    this.moveSelection = function(dir) {
        var items = this.box.find('.search_item');
        if (items.length == 0) {
            return;
        }
        if (items.length == 1) {
            this.Select(items.first());
            return;
        }
        var csel = this.box.find('.search_item_active');
        if(csel.length == 0) {
            if (dir == 'up') {
                csel = items.first();
            } else {
                csel = items.last();
            }
        }
        
        var rel_node = (dir == 'up' ? csel.prev() : csel.next());
        if (rel_node.length == 0) {
            rel_node = (dir == 'up' ? items.last() : items.first());
        }
        this.Select(rel_node);
    }
    
    this.Select = function(n) {
        this.box.find('.search_item_active').removeClass('search_item_active');
        n.addClass('search_item_active');
        var item_top = n.position().top;
        var item_bottom = item_top + n.outerHeight();
        var box_height = this.box.height();
        var box_scroll = this.box.scrollTop();
        
        var visible_top = 0;
        var visible_bottom = box_height;
        
        if (item_top < visible_top) {
            var scroll_to_set = box_scroll - (visible_top - item_top);
            this.box.scrollTop(scroll_to_set);
        } else if (item_bottom > visible_bottom) {
            var scroll_to_set = box_scroll + (item_bottom - visible_bottom);
            this.box.scrollTop(scroll_to_set);
        } 
    };
    
    this.Init();
};

})($fxj);