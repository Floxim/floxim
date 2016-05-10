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
    
    row: function(json) {
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
        if (!this[type]) {
            type = 'default';
        }
        
        var $node = this[type](json);
        if (json.field_meta) {
            $node.data('field_meta', json.field_meta);
        }
        return $node;
    },
    
    group:function(json) {
        var $row =  $t.jQuery('form_row', json),
            b = 'fx-field-group',
            exp_class = b+'_expanded',
            $group = $('.'+b, $row),
            $fields = $('.'+b+'__fields', $group);
        
        function is_expanded() {
            return $group.hasClass(exp_class);
        }
        
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
            if (is_expanded()) {
                collapse();
            } else {
                expand();
            }
        }
        
        $group
            .find('.'+b+'__title')
            .on('click', toggle)
            .on('keydown', function(e) {
                // enter or space - toggle
                if (e.which === 13 || e.which === 32) {
                    toggle();
                    return false;
                }
                // down or right - expand
                if ( (e.which === 40 || e.which === 39) && !is_expanded()) {
                    expand();
                    return false;
                }
                // up left or escape - collapse
                if ( (e.which === 37 || e.which === 38 || e.which === 27) && is_expanded()) {
                    collapse();
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
    
    control: function(json) {
        var type = json.type || 'string',
            $res;
        if (typeof this[type] === 'function') {
            $res = this[type](json, 'input');
        } else {
            $res = $t.jQuery('input', json);
        }
        return $res;
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

    radio_facet: function (json , template) {
        template = template || 'form_row';
        var $node = $t.jQuery(template, json),
            cl = 'fx-radio-facet',
            vcl = cl + '__variant';
        function select_variant($variant) {
            $('.'+vcl, $node).removeClass(vcl+'_active');
            $variant.addClass(vcl+'_active');
            $('input[type="hidden"]',$node).val($variant.data('value')).trigger('change');
        }
        $node.on('click', '.'+vcl, function() {
            select_variant($(this));
        });
        $node.on('keydown', '.'+vcl, function(e) {
            if (e.which === 13 || e.which === 32) {
                select_variant($(this));
                return false;
            }
        });
        return $node;
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
        var $res = $t.jQuery('form_row', json),
            $inp = $('.fx-colorpicker-input', $res);
        
        setTimeout(
            function() {
                $inp.spectrum({
                    preferredFormat:'rgb',
                    showInput: true,
                    allowEmpty:true,
                    showAlpha: json.alpha === undefined ? true : json.alpha,
                    clickoutFiresChange: true,
                    move:function(c) {
                        $inp.spectrum('set', c === null ? c : c.toRgbString());
                        $inp.trigger('change');
                    },
                    hide:function(c) {
                        $inp.spectrum('set', c === null ? c : c.toRgbString());
                        $inp.trigger('change');
                    }
                });
            },
            50
        );
        return $res;
    },

    iconselect: function(json) {
        return $t.jQuery('form_row', json);
    },

    livesearch: function(json, template) {
        template = template || 'form_row';
        json.params = json.params || {};
        if (!json.type) {
            json.type = 'livesearch';
        }
        
        if (json.content_type && !json.params.content_type) {
            json.params.content_type = json.content_type;
        }
        
        function vals_to_obj(vals, path) {
            var res = [];
            if (path === undefined) {
                path = [];
            }
            
            for (var i = 0; i < vals.length; i++) {
                var val = vals[i],
                    res_val = val;
                
                if (val instanceof Array && val.length >= 2) {
                    res_val = {
                        id:val[0],
                        name:val[1]
                    };
                    if (val.length > 2) {
                        res_val =  $.extend({}, res_val, val[2]);
                    }
                }
                if (json.value == res_val.id) {
                    json.value = res_val;
                    json.value.path = path.slice(0);
                }
                path.push(res_val.name);
                if (res_val.children && res_val.children.length) {
                    res_val.children = vals_to_obj(res_val.children, path);
                }
                path.pop();
                res.push(res_val);
                
            }
            return res;
        }
        
        if (json.values) {
            var preset_vals = json.values;
            if ( ! (json.values instanceof Array) ) {
                preset_vals = [];
                $.each(json.values, function(k, v) {
                    preset_vals.push([k, v]);
                });
            }
            json.params.preset_values = vals_to_obj(preset_vals);
            if (json.allow_empty === false && (!json.value || typeof json.value.id === 'undefined')) {
                json.value = json.params.preset_values[0];
            }
        }
        if (json.allow_select_doubles) {
            json.params.allow_select_doubles = json.allow_select_doubles;
        }
        var ls = $t.jQuery(template, json);
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
        if (!json.type) {
            json = $.extend({}, json, {type: 'button'} );
        }
        return $t.jQuery('form_row', json);
    },

    link: function(json) {
        return $t.jQuery('form_row', json);
    },

    list: function(json) {
        return $t.jQuery('form_row', json);
    },

    datetime: function ( json , template) {
        template = template || 'form_row';
        return $t.jQuery(template, json);
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
        new fx_google_map_field($field, json);
        return $field;
    },
    joined_group: function(json) {
        return $t.jQuery('form_row', json);
    },
    make_codemirror: function(textarea, options) {
        
    },
    make_redactor: function($node, options) {
        options = $.extend({
            imageUpload : document.baseURI + 'vendor/Floxim/Floxim/Admin/Controller/redactor-upload.php',
            tidyHtml:false,
            toolbarFixed:false,
            buttons: [
                    'html', 
                    //'formatting',  
                    'bold', 'italic', 'deleted', 'link',
                    'unorderedlist', 'orderedlist', 'alignment'
                        //'outdent', 'indent',
                        /*'image', 'video', 'file', 'table',  'horizontalrule'*/
                    ],
            //plugins: ['fontcolor'],
            cleanSpaces:false,
            lang: $fx.lang('lang'),
            formatting: ['p', 'h2', 'h3'],
            tabKey:false
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
    },
    init_fieldset: function(html, _c) {
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
                var inp_props = $.extend(
                    {}, 
                    tpl_props, 
                    {
                        name:_c.name+'['+row_index+']['+tpl_props.name+']',
                        value:val[tpl_props.name]
                    }
                );
                if (tpl_props.type === 'radio' && !tpl_props.values) {
                    inp_props.$input_node = $('<input type="radio" name="'+_c.name+'['+tpl_props.name+']" value="'+row_index+'" />');
                    if (tpl_props.value == row_index) {
                        inp_props.$input_node.attr('checked', 'checked');
                    }
                }
                inputs.push(inp_props);
            });
            $('.fx_fieldset_rows', html).append($t.jQuery('fieldset_row', inputs, {index:row, set_field: _c}));
        });
        function remove_row($row) {
            var $next_row = $row.next('.fx_fieldset_row');
            $row.remove();
            if ($next_row.length > 0) {
                $next_row.find(':input, .fx_fieldset_remove').first().focus();
            } else {
                $('.fx_fieldset_add', fs).focus();
            }
        }
        function add_row() {
            var inputs = [];
            var index = $('.fx_fieldset_row', fs).length + 1;
            for (var i = 0; i < _c.tpl.length; i++) {
                inputs.push( 
                    $.extend({}, _c.tpl[i], {
                        name:_c.name+'[new_'+index+']['+_c.tpl[i].name+']'
                    })
                );
            }
            var $new_row = $t.jQuery('fieldset_row', inputs, {index:index, set_field: _c});
            $('.fx_fieldset_rows', fs).append($new_row);
            $new_row.find(':input:visible').first().focus();
        }
        fs.on('click', '.fx_fieldset_remove', function() {
            remove_row($(this).closest('.fx_fieldset_row'));
        });
        fs.on('keydown', '.fx_fieldset_remove', function(e) {
            if (e.which === 32 || e.which === 13) {
                remove_row($(this).closest('.fx_fieldset_row'));
                return false;
            }
        });
        $('.fx_fieldset_add', fs).click( function() {
            add_row();
        }).on('keydown', function(e) {
            if (e.which === 32 || e.which === 13) {
                add_row();
            }
        });
    }
};

// file field

var $html = $('html');
$html.on('keydown', '.fx_image_field .fx_file_control', function(e) {
    var $target = $(e.target);
    // skip bubbled events
    if (!$target.is('.fx_file_control')) {
        return;
    }
    if (e.which === 32 || e.which === 13) {
        $target.click();
        return false;
    }
});

$html.on('click.fx', '.fx_image_field .fx_remote_file_block',  function() {
    var $block = $(this);
    if ($block.is('active')) {
        return;
    }
    $block.addClass('active');
    $block.closest('.fx_preview').addClass('fx_preview_active');
    var $inp = $block.find('input');
    $inp.focus().off('keydown.fx_blur').on('keydown.fx_blur', function(e) {
        if (e.which === 27) {
            $(this).blur().trigger('change');
            $block.focus();
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
        url: $fx.settings.action_link,
        secureuri:false,
        fileElementId:inp_id,
        dataType: 'json',
        data: { entity:'file', fx_admin:1, action:'upload_save', format:format },
        success: function ( data ) {
            handle_upload(data, $block);
        }
    });
});

function load_cropper($inp) {
    var format = $inp.data('format_modifier'),
        src = $inp.val();
    $fx.post({
        entity:'file',
        action:'get_image_meta',
        file: src,
        format: format
    }, function(res) {
        create_cropper($inp, res);
    });
}

function create_cropper($inp, meta) {
    var format = $inp.data('format_modifier'),
        src = $inp.val(),
        parsed_format = meta.format,
        current_crop = meta.current ? meta.current.crop : {},
        aspect_ratio = null,
        $block = $inp.closest('.fx_image_field');
    
    if (!src){
        return;
    }
    if (parsed_format && parsed_format.width && parsed_format.height) {
        aspect_ratio = parsed_format.width / parsed_format.height;
    }
    
    var cl = 'fx-cropper-popup',
        $popup = $(
            '<div class="'+cl+' fx_overlay">'+
                '<div class="'+cl+'__wrapper">'+
                    '<div class="'+cl+'__image-container">'+
                        '<img src="'+src+'" class="'+cl+'__image" />'+
                    '</div>'+
                '</div>'+
                '<div class="'+cl+'__controls"></div>'+
                '<input type="hidden" value="" class="'+cl+'__value" />'+
            '</div>'
        ),
        $wrapper = $('.'+cl+'__wrapper', $popup),
        $img = $('.'+cl+'__image', $popup),
        $controls = $('.'+cl+'__controls', $popup),
        $cancel = $fx_fields.button({class:'cancel',label:'Отмена'}),
        $save = $fx_fields.button({label:'Готово',is_active:true}),
        $val = $('.'+cl+'__value', $popup);
        
    $popup.css('opacity', 0);
    $('body').append($popup);
    var c_color = current_crop && current_crop.color ? current_crop.color : '',
        $color = $fx_fields.color({
            name:'cropper-color', 
            value: c_color, 
            type:'color',
            alpha:false
        }),
        $color_input = $('.fx-colorpicker-input', $color);
    
    if (c_color) {
        $wrapper.css('background', c_color).addClass(cl+'__wrapper_has-color');
    }
    
    $controls.append($color);
    $controls.append($cancel).append($save);
    
    $cancel.click(function() {$popup.remove();});
    
    var  sides = {
        n: 'Y',
        e: 'X',
        s: 'Y',
        w: 'X'
    };
    
    function get_crop_data() {
        var crop = {},
            data = $img.cropper('getData');
    
        $.each(['width', 'height', 'x', 'y'], function() {
            crop[this] = Math.round( data[this] );
        });
        return crop;
    }
    
    var bounds = {};
    
    $img.cropper({
        //background: c_color ? false : true,
        modal:false,
        dragMode:'move',
        data:current_crop,
        aspectRatio:aspect_ratio,
        autoCropArea:1,
        movable:false,
        built: function(e) {
            var image = $img.cropper('getImageData'),
                container = $img.cropper('getContainerData'),
                ratio = image.naturalHeight / image.height;
            if (ratio < 1) {
                var image_data = {
                    width:image.naturalWidth,
                    height:image.naturalHeight,
                    top: (container.height - image.naturalHeight) / 2,
                    left: (container.width - image.naturalWidth) / 2
                },
                crop_data = get_crop_data();
                
                $img.cropper('setCanvasData', image_data);
                
                $img.cropper('setData', crop_data);
                /*
                if (current_crop) {
                    $img.cropper('setData', current_crop);
                }
                */
            }
            $popup.css('opacity', 1);
        },
        cropstart: function(e) {
            var canvas = $img.cropper('getCanvasData'),
                box = $img.cropper('getCropBoxData'),
                sx = e.originalEvent.pageX,
                sy = e.originalEvent.pageY;
        
            bounds = {
                    n: sy - (box.top - canvas.top),
                    e: sx - ((box.left + box.width) - (canvas.left + canvas.width)),
                    s: sy - ((box.top + box.height) - (canvas.top + canvas.height)),
                    w: sx - (box.left  - canvas.left)
                };
        },
        cropmove: function(e) {
            
            var oe = e.originalEvent;
            if (oe && oe.ctrlKey) {
                return;
            }
            if (!e.action || !oe) {
                return;
            }
            var tolerance = 15;

            var act = e.action === 'all' ? 'nesw' : e.action,
                offsets = {
                    X:[],
                    Y:[],
                    mapX:{},
                    mapY:{}
                };

            $.each(bounds, function(k, v) {
                var axis = sides[k],
                    val = oe['page'+axis],
                    diff = Math.abs(val - v);
                if (diff > tolerance) {
                    return;
                }
                offsets[axis].push(diff);
                offsets['map'+axis][diff] = v;
            });
            if (!offsets.X.length && !offsets.Y.length) {
                return;
            }

            var fe = $.extend(
                $.Event(oe.type), 
                oe, 
                {
                    preventDefault:function() {}
                }
            );

            if (offsets.X.length) {
                fe.pageX = offsets.mapX[ Math.min.apply(null, offsets.X) ];
            }
            if (offsets.Y.length) {
                fe.pageY = offsets.mapY[ Math.min.apply(null, offsets.Y) ];
            }
            e.preventDefault();
            $(oe.target).trigger(fe);
        },
        crop: function(e) {
            var crop = get_crop_data();
            $val.data( 'crop_data', crop );
        },
        zoom: function(e) {
            if (e.originalEvent) {
                e.preventDefault();
                if (e.ratio > 4) {
                    return;
                }
                var crop_data = get_crop_data();
                $img.cropper('zoomTo', e.ratio);
                $img.cropper('setData', crop_data);
            }
        }
    });
    window.$cropper = $img;
    $color_input.on('change', function() {
        var color = $color_input.val();
        $wrapper.css('background-color', color);
        $wrapper.toggleClass(cl+'__wrapper_has-color', color ? true : false);
    });
    
    $save.click(function() {
        var crop = $val.data('crop_data');
        var color = $color_input.val();
        if (color) {
            crop.color = color;
        }
        var data = {
            entity:'file',
            action:'save_image_meta',
            file: src,
            format: format,
            crop: JSON.stringify(crop)
        };
        $fx.post(data, function(res) {
            $popup.remove();
            handle_upload(res, $block);
        });
    });
}

$html.on('click.fx', '.fx_image_field .fx_file_uploader', function(e) {
    var $control = $(this);
    if (e.ctrlKey) {
        var $block = $control.closest('.fx_image_field'),
            $real_inp = $('.real_value', $block);
            
        load_cropper($real_inp);
        return false;
    }
    
    $control.closest('.fx_image_field').find('input.file').focus().click();
    $control.focus();
});

$html.on('click.fx', '.fx_image_field .fx_file_killer', function() {
   var $field = $(this).closest('.fx_image_field'); 
   $('.real_value', $field).val('').trigger('fx_change_file');
   $('.fx_file_input', $field).show();
   $('.fx_preview', $field).removeClass('fx_preview_filled');
   $(this).hide();
   $field.find('.fx_file_control:visible').last().focus();
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
            url:$fx.settings.action_link,
            type:'post',
            data: { entity:'file', fx_admin:1, action:'upload_save' , file:val, format:format},
            dataType: 'json',
            success: function ( data ) {
                handle_upload(data, $block);
                $inp.get(0).removeAttribute('disabled');
                $inp.val('').blur();
            },
            error: function( data ) {
                
                $inp.get(0).removeAttribute('disabled');
                $inp.val('').focus();
                var message = $fx.lang('Can not load this file');
                if (data && data.error_text) {
                    message += '<br />'+data.error_text;
                }
                $fx.alert(message, 'error');
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