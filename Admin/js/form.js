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
        
        $form.off('submit.fx_submit').on('submit.fx_submit', onsubmit);

        return $form;
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
        $.each(settings.fields, function(i, json) {
            var $target = $form_body;
            if (json.group) {
                $target = $('.fx-field-group_keyword_'+json.group+' .fx-field-group__fields', $form_body);
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
            $fx_form.draw_field(json, $target);
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
            } else {
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
        
        $form.ajaxSubmit(function ( data ) {
            $fx.form.unlock_form($form);
            if (typeof data === 'string') {
                try {
                    data = $.parseJSON( data );
                } catch(e) {
                    $fx.alert('Responce parse error');
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
                        $fx.alert(this.error, 'error', 3);
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
                $fx.reload(data.reload);
            } else if (data.show_result) {
                $fx.admin.load_page(data);
            } else {
                $(window).hashchange();
            }
        });
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
                $node.addClass(c_class+'-active');
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

    draw_field: function(json, target, position) {
        position = position || 'into';
        var $rel_node = null;
        if (position !== 'into') {
            $rel_node = target;
            target = $rel_node.parent();
        }
        if (json.type === undefined) {
            json.type = 'input';
        }
        json.type = json.type.replace(/^field_/, '');
        var type='';
        switch(json.type) {
            case 'hidden': case 'string': case 'short': case 'medium': case 'long': case 'int':
                type = 'input';
                break;
            case 'textarea': case 'text':
                type = 'textarea';      
                break;
            default:
                type = json.type;
                break;
        }
        var field_method = $fx_fields[type];
        if (typeof field_method !== 'function') {
            field_method = $fx_fields.default;
        }
        var node = field_method(json);
        switch (position) {
            case 'into':
                target.append(node);
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
            var post_container = $('<div class="container"></div>').appendTo(target);
            
            node.on('change', function(){
                var form_vals = {};
                $('input, textarea, select', node.closest('form')).each(function(){
                    var c_field_name = $(this).attr('name');
                    var c_field_type = $(this).attr('type');
                    if (c_field_name !== 'posting' && c_field_type !== 'button') {
                        var val;
                        if (c_field_type === 'radio') {
                            val = $('input[name="'+$(this).attr('name')+'"]:checked').val();
                        } else {
                            val = $(this).val();
                        }
                        form_vals[c_field_name] = val;
                    }
                });
                var data_to_post = $.extend({}, form_vals, json.post);
                $fx.post(data_to_post, function(fields){
                    post_container.html('');
                    $fx_form.draw_fields(fields, post_container);
                });
            });
            node.trigger('change');
        }
        if (json.parent) {
            this.add_parent_condition(json.parent, node, target);
        } else if (json.type === 'joined_group') {
            $.each(json.fields, function() {
                if (this.parent && this.$input) {
                    $fx_form.add_parent_condition(this.parent, this.$input, target);
                }
            });
        }
        return node;
    },

    add_parent_condition: function(parent, _el, container) {
        if (parent instanceof Array) {
            parent = {};
            parent[parent[0]] = parent[1];
        } else if (typeof parent === 'string') {
            var obj = {};
            obj[parent] = '!=0';
            parent = obj;
        }
       
        var check_parent_state = function() {
            var do_show = true;
            $.each(parent, function(pkey, pval) {
                // convert checked value to string
                // because input value is always returned as a string
                pval = pval+'';
                var pexp = '==';
                if (/^!=/.test(pval)) {
                    pval = pval.replace(/^!=/, '');
                    pexp = '!=';
                } else if (/^\~/.test(pval)) {
                    pval = pval.replace(/^\~/, '');
                    pexp = 'regexp';
                } else if (/^\!\~/.test(pval)) {
                    pval = pval.replace(/^\!\~/, '');
                    pexp = 'not_regexp';
                }
                var par_inp = $(':input[name="'+pkey+'"]', container);
                if (par_inp.length === 0) {
                    return;
                }
                
                // checkbox & hidden couple
                if (par_inp.length > 1) {
                    var $par_chb = par_inp.filter('input[type="checkbox"]');
                    if ($par_chb.length) {
                        par_inp = $par_chb;
                    }
                }

                if (par_inp.attr('type') === 'checkbox') {
                    var par_val = par_inp.get(0).checked * 1;
                } else {
                    var par_val = par_inp.val();
                }
                
                if (par_inp.attr('type') === 'radio') {
                    par_val = $(':input[name="'+pkey+'"]:checked').val();
                }
                switch (pexp) {
                    case '==':
                        do_show = par_val === pval;
                        // check parent visibility
                        // jquery 'is visible' magic doesn't work with input[type=hidden]
                        // so we invent our own magic!
                        if (do_show) {
                            var $inp_field_block = par_inp.closest('.field');
                            if ($inp_field_block.length) {
                                do_show = $inp_field_block.css('display') !== 'none';
                            }
                        }
                        break;
                    case '!=':
                        if (
                            par_inp.css('display') === 'none' ||
                            par_inp.closest('.field').css('display') === 'none'
                            ) {
                            do_show = true;
                        } else {
                            if (typeof pval === 'string' && pval.match(/^[0-9]+$/)) {
                                pval = pval*1;
                            }
                            do_show = (par_val !== pval);
                        }
                        break;
                    case 'regexp':
                        var prex = new RegExp(pval);
                        do_show = prex.test(par_val);
                        break;
                    case 'not_regexp':
                        var prex = new RegExp(pval);
                        do_show = !prex.test(par_val);
                        break;
                }
                if (!do_show) {
                    return false;
                }
            });
            var is_visible = _el.is(':visible');
            var $el_inp =  _el.find(':input');
            
            if (do_show && !is_visible) {
                _el.show();
                $el_inp.trigger('change').trigger('fx_show_input');
            } else if (!do_show && is_visible) {
                _el.hide();
                $el_inp.trigger('change').trigger('fx_hide_input');
            }
        };
        _el.hide();
        var parent_selector = [];
        $.each(parent, function(pkey, pval) {
            parent_selector.push(':input[name="'+pkey+'"]');
        });
        parent_selector = parent_selector.join(', ', parent_selector);

        $(container).on('change', parent_selector, check_parent_state);

        setTimeout(function() {
            check_parent_state.apply($(parent_selector).get(0));
        }, 1);
    }
};
})(jQuery);

$fx.form = window.fx_form = window.$fx_form = fx_form;

(function($) {
    $.fn.fx_create_form = function(options) {
        $fx_form.create(options, this);
        return this;
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