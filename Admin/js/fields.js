(function($){
window.$fx_fields = {
    html: function (json) {
      return json.html;  
    },

    label: function(json) {
        return $t.jQuery('field_label', json);
    },

    input: function(json) {
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

    checkbox: function(json) {
        return $t.jQuery('form_row', json);
    },

    color: function(json) {
        return $t.jQuery('form_row', json);
    },

    iconselect: function(json) {
        return $t.jQuery('form_row', json);
    },

    livesearch: function(json) {
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
                    'unorderedlist', 'orderedlist', 'outdent', 'indent',
                    'image', 'video', 'file', 'table', 'link', 'alignment', 'horizontalrule'],
            plugins: ['fontcolor']
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
    $(this).closest('.fx_remote_file_block').removeClass('active');
    $(this).closest('.fx_preview').removeClass('fx_preview_active');
});

function handle_upload(data, $block) {
    var $res_inp = $('.real_value', $block);
    var field_type = $block.data('field_type');
    $res_inp.val(data.path);
    var $panel = $res_inp.closest('.fx_node_panel');
    if ($panel.length === 0) {
        if (field_type === 'image') {
            $('.fx_preview img', $block).attr('src', data.path).show();
        } else {
            $('.fx_file_info', $block)
                    .html(
                        '<a href="'+data.path+'">'+data.filename+'</a>'+
                        '<br /><span class="file_size">'+data.size+'</span>'
                    );
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
   $('.fx_preview img', $field).hide();
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
        $.ajax({
            url:'/floxim/',
            type:'post',
            data: { entity:'file', fx_admin:1, action:'upload_save' , file:val, format:format},
            dataType: 'json',
            success: function ( data ) {
                handle_upload(data, $block);
                $inp.val('').blur();
            }
        });
    }, 50);
});

window.$fx_fields.handle_date_field = function(html) {
    var inp  = $('input.date_input', html);

var export_parts = function() {
    var res = '',
        filled = true;
    $.each('y,m,d,h,i'.split(','), function(index, item) {
        var c_val = $('.fx_date_part_'+item, html).val();
        if (!c_val) {
            if (item === 'h' || item === 'i') {
                c_val = '00';
            } else {
                filled = false;
            }
        }
        res += c_val;
        res += (index < 2 ? '-' : index === 2 ? ' ' : ':');
    });
    res += '00';
    if (filled) {
        var date = new Date(res);
        if (date && !isNaN(date.getTime())) {
            inp.val( format_date ( date ) );
            //console.log(res, date, date.getTime());
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
        part_val += (e.which === 40 ? 1 : -1);
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