(function($){
$.fn.edit_in_place = function(command) {
    var $nodes = this;
    $nodes.each(function() {
        var $node = $(this);
        var eip = $node.data('edit_in_place');
        if (!eip) {
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

function fx_edit_in_place( node ) { 
    this.node = node;
    
    node.data('edit_in_place', this);
    node.addClass('fx_edit_in_place');
    
    this.panel_fields = [];
    this.is_content_editable = false;
    
    this.ib_meta = node.closest('.fx_infoblock').data('fx_infoblock');
    
    var eip = this;
    
    $('html').on('keydown.edit_in_place', function(e) {
       eip.handle_keydown(e);
    });
    
    // need to edit the contents of the site
    if (this.node.data('fx_var')) {
        this.meta = node.data('fx_var');
        this.start(node.data('fx_var'));
    }
    // edit the attributes of the node
    for( var i in this.node.data()) {
        if (!/^fx_template_var/.test(i)) {
            continue;
        }
        var meta = this.node.data(i);
        meta.is_att = true;
        this.start(meta);
    }
    // edit fields from fx_controller_meta['field']
    var c_meta = this.node.data('fx_controller_meta');
    if (c_meta && c_meta.fields) {
        $.each(c_meta.fields, function(index, field) {
            eip.start(field);
        });
    }
}

fx_edit_in_place.prototype.handle_keydown = function(e) {
    if (e.which === 27) {
        if (e.isDefaultPrevented && e.isDefaultPrevented()) {
            return;
        }
        this.stop();
        this.restore();
        $fx.front.deselect_item();
        return false;
    }
    if (e.which === 13 && (!this.is_wysiwyg || e.ctrlKey)) {
        this.save().stop();
        e.which = 666;
        $(this.node).closest('a').blur();
        return false;
    }
};

fx_edit_in_place.prototype.start = function(meta) {
	var edit_in_place = this;
	switch (meta.type) {
            case 'datetime':
                this.add_panel_field(
                    $.extend({}, meta, {
                        value: meta.real_value || this.node.text()
                    })
                );
                break;
            case 'image': case 'file': 
                var field_meta = $.extend(
                        {}, 
                        meta, 
                        {
                            //value:'olo' //{path: meta.real_value || ''}
                            real_value:{path: meta.real_value || ''}
                        }
                );
                this.add_panel_field(
                    field_meta
                ).on('fx_change_file', function() {
                    edit_in_place.save().stop();
                });
                break;
            case 'select': case 'livesearch': case 'bool': case 'color': case 'map':
                this.add_panel_field(meta);
                break;
            case 'string': case 'html': case '': case 'text': case 'int': case 'float':
                if (meta.is_att) {
                    this.add_panel_field(meta);
                } else {
                    this.is_content_editable = true;
                    if (!$($fx.front.get_selected_item()).hasClass('fx_essence')) {
                        setTimeout(function() {
                            $fx.front.stop_essences_sortable();
                        }, 50);
                    }
                    if (this.node.hasClass('fx_hidden_placeholded')) {
                        this.was_placeholded_by = this.node.html();
                        this.node.removeClass('fx_hidden_placeholded');
                        this.node.html('');
                    }
                    this.node.addClass('fx_var_editable');
                    this.node.data('fx_saved_value', this.node.html());
                    if ( (meta.type === 'text' && meta.html && meta.html !== '0') || meta.type === 'html') {
                        this.is_wysiwyg = true;
                        this.make_wysiwyg();
                    }
                    
                    var $n = this.node;
                    setTimeout(function() {
                        $n.attr('contenteditable', 'true').focus();
                        if (edit_in_place.is_wysiwyg) {
                            return;
                        }
                        $n.on('keydown.edit_in_place', function() {
                            //$n.removeClass('fx_editable_empty');
                        });
                        $n.on(
                            'keyup.edit_in_place click.edit_in_place change.edit_in_place', 
                            function () {
                                $n.toggleClass(
                                    'fx_editable_empty', 
                                    $n.text().length < 2
                                );
                            }
                        );
                    }, 50);
                }
                break;
	}
        $('html').one('fx_deselect.edit_in_place', function() {
            edit_in_place.save().stop();
	});
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
    var field = $fx.front.add_panel_field(meta);
    field.data('meta', meta);
    this.panel_fields.push(field);
    return field;
};

fx_edit_in_place.prototype.stop = function() {
    this.node.data('edit_in_place', null);
    this.node.removeClass('fx_edit_in_place').removeClass('fx_editable_empty');
    if (this.stopped) {
        return this;
    }
    for (var i =0 ;i<this.panel_fields.length; i++) {
        this.panel_fields[i].remove();
    }
    this.panel_fields = [];
    this.node.data('edit_in_place', null);
    this.node.attr('contenteditable', null);
    this.node.removeClass('fx_var_editable');
    if (this.is_content_editable && this.is_wysiwyg) {
        this.destroy_wysiwyg();
    }
    $('*').off('.edit_in_place');
    this.node.blur();
    this.stopped = true;
    if (this.was_placeholded_by && this.node.text().match(/^\s*$/)) {
        this.node.addClass('fx_hidden_placeholded').html(this.was_placeholded_by);
    }
    return this;
};

fx_edit_in_place.prototype.get_vars = function() {
    var node = this.node;
    var vars = [];
    // edit the text node
    var is_content_editable = this.is_content_editable;
    if (is_content_editable) {
        if (this.is_wysiwyg && this.source_area.is(':visible')) {
            this.node.redactor('toggle');
        }
        var text_val = this.is_wysiwyg ? node.redactor('get') : node.text();
        var html_val = this.is_wysiwyg ? node.redactor('get') : node.html();
        var saved_val = node.data('fx_saved_value');
        
        if (text_val !== saved_val && html_val !== saved_val ) {
            vars.push({
                'var':this.meta,
                value:this.is_wysiwyg ? html_val : text_val
            });
        }
    }
    for (var i = 0; i < this.panel_fields.length; i++) {
        var pf = this.panel_fields[i];
        var pf_meta= pf.data('meta');
        var old_value = pf_meta.value;
        if (pf_meta.type === 'bool') {
            var c_input = $('input[name="'+pf_meta['name']+'"][type="checkbox"]', pf);
            var new_value = c_input.is(':checked') ? "1" : "0";
            if (old_value === null) {
                old_value = '0';
            }
        } else if (pf_meta.type === 'livesearch') {
            var livesearch = $('.livesearch', pf).data('livesearch');
            var new_value = livesearch.getValues();
        } else {
            var new_value = $(':input[name="'+pf_meta['name']+'"]', pf).val();
        }
        
        var value_changed = false;
        if (pf_meta.type === 'image' || pf_meta.type === 'file') {
            value_changed = new_value !== old_value.path;
        } else if (new_value instanceof Array && old_value instanceof Array) {
            value_changed = new_value.join(',') !== old_value.join(',');
        } else {
            value_changed = new_value !== old_value;
        }
        if (value_changed) {    
            vars.push({
                'var': pf_meta,
                value:new_value
            });
        }
    }
    return vars;
};

fx_edit_in_place.prototype.save = function() {
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
        }
    });
    
    //var vars = this.get_vars();
    
    // nothing has changed
    if (vars.length === 0) {
        return this;
    }
    var node = this.node;
    $fx.front.disable_infoblock(node.closest('.fx_infoblock'));
    $fx.post(
        {
            essence:'infoblock',
            action:'save_var',
            infoblock:this.ib_meta,
            vars: vars,
            fx_admin:true,
            page_id:$fx.front.get_page_id()
        }, 
        function() {
            $fx.front.reload_infoblock(node.closest('.fx_infoblock').get(0));
	}
    );
    return this;
};

fx_edit_in_place.prototype.restore = function() {
    if (!this.is_content_editable || this.was_placeholded_by) {
        return this;
    }
    var saved = this.node.data('fx_saved_value');
    this.node.html(saved);
    return this;
}

fx_edit_in_place.prototype.make_wysiwyg = function () {
    var doc = this.node[0].ownerDocument || this.node[0].document;
    var win = doc.defaultView || doc.parentWindow;
    var sel = win.getSelection();
    var is_ok = false;
    if (sel) {
        var cp = sel.focusNode;
        var is_ok = $.contains(this.node[0], sel.focusNode);
    }
    if (is_ok) {
        var range = sel.getRangeAt(0);
        range.collapse(true);
        range.insertNode($('<span id="fx_marker-1">&#x200b;</span>')[0]);
        range.detach();
    }
    if (!this.node.attr('id')) {
        this.node.attr('id', 'stub'+Math.round(Math.random()*1000));
    }
    var $panel = $fx.front.get_node_panel();
    $panel.append('<div class="editor_panel" />').show();
    var linebreaks = this.meta.var_type === 'visual';
    if (this.meta.linebreaks !== undefined) {
        linebreaks = this.meta.linebreaks;
    }
    
    var _node = this.node;
    this.node.redactor({
        linebreaks:linebreaks,
        toolbarExternal: '.editor_panel',
        imageUpload : '/floxim/admin/controller/redactor-upload.php',
        buttons: ['formatting', '|', 'bold', 'italic', 'deleted', '|',
                'unorderedlist', 'orderedlist', '|',
                'image', 'video', 'file', 'table', 'link', '|', 'alignment', '|', 'horizontalrule'],
        plugins: ['fontcolor'],
        initCallback: function() {
            var marker = _node.find('#fx_marker-1');
            var selection = window.getSelection();
            if (selection) {
                var range = document.createRange();
            }
            if (marker.length !== 0) {
                range.selectNodeContents(marker[0]);
                range.collapse(false);
                selection.removeAllRanges();
                selection.addRange(range);
                marker.remove();
            } else {
                range.setStart(_node[0], 0);
                range.collapse(true);
                selection.removeAllRanges();
                selection.addRange(range);
            }
            this.sync();
            _node.data('fx_saved_value', this.get());
        }

    });

    this.source_area = $('textarea[name="'+ this.node.attr('id')+'"]');
    this.source_area.addClass('fx_overlay');
    this.source_area.css({
        position:'relative',
        top:'0px',
        left:'0px'
    });
};

fx_edit_in_place.prototype.destroy_wysiwyg = function() {

    
    this.node.redactor('destroy');
    var marker = this.node.find('#fx_marker-1');
    marker.remove();
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
            if (rule.selectorText.match(/\.redactor_editor/)) {
                var new_css = rule.cssText.replace(/\.redactor_editor/g, '.redactor_fx_wysiwyg');
                sheet.deleteRule(j);
                sheet.insertRule(
                    new_css,
                    j
                );
            } else if ( rule.selectorText === '.redactor_box') {
                sheet.deleteRule(j);
            }
        }
    }
});

})($fxj);