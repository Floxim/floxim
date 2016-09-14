(function($){;
    
var bl = 'fx_admin_form';

fx_form = {
    
    create:function(options, $target) {
        
        var settings = {
            form: {
                id:'fx_dialog_form', 
                action:$fx.settings.action_link, 
                target:'fx_form_target'
            }
        };
        
        if (options) {
            $.extend(true, settings, options);
        }
        
        $('html').trigger('fx_before_adm_form_created', settings);
        
        var $form = $(
                '<form '+
                    'class="'+bl+'" '+
                    'id="'+settings.form.id+'" '+
                    'action="'+settings.form.action+'" '+
                    'enctype="multipart/form-data" '+
                    'method="post" '+
                    'target="'+settings.form.target+'" />');
        if (settings.class_name) {
            $form.addClass(settings.class_name);
        }
        $form.append('<iframe id="'+settings.form.target+'" name="'+settings.form.target+'" style="display:none;"></iframe>');
        $target.html('').append($form);
        
        var $form_header = $(
            '<div class="'+bl+'__header">'+
                (settings.header ? '<div class="'+bl+'__title">'+ settings.header +'</div>' : '')+
            '</div>'
        );
        $form.append($form_header);
        
        var $form_body = $('<div class="'+bl+'__body"></div>');
        $form.append($form_body);
        var $form_footer = $('<div class="'+bl+'__footer"></div>');
        $form.append($form_footer);
        
        $fx_form.draw_fields(settings, $form_body);
        

        if (options.buttons_entity) {
            $fx.admin.set_entity(options.buttons_entity);
        }
        
        var onsubmit = options.onsubmit || $fx_form.submit_handler;
        
        if (typeof onsubmit === 'function') {
            onsubmit = [ onsubmit ];
        }
        
        $form.off('submit.fx_submit');
        for (var i = 0; i < onsubmit.length; i++ ) {
            $form.on('submit.fx_submit', onsubmit[i])
        }
        
        if (settings.lockable) {
            $fx_form.handle_lockable($form);
        }
        var passed_settings = $.extend({request:{}}, settings);
        $form.data('fx_response', passed_settings);
        $form.trigger('fx_adm_form_created', passed_settings);
        return $form;
    },
    // find fields that are placed before containing group and put it right after it
    sort_fields: function(fields) {
        var groups = {},
            fields_with_lost_group = [];
        for (var i = 0 ; i < fields.length; i++) {
            var f = fields[i];
            if (f.type === 'group') {
                groups[f.keyword] = f;
            }
            if (f.group && !groups[f.group]) {
                fields_with_lost_group.push( f );
            }
        }
        if (fields_with_lost_group.length === 0) {
            return fields;
        }
        function get_priority(f) {
            var p = f.priority || 0;
            if (f.group && fields_with_lost_group.indexOf(f) !== -1) {
                p = get_priority(groups[f.group]) + p*0.000001;
            }
            return p;
        }
        for (var i = 0; i < fields_with_lost_group.length; i++) {
            var cf = fields_with_lost_group[i];
            cf.group_priority = groups[cf.group].priority;
        }
        var res = fields.sort(function(a, b) {
            return get_priority(a) - get_priority(b);
        });
        return res;
    },
    draw_fields: function(settings, $form_body) {
        if (settings.fields === undefined) {
            //return;
            settings.fields = [];
        }
        
        var $form = $form_body.closest('form');
        
        var use_tabs = settings.tabs && !settings.ignore_cols;

        settings.fields = $fx.form.init_joins(settings.fields);
        
        if (use_tabs) {
            $fx_form.init_tabs(settings, $form);
        }
        
        // draw list buttons in backoffice
        if ($fx.mode !== 'page') {
            $fx.buttons.draw_buttons(settings.buttons);
        }
        var rendered_groups = {},
            sorted_fields = this.sort_fields(settings.fields);
        
        $.each(sorted_fields, function(i, json) {
            var $target = $form_body;
            if (json.group) {            
                $target = rendered_groups[json.group];
            } else if (json.tab === 'header' || json.tab === 'footer') {
                var $tab_container = $form_body.closest('form').find('.'+bl+'__'+json.tab),
                    target_class = bl+'__'+json.tab+'_fields',
                    $target = $tab_container.find('.'+target_class);
                if ($target.length === 0) {
                    $target = $('<div class="'+target_class+'"></div>');
                    $tab_container.append($target);
                }
                
            } else if (use_tabs && json.tab !== undefined) {
                $target = $('.fx_tab_data-key-'+json.tab, $form_body);
                if (json.type !== 'hidden' && $target.data('tab_label')) {
                    $target.data('tab_label').show();
                }
            }
            if (settings.lockable) {
                json.form_is_lockable = true;
            }
            
            var $field_node = $fx_form.draw_field(json, $target);
            if (json.type === 'group') {
                var $group_fields_container = $field_node.find('.fx-field-group__fields');
                rendered_groups[json.keyword] = $group_fields_container;
                /*
                if (json.fields) {
                    $.each(json.fields, function(index, field) {
                        $fx_form.draw_field(field, $group_fields_container);
                    });
                }
                */
            }
        });
        
        $('.fx_tab_data .field:last-child', $form_body).addClass('field_last');
        if (typeof settings.form_button === 'undefined') {
            settings.form_button = [];
        }
        var submit_added = false;
        var button_container = settings.button_container || 'footer',
            $button_container = button_container,
            $buttons = $('<div class="'+bl+'__buttons"></div>');
    
        if (typeof button_container === 'string') {
            $button_container = $('.'+bl+'__'+button_container, $form);
            $button_container.show();
            $buttons.addClass(bl+'__buttons-in_'+button_container);
        }
        
        $button_container.append($buttons);
        $form.data('button_container', $button_container);
        
        $.each(settings.form_button, function (key,options) {
            if (typeof options === 'string') {
                options = {key:options};
            }
            if (!options.type) {
                options.type = 'button';
            }
            if (!options.label) {
                options.label = $fx.lang(options.key);
            }
            if (typeof options.is_submit === 'undefined') {
                options.is_submit = true;
            }
            if (options.key ==='cancel') {
                options['class'] = 'cancel';
                options.is_submit = false;
            } else if (options.is_active !== false) {
                options.is_active = true;
            }
            var b = $t.jQuery('input', options);
            b.data('key', options.key);
            $buttons.append(b);
            if (options.key === 'cancel') {
                b.on('click', function() {
                    $form.trigger('fx_form_cancel');
                });
            }
            if (options.is_submit) {
                b.on('click', function() {
                    $form.append(
                        '<input type="hidden" name="pressed_button" '+
                            ' value="'+$(this).data('key')+'" />'
                    );
                    $form.submit();
                });
                if (!submit_added) {
                    $form.append(
                        '<input '+
                            ' type="submit" tabindex="-1" '+
                            ' style="position:absolute; top:-10000px; left:-10000px" />'
                    );
                    submit_added = true;
                }
            } else if (options.onclick) {
                b.on('click', options.onclick);
            }
        });
    },
    lock_form: function($form) {
        $form.data('is_locked', true);
        var $bc = $form.data('button_container');
        if ($bc) {
            $bc.find('.fx_button').addClass('fx_button-disabled');
        }
    },
    unlock_form: function($form) {
        $form.data('is_locked', false);
        var $bc = $form.data('button_container');
        if ($bc) {
            $bc.find('.fx_button-disabled').removeClass('fx_button-disabled');
        }
    },
    form_is_locked: function($form) {
        return $form.data('is_locked');
    },
            
    submit_handler : function() {
        var status_block = $("#fx_admin_status_block");
        var $form = $(this);
        $(".ui-state-error").removeClass("ui-state-error");
        
        if ($fx.form.form_is_locked($form)) {
            return false;
        }
        
        $fx.form.lock_form($form);
        
        $form.trigger('fx_form_submit');
        
        //$form.ajaxSubmit(function ( data ) {
        $fx.post(
            $form.formToHash(),
            function(data) {
                $fx.form.unlock_form($form);
                if (typeof data === 'string') {
                    try {
                        data = $.parseJSON( data );
                    } catch(e) {
                        $fx.alert('Responce parse error');
                        console.log(data, e);
                        return false;
                    }
                }
                $form.trigger('fx_form_sent', data);

                if ( data.status === 'ok') {
                    status_block.show();
                    status_block.writeOk( data.text ? data.text : 'Ok');
                    $form.trigger('fx_form_ok');
                }
                else if (data.status === 'error') {
                    //status_block.writeError( data );
                    if (data.errors.length) {
                        $.each(data.errors, function() {
                            $fx.alert(this.error || this.text, 'error', 3);
                        });
                    } else {
                        $fx.alert('Error!', 'error', 3);
                    }
                    return;
                }
                else if (data.text) {
                    status_block.show();
                    status_block.writeError(data['text']);
                    for ( i in data.fields ) {
                        $('[name="'+data.fields[i]+'"]').addClass("ui-state-error");
                    }
                }
                if (data.reload) {
                    $fx.form.lock_form($form);
                    $fx.reload(data.reload);
                } else if (data.show_result) {
                    $fx.admin.load_page(data);
                } else {
                    $(window).hashchange();
                }
            }
        );
        return false;
    },

    init_tabs: function ( settings, $form) {
        var $form_body = $('.'+bl+'__body', $form),
            $form_header = $('.'+bl+'__header', $form),
            $tab_labels = $('<div class="'+bl+'__tab_labels"></div>'),
            $tab_data = $('<div class="'+bl+'__tab_data"></div>'),
            c_label = 'fx_tab_label',
            c_data = 'fx_tab_data';
    
        $form_body.append($tab_data);
        $form_header.append($tab_labels);
        
        var has_active = false;
        $.each(settings.fields, function(field_index, field) {
            if (field.tab && typeof field.tab === 'object') {
                var tab_key = field.tab.key;
                if (!settings.tabs[tab_key]) {
                    settings.tabs[tab_key] = {
                        key:tab_key,
                        label:field.tab.label
                    };
                }
                field.tab = tab_key;
            }
        });
            
        $.each(settings.tabs, function(key,val){
            if (typeof val === 'string') {
                val = {label:val};
            }
            if (key === 'header') {
                return;
            }
            if (val.active) {
                has_active = true;
            }
            var $tab_label = 
                $('<div data-key="'+key+'" class="'+c_label + (val.active ? ' '+c_label+'-active' : '') + '" style="display:none;">'+
                    (val.icon ? 
                    '<span class="'+c_label+'__icon fx_icon fx_icon-type-'+val.icon+'"></span>' 
                    : '')+
                    '<span class="'+c_label+'__title">'+(val.label || key)+'</span>'+
                '</div>'),
                $tab_data_item = 
                $('<div class="'+c_data+' '+c_data+'-key-'+key+(val.active ? ' '+c_data+'-active' : '')+'">'+
                '</div>');
            $tab_data_item.data('tab_label', $tab_label);
            $tab_labels.append($tab_label);
            $tab_data.append($tab_data_item);
        });
        //$tab_labels.html(tab_labels_html);
        //$tab_data.html(tab_data_html);
        
        function select_tab($tab_label) {
            var key = $tab_label.data('key'),
                $tab_data = $form_body.find('.fx_tab_data-key-'+key),
                map = {};
            map[c_label] = $tab_label;
            map[c_data] = $tab_data;

            $.each(map, function(c_class, $node) {
                $('.'+c_class+'-active', $node.closest('.'+bl+'__tab_data, .'+bl+'__tab_labels')).removeClass(c_class+'-active');
                $node.addClass(c_class+'-active').trigger('fx_tab_focus');
            });
            $('.'+c_label+' .fx_icon').addClass('fx_icon-clickable');
            $('.'+c_label+'-active .fx_icon').removeClass('fx_icon-clickable');
        }
        
        $tab_labels.on('click', '.'+c_label, function() {
            select_tab($(this));
        });
        
        if (!has_active) {
            select_tab($tab_labels.find('.'+c_label).first());
        }
    },
    
    init_joins: function(fields) {
        var groups = {},
            res = [],
            field_names = {};
        for (var i = 0; i < fields.length; i++) {
            var c_field = fields[i],
                c_join = c_field.join_with;
    
            field_names[fields[i].name] = true;
            if (!c_join) {
                continue;
            }
            if (!groups[c_join]) {
                var group = {type:'joined_group', fields:[]};
                if (c_field.tab) {
                    group.tab = c_field.tab;
                }
                group.join_type = c_field.join_type || 'tabs';
                groups[c_join] = group;
            }
        }
        
        for (var i = 0; i < fields.length; i++) {
            var f = fields[i];
            if (f.join_with && field_names[f.join_with]) {
                groups[f.join_with].fields.push(f);
                continue;
            }
            if (groups[f.name]) {
                groups[f.name].fields.push(f);
                res.push(groups[f.name]);
                continue;
            }
            res.push(f);
        }
        return res;
    },

    draw_field: function(json, $target, position) {
        if (json.form_is_lockable && json.type !== 'hidden') {

            var $lock_group = $(
                    '<div class="fx_lock_group">'+
                        '<div class="fx_lock_control"></div>'+
                        '<div class="fx_lock_container"></div>'+
                    '</div>');
            $target.append($lock_group);
            $target = $('.fx_lock_container', $lock_group);
            if (json.locked !== undefined) {
                var locker_name = json.name;
                if (locker_name.match(/\]$/)) {
                    locker_name = locker_name.replace(/\]$/, '__is_locked]');
                } else {
                    locker_name += '__is_locked';
                }
                var $control = $fx_fields.input({
                    type: 'checkbox',
                    name: locker_name,
                    class_name:'locker',
                    value: json.locked
                });
                if (json.locked) {
                    json.disabled = true;
                }
                $('.fx_lock_control', $lock_group).append($control);
            }
        }
        position = position || 'into';
        var $rel_node = null;
        if (position !== 'into') {
            $rel_node = $target;
            $target = $rel_node.parent();
        }
        var node = $fx_fields.row(json);
        switch (position) {
            case 'into':
                $target.append(node);
                break;
            case 'before':
                node.insertBefore($rel_node);
                break;
            case 'after':
                node.insertAfter($rel_node);
                break;
        }
        
        
        if (node === '') {
            return null;
        }
        // ajax change
        if (json.post && json.type !== 'button') {
            // creating container for extra json-loaded fields
            var post_container = $('<div class="container"></div>').appendTo($target);
            
            node.on('change', function(){
                var form_vals = {},
                    $form = node.closest('form'),
                    $inputs = $('input, textarea, select', $form)
                $inputs.each(function(){
                    var $inp = $(this),
                        c_field_name = $inp.attr('name'),
                        c_field_type = $inp.attr('type');
                    
                    if (c_field_name !== 'posting' && c_field_type !== 'button') {
                        var val;
                        if (c_field_type === 'radio') {
                            val = $('input[name="'+c_field_name+'"]:checked').val();
                        } else {
                            val = $inp.val();
                        }
                        form_vals[c_field_name] = val;
                    }
                });
                var data_to_post = $.extend({}, form_vals, json.post);
                $fx.post(data_to_post, function(fields){
                    post_container.html('');
                    if (fields.fields) {
                        for (var i = 0 ; i < fields.fields.length; i ++ ) {
                            var c_field = fields.fields[i];
                            if (c_field.locked !== undefined){
                                fields.lockable = true;
                                break;
                            }
                        }
                    }
                    $fx_form.draw_fields(fields, post_container);
                });
            });
            // delay change event to let following fields render themselves before initial data is sent
            setTimeout(function() {
                node.trigger('change');
            }, 50);
        }
        if (json.parent) {
            this.add_parent_condition(json.parent, node, $target);
        } else if (json.type === 'joined_group') {
            /*
            $.each(json.fields, function() {
                if (this.parent && this.$input) {
                    $fx_form.add_parent_condition(this.parent, this.$input, $target);
                }
                if (this.values_filter) {
                    console.log(this);
                    if (!this.all_values) {
                        this.all_values = this.values;
                    }
                    $fx_form.bind_values_filter(this.$input, this, $target);
                }
            });
            */
        }
        if (json.values_filter) {
            if (!json.all_values) {
                json.all_values = json.values;
            }
            this.bind_values_filter(node, json, $target);
        }
        return node;
    },
    
    bind_values_filter: function($field, json, $container) {
        var all_conds = [],
            that = this,
            filters = json.values_filter;
        
        // collect all conditions to one collection to make a single handler
        $.each(filters, function (val, filter) {
            var filter_conds = that.make_conditions(filter);
            for (var i = 0; i < filter_conds.length; i++) {
                all_conds.push( filter_conds[i] );
            }
        });
        
        $container = $container.closest('form');
        
        var handler = this.bind_conditions(
            all_conds, 
            $container,
            function () {
                var new_vals = [],
                    new_vals_hash = '';
                for (var i = 0; i < json.all_values.length; i++) {
                    var c_val = json.all_values[i],
                        c_val_name = c_val[0];
                    if (!filters[c_val_name]) {
                        new_vals.push(c_val);
                        new_vals_hash += c_val_name+'';
                        continue;
                    }
                    var value_avail = that.check_conditions(filters[c_val_name], $container);
                    if (value_avail) {
                        new_vals.push(c_val);
                        new_vals_hash += c_val_name+'';
                    }
                }
                var is_diff = json.values.length !== new_vals.length,
                    old_vals_hash = '';
                if (!is_diff) {
                    for (var i = 0; i < json.values.length; i++) {
                        old_vals_hash += json.values[i][0]+'';
                    }
                    is_diff = old_vals_hash !== new_vals_hash;
                }
                if (is_diff) {
                    var $livesearch = $field.find('.livesearch'),
                        prev_value;
                    if ($livesearch.length) {
                        var ls = $livesearch.data('livesearch');
                        prev_value = ls.getValue();
                        ls.destroy();
                    }
                    var new_params = $.extend({}, json, {values:new_vals});
                    if (new_vals.length) {
                        new_params.value = new_vals[0][0];
                    }
                    var $new_field = that.draw_field(new_params, $field, 'before');
                    $new_field.find('input[type="hidden"]').trigger('change');
                    $field.remove();
                    handler.unbind();
                }
            }
        );
    },
    
    make_conditions: function(conds) {
        var res = [];
        if (typeof conds === 'string') {
            res.push(
                [conds, null, 'not_empty']
            );
            return res;
        }
        if (conds instanceof Array) {
            if (typeof conds[0] === 'string') {
                res.push(conds);
            } else {
                res = conds;
            }
        } else if (typeof conds === 'object') {
            $.each(conds, function(index, item) {
                res.push([index, item]);
            });
        }
        for (var i = 0; i < res.length; i++) {
            var cond = res[i];
            if (cond.length === 1){
                cond[1] = null;
                cond[2] = 'not_empty';
            } else if (cond.length === 2) {
                var test_value = cond[1];
                if (/^!=/.test(test_value)) {
                    cond[1] = test_value.replace(/^!=/, '');
                    cond[2] = '!=';
                } else if (/^\~/.test(test_value)) {
                    cond[1] = new RegExp(test_value.replace(/^\~/, ''));
                    cond[2] = 'regexp';
                } else if (/^\!\~/.test(test_value)) {
                    cond[1] = new RegExp(test_value.replace(/^\!\~/, ''));
                    cond[2] = 'not_regexp';
                } else if (cond[1] instanceof Array) {
                    cond[2] = 'in';
                } else {
                    cond[2] = '==';
                }
            }
        }
        return res;
    },
    
    check_condition: function(cond, value) {
        var arg = cond[1],
            op = cond[2];
        
        switch (op) {
            case 'empty':
                return !value;
            case 'not_empty':
                return !!value;
            case 'regexp':
                return arg.test(value);
            case 'not_regexp':
                return !arg.test(value);
            case '==':
                return arg == value;
            case '!=':
                return arg != value;
            case 'in':
                for (var i = 0; i < arg.length; i++) {
                    if (arg[i] == value) {
                        return true;
                    }
                }
                return false;
        }
        return true;
    },
    
    get_value: function($inp) {
        if (!$inp || !$inp.length) {
            return false;
        }
        
        // smart checkbox
        if ($inp.length === 2) {
            var $checkbox = $inp.filter('[type="checkbox"]');
            if ($checkbox.length) {
                $inp = $checkbox;
            }
        }
        var inp_type = $inp.attr('type'),
            val;
        if (inp_type === 'radio') {
            var $current = $inp.closest('form').find('input[name="'+$inp.attr('name')+'"]:checked');
            val = $current.val();
        }  else if (inp_type === 'checkbox') {
            val = $inp.is(':checked') ? ($inp.attr('value') || '1') : false;
        } else {
            val = $inp.val();
        }
        return val;
    },
    
    check_conditions: function(conds, $container) {
        var result = true;
        for (var i = 0 ; i < conds.length; i++) {
            var $inp = $container.find('*[name="'+conds[i][0]+'"]');

            var value = this.get_value($inp);
            var check_result = this.check_condition(conds[i], value);
            if (!check_result) {
                result = false;
                break;
            }
        }
        return result;
    },
    
    // fetch name from input - handle livesearch with no value
    get_input_name: function($inp) {
        if ($inp.is('.monosearch__input') || $inp.is('.livesearch')) {
            var ls = $inp.closest('.livesearch').data('livesearch');
            return ls.getInputName();
        }
        return $inp.attr('name');
    },
    
    bind_conditions: function( conds, $container, callback) {
        var that = this;
    
        conds = this.make_conditions(conds);
        
        var handled_input_names = {};
        for (var i = 0; i < conds.length; i++ ) {
            handled_input_names[ conds[i][0] ] = true;
        }
        
        var change_handler = function(e) {
            var $changed_inp = $(e.target),
                changed_inp_name = that.get_input_name($changed_inp);
            
            // changed input is not mentioned in any rule
            if (!handled_input_names[ changed_inp_name ]) {
                return;
            }
            callback(conds, $container, $changed_inp);
        };
        
        $container.on('change', change_handler);
        // initial call
        setTimeout(
            function() {
                callback(conds, $container);
            },
            1
        );
        change_handler.unbind = function() {
            $container.off('change', change_handler);
        };
        return change_handler;
    },

    //add_parent_condition: function(parent, _el, container) {
    add_parent_condition: function(conds, $field, $container) {
        var that = this;
        $container = $container.closest('form');
        var handler = this.bind_conditions(
            conds, 
            $container, 
            function(conds, $container) {
                // unbind handler if the field has been removed from DOM
                if ($field.closest($container).length === 0) {
                    handler.unbind();
                    return;
                }
                var res = that.check_conditions(conds, $container);
                $field.toggleClass('fx_field_hidden_by_condition', !res);
            }
        );
    },
    handle_lockable: function($form) {
        function get_inputs($group) {
            return $('.fx_lock_container :input:not([type="hidden"])', $group);
        }
        function lock_inputs($group) {
            var $inp = get_inputs($group);
            $inp.attr('disabled', 'disabled');
            $('.fx_lock_container', $group).addClass('fx_lock_container__locked');
        }
        function unlock_inputs($group) {
            var $inp = get_inputs($group);
            $inp.attr('disabled', null);
            $('.fx_lock_container', $group).removeClass('fx_lock_container__locked');
        }
        $form
            .off('.fx_lockable')
            .on(
                'change.fx_lockable', 
                '.fx_lock_control', 
                function(e) {
                    var $ctr = $(e.target),
                        $group = $ctr.closest('.fx_lock_group'),
                        locked = e.target.checked;
                    locked ? lock_inputs($group) : unlock_inputs($group);
                }
            );
        var $checked_lockers = $('.fx_lock_control input[checked="checked"]', $form);
        $checked_lockers.each(function() {
            var $group = $(this).closest('.fx_lock_group');
            lock_inputs($group);
        });
    }
    
    
};
})(jQuery);

