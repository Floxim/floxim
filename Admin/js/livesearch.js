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
        this.preset_values = data_params.preset_values;
        this.ajax_preload = data_params.ajax_preload;
        this.plain_values = data_params.plain_values || [];
        this.skip_ids = data_params.skip_ids || [];
        this.allow_new = data_params.allow_new || false;
    } else {
        this.datatype = n.data('content_type');
        this.count_show = n.data('count_show');
        this.preset_values = n.data('preset_values');
    }
    if (!this.preset_values) {
        this.preset_values=[];
    }
    this.inputNameTpl = n.data('prototype_name');
    
    this.inputContainer = n.find('li.livesearch_input');
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
            //url: '/vendor/Floxim/Floxim/index.php',
            url:'/~ajax/floxim.main.content:livesearch/',
            data:{
                //entity:'content',
                //action:'livesearch',
                content_type:this.datatype,
                //fx_admin:'true'
            },
            count_show:this.count_show
        };
        if (this.conditions) {
            params.data.conditions = this.conditions;
        }
        var vals = this.getValues();
        params.skip_ids = [];
        if (this.skip_ids) {
            params.skip_ids = this.skip_ids;
        }
        if (vals.length > 0) {
            for (var i = 0; i < vals.length; i++) {
                params.skip_ids.push(vals[i]);
            }
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
        livesearch.addValue(value);
        if (livesearch.isMultiple) {
            livesearch.input.val('').focus().trigger('keyup');
        } else {
            //livesearch.focusNextInput();
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
                livesearch.n.css('visibility', 'hidden');
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
                livesearch.n.css('visibility', '');
            }
        });
    };
    
    
    // recount axis prop for sortable
    // if there is only one row, use "x", otherwise - "false"
    this.updateSortableAxis = function() {
        if (!this.isMultiple) {
            return;
        }
        var $container = $('.livesearch_items', this.n);
        var $items = $container.children('.livesearch_item');
        var axis = false;
        if (!$items || !$items.length) {
            return;
        }
        if ($items.first().offset().top === $items.last().offset().top) {
            axis = 'x';
        }
        setTimeout(function() {
            $container.sortable('option', 'axis', axis);
        }, 150);
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

        if (id && this.hasValue(id) && this.isMultiple) {
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
            console.trace();
        }

        var node = $('<li class="livesearch_item'+ (!id ? ' livesearch_item_empty' : '')+'">'+
            (this.isMultiple ? '<span class="killer">&times;</span>' : '')+
            '<input type="hidden" name="'+input_name+'" value="'+res_value+'" />'+
            '<span class="title">'+name+'</span>'+
            '</li>');
        this.inputContainer.before( node );
        this.updateSortableAxis();
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
        if (!n) {
            return;
        }
        this.lastRemovedValue = n.find('input').val();
        n.remove();
        this.enableAdd();
        this.Suggest.setRequestParams(this.getSuggestParams());
        this.n.trigger('change');
        this.updateSortableAxis();
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
            var c_text = item_node.find('.title').text();
            this.input.val(c_text);
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
            input:n.find('input.livesearch_input'),
            requestParams:this.getSuggestParams(),
            resultType:'json',
            onSelect:this.Select,
            offsetNode:n.find('.livesearch_items'),
            minTermLength:0,
            preset_values: this.preset_values
        });
        var inputs = n.find('.preset_value');
        if (!this.isMultiple) {
            this.inputName = inputs.first().attr('name');
        }
        
        if (this.isMultiple) {
            /* 
            * tolerance may be 'pointer' or 'intersect' (default), 
            * still don't which is better
            */
            setTimeout(function() {
                livesearch.n.find('.livesearch_items').sortable({
                    items:'.livesearch_item',
                    axis:'x',
                    //tolerance:'pointer', 
                    containment:'parent',
                    stop:function () {
                        n.trigger('change');
                    }
                });
            }, 100);
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
        this.n.on('keydown', 'li.livesearch_input input', function(e) {
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
        this.n.on('keypress keyup', 'li.livesearch_input input', function(e) {
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
            var width = proto.width()*1 + (livesearch.isMultiple ? 15 : 4);
            $(this).css({width:width+'px'});

            proto.remove();
        });

        function edit_item(item_node) {
            // @todo
        }

        this.n.on('focus', 'li.livesearch_input input', function() {
            if (livesearch.isMultiple) {
                return;
            }
            var item_node = livesearch.getValueNode();
            if (item_node && item_node.hasClass('livesearch_item_empty')) {
                var item_title = item_node.find('input[type="hidden"]').val();
                livesearch.input.val(item_title);
            }
            livesearch.hideValue();
            $(this).select().trigger('keyup');
        });

        this.n.on('click', '.livesearch_items', function(e) {
            if (!livesearch.input.is(':focus')) {
                livesearch.input.focus();
            }
            return;
        });

        this.n.on('click', '.livesearch_item', function(e) {
            var item_node = $(this);
            if (e.ctrlKey) {
                edit_item(item_node);
                return false;
            }
        });

        this.n.on('suggest_blur', 'li.livesearch_input input', function() {
            var $input = $(this),
                input_value = $input.val();
            
            if (!livesearch.isMultiple) {
                var selected_value = livesearch.getValue();
                var c_search_item = livesearch.Suggest.getSearchItems().first();
                // current value is in first in list - save it
                if (
                    c_search_item && selected_value && 
                    c_search_item.data('id') === selected_value * 1
                ) {
                    livesearch.Select(c_search_item);
                    return false;
                }
            }
            if (input_value && livesearch.allow_new) {
                livesearch.addValue({id:false, name:input_value});
            } else {
                livesearch.removeValue( livesearch.getValueNode() );
                $input.val('');
            }
            livesearch.focusNextInput();
            return false;
        });
    };
    
    this.destroy = function() {
        this.Suggest.box.remove();
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
            limit: null,
            skip_ids: []
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
        return this.requestParams;
    };

    this.input = params.input;
    this.requestParams = this.setRequestParams(params.requestParams);
    this.onSelect = params.onSelect;
    this.minTermLength = typeof params.minTermLength == 'undefined' ? 1 : params.minTermLength;
    this.resultType = params.resultType || 'html';
    this.offsetNode = params.offsetNode || this.input;
    this.preset_values = params.preset_values || [];
    this.boxVisible = false;
    if (!fx_suggest.cache) {
        /**
         * Structure cache: {
         *  'cache_key_data': {
         *      'term': res
         *  }
         * }
         *
         * @type {{}}
         */
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
        this.input.focus(function(){
            var term = Suggest.getTerm();
            if (term === '' && Suggest.preset_values.length) {
                // open preset items
                Suggest.getResults('');
            }
        });
        this.input.keydown( function(e) {
            switch (e.which) {
                // escape
                case 27:
                    return;
                    if (Suggest.boxVisible) {
                        Suggest.hideBox();
                        return false;
                    }
                    break;
                // enter
                case 13:
                    var csi = Suggest.getActiveItem();
                    if (Suggest.locked) {
                        return false;
                    }
                    if (csi.length === 1) {
                        Suggest.onSelect(csi);
                        Suggest.hideBox();
                        return false;
                    } else {
                        Suggest.triggerHide();
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
    this.locked = false;
    
    this.Lock = function () {
        this.locked = true;
    };
    
    this.Unlock = function () {
        this.locked = false;
    };
    
    this.Search = function(term) {
        if (this.disabled) {
            return;
        }
        if (term.length < this.minTermLength) {
            this.hideBox();
            this.lastTerm = term;
            return;
        }
        if (term === this.lastTerm) {
            return;
        }
        this.lastTerm = term;
        
        this.Lock();
        // the timeout for fast printing
        setTimeout( function() {
            // query time to change
            if (term !== Suggest.getTerm()) {
                return;
            }
            Suggest.getResults(term);
        }, 200);
    };

    this.processResults = function(res,requestParams) {
        // skip ids
        res.results=this.skipByIds(res.results,requestParams.skip_ids);
        // show limit
        res.results=this.sliceShowLimit(res.results,requestParams.count_show);
        var resHtml = Suggest.renderResults(res);
        if (resHtml) {
            Suggest.showBox();
            Suggest.box.html(resHtml);
            Suggest.Select(Suggest.box.find('.search_item').first());
        } else {
            Suggest.hideBox(false);
        }
    };

    this.getResultsFromPreset = function(term) {
        var res=this.searchFromJson(this.preset_values,term);
        this.processResults(res,this.requestParams);
    };

    this.getResults = function(term) {
        if (this.preset_values && this.preset_values.length) {
            return this.getResultsFromPreset(term);
        }

        var request_params = {
            dataType: Suggest.resultType,
            type: 'POST'
        };
        var url;
        url = this.requestParams.url;
        request_params.url = url;
        var data = $.extend({},this.requestParams.data);
        data.term = term;
        data.limit = this.requestParams.limit;
        request_params.data = data;

        var resCache=this.getCacheData(this.requestParams,term);
        if (false!==resCache) {
            this.Unlock();
            this.processResults(resCache,this.requestParams);
            return;
        }
        
        var that=this;
        request_params.success = function(res) {
            // query has changed while they were loading Majesty
            if (term !== Suggest.getTerm()) {
                return;
            }
            that.Unlock();
            resCache=$.extend({},res); // copy for cache
            that.processResults(res,that.requestParams);
            that.setCacheData(that.requestParams,term,resCache);
        };
        
        $.ajax(request_params);
    };

    this.skipByIds = function(results,ids) {
        if (!results) {
            return [];
        }
        var resReturn=[];
        var $in_array=function(needle, haystack){
            for (key in haystack) {
                if (haystack[key] == needle) {
                    return true;
                }
            }
            return false;
        };
        $.each(results,function(k,item){
            if (item.id && $in_array.call(this,item.id,ids)) {
                return true;
            }
            resReturn.push(item);
        });
        return resReturn;
    };

    this.sliceShowLimit = function(results,count_show) {
        return results.slice(0,count_show);
    };

    this.setCacheData = function(requestParams,term,res) {
        var cache_key_data = $.param($.extend({},requestParams,{ skip_ids: []})), // clear skip ids
            cache_key_term = term.toLowerCase(),
            item={};

        item[cache_key_term]=res;
        fx_suggest.cache[cache_key_data]=$.extend({},fx_suggest.cache[cache_key_data] || {},item);
    };

    this.getCacheData = function(requestParams,term) {
        var cache_key_data = $.param(
                $.extend(
                    {},
                    requestParams//,
                    //{ skip_ids: []}
                )
            ), // clear skip ids
            cache_key_term = term.toLowerCase(),
            $this=this,
            resReturn=null;

        if (fx_suggest.cache[cache_key_data]) {
            if (typeof fx_suggest.cache[cache_key_data][cache_key_term] != 'undefined') {
                return fx_suggest.cache[cache_key_data][cache_key_term];
            } else {
                // search for first charters
                $.each(fx_suggest.cache[cache_key_data],function(termCache,res){
                    if (cache_key_term.indexOf(termCache)===0 && res.meta && res.meta.total && res.results) {
                        if (res.meta.total<=requestParams.limit) {
                            // run search from json
                            resReturn=$this.searchFromJson(res.results,cache_key_term);
                            resReturn.results=$this.skipByIds(resReturn.results,requestParams.skip_ids);
                            resReturn.results=$this.sliceShowLimit(resReturn.results,requestParams.count_show);
                            return false;
                        }
                    }
                });
                if (resReturn) {
                    return resReturn;
                }
            }
        }
        return false;
    };

    this.searchFromJson = function(jsonResults,term) {
        var results=[];
        $.each(jsonResults,function(k,item){
            if (item.name && item.name.toLowerCase().indexOf(term)!=-1) {
                results.push(item);
            }
        });
        return {
            meta: {
                total: results.length
            },
            results: results
        };
    };
    
    this.renderResults = function(res) {
        if (res.results_html) {
            return res.results_html;
        }
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
            top:node.offset().top + node.outerHeight(),
            left:this.input.offset().left
        });
        
        var tmp_box = this.box;
        setTimeout (
            function () {
                tmp_box.offset({
                        top:tmp_box.offset().top+1
                });
            }
        , 1);
        this.box.css({
            //width:node.width()-10+'px'
            float:'left'
        });
        this.skipBlur = false;
        $('html').off('.suggest_clickout').on('mousedown.suggest_clickout', this.clickOut);
    };
    
    
    
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
    };
    
    this.clickOut = function(e) {
        var $target = $(e.target);
        if ($target.closest('.fx_suggest_box').length) {
            Suggest.skipBlur = true;
            return;
        }
        
        var $pars = $target.parents().add($target);
        if ($pars.index(Suggest.offsetNode) !== -1) {
            Suggest.skipBlur = true;
            if (!$target.is('.livesearch_input')) {
                return false;
            }
            return;
        }
        Suggest.skipBlur = false;
    };
    
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
            // Suggest is not active
            if (Suggest.disabled) {
                return;
            }
            // Suggest.skipBlur set to "true" by clickOut 
            // if the click target is inside suggest box or is part of input
            if(Suggest.skipBlur) {
                Suggest.skipBlur = false;
                return;
            }
            Suggest.triggerHide();
            Suggest.hideBox();
        });
        
        this.input.focus(function(){
            $(this).trigger('keyup');
        });
        
        this.box.on('mouseover', '.search_item', function() {
            Suggest.Select($(this));
        });
    };
    
    this.getSearchItems = function() {
        return this.box.find('.search_item:visible');
    };
    
    this.getActiveItem = function() {
        return this.getSearchItems().filter('.search_item_active');
    };
    
    this.moveSelection = function(dir) {
        var items = this.getSearchItems();
        if (items.length === 0) {
            return;
        }
        if (items.length === 1) {
            this.Select(items.first());
            return;
        }
        var csel = this.box.find('.search_item_active');
        if(csel.length === 0) {
            if (dir === 'up') {
                csel = items.first();
            } else {
                csel = items.last();
            }
        }
        
        var rel_node = (dir === 'up' ? csel.prev() : csel.next());
        if (rel_node.length === 0) {
            rel_node = (dir === 'up' ? items.last() : items.first());
        }
        this.Select(rel_node);
    };
    
    // hilight active item by up/down arrows or mouseover
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

})(jQuery);