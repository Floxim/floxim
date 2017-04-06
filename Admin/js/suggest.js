(function($) {

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

    this.requestParamsFilter = params.requestParamsFilter || null;

    /**
     * Set request params use default settings
     *
     * @param params
     */
    this.setRequestParams = function(params) {
        this.requestParams = $.extend({}, this.defaults.requestParams, params);
        return this.requestParams;
    };

    this.getRequestParams = function() {
        var res = this.requestParams;
        if (this.requestParamsFilter) {
            res = $.extend({}, res, this.requestParamsFilter());
        }
        // calc limit
        res.limit=res.count_show*2;
        return res;
    };

    this.input = params.input;
    this.setRequestParams(params.requestParams);
    this.onSelect = params.onSelect;
    this.onRender = params.onRender || function($res) {return $res;};
    this.minTermLength = typeof params.minTermLength === 'undefined' ? 1 : params.minTermLength;
    this.resultType = params.resultType || 'html';
    this.offsetNode = params.offsetNode || this.input;
    this.preset_values = params.preset_values || [];
    this.boxVisible = false;

    this.currentId = null;

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

    this.collapseGroup = function($g) {
        $g.removeClass('search_group_expanded').addClass('search_group_collapsed');
        $g.find('.search_item .search_group_toggler').first().html( toggle_right );
        this.placeBox();
    };

    this.expandGroup = function($g) {
        $g.addClass('search_group_expanded').removeClass('search_group_collapsed');
        $g.find('.search_item .search_group_toggler').first().html( toggle_down );
        this.placeBox();
    };

    this.Init = function() {
        if (!this.input) {
            return;
        }
        this.input.attr('autocomplete', 'off');
        this.input.keyup( function(e) {
            switch (e.which) {
                // up & down & enter
                case 38: case 40: case 13: case 37: case 39:
                    return false;
                    break;
                // anything
                default:
                    var term = Suggest.getTerm();
                    if (Suggest.lockTerm) {
                        if (term === Suggest.lockTerm) {
                            return;
                        }
                        this.lockTerm = null;
                    }
                    if (term !== '' || (Suggest.lastTerm !== null && term !== Suggest.lastTerm) ) {
                        Suggest.Search(term);
                    }
                    break;
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
                    if (csi.is('.search_item_disabled')) {
                        return false;
                    }
                    if (csi.length === 1) {
                        Suggest.onSelect(csi);
                        Suggest.hideBox();
                        return false;
                    }
                    Suggest.triggerHide();
                    Suggest.hideBox();
                    return false;
                    break;
                // up
                case 38:
                    Suggest.moveSelection('up');
                    return false;
                    break
                // down
                case 40:
                    if (Suggest.getTerm() === '' && Suggest.minTermLength === 0 && !Suggest.boxVisible) {
                        Suggest.Search('');
                        return false;
                    }
                    Suggest.moveSelection('down');
                    return false;
                    break;
                // left & right
                case 37: case 39:
                    var $selected = Suggest.getActiveItem();
                    if (e.which === 37) {
                        var $expanded_group = $selected.closest('.search_group_expanded');
                        if ($expanded_group.length > 0) {
                            Suggest.collapseGroup($expanded_group);
                            Suggest.Select($expanded_group.find('>.search_item'));
                            return false;
                        }
                    } else {
                        var $collapsed_group = $selected.closest('.search_group_collapsed');
                        if ($collapsed_group.length > 0) {
                            Suggest.expandGroup($collapsed_group);
                            return false;
                        }
                    }
                    break;
            }
        });

        setTimeout(function() {

            Suggest.createBox();

            var $scrollable = $(window),
                $parents = Suggest.input.parents();

            $parents.each(function() {
                if ($(this).css('overflow') !== 'visible') {
                    $scrollable = $scrollable.add(this);
                }
            });
            var scroll_handler = function() {
                if (Suggest.boxVisible) {
                    Suggest.placeBox();
                }
            };
            $scrollable.on('scroll.suggest_scroll', scroll_handler);
            $parents.on('fx_reposition', scroll_handler);
            Suggest.onDestroy(function() {
                $scrollable.off('scroll', scroll_handler);
                $parents.off('fx_reposition', scroll_handler);
            });
        }, 50);
    };

    this.onDestroy = function(cb) {
        this.input.on('fx_suggest_destroy', cb);
    };

    this.destroy = function() {
        if (this.box) {
            this.box.remove();
        }
        this.input.trigger('fx_suggest_destroy');
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

    this.Search = function(term, params) {

        params = params || {};

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
            if (term !== Suggest.getTerm() && !params.immediate) {
                return;
            }
            Suggest.getResults(term, params);
        }, params.immediate ? 1 : 200);
    };

    this.processResults = function(res,requestParams) {
        // skip ids
        res.results=this.skipByIds(res.results,requestParams.skip_ids);
        // show limit
        res.results=this.sliceShowLimit(res.results,requestParams.count_show);
        var $res = Suggest.renderResults(res);
        if ($res && $res.length) {
            if (!Suggest.box) {
                return;
            }
            Suggest.box.html('').append($res);
            Suggest.showBox();
            var selected_node = [];
            if (typeof Suggest.currentId !== 'undefined') {
                selected_node = Suggest.box.find('.search_item').filter(
                    function() {
                        return $(this).data('id') === Suggest.currentId;
                    }
                );
            }
            if (selected_node.length === 0) {
                selected_node = Suggest.box.find('.search_item').first();
            }
            Suggest.Select(selected_node);

        } else {
            Suggest.hideBox(false);
        }
    };

    this.getResultsFromPreset = function(term) {
        var res=this.searchFromJson(this.preset_values,term);
        this.processResults(res,this.getRequestParams());
    };

    this.getResults = function(term, params) {
        params = params || {};

        if (this.preset_values && this.preset_values.length) {
            this.Unlock();
            return this.getResultsFromPreset(term);
        }

        var ajax_params = {
            dataType: Suggest.resultType,
            type: 'POST'
        };

        var url;
        var requestParams = this.getRequestParams();
        url = requestParams.url;
        ajax_params.url = url;
        var data = $.extend({},requestParams.data);
        data.term = term;
        data.limit = requestParams.limit;
        ajax_params.data = data;
        var resCache=this.getCacheData(requestParams,term);
        if (false!==resCache) {
            this.Unlock();
            this.processResults(resCache,requestParams);
            return;
        }

        var that=this;
        this.input.trigger('fx_livesearch_request_start');
        ajax_params.success = function(res) {
            // query has changed while they were loading Majesty
            if (term !== Suggest.getTerm() && !params.immediate) {
                return;
            }
            that.Unlock();
            resCache=$.extend({},res); // copy for cache
            that.processResults(res,requestParams);
            that.setCacheData(requestParams,term,resCache);
            that.input.trigger('fx_livesearch_request_end');
        };

        $.ajax(ajax_params);
    };

    this.skipByIds = function(results,ids) {
        if (!results) {
            return [];
        }
        var resReturn=[];
        function in_array(needle, haystack){
            needle += '';
            for (var key in haystack) {
                if (haystack[key]+'' === needle) {
                    return true;
                }
            }
            return false;
        };
        $.each(results,function(k,item){
            if (item.id && in_array(item.id,ids)) {
                return true;
            }
            resReturn.push(item);
        });
        return resReturn;
    };

    this.sliceShowLimit = function(results,count_show) {
        return results.slice(0,count_show);
    };

    this.getCacheKey = function(requestParams) {
        return $.param(
            $.extend(
                {},
                requestParams,
                { skip_ids: []}
            )
        );
    };

    this.setCacheData = function(requestParams,term,res) {
        var cache_key_data = this.getCacheKey(requestParams),
            cache_key_term = term.toLowerCase(),
            item={};

        item[cache_key_term]=res;
        fx_suggest.cache[cache_key_data]=$.extend({},fx_suggest.cache[cache_key_data] || {},item);
    };

    this.getCacheData = function(requestParams,term) {
        var cache_key_data = this.getCacheKey(requestParams),
            cache_key_term = term.toLowerCase(),
            that = this,
            found_res = null;

        if (!fx_suggest.cache[cache_key_data]) {
            return false;
        }

        if (typeof fx_suggest.cache[cache_key_data][cache_key_term] !== 'undefined') {
            return $.extend(true, {}, fx_suggest.cache[cache_key_data][cache_key_term]);
        }
        
        // search for first charters
        $.each(fx_suggest.cache[cache_key_data],function(termCache,res){
            if (cache_key_term.indexOf(termCache) === 0 && res.meta && res.meta.total && res.results) {
                if (res.meta.total <= requestParams.limit) {
                    // run search from json
                    var found = that.searchFromJson(res.results, cache_key_term);
                    found.results = that.skipByIds(found.results,requestParams.skip_ids);
                    found.results = that.sliceShowLimit(found.results,requestParams.count_show);
                    found_res = found;
                    return false;
                }
            }
        });

        if (found_res) {
            return found_res;
        }
        return false;
    };


    this.checkItem = function(item, term) {
        if (item.custom) {
            return true;
        }
        return item.name && item.name.toLowerCase().indexOf(
                term.toLowerCase()
        )!== -1;
    };

    this.searchFromJson = function(jsonResults,term) {
        var results=[];
        for (var i = 0; i < jsonResults.length; i++) {
            var item = $.extend({}, jsonResults[i]);
            if (item.children && item.children.length) {
                item.children = this.searchFromJson(item.children, term).results;
            }
            var item_match = this.checkItem(item, term);
            if (item_match || (item.children && item.children.length)) {
                results.push(item);
                if (!item_match) {
                    item.disabled = true;
                }
            }

        }
        return {
            meta: {
                total: results.length
            },
            results: results
        };
    };

    this.renderResults = function(res) {
        if (res.results_html) {
            return $(res.results_html);
        }
        var $res = $([]);
        $.each(res.results, function(index, item) {
            $res = $res.add( Suggest.renderItem(item) );
        });
        $res = Suggest.onRender($res, res);
        return $res;
    };

    var toggle_right = '&#9654;',
        toggle_down = '&#9660;';

    this.renderItem = function(item, level) {
        level = level || 0;
        var item_html = '';
        if (item.path && item.path.length && !item.path_is_auto) {
            item_html += '<span class="search_item__path">';
            $.each(item.path, function() {
                item_html += '<span class="search_item__path-chunk">'+this+'</span>';
                //item_html += '<span class="search_item__path-separator">&#9657;</span>';
                item_html += '<span class="search_item__path-separator">&gt;</span>';
            });
            item_html += '</span>';
        }

        item_html += '<span class="search_item__name">'+(item.name || '')+'</span>';

        var $item = $('<div class="search_item">'+item_html+'</div>');

        if (item.custom) {
            this.drawCustomControl(item, $item.find('.search_item__name'));
        }

        if (item.title !== undefined) {
            $item.attr('title', item.title);
        }
        if (level > 0) {
            $item.addClass('search_item_level_'+level);
        }
        for (var prop in item) {
            $item.data(prop, item[prop]);
        }
        $item.data('value', item);

        if (item.id === undefined || item.disabled) {
            $item.addClass('search_item_disabled');
        }
        if (item.children && item.children.length) {
            var is_collapsed = item.collapsed || item.expanded === false || level > 1;
            
            var $group = $('<div class="search_group search_group_' + (is_collapsed ? 'collapsed' : 'expanded') +'"></div>');
            $group.append($item);
            var $children = $('<div class="search_group_children"></div>');
            for (var i = 0; i < item.children.length; i++) {
                $children.append( this.renderItem(item.children[i], level + 1) );
            }
            $group.append($children);
            if (item.expanded !== 'always') {
                var $toggler = $('<div class="search_group_toggler">' + (is_collapsed ? toggle_right : toggle_down )+ '</div>');
                $toggler.click(function() {
                    if ($group.hasClass('search_group_collapsed')) {
                        Suggest.expandGroup($group);
                    } else {
                        Suggest.collapseGroup($group);
                    }
                    Suggest.input.focus();
                    return false;
                });
                $item.append($toggler);
            }
            return $group;
        }
        return $item;
    };

    this.custom_value = null;

    this.drawCustomControl = function(item, $target) {
        if (this.custom_value !== null) {
            item = $.extend(item, {value: this.custom_value});
        }
        var $custom_control = $fx_fields.control(item);
        $target.addClass('search_item__custom-value');
        $target.append($custom_control);
        $custom_control.on(
            'input change',
            function(e) {
                var $t = $(e.target),
                    v = $t.val();
                Suggest.custom_value = v;
            }
        );
        return $custom_control;
    },

    this.placeBox = function() {
        var $node = this.offsetNode,
            rect = $node[0].getBoundingClientRect(),
            $box =  this.box,
            scroll_top = $box.scrollTop();

        if (!$node.is(':visible')) {
            $box.hide();
            return;
        }
        $box.css({
            position:'fixed',
            display:'block',
            'max-height':'none',
            height:'auto',
            top:0,
            left:0
        });

        var place_top = rect.top,
            place_bottom = $(window).height() - rect.bottom,
            box_height = $box.outerHeight(),
            css = {
                'max-height':null
            };

        if (box_height > place_bottom && place_top > place_bottom) {
            css.height = Math.min(box_height, place_top - 15);
            css.top = place_top - css.height - 5;
        } else {
            css.height = Math.min(box_height, place_bottom - 15);
            css.top = rect.top + rect.height + 5;
        }
        css.left = rect.left;
        //css.width = rect.width;
        css['min-width'] = rect.width;
        $box.css(css);
        $box.scrollTop(scroll_top);
    };

    this.showBox = function() {
        if (!this.boxVisible) {
            this.boxVisible = true;
            this.skipBlur = false;
            $('html').off('.suggest_clickout').on('mousedown.suggest_clickout', this.clickOut);
        }
        this.placeBox();
        this.input.trigger('fx-livesearch-showbox', [this.box]);
    };

    this.hideBox = function(clear_input) {
        
        if (window.fx_no_hide) {
            return;
        }
        if (typeof clear_input === 'undefined') {
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
            if (!$target.is('.livesearch__input')) {
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
            var $item = $(this);
            if (!$item.is('.search_item_disabled')) {
                Suggest.onSelect($item);
                Suggest.hideBox();
            } else {
                var $toggler = $('.search_group_toggler', $item);
                if ($toggler.length) {
                    $toggler.click();
                } else {
                    Suggest.input.focus();
                }
            }
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
            //$(this).trigger('keyup');
        });

        this.box.on('mouseover', '.search_item', function() {
            Suggest.Select($(this));
            return false;
        });
    };

    this.getSearchItems = function() {
        var $res = this.box
                .find('.search_item:visible')
                .filter(function() {
                    var $item = $(this);
                    return $item.is('.search_item_active') ||
                           !$item.is('.search_item_disabled') ||
                           $item.closest('.search_group').is('.search_group_collapsed');
                });
        return $res;
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

        var c_index = items.index(csel);

        var new_index = dir === 'up' ? c_index - 1 : c_index + 1;
        if (new_index > items.length - 1) {
            new_index = 0;
        } else if (new_index < 0) {
            new_index = items.length - 1;
        }
        var $rel_node = items.eq(new_index);
        this.Select($rel_node);
    };

    // hilight active item by up/down arrows or mouseover
    this.Select = function(n) {
        this.box.find('.search_item_active').removeClass('search_item_active');
        n.addClass('search_item_active');
        var box_rect = this.box[0].getBoundingClientRect(),
            item_rect = n[0].getBoundingClientRect(),
            box_scroll = this.box.scrollTop();

        if (item_rect.top < box_rect.top) {
            var scroll_to_set = box_scroll - (box_rect.top - item_rect.top);
            this.box.scrollTop(scroll_to_set);
        } else if (item_rect.bottom > box_rect.bottom) {
            var scroll_to_set = box_scroll + (item_rect.bottom - box_rect.bottom);
            this.box.scrollTop(scroll_to_set);
        }
    };

    this.Init();
};
})(jQuery);
