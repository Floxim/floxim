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
        var label = $('<label />'); 
        var field = $('<input  name="'+json.name+'"  />').val( json.value !== undefined ? json.value : '' );

        if (json.label) {
            $(label).append(json.label);
        } 
        label.append(field);

        field.keypress(function(e) {
            if (!(e.which==8 || e.which==44 ||e.which==45 ||e.which==46 ||(e.which>47 && e.which<58))) {
                return false;
            }
        });

        return label;
    },

    colorbasic: function (json) {
        return $t.jQuery('form_row', json);
    }
};

// file field

var $html = $('html');
$html.on('click', '.fx_image_field .remote_file_block a',  function() {
    var $block = $(this).closest('.remote_file_block');
    $block.addClass('active');
    $block.find('input').focus();
});

$html.on('blur', '.fx_image_field .remote_file_block input', function() {
    $(this).closest('.remote_file_block').removeClass('active');
});

function handle_upload(data, $block) {
    var $res_inp = $('.real_value', $block);
    var field_type = $block.data('field_type');
    $res_inp.val(data.path);
    if (field_type === 'image') {
        $('.preview img', $block).attr('src', data.path).show();
    } else {
        $('.file_info', $block)
                .html(
                    '<a href="'+data.path+'">'+data.filename+'</a>'+
                    '<br /><span class="file_size">'+data.size+'</span>'
                );
    }
    $('.killer', $block).show();
    $('.file_input', $block).hide();
    $res_inp.trigger('fx_change_file');
}

$html.on('change', '.fx_image_field input.file', function() {
    var $field = $(this);
    var $block = $field.closest('.fx_image_field');
    var inp_id = $field.attr('id');
    
    $.ajaxFileUpload({
        url:'/floxim/index.php',
        secureuri:false,
        fileElementId:inp_id,
        dataType: 'json',
        data: { essence:'file', fx_admin:1, action:'upload_save' },
        success: function ( data ) {
            handle_upload(data, $block);
        }
    });
});

$html.on('click', '.fx_image_field .uploader', function() {
    $(this).closest('.fx_image_field').find('input.file').focus().click();
});

$html.on('click', '.fx_image_field .killer', function() {
   var $field = $(this).closest('.fx_image_field'); 
   $('.preview img', $field).hide();
   $('.real_value', $field).val('');
   $('.file_input', $field).show();
   $(this).hide();
});

$html.on('paste', '.fx_image_field .remote_file_location', function() {
    var $inp = $(this);
    var $block = $inp.closest('.fx_image_field');
    setTimeout(function() {
        var val = $inp.val();
        if (!val.match(/https?:\/\/.+/)) {
            return;
        }
        $.ajax({
            url:'/floxim/index.php',
            type:'post',
            data: { essence:'file', fx_admin:1, action:'upload_save' , file:val},
            dataType: 'json',
            success: function ( data ) {
                handle_upload(data, $block);
                $inp.val('').blur();
            }
        });
    }, 50);
});


})($fxj);