$fx.form = window.fx_form = window.$fx_form = fx_form;

(function($) {
    $.fn.fx_create_form = function(options) {
        $fx_form.create(options, this);
        return this;
    };
    
    $.fn.formToHash = function(filter) {
        var $form = this,
            data = $form.formToArray(true),
            res = {};
        
        filter = filter || function(f) {return true;}
        
        for (var i = 0; i < data.length; i++) {
            var f = data[i];
            if (!filter(f)) {
                continue;
            }
            var name = f.name,
                value = f.value,
                name_path_parts = name.match(/\[.+?\]/g);
            if (name_path_parts) {
                var name_base = name.replace(/\[.+/, ''),
                    name_path = [name_base];
                for (var j = 0; j < name_path_parts.length; j++) {
                    name_path.push(name_path_parts[j].replace(/[\[\]]/g, ''));
                }
                var c_res = res;
                for (var j = 0; j < name_path.length; j++) {
                    var part = name_path[j],
                        is_last = j === name_path.length - 1;
                   
                    if (typeof c_res[part] === 'undefined' && !is_last) {
                        c_res[part] = {};
                    } else if (is_last) {
                        c_res[part] = value;
                    }
                    c_res = c_res[part];
                }
            } else {
                res[name] = value;
            }
        }
        return res;
    };

    $.fn.writeError = function(message){
        if (message.errors) {
            var errors = [];
            $.each(message.errors, function(i, e) {
                errors.push(e.text);
            });
            message = errors.join("<br />");
        }
        if ( ! (message instanceof Array) ) {
            message = [message];
        }
        return this.each(function(){
            var $this = $(this);
            $this.show();
            var errorHtml = "<div class=\"ui-widget\">";
            errorHtml+= "<div class=\"ui-state-error ui-corner-all\" style=\"padding: 0 .7em;\">";
            errorHtml += '<a class="fx_close">&times;</a>';
            errorHtml+= "<p>";
            errorHtml+= "<span class=\"ui-icon ui-icon-alert\" style=\"float:left; margin-right: .3em;\"></span>";
            errorHtml+= message.join('<br/>');
            errorHtml+= "</p>";
            errorHtml+= "</div>";
            errorHtml+= "</div>";
            
            $this.html(errorHtml);
            $('a.fx_close', this).click(function() {
                $this.hide();
            });
        });
    };

    $.fn.writeAlert = function(message){
        return this.each(function(){
            var $this = $(this);

            var alertHtml = "<div class=\"ui-widget\">";
            alertHtml+= "<div class=\"ui-state-highlight ui-corner-all\" style=\"padding: 0 .7em;\">";
            alertHtml+= "<p>";
            alertHtml+= "<span class=\"ui-icon ui-icon-info\" style=\"float:left; margin-right: .3em;\"></span>";
            alertHtml+= message;
            alertHtml+= "</p>";
            alertHtml+= "</div>";
            alertHtml+= "</div>";

            $this.html(alertHtml);
        });
    };


    $.fn.writeOk = function(message){
        return this.each(function(){
            var $this = $(this);

            var alertHtml = "<div class=\"ui-widget\">";
            alertHtml+= "<div class=\"ui-state-highlight ui-corner-all\" style=\"padding: 0 .7em;\"><p>";
            alertHtml+= message;
            alertHtml+= "</p></div></div>";
            $this.html(alertHtml);

            setTimeout(function(){
                $this.fadeOut('normal');
            }, 2000);
        });
    };
})($fxj);