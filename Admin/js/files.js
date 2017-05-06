(function($) {


function fc($node, params) {
    this.$node = $node;
    this.$params = params;
    $node.data('file_control', this);
    this.init();
}

fc.prototype.handle_upload = function(data) {
    if (data.format === 'fx-response') {
        data = data.response;
    }
    
    var $block = this.$node;
    
    var $res_inp = $('.real_value', $block);
    var field_type = $block.data('field_type');
    $res_inp.val(data.path);
    var $panel = $res_inp.closest('.fx_node_panel');
    if ($panel.length === 0) {
        if (field_type === 'image') {
            $('.fx_preview img', $block).attr('src', data.path+'?r='+Math.random()).show();
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
    $res_inp.trigger('change');
};


fc.prototype.init = function() {
    
    var $n = this.$node,
        that = this;
    
    $n.on('keydown', '.fx_file_control', function(e) {
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
    
    $n.on('click', '.fx_remote_file_block',  function() {
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
    
    $n.on('blur.fx', '.fx_remote_file_block input', function() {
        var $inp = $(this);
        if ($inp.attr('disabled') !== undefined) {
            return;
        }
        $inp.closest('.fx_remote_file_block').removeClass('active');
        $inp.closest('.fx_preview').removeClass('fx_preview_active');
    });
    
    
    $n.on('change.fx', 'input.file', function(e) {
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
                that.handle_upload(data);
            }
        });
        e.stopImmediatePropagation();
        return false;
    });
    
    
    $n.on('click.fx', '.fx_file_uploader', function(e) {
        var $control = $(this);
        $control.closest('.fx_image_field').find('input.file').focus().click();
        $control.focus();
    });

    $n.on('click.fx', '.fx_image_cropper', function(e) {
        var $real_inp = 
                $(this)
                    .closest('.fx_image_field')
                    .find('.real_value');

        load_cropper($real_inp);
        return false;
    });

    $n.on('click.fx', '.fx_file_killer', function() {
       var $field = $(this).closest('.fx_image_field'); 
       $('.real_value', $field).val('').trigger('fx_change_file');
       $('.fx_file_input', $field).show();
       $('.fx_preview', $field).removeClass('fx_preview_filled');
       $(this).hide();
       $field.find('.fx_file_control:visible').last().focus();
    });

    $n.on('paste', '.remote_file_location', function() {
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
                    that.handle_upload(data, $block);
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
};

window.fx_file_control = fc;

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
        src = meta.http || $inp.val(),
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
            var file_control = $block.data('file_control');
            file_control.handle_upload(res, $block);
        });
    });
}

})($fxj);