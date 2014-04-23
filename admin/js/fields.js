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
})($fxj);