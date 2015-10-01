(function($){
window.$fx_fields = {
    
    default:function(json){
        return $t.jQuery('form_row', json);
    },
    
    html: function (json) {
      return json.html || json.value;  
    },
    
    raw: function(json) {
        if (json.wrap) {
            return $t.jQuery('form_row', json);
        }
        return json.value;
    },
    
    group:function(json) {
        var $row =  $t.jQuery('form_row', json),
            b = 'fx-field-group',
            exp_class = b+'_expanded',
            $group = $('.'+b, $row),
            $fields = $('.'+b+'__fields', $group);
        
        function expand() {
            $group.addClass(exp_class);
            var fields_height = $fields.height();
            $fields.css({
                overflow:'hidden',
                height:0
            }).animate(
                {
                    height:fields_height
                },
                300,
                null,
                function(){ 
                    $fields.attr('style', '');
                }
            );
        }
        
        function collapse() {
            $fields.css({
                overflow:'hidden'
            }).animate(
                {
                    height:0
                }, 
                300, 
                null, 
                function() {
                    $group.removeClass(exp_class);
                    $fields.attr('style', '');
                }
            );
            
        }
        
        function toggle() {
            if ($group.hasClass(exp_class)) {
                collapse();
            } else {
                expand();
            }
        }
        
        $group
            .find('.'+b+'__title')
            .on('click', toggle)
            .on('keydown', function(e) {
                if (e.which === 13 || e.which === 32) {
                    toggle();
                    return false;
                }
            });
        
        return $row;
    },

    label: function(json) {
        return $t.jQuery('field_label', json);
    },

    input: function(json) {
        return $t.jQuery('form_row', json);
    },
    
    number: function(json) {
        json.class_name = 'number' + (json.class_name || '');
        return $t.jQuery('form_row', json);
    },

    file: function (json) {
        return $t.jQuery('form_row', json);
    },

    image: function ( json ) {
        return $t.jQuery('form_row', json);
    },

    textarea: function(json) {
        json.field_type = 'textarea';
        return $t.jQuery('form_row', json);
    },

    select: function(json) {
        return $t.jQuery('form_row', json);
    },

    radio: function(json) {
        return $t.jQuery('form_row', json);
    },

    radio_facet: function (json ) {
        return $t.jQuery('form_row', json);
    },
    
    bool:function(json) {
        delete json.values;
        json.type = 'checkbox';
        return $fx_fields.checkbox(json);
    },

    checkbox: function(json) {
        var is_toggler = json.class === 'toggler';
        if (is_toggler) {
            json.class_name = json.class;
        }
        var $res = $t.jQuery('form_row', json);
        if (is_toggler) {
            var $toggler = $('.fx_toggler', $res),
                $input = $('input', $res),
                $control = $('.fx_toggler__control', $res);
            function toggle() {
                if ($toggler.hasClass('fx_toggler_on')) {
                    $toggler.removeClass('fx_toggler_on').addClass('fx_toggler_off');
                    $input.val(0);
                } else {
                    $toggler.removeClass('fx_toggler_off').addClass('fx_toggler_on');
                    $input.val(1);
                }
                $control.focus();
            }
            $toggler.click(toggle).keydown(function(e) {
                if (e.which === 13 || e.which === 32) {
                    toggle();
                    return false;
                }
            });
        }
        return $res;
    },

    color: function(json) {
        return $t.jQuery('form_row', json);
    },

    iconselect: function(json) {
        return $t.jQuery('form_row', json);
    },

    livesearch: function(json) {
        json.params = json.params || {};
        if (json.values) {
            var vals = [];
            $.each(json.values, function() {
                var c_val = this;
                // [ [id1, name1], [id2, name2] ]
                if (this instanceof Array && this.length >= 2) {
                    c_val = {
                        id:this[0],
                        name:this[1]
                    };
                }
                vals.push(c_val);
                if (json.value === c_val.id) {
                    json.value = c_val;
                }
            });
            json.params.preset_values = vals;
        }
        if (json.allow_select_doubles) {
            json.params.allow_select_doubles = json.allow_select_doubles;
        }
        var ls = $t.jQuery('form_row', json);
        return ls;
    },


    set: function(json) {
        return $t.jQuery('form_row', json);
    },

    tree: function(json) {
        return $t.jQuery('form_row', json);
    },

    table: function (json) {
        return $t.jQuery('form_row', json);
    },
    
    buttons: function(json) {
        return $t.jQuery('form_row', json);
    },

    button: function (json) {
        return $t.jQuery('form_row', json);
    },

    link: function(json) {
        return $t.jQuery('form_row', json);
    },

    list: function(json) {
        return $t.jQuery('form_row', json);
    },

    datetime: function ( json ) {
        return $t.jQuery('form_row', json);
    },

    float: function (json ) {
        return $t.jQuery('form_row', json);
    },

    colorbasic: function (json) {
        return $t.jQuery('form_row', json);
    },
    
    password: function(json){
        return $t.jQuery('form_row', json);
    },
    map: function (json) {
        var $field = $t.jQuery('form_row', json);
        new fx_google_map_field($field);
        return $field;
    },
    joined_group: function(json) {
        return $t.jQuery('form_row', json);
    },
    make_codemirror: function(textarea, options) {
        
    },
    make_redactor: function($node, options) {
        options = $.extend({
            imageUpload : '/vendor/Floxim/Floxim/Admin/Controller/redactor-upload.php',
            tidyHtml:false,
            toolbarFixed:false,
            buttons: ['html', 'formatting',  'bold', 'italic', 'deleted',
                    'unorderedlist', 'orderedlist',
                    //'outdent', 'indent',
                    'image', 'video', 'file', 'table', 'link', 'alignment', 'horizontalrule'],
            plugins: ['fontcolor'],
            cleanSpaces:false,
            lang: $fx.lang('lang'),
            formatting: ['p', 'h2', 'h3']
        }, options);
        
        if (options.toolbarPreset === 'inline') {
            options.buttons = ['bold', 'italic', 'deleted'];
        }
        
        if (options.extra_buttons) {
            for(var i = 0; i < options.extra_buttons.length; i++) {
                options.buttons.push(options.extra_buttons[i]);
            }
            delete options.extra_buttons;
        }
        var e = $.Event('fx_create_redactor');
        e.redactor_options = options;
        $node.trigger(e);
        $node.redactor(options);
    }
};

window.$fx_fields.init_fieldset = function(html, _c) {
    $('tbody.fx_fieldset_rows', html).sortable();

    var fs = $('.fx_fieldset', html);

    if (!_c.values) {
        _c.values = _c.value || [];
    }
    var flag = false;
    $.each(_c.values, function(row, val) {
        var val_num = 0;
        var inputs = [];
        var row_index = val._index || row;
        $.each(_c.tpl, function(tpl_index, tpl_props) {
            inputs.push(
                $.extend(
                    {}, 
                    tpl_props, 
                    {
                        name:_c.name+'['+row_index+']['+tpl_props.name+']',
                        value:val[tpl_props.name]
                    }
                )
            );
        });
        $('.fx_fieldset_rows', html).append($t.jQuery('fieldset_row', inputs, {index:row, set_field: _c}));
    });

    fs.on('click', '.fx_fieldset_remove', function() {
            $(this).parents('.fx_fieldset_row').remove();	
    });
    $('.fx_fieldset_add', fs).click( function() {
        var fs = $(this).closest('.fx_fieldset');
        var inputs = [];
        var index = $('.fx_fieldset_row', fs).length + 1;
        for (var i = 0; i < _c.tpl.length; i++) {
            inputs.push( 
                $.extend({}, _c.tpl[i], {
                    name:_c.name+'[new_'+index+']['+_c.tpl[i].name+']'
                })
            );
        }
        var new_row = $t.jQuery('fieldset_row', inputs, {index:index, set_field: _c});
        $('.fx_fieldset_rows', fs).append(new_row);
        new_row.find(':input:visible').first().focus();
    });
    /*
    if (!flag) {
        $('.fx_fieldset_add', fs).click();
        $('.fx_fieldset_rows .fx_fieldset_row', fs).addClass('fake').one('change', function() {
            $(this).removeClass('fake')
        });
    }
    */
};

window.$fx_fields.init_condset = function(html, _c, _o) {
    var i = 0;
    var is_new_row = typeof _o.index === 'number';

    var intervals = _o.set_field.date_intervals;

    html.on('change', '.input_cell_0 select', function() {
        var opt = $(this).find('option').filter(':selected');
        set_control(opt.data('meta'), $(this).parent().parent());
    });
    html.on('change', '.input_cell_1 select.date_operator', function() {
        var type = $(this).val(); 

        if (typeof _o.index === 'number') {
            var name = 'new_'+_o.index;
        } else {
            var name = _o.index;
        }
        switch (type){
            case 'in_future':
            case 'in_past':
                var cells = $(this).parent().parent().find('[class*="input_cell"]').not('.input_cell_0').not('.input_cell_1');
                cells.empty();
            break;
            case 'next':
            case 'last':

                var cells = $(this).parent().parent().find('[class*="input_cell"]').not('.input_cell_0').not('.input_cell_1');
                cells.remove();
                $(this).parent()
                .after('<td class="input_cell_2"><input type="text" class="fx_input fx_input_number" name="params[conditions]['+name+'][value]" id="params[conditions]['+name+'][value]" value=""></td>')
                //.next().after('<td class="input_cell_3"></td>');

                var select = $t.jQuery(
                    'input', {
                        'type':'select', 
                        'name': 'params[conditions]['+name+']['+'interval'+']',
                        'values':
                            intervals
                        }
                );
                $(this).parent().parent().find('.input_cell_2').append(select)
            break;
            default:
                var cells = $(this).parent().parent().find('[class*="input_cell"]').not('.input_cell_0').not('.input_cell_1');
                cells.remove();
                $(this).parent().after('<td class="input_cell_2"></td>');
                var input_line = $t.jQuery(
                    'input', {
                        'type':'datetime', 
                        'name': 'params[conditions]['+name+']['+'value'+']',
                        }
                );
                $(this).parent().parent().find('.input_cell_2').html(input_line);
            break;
        }
    });
    function set_saved () {
        if (!is_new_row){
            var name = "[name='params[conditions]["+_o.index+"]";
            $(name+"[name]']", html)
                .val(_o.set_field.values[_o.index].name)
                .change();
            $(name+"[operator]']", html).val(_o.set_field.values[_o.index].operator);
            var option_meta = $(name+"[name]']", html).find('option').filter(':selected').data('meta');
            if (option_meta.type === 'bool') {
                if(_o.set_field.values[_o.index].value === 1) {
                    $(name+"[value]']", html).eq(1).attr('checked', 'checked');
                }
            } else if (option_meta.type === 'datetime') {
                if (_o.set_field.values[_o.index].value === undefined) {
                    $(name+"[value]']", html).remove();
                } else if (_o.set_field.values[_o.index].interval !== undefined) {
                    var select = $t.jQuery(
                        'input', {
                            'type':'select', 
                            'name': 'params[conditions]['+_o.index+']['+'interval'+']',
                            'values':
                                intervals
                            }
                    );
                    var cell = $('<td class="input_cell_3"></td>').append(select);
                    $(name+"[value]']", html).parent().parent().after(cell);
                    $(name+"[value]']", html).unbind();
                }
                $(name+"[operator]']", html).change();
                if (_o.set_field.values[_o.index].interval !== undefined) {
                    $(name+"[interval]']", html).val(_o.set_field.values[_o.index].interval);
                }

                $(name+"[value]']", html).val(_o.set_field.values[_o.index].value).css('width', '40px');

            } else {
                $(name+"[value]']", html).val(_o.set_field.values[_o.index].value);
            }
        }
    }
    function set_control (meta, selector) {
        var type = meta.type;
        var c_value;
        if (typeof _o.index === 'number') {
            var name = 'params[conditions][new_'+_o.index+'][';
        } else {
            var name = 'params[conditions]['+_o.index+'][';
            c_value = _o.set_field.value[_o.index].value;
        }

        var html = selector;
        switch(type) {
            case 'text':
            case 'string':
                var select = $t.jQuery(
                    'input', {
                        'type':'select', 
                        'name': name+'operator'+']',
                        'values':
                            _o
                                .set_field
                                .operators_map
                                .string
                        }
                );
                $('.input_cell_1', html).html(select);
                var input_line = $t.jQuery(
                    'input', {
                        'type':'text', 
                        'name': name+'value'+']',
                        }
                );
                $('.input_cell_2', html).html(input_line);
            break;
            case 'int':
                var select = $t.jQuery(
                    'input', {
                        'type':'select', 
                        'name': name+'operator'+']',
                        'values':
                            _o
                                .set_field
                                .operators_map
                                .int
                        }
                );
                $('.input_cell_1', html).html(select);
                var input_line = $t.jQuery(
                    'input', {
                        'type':'int', 
                        'name': name+'value'+']'
                    }
                );
                $('.input_cell_2', html).html(input_line);
            break;
            case 'bool':
                var select = $t.jQuery(
                    'input', {
                        'type':'hidden', 
                        'name': name+'operator'+']',
                        'value': '='
                    }
                );
                $('.input_cell_1', html).html(select);
                var input_line = $t.jQuery(
                    'input', {
                        'type':'checkbox', 
                        'name': name+'value'+']'
                    }
                );
                $('.input_cell_2', html).html(input_line);
            break;
            case 'datetime':
                var select = $t.jQuery(
                    'input', {
                        'type':'select', 
                        'name': name+'operator'+']',
                        'values':
                            _o
                                .set_field
                                .operators_map
                                .datetime
                    }
                );
                select.addClass('date_operator');
                $('.input_cell_1', html).html(select);
                var input_line = $t.jQuery(
                    'input', {
                        'type':'datetime', 
                        'name': name+'value'+']'
                    }
                );
                $('.input_cell_2', html).html(input_line);
            break;
            case 'multilink':
            case 'link':
                var $select = $t.jQuery(
                    'input', {
                        'type':'select', 
                        'name': name+'operator'+']',
                        'values':
                            _o
                                .set_field
                                .operators_map
                                .link
                        }
                );

                $('.input_cell_1', html).html($select);
                
                var livesearch_opts = {
                    'type':'livesearch',
                    'name': name+'value'+']',
                    'is_multiple': true,
                    'value':c_value,
                    ajax_preload:true,
                    params: {
                        'content_type' : meta.content_type,
                        count_show : 20,
                        preset_values : meta.values,
                        conditions:meta.conditions
                    }
                };
                var $livesearch = $t.jQuery(
                    'input', livesearch_opts
                );
                $('.input_cell_2', html).html($livesearch);
                
                function handle_current() {
                    var cv = $select.val();
                    if (cv === 'is_current' || cv === 'is_not_current') {
                        $livesearch.hide();
                    } else {
                        $livesearch.show();
                    }
                }
                
                $select.on('change', handle_current);
                setTimeout(handle_current,100);
            break;
        }
    }
    $.each(_o.set_field.tpl[0].values, function (key, value) {
        var opt = $('<option value="'+key+'">'+value.description+'</option>');
        opt.data('meta', value);
        $('.input_cell_0 select', html).append(opt);

        if (i++ === 0 && is_new_row)  {
            set_control(opt.data('meta'), $('.input_cell_0 select', html).parent().parent())
        }
    });
    set_saved();
};

// file field

var $html = $('html');
$html.on('click.fx', '.fx_image_field .fx_remote_file_block a',  function() {
    var $block = $(this).closest('.fx_remote_file_block');
    $block.addClass('active');
    $block.closest('.fx_preview').addClass('fx_preview_active');
    var $inp = $block.find('input');
    $inp.focus().off('keydown.fx_blur').on('keydown.fx_blur', function(e) {
        if (e.which === 27) {
            $(this).blur().trigger('change');
            
            return false;
        }
    }).trigger('change');
});

$html.on('blur.fx', '.fx_image_field .fx_remote_file_block input', function() {
    var $inp = $(this);
    if ($inp.attr('disabled') !== undefined) {
        return;
    }
    $inp.closest('.fx_remote_file_block').removeClass('active');
    $inp.closest('.fx_preview').removeClass('fx_preview_active');
});

function handle_upload(data, $block) {
    var $res_inp = $('.real_value', $block);
    var field_type = $block.data('field_type');
    $res_inp.val(data.path);
    var $panel = $res_inp.closest('.fx_node_panel');
    if ($panel.length === 0) {
        if (field_type === 'image') {
            $('.fx_preview img', $block).attr('src', data.path).show();
        }
        var $fi = $('.fx_file_info', $block);
        
        $('.fx_file_name', $fi)
            .attr('href', data.path)
            .text(data.filename);
        
        $('.fx_file_size', $fi)
            .text(data.size);
    
        if (field_type === 'image') {
            $('.fx_image_size')
                .html(data.width+'&times;'+data.height);
        }
        
    }
    $('.fx_file_killer', $block).show();
    $('.fx_file_input', $block).hide();
    $('.fx_preview', $block).addClass('fx_preview_filled');
    
    $res_inp.data('fx_upload_response', data);
    
    var e = $.Event('fx_change_file');
    e.upload_response = data;
    $res_inp.trigger(e);
}

$html.on('change.fx', '.fx_image_field input.file', function() {
    var $field = $(this);
    var $block = $field.closest('.fx_image_field');
    var inp_id = $field.attr('id');
    var $real_inp = $('.real_value', $block);
    var format = $real_inp.data('format_modifier');
    
    $.ajaxFileUpload({
        url:'/floxim/',
        secureuri:false,
        fileElementId:inp_id,
        dataType: 'json',
        data: { entity:'file', fx_admin:1, action:'upload_save', format:format },
        success: function ( data ) {
            handle_upload(data, $block);
        }
    });
});

$html.on('click.fx', '.fx_image_field .fx_file_uploader', function() {
    $(this).closest('.fx_image_field').find('input.file').focus().click();
});

$html.on('click.fx', '.fx_image_field .fx_file_killer', function() {
   var $field = $(this).closest('.fx_image_field'); 
   $('.real_value', $field).val('').trigger('fx_change_file');
   $('.fx_file_input', $field).show();
   $('.fx_preview', $field).removeClass('fx_preview_filled');
   $(this).hide();
});

$html.on('paste.fx', '.fx_image_field .remote_file_location', function() {
    var $inp = $(this);
    var $block = $inp.closest('.fx_image_field');
    var $real_inp = $('.real_value', $block);
    var format = $real_inp.data('format_modifier');
    setTimeout(function() {
        var val = $inp.val();
        if (!val.match(/https?:\/\/.+/)) {
            return;
        }
        $inp.attr('disabled', 'disabled').val($fx.lang('loading')+'...');
        $.ajax({
            url:'/floxim/',
            type:'post',
            data: { entity:'file', fx_admin:1, action:'upload_save' , file:val, format:format},
            dataType: 'json',
            success: function ( data ) {
                handle_upload(data, $block);
                $inp.get(0).removeAttribute('disabled');
                $inp.val('').blur();
            },
            error: function() {
                console.log('on err');
                $inp.get(0).removeAttribute('disabled').val($fx.lang('error')).focus();
            }
        });
    }, 50);
});

window.$fx_fields.parse_std_date = function(date_str) {
    var parts = date_str.split(' ');
    var date_parts = parts[0].split('-');
    var time_parts = parts[1].split(':');
    // year, month, date[, hours, minutes, seconds
    var res = new Date(
        date_parts[0],
        date_parts[1]-1,
        date_parts[2],
        time_parts[0],
        time_parts[1],
        time_parts[2]
    );
    return res;
};

window.$fx_fields.handle_date_field = function(html) {
    var inp  = $('input.date_input', html);

function export_parts() {
    var res = '',
        months = {
            '01':'Jan', '02':'Feb', '03':'Mar', '04':'Apr', '05':'May', '06':'Jun',
            '07':'Jul', '08':'Aug', '09':'Sep', '10':'Oct', '11':'Nov', '12':'Dec'
        },
        filled = true;
    $.each(
        'd,m,y,h,i'.split(','), 
        function(index, item) {
            var c_val = $('.fx_date_part_'+item, html).val();
            if (!c_val) {
                if (item === 'h' || item === 'i') {
                    c_val = '00';
                } else {
                    filled = false;
                }
            }
            if (item === 'm') {
                c_val = months[c_val];
            }
            res += c_val;
            res += (index < 2 ? ' ' : index === 2 ? ' ' : ':');
        }
    );
    res += '00';
    if (filled) {
        var date = new Date(res);
        if (date && !isNaN(date.getTime())) {
            inp.val( format_date ( date ) );
            inp.trigger('change');
        }
    }
};

html.on('keydown', '.fx_date_part', function(e) {
    var $part = $(this),
        part_val = $part.val(),
        max = $part.data('max'),
        min = $part.data('min') || 0,
        len = $part.data('len'),
        strikes = ( $part.data('strikes') || 0) + 1;
    
    $part.data('strikes', strikes);

    if (e.which === 40 || e.which === 38) { // down or up
        part_val = part_val*1;
        part_val += (e.which === 40 ? -1 : 1);
        if (part_val < min) {
            part_val = max;
        } else if (part_val > max) {
            part_val = min;
        }
        
        if (len === 2 && part_val < 10) {
            part_val = '0'+part_val;
        }
        
        $part.val(part_val);
        return false;
    }
});

function format_date(d) {
    var res = $.datepicker.formatDate("yy-mm-dd", d );
    res += ' ';
    var h = d.getHours();
    res += (h < 10 ? '0' : '')+h + ':';
    var m = d.getMinutes();
    res += (m < 10 ? '0' : '')+m+':00';
    return res;
}

html.on('focus mouseup click', '.fx_date_part', function(e) {
    this.setSelectionRange(0, this.value.length);
    $(this).data('strikes', 0);
    return false;
});

html.on('keyup', '.fx_date_part', function(e) {
    var $part = $(this),
        part_val = $part.val(),
        min = $part.data('min'),
        max = $part.data('max'),
        len = $part.data('len');
    
    if (part_val.length > len) {
        part_val = part_val.slice(0, len);
    }
    
    if (part_val.match(/[^0-9]/)) {
        part_val = part_val.replace(/[^0-9]+/g, '');
    }
    
    var int_val = part_val*1;
    
    if (int_val > max) {
        part_val = max;
    }
    if (part_val + '' !== $part.val()) {
        $part.val(part_val);
    }
    
    export_parts();
    
    if (this.selectionStart !== undefined && this.selectionStart === this.selectionEnd) {
        if (this.selectionStart === 0 && e.which === 37) {
            var $prev = $part.prevAll('.fx_date_part').first();
            if ($prev.length) {
                $prev.focus().focus();
            }
        } else if (this.selectionEnd === part_val.length && e.which === 39) {
            var $next = $part.nextAll('.fx_date_part').first();
            if ($next.length) {
                $next.focus().focus();
            }
        }
    }
    
    if (e.which < 48 || e.which > 57 || !$part.data('strikes')) { 
        return;
    }
    
    if (part_val.length === $part.data('len')) {
        var int_val = part_val*1;
        if (int_val >= min && int_val <= max) {
            var $next = $part.nextAll('.fx_date_part').first();
            if ($next.length) {
                $next[0].setSelectionRange(0, $next.val().length);
                $next.focus().focus();
            }
        }
    }
});
/*
function get_inp_time(inp) {
    var v = inp.val();
    var v_time = v.replace(/^[^\s]+\s/, '');
    inp.data('time', v_time);
}
get_inp_time(inp);
inp.keyup(function() {get_inp_time($(this))});
inp.click(function() {
    $(this).datepicker('show');
});
*/

var show_format = 'yy-mm-dd';

inp.datepicker({
    changeMonth: true,
    changeYear: true,
    firstDay:1,
    dateFormat: show_format,
    onSelect:function(dateText, datepicker) {
        var d = new Date(dateText);
        $('.fx_date_part_d', html).val( $.datepicker.formatDate("dd", d) );
        $('.fx_date_part_m', html).val( $.datepicker.formatDate("mm", d) );
        $('.fx_date_part_y', html).val( $.datepicker.formatDate("yy", d) );
        export_parts();
    }
});
inp.datepicker('widget').addClass('fx_overlay');

$('.fx_datepicker_icon', html).click(function() {
    inp.datepicker('show');
});

};

})($fxj);