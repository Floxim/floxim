/**
 * misc scripts related to infoblock management
 */


(function ($) {

function init_ib_fields() {
    $('.fx_field_limit').each(function() {
        
    });
}

window.fx_controller_tree = function(html) {
    function select_variant($variant) {
        var id = $variant.data('id');
        var $input = $('.tree_value_input');
        $input.val(id);
        $input.closest('form').submit();
    }
    
    function expand_group($group) {
        $group.addClass('fx_controller_group-active');
        var $children = $('.fx_controller_group__children', $group);
        $children.slideDown();
    }
    
    function collapse_group($group) {
        $group.removeClass('fx_controller_group-active');
        var $children = $('.fx_controller_group__children', $group);
        $children.slideUp();
    }
    
    $(html).click(function(e) {
        var $t = $(e.target);
        var $variant = $t.closest('.fx_controller_variant');
        if ($variant.length) {
            select_variant($variant);
            return false;
        }
        var $group = $t.closest('.fx_controller_group');
        if ($group.length) {
            if ($group.hasClass('fx_controller_group-active')) {
                collapse_group($group);
                return false;
            }
            var $variants = $('.fx_controller_variant', $group);
            if ($variants.length === 1) {
                select_variant($variants);
                return false;
            }
            expand_group($group);
        }
    });
};

window._fx_controller_tree = function (html) {
    
    setTimeout(function() {
        $('#fx_admin_extra_panel .fx_button').not('.fx_admin_button_cancel').hide();
        var $filters = $('.filter_set', html),
            $header = $filters.closest('form').find('.form_header');
        $header.append($filters);
    }, 100);
    
    
    $('.filter_controller', html).closest('.filter_box').hide();
    $('.filter_type', html).closest('.filter_box').hide();
   
    var $filters = $('.filter_set .filter', html),
        $filter_search = $('.filter_search input', html);
    
    var $filter_dropper = $('.drop_filters a', html);
    $filter_dropper.click(function() {
        $filter_search.val('');
        $(':input', $filters).val('');
        //$('.filter_controller', html).val('');
        //$('.filter_type', html).val('');
        filter_controllers();
    });
    
    function filter_controllers() {
        var subs = $('.fx_sub', html);
        var term = $filter_search.val();
        var has_filters = term.length > 0;
        subs.show();
        $filters.each(function() {
            var filter = $(this).data('filter');
            var filter_value = $(':input', this).val();
            if (filter_value === '') {
                return;
            }
            if (filter === 'controller') {
                filter_value = filter_value.replace(/\./g, '__');
            }
            has_filters = true;
            var selector = '.'+filter+'_'+filter_value;
            subs.not(selector).hide();
        });
        if (!has_filters) {
            subs.filter('.fx_group_hidden').hide();
            $filter_dropper.hide();
        } else {
            subs.filter('.fx_sub_group').hide();
            $filter_dropper.show();
        }
        
        
        if (term) {
            var words = term.split(' ');
            var rex = [];
            for (wi = 0; wi < words.length; wi++) {
                rex.push(new RegExp(words[wi], 'i'));
            }
            subs.filter(':visible').each(function() {
                var c_text = $(this).text();
                for (var ri = 0; ri < rex.length; ri++) {
                    if (!c_text.match(rex[ri])) {
                        $(this).hide();
                    }
                }
            });
        }
        $('.fx_controller_list_table').css('left', 0);
        //recount_buttons();
    }

    $('.fx_controller_groups', html).on('change', function() {
        filter_controllers();
    });

    $('.filter_search input', html).on('keyup', function() {
        filter_controllers();
    });


    $('.fx_controller_list', html).on('click', '.fx_sub', function() {
        var sub = $(this);
        
        if (sub.hasClass('fx_sub_group')) {
            var group_id = sub.data('id');
            $('.filter_controller').val(group_id);
            filter_controllers();
            return;
        }
        
        var inp = html.find('.tree_value_input');
        html.find('.fx_controller_list .fx_admin_selected').removeClass('fx_admin_selected');
        sub.addClass('fx_admin_selected');
        inp.val(sub.data('id'));
        $('#fx_admin_extra_panel .fx_button').eq(1).click();
    });
};

window.fx_controller_tree.get_color = function(item_id) {
    var makeCRCTable = function(){
        var c;
        var crcTable = [];
        for(var n =0; n < 256; n++){
            c = n;
            for(var k =0; k < 8; k++){
                c = ((c&1) ? (0xEDB88320 ^ (c >>> 1)) : (c >>> 1));
            }
            crcTable[n] = c;
        }
        return crcTable;
    };

    var getColors = function() {
        if (window.fx_controller_colors) {
            return window.fx_controller_colors;
        }
        var rgbs = ['00', '33', '66', '88', 'AA', 'CC', 'EE', 'FF'];
        var res = [];
        for (var a = 0; a < rgbs.length; a++) {
            for (var b = 0; b < rgbs.length; b++) {
                for (var c = 0; c < rgbs.length; c++) {
                    if (a === b && b === c) {
                        continue;
                    }
                    var cr = rgbs[a];
                    var cg = rgbs[b];
                    var cb = rgbs[c];
                    res.push ('#'+cr + cg + cb);
                }
            }
        }
        window.fx_controller_colors = res;
        return res;
    };

    var crc32 = function(str) {
        var crcTable = window.crcTable || (window.crcTable = makeCRCTable());
        var crc = 0 ^ (-1);

        for (var i = 0; i < str.length; i++ ) {
            crc = (crc >>> 8) ^ crcTable[(crc ^ str.charCodeAt(i)) & 0xFF];
        }

        return (crc ^ (-1)) >>> 0;
    };

    var inv_color = function(hexcolor) {
        var r = parseInt('0x'+hexcolor.slice(1,3), 16);
        var g = parseInt('0x'+hexcolor.slice(3,5), 16);
        var b = parseInt('0x'+hexcolor.slice(5,7), 16);
        return (r+g+b < 765/1.6) ? 'white' : 'black';
    };
    
    var colors = getColors();
    
    var crc = crc32(item_id);
    var bg_color = colors [ crc % colors.length ];
    var text_color = inv_color(bg_color);
    
    return {
        bg:bg_color,
        text:text_color
    };
};
    
})($fxj);