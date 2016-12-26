(function($){


$.fn.edit_in_place = function(command) {
    var $nodes = this;
    if ($fx.front.is_frozen) {
        return;
    }
    $nodes.each(function() {
        var $node = $(this);
        
        var eip = $node.data('edit_in_place');
        if (!eip || !eip.panel_fields.length) {
            eip = new fx_edit_in_place($node);
        }
        if (!command) {
            return eip;
        }
        switch(command) {
            case 'destroy':
                eip.stop();
                break;
        }
    });
};

window.fx_eip = {
    vars: {},
    get_var_hash: function(meta) {
        return meta.id+'.'+meta.var_type+'.'+meta.content_id+'.'+meta.content_type_id;
    },
    get_vars: function() {
        var res = [];
        $.each(this.vars, function() {
            res.push(this);
        });
        return res;
    },
    fix: function() {
        var vars = this.get_edited_vars();
        for (var i = 0; i < vars.length; i++) {
            var hash = this.get_var_hash(vars[i].var);
            this.vars[hash] = vars[i];
        }
        return vars;
    },
    get_modified_vars: function()
    {
        var res = [];
        $.each(this.vars, function() {
            if (this.value !== this.var.computed_default_value) {
                res.push(this);
            }
        });
        return res;
    },
    get_values: function(entity_id) {
        var res = {};
        if (typeof entity_id === 'undefined') {
            entity_id = null;
        }
        $.each(this.vars, function() {
            if (this.var.content_id === entity_id) {
                res[this.var.name] = this.value;
            }
        });
        return res;
    },
    get_edited_nodes: function() {
        return $('.fx_edit_in_place');
    },
    get_edited_vars: function() {
        var vars = [],
            $edited = this.get_edited_nodes();
        
        $edited.each(function() {
            var c_eip = $(this).data('edit_in_place');
            if (c_eip){
                $.each(c_eip.get_vars(), function(index, item) {
                    vars.push(item);
                });
            }
        });
        return vars;
    },
    stop: function() {
        this.get_edited_nodes().each(function() {
            $(this).data('edit_in_place').stop();
        });
        this.vars = {};
    },
    append_value: function($node, meta, value, formatted_value) {
        if (!meta.target_type && meta.inatt) {
            meta.target_type = 'att';
        }
        switch (meta.target_type) {
            case 'var':
                
                formatted_value = value;
                if (meta.type === 'datetime' && meta.format_modifier){
                    var timestamp = $fx_fields.parse_std_date(value).getTime() / 1000;
                    formatted_value = fx_date_format(meta.format_modifier, timestamp);
                }
                
                $node.html(formatted_value);
                if (formatted_value === '') {
                    $node.addClass('fx_hidden_placeholded');
                    var placeholder = meta.label ? meta.label : meta.id;
                    if (meta.type === 'html' && !meta.linebreaks) {
                        placeholder = '<p>'+placeholder+'</p>';
                    }
                    $node.html(placeholder);
                } else {
                    $node.removeClass('fx_hidden_placeholded');
                }
                $node.data('fx_var', $.extend(
                    {},
                    meta,
                    {real_value:value}
                ));
                break;
            case 'att':
                if (meta.type === 'image') {
                    function append_image(v) {
                        if (meta.att) {
                            var att_style = meta.att.match(/style:(.+)$/);
                            if (att_style) {
                                if (att_style[1] === 'background-image') {
                                    $node.css('background-image', 'url("'+v+'")');
                                }
                            } else {
                                $node.attr(meta.att, v);
                            }
                        }
                    }
                    if (meta.format_modifier && !formatted_value) {
                        var post_data = {
                            entity:'file',
                            action:'get_image_meta',
                            file: value,
                            format: meta.format_modifier
                        };
                        $fx.post(post_data, function(res) {
                            append_image(res.formatted_value);
                        });
                    } else {
                        formatted_value = formatted_value || value;
                        append_image(formatted_value);
                    }
                } else if (meta.inatt === 'class') {
                    if (meta.type === 'icon') {
                        set_icon($node, value);
                    }
                } else {
                    $node.attr(meta.inatt, value);
                }
                //fx_template_var
                $node.data( 
                    meta.target_key,
                    $.extend(
                        {},
                        meta,
                        {real_value:value}
                    )
                );
                break;
        }
    },
    set_value: function(entity_type_id, entity_id, prop_name, prop_value) {
        for (var i = 0; i < this.nodes.length; i++) {
            var m = this.nodes[i][1];
            if (m.content_id === entity_id && m.content_type_id === entity_type_id && m.name === prop_name) {
                this.append_value(this.nodes[i][0], m, prop_value);
            }
        }
    },
    save: function(callback) {
        var vars = [],
            $node = null;
    
        this.fix();
        
        $.each(this.vars, function() {
            vars.push({
                'var':this.var,
                'value':this.value
            });
            $node = this.node;
        });
        
        if (vars.length === 0) {
            return vars;
        }
        var $infoblock = $node.closest('.fx_infoblock'),
            ib_meta = $infoblock.data('fx_infoblock');
    
        var post_data = {
            entity:'infoblock',
            action:'save_var',
            infoblock:ib_meta,
            vars: vars,
            fx_admin:true,
            page_id:$fx.front.get_page_id()
        };
        
        var preset_params = $infoblock.data('fx_preset_params');
        if (preset_params) {
            post_data.preset_params = preset_params;
        }
        
        var $adder_placeholder = $node.closest('.fx_entity_adder_placeholder'),
            entity_meta = $adder_placeholder.data('fx_entity_meta');
    
        if ($adder_placeholder.length > 0 && entity_meta) {
            if (entity_meta.placeholder_linker) {
                post_data.new_entity_props = entity_meta.placeholder_linker;
                if (entity_meta.placeholder.__move_before) {
                    post_data.new_entity_props.__move_before = entity_meta.placeholder.__move_before;
                } else if (entity_meta.placeholder.__move_after) {
                    post_data.new_entity_props.__move_after = entity_meta.placeholder.__move_after;
                }
                // user pressed "add new" button to create linked entity
                if (!$adder_placeholder.is('.fx_linker_placeholder')) {
                    post_data.create_linked_entity = entity_meta.placeholder.type;
                }
            } else {
                post_data.new_entity_props = entity_meta.placeholder;
            }
        }
        
        $fx.front.disable_infoblock($infoblock);
        
        if (!callback) {
            callback = function(res) {
                var ib_reload_data = {};
                if (res.real_infoblock_id) {
                    ib_reload_data.real_infoblock_id = res.real_infoblock_id;
                }
                $fx.front.reload_infoblock($infoblock[0], null, ib_reload_data);
            };
        }
        
        $fx.post(
            post_data, 
            function(res) {
                var $entity = $node.closest('.fx_entity');
                if (res.status === 'error') {
                    var $node_to_focus = $entity,
                        $editables = $('.fx_template_var', $entity);

                    if (res.errors && res.errors.length) {
                        $.each(res.errors, function() {
                            $fx.alert(this.error, 'error', 3);
                            if ($editables.length && this.field) {
                                var error_field = this.field;
                                $editables.each(function() {
                                    var $ce = $(this),
                                        c_meta = $ce.data('fx_var');
                                    if (c_meta && c_meta.name === error_field) {
                                        $node_to_focus = $ce;
                                    }
                                });
                            }
                        });
                    } else {
                        $fx.alert('Error!', 'error', 3);
                    }
                    $fx.front.enable_infoblock($infoblock[0]);
                    $fx.front.select_item($node_to_focus);
                    return;
                }
                if ($entity.data('fx_finish_form')) {
                    $fx.front_panel.hide();
                }
                fx_eip.stop();
                callback(res);
            }
        );
        return vars;
    },
    // this is called by click-out or Enter / Ctrl+Enter on text fields
    submit: function() {
        var $finish_form = $('.fx-top-panel_style_finish form:visible');
        if ($finish_form.length) {
            $finish_form.submit();
        } else {
            this.save();
        }
    },
    cancel: function() {
        var $edited = $('.fx_edit_in_place');
        $edited.each(function() {
            var c_eip = $(this).data('edit_in_place');
            c_eip.stop();
            c_eip.restore();
        });
        $fx.front.deselect_item();
    },
    nodes: [],
    collect_nodes: function($container) {
        var $var_nodes = $container.descendant_or_self('*[data-fx_var], *[data-has_var_in_att]'),
            that = this;

        $var_nodes.each(function() {
            var $node = $(this);
            var content_var = $node.data('fx_var');
            if (content_var) {
                content_var.target_type = 'var';
                that.nodes.push([$node, content_var]);
            }
            for( var i in $node.data()) {
                if (!/^fx_template_var/.test(i)) {
                    continue;
                }
                var meta = $node.data(i);
                meta.target_type = 'att';
                meta.target_key = i;
                that.nodes.push([$node, meta]);
            }
        });
    }
};

/*
 * @todo: more icon libs
 */
function set_icon($node, value) {
    var class_parts = value.split(' '),
        c_class = $node.attr('class').replace(/fa-[^\s]+/, '');
    
    if (!class_parts[1]) {
        c_class += ' fa-ban';
    } else {
        c_class += ' fa-'+class_parts[1];
    }
    $node.attr('class', c_class);
}

function fx_edit_in_place( node ) {
    this.node = node;
    
    node.data('edit_in_place', this);
    node.addClass('fx_edit_in_place');
        
    this.panel_fields = [];
    this.is_content_editable = false;
    
    this.ib_meta = node.closest('.fx_infoblock').data('fx_infoblock');
    
    var eip = this;
    
    // need to edit the contents of the site
    if (this.node.data('fx_var')) {
        this.meta = node.data('fx_var');
        this.meta.target_type = 'var';
        this.start(this.meta);
    }
    
    // edit the attributes of the node
    for( var i in this.node.data()) {
        if (!/^fx_template_var/.test(i)) {
            continue;
        }
        var meta = this.node.data(i);
        meta.target_type = 'att';
        meta.target_key = i;
        
        this.start(meta);
    }
    // edit fields from fx_controller_meta['field']
    var c_meta = this.node.data('fx_controller_meta');
    if (c_meta && c_meta.fields) {
        $.each(c_meta.fields, function(index, field) {
            field.target_type = 'controller_meta';
            field.target_key = index;
            eip.start(field);
        });
    }
    var $selected_entity = this.node.closest('.fx_entity');

    $(this.node)
    .closest('.fx_selected')
    .off('fx_deselect.edit_in_place')
    .one('fx_deselect.edit_in_place', function(e) {
        fx_eip.fix();
        eip.stop();
        
        if ($selected_entity[0] !== this) {
            $selected_entity.edit_in_place('destroy');
        }
        var c_vars = fx_eip.get_vars();
        
        setTimeout(function() {
            if (c_vars.length === 0 && !$selected_entity.is('.fx_entity_adder_placeholder')) {
                return;
            }
            var do_save = true;
            var selected = $fx.front.get_selected_item();
            if (selected) {
                var $selected = $(selected);
                var new_entity = $selected
                                    .closest('.fx_entity')
                                    .get(0);
                if (new_entity && new_entity === $selected_entity.get(0)) {
                    do_save = false;
                }
            }
            if (do_save) {
                fx_eip.submit();
            }
        }, 50);
    });
    
    $('html').on('keydown.edit_in_place', function(e) {
        return eip.handle_keydown(e);
    });

    this.is_linker_placeholder = false;
    var $placeholder = this.node.closest('.fx_entity_adder_placeholder');
    if ($placeholder) {
        var ph_meta = $placeholder.data('fx_entity_meta');
        if (ph_meta && ph_meta.placeholder_linker) {
            this.is_linker_placeholder = true;
        }
    }
}

fx_edit_in_place.prototype.start = function(meta) {
    var edit_in_place = this;
    if (!meta.type) {
        meta.type = 'string';
    }
    if (meta.type === 'link') {
        meta.type = 'livesearch';
    }
    if (meta.type === 'boolean' || meta.type === 'checkbox') {
        meta.type = 'bool';
    }
    if (!meta.name) {
        meta.name = meta.id;
    }
    this.node.trigger('fx_before_editing');
    
    var is_linker_selector = false;
    // skip "select entity" field if user is adding new entity
    if (meta.type === 'livesearch') {
        var entity_meta = this.node.data('fx_entity_meta') || {};
        if (
            entity_meta.placeholder_linker
            && entity_meta.placeholder_linker._link_field === meta.name
        ) {
            is_linker_selector = true;
        }
    }
    
    if (is_linker_selector && !this.node.is('.fx_linker_placeholder')) {
        return;
    }
    
    if (meta.initial_value === undefined) {
        if (meta.real_value !== undefined) {
            meta.initial_value = meta.real_value;
        } else if (meta.target_type === 'var') {
            if (this.node.is('.fx_hidden_placeholded')) {
                meta.initial_value = '';
            } else if ((meta.type === 'text' && meta.html && meta.html !== '0') || meta.type === 'html') {
                meta.initial_value = this.node.html();
            } else {
                meta.initial_value = this.node.text();
            }
        } else {
            meta.initial_value = meta.value;
        }
    }
    
    var stored_var = fx_eip.vars[fx_eip.get_var_hash(meta)],
        stored_value = stored_var ? stored_var.value : null;
    
    
    switch (meta.type) {
        case 'datetime':
            var $field = this.add_panel_field(
                $.extend({}, meta, {
                    value: meta.real_value
                })
            );
            var $date_inp = $('.date_input', $field);
            $date_inp.on('change', function() {
                //append_value: function($node, meta, value, formatted_value) {
                fx_eip.append_value(edit_in_place.node, meta, $date_inp.val());
            });
            break;
        case 'image': case 'file': 
            var field_meta = $.extend(
                {}, 
                meta, 
                {real_value:{path: meta.real_value || ''}}
            );
            this.add_panel_field(
                field_meta
            ).on('fx_change_file', function(e) {
                edit_in_place.fix(false);
                $(this).trigger('change');
                if (!meta.att || (!e.target.value && meta.initial_value) ) {
                    edit_in_place.save();
                }
            });
            break;
        case 'select': 
        case 'livesearch': 
        case 'bool': 
        case 'color': 
        case 'map': 
        case 'link': 
        case 'label':
        case 'icon':
            var field_meta =$.extend({}, meta);
            if (stored_value !== null) {
                field_meta.value = stored_value;
            }
            var $field = this.add_panel_field(field_meta);
            if (is_linker_selector) {
                $field.on('change', function(e) {
                    edit_in_place.fix().save().stop();
                });
            }
            if (meta.type === 'icon') {
                $field.on('change', function(e) {
                    set_icon(edit_in_place.node, e.target.value);
                });
            }
            break;
        case 'string': case 'html': case '': case 'text': case 'int': case 'float':
            if (meta.target_type === 'att') {
                this.add_panel_field(meta);
            } else {
                this.start_content_editable(meta);
            }
            break;
    }
};

fx_edit_in_place.prototype.handle_keydown = function(e) {
    if (e.which === 27) {
        if (e.isDefaultPrevented && e.isDefaultPrevented()) {
            return;
        }
        if ($('#redactor_modal:visible').length) {
            e.stopImmediatePropagation();
            return false;
        }
        fx_eip.cancel();
        return false;
    }
    if (e.which === 13) {
        var $target = $(e.target),
            $node = $target.closest('.fx_edit_in_place');
        if ($target.closest('.fx_admin_form').length > 0) {
            return;
        }
        if ($node.length) {
            var c_eip = $node.data('edit_in_place');
        } else {
            c_eip = this;
        }
        if (c_eip.is_wysiwyg) {
            return;
        }
        fx_eip.submit();
        $(this.node).closest('a').blur();
        e.stopImmediatePropagation();
        return false;
    }
};

var force_focus = function($n) {
    var selection = window.getSelection(),
        range = document.createRange();
    
    range.setStart($n[0], 0);
    range.collapse(true);
    selection.removeAllRanges();
    selection.addRange(range);
};

fx_edit_in_place.prototype.force_focus = force_focus;

window.force_focus = force_focus;

fx_edit_in_place.prototype.is_text_empty = function(text) {
    return text.length === 0 || (text.length === 1 && text.charCodeAt(0) === 8203);
};

fx_edit_in_place.prototype.start_content_editable = function(meta) {
    var $n = this.node;
    this.is_content_editable = true;
    
    
    if ($n.hasClass('fx_hidden_placeholded')) {
        $n.data('was_placeholded_by', this.node.html());
        $n.removeClass('fx_hidden_placeholded');
        $n.html('');
    }
    
    var $var_nodes = $('*[data-fx_var], *[data-has_var_in_att]'),
        c_node = $n[0],
        that = this;
    
    this.nodes_to_sync = [];
    
    $var_nodes.each(function() {
        if (this === c_node) {
            return;
        }
        var $node = $(this);
        var content_var = $node.data('fx_var');
        if (!content_var) {
            return;
        }
        if (
            content_var.var_type === meta.var_type && 
            (
                (
                    content_var.content_id && 
                    content_var.var_type === 'content' && 
                    content_var.content_id === meta.content_id &&
                    content_var.id === meta.id
                ) || (
                    content_var.var_type === 'visual' &&
                    content_var.id === meta.id &&
                    content_var.scope_path === meta.scope_path && 
                    $node.closest('.fx_infoblock').data('fx_infoblock').id === that.ib_meta.id
                )
            )
        ) {
            content_var.target_type = 'var';
            that.nodes_to_sync.push($node);
        }
    });

    // create css stylesheet for placeholder color
    // we cannot just append styles to an element, 
    // because placeholder is implemented by css :before property
    var c_color = window.getComputedStyle($n[0]).color.replace(/[^0-9,]/g, '').split(',');
    var avg_color = (c_color[0]*1 + c_color[1]*1 + c_color[2]*1) / 3;
    avg_color = Math.round(avg_color);

    $("<style type='text/css' class='fx_placeholder_stylesheet'>\n"+
        ".fx_var_editable:empty:after, .fx_editable_empty:after {"+
            "color:rgb("+avg_color+","+avg_color+","+avg_color+") !important;"+
            "content:attr(fx_placeholder) !important;"+
            "position:static !important;"+
        "}"+
        ".fx_editable_empty p {"+
            "position:absolute;"+
            "min-width:10px;"+
        "}"+
    "</style>").appendTo( $('head') );
   
    $n.addClass('fx_var_editable');
    $n.attr('fx_placeholder', meta.placeholder || meta.label || meta.name || meta.id);

    if ( (meta.type === 'text' && meta.html && meta.html !== '0') || meta.type === 'html') {
        $n.data('fx_saved_value', $n.html());
        this.is_wysiwyg = true;
        this.make_wysiwyg();
    } else {
        $n.data('fx_saved_value', $n.text());

        // do not allow paste html into non-html fields
        // this way seems to be ugly
        // @todo onkeydown solution or clear node contents after real paste
        $n.on('paste.edit_in_place', function(e) {
           $n.css('position', 'fixed');
           setTimeout(
                function() {
                    $n.html( $n.text() );
                    $n.css('position', '');
                },
                10
            );
        });
    }
    
    var edit_in_place = this;
    var handle_node_size = function () {
        var text = $.trim($n.text());
        var is_empty = edit_in_place.is_text_empty(text);
        if (is_empty && !edit_in_place.is_wysiwyg) {
            $n.html('&#8203;');
        }
        $n.toggleClass(
            'fx_editable_empty', 
            is_empty
        );
        if (is_empty && !edit_in_place.is_wysiwyg) {
            $n.focus();
            edit_in_place.force_focus($n);
        }
    }; 
    // force node to have size
    $n.addClass('fx_setting_focus');
    
    $n.attr('contenteditable', 'true').focus();
    
    if ($n.text().length === 0) {
        this.force_focus($n);
    }
    
    this.$closest_button = $n.closest('button');
    if (this.$closest_button.length > 0) {
        this.$closest_button.off('.edit_in_place');
        this.$closest_button.on('click.edit_in_place', function() {return false;});
        this.$closest_button.on('keydown.edit_in_place', function(e) {
            if (e.which === 32) { // space
                document.execCommand('insertText',  false, ' ');
                return false;
            }
        });
    }
    if (!this.is_wysiwyg || true) {
        handle_node_size();
        $n.on(
            //'keyup.edit_in_place keydown.edit_in_place click.edit_in_place change.edit_in_place', 
            'input.edit_in_place paste.edit_in_place',
            function () {setTimeout(handle_node_size,1);}
        );
    }
    $n.removeClass('fx_setting_focus');
    if (this.nodes_to_sync.length > 0) {
        $n.on(
            //'keyup.edit_in_place keydown.edit_in_place click.edit_in_place change.edit_in_place', 
            'input.edit_in_place paste.edit_in_place',
            function () {
                var c_html = fx_edit_in_place.prototype.clear_spaces($n.html());
                $.each(that.nodes_to_sync, function() {
                    var $node_to_sync = $(this);
                    fx_eip.append_value($node_to_sync, $node_to_sync.data('fx_var'), c_html);
                });
            }
        );
    }
};

fx_edit_in_place.prototype.add_panel_field = function(meta) {
    if (meta.real_value && meta.type !== 'livesearch') {
        meta.value = meta.real_value;
    }
    meta = $.extend({}, meta);
    
    if (meta.var_type === 'visual') {
        meta.name = meta.id;
    }
    if (!meta.type) {
        meta.type = 'string';
    }
    
    if (meta.type === 'icon') {
        meta.type = 'iconpicker';
    }
    
    if (!meta.label) {
        meta.label = meta.id;
    }
    
    if (meta.type === 'livesearch' && !meta.params) {
        meta.params = {
            'content_type':meta['content_type']
        };
        if (!meta.value) {
            meta.value = meta.real_value;
            meta.ajax_preload = true;
        }
    }
    
    var $finish_form = this.node.data('fx_finish_form'),
        $field_container = null;
    if ($finish_form) {
        //$field_container = $finish_form.find('.fx_admin_form__body .fx_eip_fields_container');
        $field_container = $finish_form.find('.fx_admin_form__body');
    } else {
        var $panel = $fx.front.node_panel.get(this.node).$panel,
            npi = 'fx_node_panel__item';
        
        $field_container = $(
            '<div class="'+npi+' '+npi+'-type-field '+npi+'-field_type-'+meta.type+' '+npi+'-field_name-'+meta.name+'"></div>'
        );
        $panel.append($field_container);
        $panel.show();
    }
    
    
    var $field_node = $fx_form.draw_field(meta, $field_container);
    $field_node.data('meta', meta);
    this.panel_fields.push($field_node);
    // add conditions
    if (meta.parent) {
        $fx.form.add_parent_condition(meta.parent, $field_node, $field_container);
    }
    // use last() to find real checkbox instead of hidden helper
    var $value_inp = $field_node.descendant_or_self(':input[name="'+meta.name+'"]').last(),
        default_value = $value_inp.val();
    
    if ($value_inp.attr('type') === 'checkbox') {
        default_value = $value_inp.attr('checked') ? '1' : '0';
    }
    
    meta.computed_default_value = default_value;
    return $field_node;
};

fx_edit_in_place.prototype.stop = function() {
    this.node.data('edit_in_place', null);
    this.node.removeClass('fx_edit_in_place').removeClass('fx_editable_empty');
    if (this.stopped) {
        return this;
    }
    for (var i =0 ;i<this.panel_fields.length; i++) {
        var $c_field = this.panel_fields[i];
        if ($c_field.is('.field_livesearch')) {
            var livesearch = $('.livesearch', $c_field).data('livesearch');
            if (livesearch) {
                livesearch.destroy();
            }
        }
        this.panel_fields[i].remove();
    }
    this.panel_fields = [];
    
    this.node.attr('contenteditable', null);
    
    $('.fx_var_editable', this.node).attr('contenteditable', null);
    
    this.node.removeClass('fx_var_editable');
    if (this.is_content_editable && this.is_wysiwyg) {
        this.destroy_wysiwyg();
    }
    $('*').off('.edit_in_place');
    this.node.blur();
    this.stopped = true;
    if (!window.stopped) {
        window.stopped = [];
    }
    window.stopped.push(this);
    var was_placeholded_by = this.node.data('was_placeholded_by') || this.node.attr('fx_placeholder'),
        c_text = this.node.text();
    
    if (was_placeholded_by && this.is_text_empty(c_text)) {
        this.node.addClass('fx_hidden_placeholded').html(was_placeholded_by);
    }
    $('head .fx_placeholder_stylesheet').remove();
    $('#ui-datepicker-div').remove();
    return this;
};

/**
 * Clear extra \n after block level tags inserted by Redactor 
 * see method cleanParagraphy() in Redactor's source code
 */
fx_edit_in_place.prototype.clear_redactor_val = function (v) {
    // pre removed
    var r_blocks = '(comment|html|body|head|title|meta|style|script|link|iframe|table|thead|tfoot|caption|col|colgroup|tbody|tr|td|th|div|dl|dd|dt|ul|ol|li|select|option|form|map|area|blockquote|address|math|style|p|h[1-6]|hr|fieldset|legend|section|article|aside|hgroup|header|footer|nav|figure|figcaption|details|menu|summary)';
    var rex = new RegExp('[\\s\\t\\n\\r]*(</?'+r_blocks+'[^>]*?>)[\\s\\t\\n\\r]*', 'ig');
    v = v.replace(rex, '$1');
    /*
    var $temp = $('<div contenteditable="true"></div>');
    $temp.html(v);
    v = $temp.html();
    $temp.remove();
    */
    return v;
};

fx_edit_in_place.prototype.clear_spaces = function(line) {
    var clear_val = '';
    for (var j = 0; j < line.length; j++) {
        if (line.charCodeAt(j) !== 8203) {
            clear_val += line[j];
        }
    }
    return clear_val;
};

window.show_spaces = function() {
    for (var j = 0; j < arguments.length; j++) {
        var s = arguments[j];
        var res = '';
        for (var i = 0; i < s.length; i++) {
            var ch = s[i];
            if (ch.match(/\s/)) {
                res += '['+ch.charCodeAt(0)+']';
            } else {
                res += ch;
            }
        }
        console.log(res);
    }
};

fx_edit_in_place.prototype.get_vars = function() {
    var node = this.node;
    var vars = [];
    // edit the text node
    var is_content_editable = this.is_content_editable;
    if (is_content_editable) {
        if (this.is_wysiwyg) {
            if (this.source_area.is(':visible')) {
                this.node.redactor('toggle');
            }
            $('.fx_internal_block', this.node).trigger('fx_stop_editing');
        }
        //var saved_val = $.trim(node.data('fx_saved_value'));
        var saved_val = $.trim(this.meta.initial_value);
        var is_changed = false;
        if (this.is_wysiwyg) {
            var new_val = node.redactor('code.get');
            //new_val = $.trim(new_val);
            var clear_new = this.clear_redactor_val(new_val);
            var clear_old = this.clear_redactor_val(saved_val);
            
            is_changed = clear_new !== clear_old;
        } else {
            var new_val = $.trim(node.text());
            
            
            new_val = this.clear_spaces(new_val);
            saved_val = this.clear_spaces(saved_val);
            
            // put empty val instead of zero-width space
            if (!new_val) {
                node.html('');
            }
            is_changed = new_val !== saved_val;
            /*
            if (is_changed) {
                show_spaces(saved_val, new_val);
            }
            */
        }

        if (is_changed) {
            fx_eip.append_value(node, this.meta, new_val);
            vars.push({
                'var':this.meta,
                'value':new_val,
                node:node
            });
        }
    }
    for (var i = 0; i < this.panel_fields.length; i++) {
        var $pf = this.panel_fields[i],
            pf_meta = $pf.data('meta'),
            formatted_value = null;
        
        if (!pf_meta) {
            continue;
        }
        var old_value = pf_meta.value;
        var $c_input = null;
        if (pf_meta.type === 'bool' || pf_meta.type === 'checkbox') {
            $c_input = $('input[name="'+pf_meta['name']+'"][type="checkbox"]', $pf);
            var new_value = $c_input.is(':checked') ? "1" : "0";
            if (old_value === null) {
                old_value = '0';
            } else {
                old_value = old_value+'';
            }
        } else if (pf_meta.type === 'livesearch') {
            var livesearch = $('.livesearch', $pf).data('livesearch');
            
            if (livesearch.isMultiple) {   
                var new_value = livesearch.getValues();
                // if the loaded value contained full objects (with name and id) 
                // let's convert it to the same format as new value has - plain array of ids
                // we copy old value
                if (old_value instanceof Array) {
                    var old_copy = [];
                    for (var old_index = 0; old_index < old_value.length; old_index++) {
                        var old_item = old_value[old_index];
                        if (typeof old_item === 'object') {
                            old_copy[old_index] = old_item.id;
                        } else {
                            old_copy[old_index] = old_item;
                        }
                    }
                    old_value = old_copy;
                }
            } else {
                var new_value = livesearch.getValue();
                
                if (typeof(new_value) === 'string' && !new_value.match(/^\d+$/)) { 
                    new_value = null;
                }
                
                if (new_value !== null) {
                     new_value = new_value * 1;
                }
                
                if (old_value && old_value.id) {
                    old_value = old_value.id * 1;
                }
            }
        } else {
            $c_input = $pf.descendant_or_self(':input[name="'+pf_meta['name']+'"]');
            var new_value = $c_input.val();
        }
        
        var value_changed = false;
        if (pf_meta.type === 'image' || pf_meta.type === 'file') {
            value_changed = new_value !== old_value.path;
            
            var file_data = $c_input.data('fx_upload_response');
            
            if (new_value && file_data && file_data.formatted_value) {
                formatted_value = file_data.formatted_value;
            }
            
            if (file_data && file_data.action === "save_image_meta") {
                value_changed = true;
            }
            if (!old_value.path) {
                var new_meta = $.extend({}, pf_meta, {value:{path:new_value}});
                $pf.data('meta', new_meta);
            }
        } else if (new_value instanceof Array && old_value instanceof Array) {
            value_changed = new_value.join(',') !== old_value.join(',');
        } else {
            if (pf_meta.type !== 'boolean' && (old_value === undefined || old_value === null) && new_value === '') {
                value_changed = false;
            } else {
                value_changed = new_value !== old_value;
            }
        }
        if (value_changed) {
            fx_eip.append_value(this.node, pf_meta, new_value, formatted_value);
            vars.push({
                'var': pf_meta,
                value:new_value,
                node:node
            });
        }
    }
    return vars;
};

fx_edit_in_place.prototype.fix = function(stop_on_empty) {
    if (typeof stop_on_empty === 'undefined') {
        stop_on_empty = true;
    }
    if (this.stopped) {
        return this;
    }
    
    var vars = fx_eip.fix();
    
    // nothing has changed
    if (vars.length === 0 && stop_on_empty) {
        this.stop();
        //this.restore();
        return this;
    }
    return this;
};

fx_edit_in_place.prototype.save = function() {
    fx_eip.save();
    return this;
};

fx_edit_in_place.prototype.restore = function() {
    if (!this.is_content_editable) {// || this.node.data('was_placeholded_by')) {
        return this;
    }
    var saved = this.node.data('fx_saved_value');
    //this.node.html(saved);
    fx_eip.append_value(this.node, this.node.data('fx_var'), saved);
    this.node.trigger('fx_editable_restored');
    if (this.nodes_to_sync) {
        for(var i = 0; i< this.nodes_to_sync.length; i++) {
            var $n = $(this.nodes_to_sync[i]);
            fx_eip.append_value($n, $n.data('fx_var'), saved);
        }
    }
    fx_eip.vars = {};
    return this;
};

fx_edit_in_place.prototype.make_wysiwyg = function () {
    var sel = window.getSelection(),
        $node = this.node,
        node = $node[0],
        eip = this;
    
    $node.on('keydown.edit_in_place', function(e) {
        if (e.which === 13 && e.ctrlKey) {
            eip.fix();
            eip.save().stop();
            e.stopImmediatePropagation();
            return false;
        }
    });
    
    if (sel && $.contains(node, sel.focusNode)) {
        var range = sel.getRangeAt(0);
        range.collapse(true);
        var click_range_offset = range.startOffset,
            $range_text_node = $(range.startContainer),
            c_text = $range_text_node[0],
            range_text_position = 0;
        while (c_text.previousSibling){
            c_text = c_text.previousSibling;
            range_text_position++;
        };
        $range_text_node.parent().addClass('fx_click_range_marker');
        //range.detach();
    }
    if (!$node.attr('id')) {
        $node.attr('id', 'stub'+Math.round(Math.random()*1000));
    }
    var $panel = $fx.front.get_node_panel();
    if ($panel) {
    	$panel.append('<div class="editor_panel fx_node_panel__item" />').show();
    }
    var linebreaks = this.meta.var_type === 'visual';
    if (this.meta.linebreaks !== undefined) {
        linebreaks = !!this.meta.linebreaks;
    }
    var toolbar = this.meta.toolbar;
    if (!toolbar && this.node.closest('a, i, span, b, strong, em').length > 0) {
        toolbar = 'inline';
    }
    if (toolbar === 'inline' && this.meta.linebreaks === undefined) {
        linebreaks = true;
    }
    
    $fx_fields.make_redactor($node, {
        linebreaks:linebreaks,
        placeholder:false,
        toolbarPreset: toolbar,
        source:false,
        toolbarExternal: '.editor_panel',
        initCallback: function() {
            var $box = $node.closest('.redactor-box');
            $box.after($node);
            $('body').append($box);
            $node.data('redactor_box', $box);
            
            var $range_node = $node.parent().find('.fx_click_range_marker');
            if ($range_node.length) {
                var range_text = $range_node[0].childNodes[range_text_position];
                if (!range_text && $range_node[0].childNodes.length > 0) {
                    range_text = $range_node[0].childNodes[0];
                    click_range_offset = 0;
                }
                if (range_text && range_text.nodeType === 3) {
                    var selection = window.getSelection(),
                        range = document.createRange();
                    if (click_range_offset > range_text.length) {
                        click_range_offset = range_text.length;
                    }
                    range.setStart(range_text, click_range_offset);
                    range.collapse(true);
                    selection.removeAllRanges();
                    selection.addRange(range);
                }
                $range_node.removeClass('fx_click_range_marker');
                if ($range_node.attr('class') === '') {
                    $range_node.attr('class', null);
                }
            }
            
            //this.code.sync();
            
            // use 'startSync' instead of 'sync' because 'sync' adds 10ms timeout, 
            // so if we call redactor('core.destroy') immediately it will clear the node html
            this.code.startSync();
        }
    });
    this.source_area = $('textarea[name="'+ $node.attr('id')+'"]');
    this.source_area.addClass('fx_overlay');
    this.source_area.css({
        position:'relative',
        top:'0px',
        left:'0px'
    });
    
    if ($node.is('.fx_var_bound_to_entity')) {
        this.$owner_entity = $node.closest('.fx_entity');
        this.$owner_entity.on('mousedown.eip', function(e) {
            if ( $(e.target).closest('.fx_var_editable').length === 0) {
                return false;
            }
        });
    }
};

fx_edit_in_place.prototype.destroy_wysiwyg = function() {
    this.node.before(this.node.data('redactor_box'));
    
    this.node.redactor('core.destroy');
    
    $('#fx_admin_control .editor_panel').remove();
    this.node.get(0).normalize();
    if (this.$owner_entity) {
        this.$owner_entity.off('.eip');
        delete this.$owner_entity;
    }
};

$(function() {
    for (var i = 0; i < document.styleSheets.length; i++) {
        var sheet = document.styleSheets[i];
        try {
            if (!sheet.cssRules) {
                continue;
            }
        } catch (e) {
            continue;
        }
        
        for (var j = 0; j < sheet.cssRules.length; j++) {
            var rule = sheet.cssRules[j];
            if (rule.type !== 1 || !rule.cssText) {
                continue;
            }
            if (rule.selectorText.match(/\.redactor\-editor/)) {
                var new_css = rule.cssText.replace(/\.redactor\-editor/g, '.redactor_fx_wysiwyg');
                sheet.deleteRule(j);
                sheet.insertRule(
                    new_css,
                    j
                );
            } else if ( rule.selectorText === '.redactor\-box') {
                sheet.deleteRule(j);
            }
        }
    }
});

var smart_date_format = function(format, timestamp) {
    var ru_month = function(date, placeholder) {

        var parts = placeholder.split(/\:/);
        var names = $fx.lang(parts[1] === 'gen' ? 'months_gen' : 'months');
        var month_num = php_date_format('m', date) * 1;
        var month_name = names[month_num];
        if ( parts[0][0].toUpperCase() === parts[0][0]) {
            month_name = month_name[0].slice(0,1).toUpperCase() + month_name.slice(1);
        }
        return month_name;
    };
    var parts = format.split(/(\%.+?\%)/),
        res = [];
    for (var i = 0; i< parts.length; i++) {
        var part = parts[i];
        var placeholder = part.match(/\%(.+)\%/);
        if (!placeholder) {
            res.push(php_date_format(part, timestamp));
            continue;
        }
        placeholder = placeholder[1];
        var chunk = '';
        switch (placeholder) {
            case 'month:gen':
            case 'Month:gen':
            case 'month':
            case 'Month':
                chunk = ru_month(timestamp, placeholder);
                break;
        }
        res.push(chunk);
    }
    return res.join('');
};

var fx_date_format = function(format, timestamp) {
    if (format.indexOf('%') === -1) {
        return php_date_format(format, timestamp);
    }
    return smart_date_format(format, timestamp);
};

var php_date_format = function ( format, timestamp ) {	// Format a local time/date
    // 
    // +   original by: Carlos R. L. Rodrigues
    // +	  parts by: Peter-Paul Koch (http://www.quirksmode.org/js/beat.html)
    // +   improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
    // +   improved by: MeEtc (http://yass.meetcweb.com)
    // +   improved by: Brad Touesnard

    var a, jsdate = new Date(timestamp ? timestamp * 1000 : null);
    var pad = function(n, c){
            if( (n = n + "").length < c ) {
                    return new Array(++c - n.length).join("0") + n;
            } else {
                    return n;
            }
    };
    var txt_weekdays = ["Sunday","Monday","Tuesday","Wednesday",
            "Thursday","Friday","Saturday"];
    var txt_ordin = {1:"st",2:"nd",3:"rd",21:"st",22:"nd",23:"rd",31:"st"};
    var txt_months =  ["", "January", "February", "March", "April",
            "May", "June", "July", "August", "September", "October", "November",
            "December"];

    var f = {
            // Day
                    d: function(){
                            return pad(f.j(), 2);
                    },
                    D: function(){
                            t = f.l(); return t.substr(0,3);
                    },
                    j: function(){
                            return jsdate.getDate();
                    },
                    l: function(){
                            return txt_weekdays[f.w()];
                    },
                    N: function(){
                            return f.w() + 1;
                    },
                    S: function(){
                            return txt_ordin[f.j()] ? txt_ordin[f.j()] : 'th';
                    },
                    w: function(){
                            return jsdate.getDay();
                    },
                    z: function(){
                            return (jsdate - new Date(jsdate.getFullYear() + "/1/1")) / 864e5 >> 0;
                    },

            // Week
                    W: function(){
                            var a = f.z(), b = 364 + f.L() - a;
                            var nd2, nd = (new Date(jsdate.getFullYear() + "/1/1").getDay() || 7) - 1;

                            if(b <= 2 && ((jsdate.getDay() || 7) - 1) <= 2 - b){
                                    return 1;
                            } else{

                                    if(a <= 2 && nd >= 4 && a >= (6 - nd)){
                                            nd2 = new Date(jsdate.getFullYear() - 1 + "/12/31");
                                            return date("W", Math.round(nd2.getTime()/1000));
                                    } else{
                                            return (1 + (nd <= 3 ? ((a + nd) / 7) : (a - (7 - nd)) / 7) >> 0);
                                    }
                            }
                    },

            // Month
                    F: function(){
                            return txt_months[f.n()];
                    },
                    m: function(){
                            return pad(f.n(), 2);
                    },
                    M: function(){
                            t = f.F(); return t.substr(0,3);
                    },
                    n: function(){
                            return jsdate.getMonth() + 1;
                    },
                    t: function(){
                            var n;
                            if( (n = jsdate.getMonth() + 1) == 2 ){
                                    return 28 + f.L();
                            } else{
                                    if( n & 1 && n < 8 || !(n & 1) && n > 7 ){
                                            return 31;
                                    } else{
                                            return 30;
                                    }
                            }
                    },

            // Year
                    L: function(){
                            var y = f.Y();
                            return (!(y & 3) && (y % 1e2 || !(y % 4e2))) ? 1 : 0;
                    },
                    //o not supported yet
                    Y: function(){
                            return jsdate.getFullYear();
                    },
                    y: function(){
                            return (jsdate.getFullYear() + "").slice(2);
                    },

            // Time
                    a: function(){
                            return jsdate.getHours() > 11 ? "pm" : "am";
                    },
                    A: function(){
                            return f.a().toUpperCase();
                    },
                    B: function(){
                            // peter paul koch:
                            var off = (jsdate.getTimezoneOffset() + 60)*60;
                            var theSeconds = (jsdate.getHours() * 3600) +
                                                             (jsdate.getMinutes() * 60) +
                                                              jsdate.getSeconds() + off;
                            var beat = Math.floor(theSeconds/86.4);
                            if (beat > 1000) beat -= 1000;
                            if (beat < 0) beat += 1000;
                            if ((String(beat)).length == 1) beat = "00"+beat;
                            if ((String(beat)).length == 2) beat = "0"+beat;
                            return beat;
                    },
                    g: function(){
                            return jsdate.getHours() % 12 || 12;
                    },
                    G: function(){
                            return jsdate.getHours();
                    },
                    h: function(){
                            return pad(f.g(), 2);
                    },
                    H: function(){
                            return pad(jsdate.getHours(), 2);
                    },
                    i: function(){
                            return pad(jsdate.getMinutes(), 2);
                    },
                    s: function(){
                            return pad(jsdate.getSeconds(), 2);
                    },
                    //u not supported yet

            // Timezone
                    //e not supported yet
                    //I not supported yet
                    O: function(){
                       var t = pad(Math.abs(jsdate.getTimezoneOffset()/60*100), 4);
                       if (jsdate.getTimezoneOffset() > 0) t = "-" + t; else t = "+" + t;
                       return t;
                    },
                    P: function(){
                            var O = f.O();
                            return (O.substr(0, 3) + ":" + O.substr(3, 2));
                    },
                    //T not supported yet
                    //Z not supported yet

            // Full Date/Time
                    c: function(){
                            return f.Y() + "-" + f.m() + "-" + f.d() + "T" + f.h() + ":" + f.i() + ":" + f.s() + f.P();
                    },
                    //r not supported yet
                    U: function(){
                            return Math.round(jsdate.getTime()/1000);
                    }
    };

    return format.replace(/[\\]?([a-zA-Z])/g, function(t, s){
            if( t!=s ){
                    // escaped
                    ret = s;
            } else if( f[s] ){
                    // a date function exists
                    ret = f[s]();
            } else{
                    // nothing special
                    ret = s;
            }

            return ret;
    });
};
window.dumpSelection = function(name) {
    var r = window.getSelection().getRangeAt(0),
        rc = r.startContainer,
        rcp = r.startContainer.parentNode;
    console.group(name || 'selection', rcp);
    for (var jj = 0; jj < rcp.childNodes.length; jj++)  {
        var cn = rcp.childNodes[jj];
        if (cn === rc) {
            console.log('>>', cn.data ? '~'+cn.data+'~' :  cn, r.startOffset)
        } else {
            console.log(cn.data ? '~'+cn.data+'~' : cn);
        }
    }
    console.groupEnd();
};

window.dumpNode = function(node, title) {
	var r = window.getSelection().getRangeAt(0),
        rc = r.startContainer;
        
	var indom = ' ('+$(node).closest('body') ? 'indom' : 'outofdom'+')';
	console.group(node, title ? title : '', rc, r.startOffset, r.endOffset);
	for (var jj = 0; jj < node.childNodes.length; jj++)  {
        var cn = node.childNodes[jj];
        if (cn === rc) {
            console.log('>>', cn.data ? '~'+cn.data+'~' :  cn, r.startOffset)
        } else {
            console.log(cn.data ? '~'+cn.data+'~' : cn);
        }
    }
    console.groupEnd();
}
})($fxj);