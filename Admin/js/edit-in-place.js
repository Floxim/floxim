(function($){
$.fn.edit_in_place = function(command) {
    var $nodes = this;
    $nodes.each(function() {
        var $node = $(this);
        
        var eip = $node.data('edit_in_place');
        if (!eip || !eip.panel_fields.length) {
            eip = new fx_edit_in_place($node);
        } else {
            console.log('existing eip', eip);
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
    
    var selected_entity = this.node.closest('.fx_entity').get(0);

    $('html')
    .one('fx_deselect.edit_in_place', function(e) {
        eip.fix().stop();
        setTimeout(function() {
            var do_save = true;
            var selected = $fx.front.get_selected_item();
            if (selected) {
                var $selected = $(selected);
                var new_entity = $selected
                                    .closest('.fx_entity')
                                    .get(0);
                if (new_entity && new_entity === selected_entity) {
                    do_save = false;
                }
            }
            if (do_save) {
                eip.save();
            }
        }, 50);
    }).on('keydown.edit_in_place', function(e) {
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

    if ( (!this.is_content_editable || this.is_linker_placeholder) && this.panel_fields.length) {
        setTimeout(function() {
            if ($('.fx_selected .fx_var_editable').length === 0) {
                var selector = ':input:visible:not(.date_input'+(eip.is_linker_placeholder ? '' : ', .livesearch_input')+')';
                var $first_inp = $(selector, eip.panel_fields[0].parent()).first();
                if ($first_inp.length) {
                    $first_inp.focus();
                }
            }
        }, 50);
    }
}

fx_edit_in_place.vars = {};

fx_edit_in_place.prototype.handle_keydown = function(e) {
    if (e.which === 27) {
        if (e.isDefaultPrevented && e.isDefaultPrevented()) {
            return;
        }
        if ($('#redactor_modal:visible').length) {
            e.stopImmediatePropagation();
            return false;
        }
        this.stop();
        this.restore();
        $fx.front.deselect_item();
        return false;
    }
    if (e.which === 13) {
        var $target = $(e.target),
            $node = $target.closest('.fx_edit_in_place');
        if ($node.length) {
            var c_eip = $node.data('edit_in_place');
        } else {
            c_eip = this;
        }
        if (c_eip.is_wysiwyg) {
            return;
        }
        this.fix();
        this.save().stop();
        $(this.node).closest('a').blur();
        e.stopImmediatePropagation();
        return false;
    }
};

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
    this.node.trigger('fx_before_editing');
    
    switch (meta.type) {
        case 'datetime':
            this.add_panel_field(
                $.extend({}, meta, {
                    value: meta.real_value
                })
            );
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
                //edit_in_place.save().stop();
                edit_in_place.fix();
            });
            break;
        case 'select': case 'livesearch': case 'bool': case 'color': case 'map': case 'link':
            this.add_panel_field(meta);
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

fx_edit_in_place.prototype.start_content_editable = function(meta) {
    var $n = this.node;
    this.is_content_editable = true;
    if (!$($fx.front.get_selected_item()).hasClass('fx_entity')) {
        setTimeout(function() {
            $fx.front.stop_entities_sortable();
        }, 50);
    }
    if ($n.hasClass('fx_hidden_placeholded')) {
        $n.data('was_placeholded_by', this.node.html());
        $n.removeClass('fx_hidden_placeholded');
        $n.html('');
    }

    // create css stylesheet for placeholder color
    // we cannot just append styles to an element, 
    // because placeholder is implemented by css :before property
    var c_color = window.getComputedStyle($n[0]).color.replace(/[^0-9,]/g, '').split(',');
    var avg_color = (c_color[0]*1 + c_color[1]*1 + c_color[2]*1) / 3;
    avg_color = Math.round(avg_color);

    $("<style type='text/css' class='fx_placeholder_stylesheet'>\n"+
        ".fx_var_editable:empty:after, .fx_editable_empty:after {"+
            "color:rgb("+avg_color+","+avg_color+","+avg_color+") !important;"+
            "content:attr(fx_placeholder);"+
        "}"+
    "</style>").appendTo( $('head') );
    
    $n.addClass('fx_var_editable');
    $n.attr('fx_placeholder', meta.label || meta.name || meta.id);

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
            e.preventDefault();
            document.execCommand(
                'inserttext', 
                false,
                prompt('Paste your text here:')
            );
        });
    }
    
    var edit_in_place = this;
    var handle_node_size = function () {
        var text = $.trim($n.text());
        var is_empty = text.length === 0 || (text.length === 1 && text.charCodeAt(0) === 8203);
        if (is_empty && !edit_in_place.is_wysiwyg) {
            $n.html('&#8203;');
        }
        $n.toggleClass(
            'fx_editable_empty', 
            is_empty
        );
        if (is_empty && !edit_in_place.is_wysiwyg) {
            setTimeout(
                function() {$n.focus();},
                1
            );
        }
    }; 
    $n.attr('contenteditable', 'true').focus();
    this.$closest_button = $n.closest('button');
    if (this.$closest_button.length > 0) {
        this.$closest_button.on('click.edit_in_place', function() {return false;});
    }
    if (!this.is_wysiwyg) {
        handle_node_size();
        $n.on(
            'keyup.edit_in_place keydown.edit_in_place click.edit_in_place change.edit_in_place', 
            function () {setTimeout(handle_node_size,1);}
        );
    }
};

fx_edit_in_place.prototype.add_panel_field = function(meta) {
    if (meta.real_value) {
        meta.value = meta.real_value;
    }
    meta = $.extend({}, meta);
    
    if (meta.var_type === 'visual') {
        meta.name = meta.id;
    }
    if (!meta.type) {
        meta.type = 'string';
    }
    
    if (!meta.label) {
        meta.label = meta.id;
    }
    
    //var $field_container = $fx.front.get_node_panel();
    var $panel = $fx.front.node_panel.get(this.node).$panel;
    $panel.show();
    var npi = 'fx_node_panel__item';
    var $field_container = $(
        '<div class="'+npi+' '+npi+'-type-field '+npi+'-field_type-'+meta.type+' '+npi+'-field_name-'+meta.name+'"></div>');
    var $field_node = $fx_form.draw_field(meta, $field_container);
    $field_node.data('meta', meta);
    this.panel_fields.push($field_node);
    $panel.append($field_container);
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
            $('.livesearch', $c_field).data('livesearch').destroy();
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
    var was_placeholded_by = this.node.data('was_placeholded_by');
    
    if (was_placeholded_by && this.node.text().match(/^\s*$/)) {
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
    var $temp = $('<div contenteditable="true"></div>');
    $temp.html(v);
    v = $temp.html();
    $temp.remove();
    return v;
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
        var saved_val = $.trim(node.data('fx_saved_value'));
        var is_changed = false;
        if (this.is_wysiwyg) {
            var new_val = node.redactor('code.get');
            new_val = $.trim(new_val);
            var clear_new = this.clear_redactor_val(new_val);
            var clear_old = this.clear_redactor_val(saved_val);
            
            is_changed = clear_new !== clear_old;
        } else {
            var new_val = $.trim(node.text());
            
            function clear_spaces(line) {
                var clear_val = '';
                for (var j = 0; j < line.length; j++) {
                    if (line.charCodeAt(j) !== 8203) {
                        clear_val += line[j];
                    }
                }
                return clear_val;
            }
            
            new_val = clear_spaces(new_val);
            saved_val = clear_spaces(saved_val);
            
            // put empty val instead of zero-width space
            if (!new_val) {
                node.html('');
            }
            is_changed = new_val !== saved_val;
        }
        
        function dump_lines() {
            var res = [];
            for (var i = 0 ; i < arguments.length; i++) {
                var line = arguments[i],
                    line_res = [];
                for (var j = 0; j < line.length; j++){
                    line_res.push({
                       code:line.charCodeAt(j),
                       char:line[j]
                    });
                }
                res.push(line_res);
            }
            return res;
        }
        
        if (is_changed) {
            //console.log('push', dump_lines(saved_val, new_val));
            vars.push({
                'var':this.meta,
                'value':new_val
            });
        }
    }
    for (var i = 0; i < this.panel_fields.length; i++) {
        var pf = this.panel_fields[i];
        var pf_meta = pf.data('meta');
        if (!pf_meta) {
            console.log('no meta', this.panel_fields[i], this);
            continue;
        }
        var old_value = pf_meta.value;
        var $c_input = null;
        if (pf_meta.type === 'bool') {
            $c_input = $('input[name="'+pf_meta['name']+'"][type="checkbox"]', pf);
            var new_value = $c_input.is(':checked') ? "1" : "0";
            if (old_value === null) {
                old_value = '0';
            }
        } else if (pf_meta.type === 'livesearch') {
            var livesearch = $('.livesearch', pf).data('livesearch');
            
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
            $c_input = $(':input[name="'+pf_meta['name']+'"]', pf);
            var new_value = $c_input.val();
        }
        
        var value_changed = false;
        if (pf_meta.type === 'image' || pf_meta.type === 'file') {
            value_changed = new_value !== old_value.path;
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
            switch (pf_meta.target_type) {
                case 'var':
                    var formatted_value = new_value;
                    if (pf_meta.type === 'datetime' && pf_meta.format_modifier){
                        var timestamp = (new Date(new_value)).getTime() / 1000;
                        formatted_value = php_date_format(pf_meta.format_modifier, timestamp);
                    }
                    this.node.text(formatted_value);
                    this.node.data('fx_var', $.extend(
                        {},
                        pf_meta,
                        {real_value:new_value}
                    ));
                    break;
                case 'att':
                    if (pf_meta.type === 'image') {
                        var formatted_value = new_value;
                        var file_data = $c_input.data('fx_upload_response');
                        if (file_data && file_data.formatted_value) {
                            formatted_value = file_data.formatted_value;
                        }
                        if (!formatted_value) {
                            formatted_value = $fx.front.image_stub;
                        }
                        if (!pf_meta.att) {
                            var that = this;
                            setTimeout(function() {
                                that.fix();
                                that.save().stop();                            
                            },200);
                        } else {
                            var att_style = pf_meta.att.match(/style:(.+)$/);
                            if (att_style) {
                                this.node.css(att_style[1], formatted_value);
                            } else {
                                this.node.attr(pf_meta.att, formatted_value);
                            }
                        }
                    }
                    //fx_template_var
                    this.node.data( 
                        pf_meta.target_key,
                        $.extend(
                            {},
                            pf_meta,
                            {real_value:new_value}
                        )
                    );
                    break;
            }
            vars.push({
                'var': pf_meta,
                value:new_value
            });
        }
    }
    return vars;
};

fx_edit_in_place.prototype.fix = function() {
    if (this.stopped) {
        return this;
    }
    var vars = [];
    var $edited = $('.fx_edit_in_place');
    $edited.each(function() {
        var c_eip = $(this).data('edit_in_place');
        if (c_eip){
            $.each(c_eip.get_vars(), function(index, item) {
                vars.push(item);
            });
            //c_eip.stop();
        }
    });
    
    // nothing has changed
    if (vars.length === 0) {
        this.stop();
        this.restore();
        return this;
    }
    for (var i = 0; i < vars.length; i++) {
        var v = vars[i].var,
            hash = v.id+'.'+v.var_type+'.'+v.content_id+'.'+v.content_type_id;
        fx_edit_in_place.vars[hash] = vars[i];
    }
    return this;
};

fx_edit_in_place.prototype.save = function() {
    var vars = [];
    $.each(fx_edit_in_place.vars, function() {
        vars.push(this);
    });
    
    if (vars.length === 0) {
        return this;
    }
    var new_entity_props = null;
    var $adder_placeholder = $(this.node).closest('.fx_entity_adder_placeholder');
    if ($adder_placeholder.length > 0) {
        var entity_meta = $adder_placeholder.data('fx_entity_meta');
        new_entity_props = null;
        if (entity_meta) {
            if (entity_meta.placeholder_linker) {
                new_entity_props = entity_meta.placeholder_linker;
                if (entity_meta.placeholder.__move_before) {
                    new_entity_props.__move_before = entity_meta.placeholder.__move_before;
                } else if (entity_meta.placeholder.__move_after) {
                    new_entity_props.__move_after = entity_meta.placeholder.__move_after;
                }
            } else {
                new_entity_props = entity_meta.placeholder;
            }
        }
    }
    
    var post_data = {
        entity:'infoblock',
        action:'save_var',
        infoblock:this.ib_meta,
        vars: vars,
        fx_admin:true,
        page_id:$fx.front.get_page_id()
    };
    if (new_entity_props) {
        post_data.new_entity_props = new_entity_props;
    }
    
    var node = this.node,
        $infoblock = node.closest('.fx_infoblock');
    
    $fx.front.disable_infoblock($infoblock);
    
    $fx.post(
        post_data, 
        function() {
            $fx.front.reload_infoblock($infoblock[0]);
	}
    );
    
    fx_edit_in_place.vars = {};
    return this;
};

fx_edit_in_place.prototype.restore = function() {
    if (!this.is_content_editable || this.node.data('was_placeholded_by')) {
        return this;
    }
    var saved = this.node.data('fx_saved_value');
    this.node.html(saved);
    this.node.trigger('fx_editable_restored');
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
        range.detach();
    }
    if (!$node.attr('id')) {
        $node.attr('id', 'stub'+Math.round(Math.random()*1000));
    }
    var $panel = $fx.front.get_node_panel();
    $panel.append('<div class="editor_panel" />').show();
    var linebreaks = this.meta.var_type === 'visual';
    if (this.meta.linebreaks !== undefined) {
        linebreaks = !!this.meta.linebreaks;
    }
    var toolbar = this.meta.toolbar;
    if (!toolbar && this.node.closest('a, i, span, b, strong, em').length > 0) {
        toolbar = 'inline';
    }
    if (toolbar === 'inline') {
        linebreaks = true;
    }
    $fx_fields.make_redactor($node, {
        linebreaks:linebreaks,
        placeholder:false,
        toolbarPreset: toolbar,
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
            this.code.sync();
        }
    });
    this.source_area = $('textarea[name="'+ $node.attr('id')+'"]');
    this.source_area.addClass('fx_overlay');
    this.source_area.css({
        position:'relative',
        top:'0px',
        left:'0px'
    });
};

fx_edit_in_place.prototype.destroy_wysiwyg = function() {
    this.node.before(this.node.data('redactor_box'));
    this.node.redactor('core.destroy');
    $('#fx_admin_control .editor_panel').remove();
    this.node.get(0).normalize();
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

})($fxj);