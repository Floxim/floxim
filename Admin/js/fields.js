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

    floatfield: function (json ) {
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
            buttons: ['formatting',  'bold', 'italic', 'deleted',
                    'unorderedlist', 'orderedlist', 'outdent', 'indent',
                    'image', 'video', 'file', 'table', 'link', 'alignment', 'horizontalrule'],
            plugins: ['fontcolor']
        }, options);
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
    $block.find('input').focus().off('keydown.fx_blur').on('keydown.fx_blur', function(e) {
        if (e.which === 27) {
            $(this).blur();
            return false;
        }
    });
});

$html.on('blur.fx', '.fx_image_field .fx_remote_file_block input', function() {
    $(this).closest('.fx_remote_file_block').removeClass('active');
    $(this).closest('.fx_preview').removeClass('fx_preview_active');
});

function handle_upload(data, $block) {
    var $res_inp = $('.real_value', $block);
    var field_type = $block.data('field_type');
    $res_inp.val(data.path);
    if (field_type === 'image') {
        $('.fx_preview img', $block).attr('src', data.path).show();
    } else {
        $('.fx_file_info', $block)
                .html(
                    '<a href="'+data.path+'">'+data.filename+'</a>'+
                    '<br /><span class="file_size">'+data.size+'</span>'
                );
    }
    $('.fx_file_killer', $block).show();
    $('.fx_file_input', $block).hide();
    $('.fx_preview', $block).addClass('fx_preview_filled');
    $res_inp.trigger('fx_change_file');
}

$html.on('change.fx', '.fx_image_field input.file', function() {
    var $field = $(this);
    var $block = $field.closest('.fx_image_field');
    var inp_id = $field.attr('id');
    
    $.ajaxFileUpload({
        url:'/vendor/Floxim/Floxim/index.php',
        secureuri:false,
        fileElementId:inp_id,
        dataType: 'json',
        data: { entity:'file', fx_admin:1, action:'upload_save' },
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
    setTimeout(function() {
        var val = $inp.val();
        if (!val.match(/https?:\/\/.+/)) {
            return;
        }
        $.ajax({
            url:'/vendor/Floxim/Floxim/index.php',
            type:'post',
            data: { entity:'file', fx_admin:1, action:'upload_save' , file:val},
            dataType: 'json',
            success: function ( data ) {
                handle_upload(data, $block);
                $inp.val('').blur();
            }
        });
    }, 50);
});


})($fxj);