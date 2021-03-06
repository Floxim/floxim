(function($) {
fx_buttons = function ( source ) {
    this.source = source;
    this.container = $('#fx_admin_buttons');
    
    this.pulldown = {};
    this.pulldown_is_hide = true;
    this.buttons_action = {};
        
    var self = this;
    $fx.panel.bind('fx.admin_load_page', function(event, data){
        self.pulldown = {};
        self.buttons_action = {};
        if ( data.buttons_pulldown ) {
            self.pulldown = data.buttons_pulldown;
        }
        if ( data.buttons_action ) {
            self.buttons_action = data.buttons_action;
        }
    });
    
    $fx.panel.bind('fx.click', function(event,owner){
        if ( owner !== 'button_pulldown') {
            self.hide_pulldown();
        } 
    });
    
};

fx_buttons.prototype.bind = function(button_key, callback) {
    var b = $('.fx_admin_button_'+button_key);
    b.show();
    b.data('has_callback', true);
    b.unbind('click');
    b.click(function() {
        callback();
        return false;
    });
};

fx_buttons.prototype.is_active = function(button_key) {
    return $('.fx_admin_button_'+button_key).data('has_callback');
};

fx_buttons.prototype.trigger = function(button_key) {
    $('.fx_admin_button_'+button_key).trigger('click');
};

fx_buttons.prototype.unbind = function(button_key, callback) {
    var b = $('.fx_admin_button_'+button_key);
    b.data('has_callback', null);
    b.hide();
    b.unbind('click', callback);
};


fx_buttons.prototype.draw_buttons = function ( buttons ) {
    var element,self = this;
    this.container.html('');

    if ( buttons === undefined ) {
        return false;
    }
    $.each(buttons, function(key, button) {
        var button_source = self.source[typeof button === 'string' ? button : button.key];
        if (!button_source && button.type === undefined) {
            button.type = 'text';
            button_source = {type:'text', title:key};
        }
        if (typeof button !== 'object') {
            button = {
                key:button,
                type:button_source.type,
                title:button_source.title
            };
        } else if (!button.type) {
            button.type = button.key === 'delete' ? 'icon' : 'text';
        }
        

        element = $('<div class="fx_button fx_button-key-'+button.key+'" title="'+button.title+'"></div>');
        if (button.type === 'text' ) {
            element.html( '<span>'+button.title+'</span>' );
        } else {
            element.addClass('fx_button-with_icon').html('<span class="fx_icon fx_icon-type-'+button.key+'"></span>');
        }

        element.data(button);
        element.click( function () {
            if ($(this).data('has_callback')) {
                return;
            }
            self.handle(button.key, button);
            return false;
        });
        element.hide();
        
        self.container.append(element);
    });
};

fx_buttons.prototype.set_active_buttons = function ( buttons ) {
    $('div', this.container).each(function() {
        var $this = $(this);
        var key = $this.data('key');
        if (key  === 'more' || key === 'divider') {
            return true;
        }
        if ($.inArray(key, buttons) === -1 ) {
            $this.hide();
        } else {
            $this.show();
        }
    });
};

fx_buttons.prototype.update_button = function ( btn, settings) {
    var button = $(".fx_admin_button_" + btn);
    if ( settings.title !== undefined ) {
        button.attr('title',settings.title );
    }
            
    if ( settings.available !== undefined ) {
        if ( settings.available ) {
            button.show();
        }
        else {
        	button.hide();
        }
    }
};

fx_buttons.prototype.show_panel = function () {
    this.container.show();
};

fx_buttons.prototype.hide_panel = function () {
    this.container.hide();
};

/**
 * Primary processing pressing, for example, the pop-up menu
 * the very pressure processing - elsewhere
 */
fx_buttons.prototype.handle = function ( button, button_data ) {
    if ( this.pulldown[button] ) {
        if ( this.pulldown_is_hide ) {
            this.show_pulldown(button, this.pulldown[button]);
        } else {
            this.hide_pulldown();
        }
        $fx.panel.trigger('fx.click', 'button_pulldown');
        return false;
    }
    
    var button_action = this.buttons_action[button];
    if ( button_action ) {
        if ( button_action.url ) {
            window.location = button_action.url;
            return false;
        }
    }
    if (button === 'delete') {
        button = {key:button, params:button_data.params};
    }
    if (button.key === 'delete' && confirm('Are you sure?')) {
        var sel = $('.fx_admin_selected');
        if (sel.length === 0) {
            return;
        }
        var cid = sel.data('id');
        if (cid.name) {
            cid = cid.name;
        }
        var opts = {
            entity:sel.data('entity'),
            action:'delete',
            id:cid,
            posting:true
        };
        if (button.params) {
            opts = $.extend({}, opts, button.params);
        }
        $fx.post(
            opts, 
            function(res) {
                if (res.fields) {
                    $fx.admin.load_page(res);
                } else {
                    $(window).hashchange();
                }
            }
        );
        return false;
    }
};

fx_buttons.prototype.show_pulldown = function ( button, data ) {
    var container = $('<div class="fx_admin_pull_down_menu"/>');
    var item;
    $.each( data, function (i,v) {
        if ( v === 'divider') {
            item = $('<span>').addClass('fx_admin_pull_down_divider');
        }
        else {
            var link_name = v.name || '[NoName]';
            item = $('<span/>').html(link_name).click(v.callback);
        }
        container.append(item);
    });
    
    var pos = $('.fx_admin_button_'+button).offset();
    pos.top -= $(document).scrollTop();
    container.css('left', pos.left).css('top', pos.top+25).appendTo($('#fx_admin_control'));
};

fx_buttons.prototype.hide_pulldown = function () {
	$('.fx_admin_pull_down_menu').remove();
}

/*
 * Click handler for the button in the form lists, private input
 */
fx_buttons.prototype.form_button_click = function(e) {
    var data = $t.inline_data($(this));
    if ( data.postdata || data.post ) {
        var postdata = data.postdata || data.post;
        if (data.send_form) {
            var form = $(this).closest('form');
            var formdata = {};
            $.each(form.serializeArray(), function(num, field) {
                    formdata[field.name] = field.value;
            });
            postdata = $.extend(formdata, postdata);
        }
        $fx.post(postdata);
        console.log('r1');
        return false;
    }
    if (data.func) {
        fx_call_user_func(data.func,data);
    }
    if (data.url) {
        document.location.hash = $fx.mode + '.' + data.url.replace(/^#/, '');
    }
    var $button = $(this),
        $target = $(e.target),
        href = $button.data('href');
    
    if ($button.is('.fx_button-has_dropdown')) {
        if (!$button.is('.fx_button-has_own_action') || $target.is('.fx_button__arrow')) {
            var $dd = $('.fx_dropdown', $button);
            if ($dd.is(':visible')) {
                $dd.hide();
            } else {
                $dd.show();
            }
            return;
        }
        return;
    }
    
    if (href) {
        document.location.href = href;
    }
};

fx_buttons.prototype.update_available_buttons = function () {
    var btn, selected = $('.fx_admin_selected', '#fx_admin_content');
    var len = selected.length;

    if ( !len ) {
        btn = [];
    } else if ( len === 1 ) {
        btn = ['edit', 'settings','on','off', 'delete', 'rights', 'change_password', 'map', 'export', 'download'];
    } else {
        btn = ['on','off','delete'];
    }
    btn.push('add', 'upload', 'import', 'store');
    
    
    if ( len >= 1 ) {
        $.each (selected, function(k,v){
           var  not_available_buttons = $(v).data('fx_not_available_buttons');
           if ( not_available_buttons ) {
               btn = array_diff(btn,not_available_buttons);
           }
        });
    }
    $fx.buttons.set_active_buttons(btn);
};
})($fxj);