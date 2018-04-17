(function($) {

var lastRowId = 0;
function newRowId () {
    lastRowId++;
    return 'new_' + lastRowId;
}

var fieldset = function(html, _c) {
    $('tbody.fx_fieldset_rows', html).sortable({
        handle: '>td:first-child',
        start: function (e, ui) {
            var $i = ui.item,
                iWidth = $i.width(),
                $p = ui.placeholder,
                $h = ui.helper,
                $iCells = $i.find('>td'),
                $pCells = $p.find('>td');

            $p.closest('table').width(iWidth);

            $i.css({
                'box-shadow': '1px 1px 3px 1px rgba(0,0,0,0.2)',
                'background': '#FFF'
            });

            $pCells.each(function(i, node) {
                $iCells.eq(i).width($(node).width())
            })

            $pCells.first().css('height', $i.height());

        },
        stop: function (e, ui) {
            var $row = $(ui.item),
                $group_row = $row.prevAll('[data-group_key]').first();
            if (!$group_row.length) {
                var $next_row = $row.nextAll('[data-group_key]').first();
                if ($next_row.length) {
                    $next_row.after($row);
                    $group_row = $next_row;
                }
            }
            if ($group_row) {
                var group_id = $group_row.data('group_key');
                append_group_value($row, group_id);
            }
            $row.attr('style', '');
            $row.find('>td').attr('style', '');
        }
    });

    var $fs = $('.fx_fieldset', html),
        $footer = $('.fx_fieldset__footer', $fs);

    if (!_c.values) {
        _c.values = _c.value || [];
    }
    if (!_c.name) {
        _c.name = '';
    }
    var set_path = _c.name.replace(/\]$/, '').split(/\]?\[/);

    var groupped_values,
        group = _c.group_by || {},
        group_field = group.field,
        group_type = group.type,
        group_values = group.values || [],
        group_allow_new = group.allow_new === "1"

    if (group_values.length) {
        groupped_values = []
        for (var i = 0; i < group_values.length; i++) {
            var group_items = [],
                group = group_values[i];
            for (var j = 0; j < _c.values.length; j++) {
                var val = _c.values[j]
                if ( (val._meta || {}).group_by_value === group[0]) {
                    group_items.push(val);
                }
            }
            if (group_items.length > 0) {
                groupped_values.push([group, group_items])
            }
        }
    } else {
        groupped_values = [[null, _c.values]];
    }

    var $rows_container = $('.fx_fieldset_rows', html);

    $.each(groupped_values, function(group_index, group_data) {
        var c_group = group_data[0];
        var c_values = group_data[1];
        if (c_group) {
            $rows_container.append(create_group_header(c_group))
        }
        $.each(c_values, function (row, val) {
            var inputs = [];
            var row_meta = val._meta || {},
                row_index = row_meta.id || newRowId() // 'new_ss_' + row;

            $.each(_c.tpl, function (tpl_index, tpl_props) {
                var c_val = val[tpl_props.name];
                if (
                    typeof c_val !== 'object' ||
                    (c_val && typeof c_val.value === 'undefined')
                ) {
                    c_val = {value: c_val};
                }
                var inp_props = $.extend(
                    {},
                    tpl_props,
                    {
                        name: _c.name + '[' + row_index + '][' + tpl_props.name + ']'
                    },
                    c_val
                );
                if (tpl_props.type === 'radio' && !tpl_props.values) {
                    inp_props.$input_node = $('<input type="radio" name="' + _c.name + '[' + tpl_props.name + ']" value="' + row_index + '" />');
                    if (tpl_props.value == row_index) {
                        inp_props.$input_node.attr('checked', 'checked');
                    }
                }
                inputs.push(inp_props);
            });
            var $row = $t.jQuery('fieldset_row', inputs, {index: row, set_field: _c});
            row_meta.row_index = row_index;
            $row.data('row_meta', row_meta);

            if (c_group) {
                append_group_value($row, c_group[0])
            }

            if (row_meta.type_id) {
                if ($fx.front) {
                    $fx.front.bind_content_form($row, row_meta.type_id, row_meta.id);
                }
            }
            $rows_container.append($row);

        });
    });

    function get_group_form_params () {
        return {
            content_type: group_type,
            onfinish: function(res) {
                var created = res.saved_entity
                group_values.push([created.id * 1, created.name])
                var ls = updateAdderLivesearch()
                ls.setValue(created.id)
            }
        }
    }

    function create_group_header (group) {
        var $group_header = $(
            '<tr class="fx_fieldset_group-header" data-group_key="'+group[0]+'">'+
            '<td></td>' +
            '<td colspan="'+(_c.tpl.length)+'">'+
            '<span class="fx_fieldset_group-header-name">' + group[1] + '</span>'+
            '</td>'+
            '</tr>'
        );
        $group_header.find('td:last-child').append(draw_item_adder());
        return $group_header;
    }
    var $adderLivesearch,
        $adderLink,
        defaultAdderLivesearchValue = {id: '', name: '-- добавить группу --'},
        adderLivesearch;
    if (!group_field) {
        $footer.append(draw_item_adder());
    } else {
        $adderLivesearch = $fx_fields.livesearch(
            {
                values: [defaultAdderLivesearchValue],
                allow_new: group_allow_new && get_group_form_params()
            },
            'input'
        );
        adderLivesearch = $adderLivesearch.data('livesearch');
        $adderLivesearch.addClass('fx_fieldset__adder-livesearch');
        $adderLivesearch.on('change', function(e) {
            var new_group_id = adderLivesearch.getValue() * 1,
                new_group = group_values.find(function(v) {
                    return v[0] === new_group_id
                });
            if (!new_group) {
                return
            }
            var $group_header = create_group_header(new_group);
            $rows_container.append($group_header);
            $group_header.find('.fx_fieldset__add a').first().click();
            adderLivesearch.setValue('');
            updateAdderLivesearch();
        })
        $footer.append($adderLivesearch);
        $adderLink = group_allow_new ? $('<a class="fx_fieldset_adder-link">Создать группу</a>') : $([]);
        $adderLink.on('click', function () {
            $fx.front.show_edit_form(get_group_form_params());
        })
        $footer.append($adderLink);
        updateAdderLivesearch();
    }

    function updateAdderLivesearch () {
        var values = [defaultAdderLivesearchValue]
        $.each(group_values, function(i, v) {
            var $c_group_header = $fs.find('[data-group_key="'+v[0]+'"]');
            if ($c_group_header.length === 0) {
                values.push({name: v[1], id: v[0]});
            }
        })
        var ls = $adderLivesearch.data('livesearch')
        // data-group_key
        ls.updatePresetValues(values);
        if (values.length > 1) {
            //$adderLivesearch.css('visibility', values.length > 1 ? 'visible' : 'hidden');
            //$adderLivesearch.css('visibility', 'visible');
            $adderLivesearch.css('display', 'inline-block');
            $adderLink.hide();
        } else {
            $adderLivesearch.css('display', 'none');
            $adderLink.show();
        }
        return ls
    }

    function append_group_value($row, group_id) {
        var cl = 'fieldset_row__group-value-input';
        var $group_value_input = $row.find('.'+ cl);
        if (!$group_value_input.length) {
            var row_index = ($row.data('row_meta') || {}).row_index;
            $group_value_input = $(
                '<input type="hidden" name="' + _c.name + '[' + row_index + '][' + group_field + ']" />'
            );
            $group_value_input.addClass(cl);
            $row.find('>td').first().append($group_value_input);
        }
        $group_value_input.val(group_id);
    }

    function append_row_values(vals, $row) {
        var row_meta = $row.data('row_meta') || {},
            row_index = row_meta.id;

        $.each(vals, function (prop, val) {
            var c_name = _c.name + '[' + row_index + '][' + prop + ']',
                $c_inp = $row.find('[name="' + c_name + '"]');

            if ($c_inp.length === 0) {
                $c_inp = $('<input type="hidden" name="' + c_name + '" />');
                $row.find('>td').first().append($c_inp);
            }

            $fx_fields.set_value($c_inp, val);
        });
    }


    html.on('click', '.fx_icon-type-edit', function (e) {
        var $row = $(e.target).closest('.fx_fieldset_row'),
            row_meta = $row.data('row_meta') || {},
            content_id = !row_meta.id || (row_meta.id + '').match(/^new/) ? null : row_meta.id,
            c_vals = $row.formToHash();

        for (var j = 0; j < set_path.length; j++) {
            c_vals = c_vals[set_path[j]];
        }

        c_vals = c_vals[row_meta.id];

        var edit_form_params = {
            content_id: content_id,
            content_type: row_meta.type,
            entity_values: c_vals,
            onsubmit: function (e) {
                var $form = $(e.target),
                    new_vals = $form.formToHash();

                append_row_values(new_vals.content, $row);

                $fx.front_panel.hide();
                return false;
            }
        };

        if (_c.relation) {
            edit_form_params.relation = JSON.stringify(_c.relation);
        }

        edit_form_params.parent_form_data = JSON.stringify($row.closest('form').formToHash());
        $fx.front.show_edit_form(edit_form_params);

    });

    function draw_item_adder () {
        if ( _c.types && _c.without_add === undefined ) {
            var res = '<div class="fx_fieldset__add">';

            if (_c.types.length < 2) {
                res += '<a tabindex="0">'+$fx.lang('Add')+'</a>';
            } else {
                res += '<span>'+ $fx.lang('Add') +' </span>';
                for (var i = 0; i < _c.types.length; i++) {
                    var c_type = _c.types[i];
                    res += '<a tabindex="0">'  +c_type.name_add +'</a>';
                }
            }
            res += '</div>'
        }
        return $(res);
    }

    function remove_row($row) {
        var $next_row = $row.next('.fx_fieldset_row');
        $row.remove();
        if ($next_row.length > 0) {
            $next_row.find(':input, .fx_fieldset_remove').first().focus();
        } else {
            $('.fx_fieldset_add', $fs).focus();
        }
        var $headers = $('.fx_fieldset_group-header', $fs),
            $emptyHeaders = $([])
        $headers.each(function(i, h) {
            var $h = $(h);
            if (!$h.next().hasClass('fx_fieldset_row')) {
                $emptyHeaders = $emptyHeaders.add($h);
            }
        });
        $emptyHeaders.remove();
        updateAdderLivesearch();
    }

    function add_row(type) {
        var inputs = [],
            index = $('.fx_fieldset_row', $fs).length + 1,
            id = newRowId(), // 'new_' + index,
            row_meta = {
                type_id: type.content_type_id,
                type: type.keyword,
                id: id,
                row_index: id
            };

        for (var i = 0; i < _c.tpl.length; i++) {
            var input_props = {
                name: _c.name + '[' + id + '][' + _c.tpl[i].name + ']'
            };
            if (_c.tpl[i].name === 'type') {
                input_props.value = type.name;
            }
            inputs.push(
                $.extend(
                    {},
                    _c.tpl[i],
                    input_props
                )
            );
        }
        var $new_row = $t.jQuery('fieldset_row', inputs, {index: index, set_field: _c});
        $new_row.data(
            'row_meta',
            row_meta
        );

        setTimeout(function() {
            $new_row.find(':input:visible').first().focus();
        },50);
        if (row_meta.type) {
            $new_row.append(
                '<input type="hidden" name="' + _c.name + '[' + id + '][type]" value="' + row_meta.type + '" />'
            );
        }

        if (row_meta.type_id && $fx.front) {
            $fx.front.bind_content_form($new_row, row_meta.type_id, row_meta.id);
        }
        return $new_row;
    }

    $fs.on('click', '.fx_fieldset_remove', function () {
        remove_row($(this).closest('.fx_fieldset_row'));
    });
    $fs.on('keydown', '.fx_fieldset_remove', function (e) {
        if (e.which === 32 || e.which === 13) {
            remove_row($(this).closest('.fx_fieldset_row'));
            return false;
        }
    });
    var addable_types = _c.types;

    $fs.on('click', '.fx_fieldset__add a', function (e) {
        var $btn = $(e.target),
            index = $btn.parent().find('a').index($btn),
            $group_row = $btn.closest('.fx_fieldset_group-header'),
            type_meta = addable_types && index !== -1 && addable_types[index] ? addable_types[index] : {};

        var $new_row = add_row(type_meta);

        var $next_group_row = $group_row.nextAll('.fx_fieldset_group-header').first();

        if (!$group_row.length || !$next_group_row.length) {
            $rows_container.append($new_row);
        } else {
            $next_group_row.before($new_row);
        }
        if ($group_row.length) {
            append_group_value($new_row, $group_row.data('group_key'));
        }
    }).on('keydown', '.fx_fieldset__add a', function (e) {
        if (e.which === 32 || e.which === 13) {
            //add_row();
            $(this).click();
            return false;
        }
    });
}


window.fieldset = fieldset;

})($fxj)