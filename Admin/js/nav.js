 (function($) {

fx_additional_menu = function ( ) {
    this.container = $('#fx_admin_additional_menu');
};

fx_additional_menu.prototype.load = function () {
    var $logout = this.container.find('a.fx_logout');
    $logout.click(function() {
       $(this).append(
        '<form action="'+$logout.data('url')+'" method="POST" style="width:1px; height:1px; overflow:hidden;">'+
            '<input type="hidden" name="entity" value="module_auth" />' +
            '<input type="hidden" name="action" value="logout" />' +
            '<input type="submit" />' +
        '</form>');
        $(this).find('form').submit();
    });
}

fx_breadcrumb = function () {
    this.container = $('#fx_admin_breadcrumb');
}

fx_breadcrumb.prototype.load = function (data) {
    var self = this, title_part = [];
    this.container.html('');

    if ( data ) {
        var count = data.length;
        $.each(data, function(key, item){
        	var is_last = !(key < count - 1);
        		
            var element = $('<a/>').text(item.name);
            title_part.push(item.name);
            
            if (item.href && !is_last) {
            	var href = /^#admin\./.test(item.href) ? item.href : '#admin.'+item.href;
            	if (href.replace(/^#/, '') != document.location.hash.replace(/^#/, '')) {
            		element.attr('href', href);
            	}
            }
            
            if (is_last) {
                element.addClass('last');
            }
            
            self.container.append(element);
            if (!is_last) {
                self.container.append('<span>/</span>');
            }
        });
            
        // title
        if ( $fx.mode && $fx.mode == 'admin') {
            $("title").html( title_part.reverse().join(' / '));
        }
    }
  
}

fx_main_menu = function ( data ) {
    this.container = $('#fx_admin_main_menu');
    this.data = data;
}

fx_main_menu.prototype.load = function () {
    this.load_items();
}

fx_main_menu.prototype.load_items = function () {
    var i=0, count = fx_object_length(this.data), self = this;
    $.each( this.data, function (key, item){
        var node = $('<div class="fx_admin_main_menu_item"></div>').appendTo(self.container);
        $('<a/>').data('key', key).text(item.name).attr('href', item.href).appendTo(node);
        if ( i++ < count-1 ) {
            self.container.append('<span class="fx_main_menu_separator" />');
        }
        if (item.children) {
            node.append(
                '<span class="fx_admin_more_menu_after_container">'+
                    '<span class="fx_admin_more_menu_after"></span>'+
                '</span>'
            );
            var sub = '<div class="fx_main_submenu">';
            $.each(item.children, function(sub_key, sub_item) {
                sub += '<div class="fx_main_subitem">'+
                            '<a href="'+sub_item.href+'">'+sub_item.name+'</a>'+
                       '</div>';
            });
            sub += '</div>';
            node.append(sub);
        }
    });
    this.container.on('click', '.fx_admin_more_menu_after_container', function() {
        var arrow = $('.fx_admin_more_menu_after', this);
        var item = arrow.closest('.fx_admin_main_menu_item');
        var sub = $('.fx_main_submenu', item);
        var item_offset = item.offset();
        sub.css({
            position:'absolute',
            left: item_offset.left + 'px'
        });
        sub.show();
        if (sub.width() < item.width()) {
            sub.css({width: item.width()+'px'});
        }
        arrow.addClass('fx_admin_more_menu_after_active');
        $fx.panel.one('fx.click', function() {
            sub.hide();
            arrow.removeClass('fx_admin_more_menu_after_active');
            return false;
        });
        return false;
    });
}

fx_main_menu.prototype.set_active_item = function (item ) {
    var elements = $('a', this.container);
    elements.removeClass('fx_admin_main_menu_active'); 
    $.each( elements, function (k,v){
        v = $(v);
        if ( v.data('key') == item ) {
            v.addClass('fx_admin_main_menu_active');
            return false;
        }
    });
}

     
fx_mode_menu = function ( ) {
    this.container = $('#fx_admin_page_modes');
    this.data =  {
        "view" : {
            "name" : $fx.lang("View"),
            "href" : "view"
        },
        "edit" : {
            "name" : $fx.lang("Edit"),
            "href" : "edit"
        }, 
        "design" : {
            "name" : $fx.lang("Design"),
            "href" : "design"
        }
    };
};

fx_mode_menu.prototype.load = function ( active ) {
    var cont = this.container.html('');
    $.each(this.data, function(key, value) {
        var $mode = $('<a/>').
                        data('key', value.href).
                        text(value.name).
                        attr('href', '#page.'+value.href ).
                        appendTo(cont);
        if (value.href === 'edit') {
            $mode.attr('title', 'F2');
        } else if (value.href === 'design') {
            $mode.attr('title', 'F4');
        }
    });
}
fx_mode_menu.prototype.set_active = function ( active ) {
    $('.fx_admin_page_modes_active').removeClass('fx_admin_page_modes_active');
    $('.fx_admin_page_modes_arrow').remove();
    $('.fx_admin_page_modes_line').remove();
    
    var arrow = $("<span>").addClass('fx_admin_page_modes_arrow');
    var line = $("<span>").addClass('fx_admin_page_modes_line');
    
    $.each( $('a', this.container), function(key, value) {
        var item = $(this);
        if ( active ===  item.data('key') ) {
            item.addClass('fx_admin_page_modes_active').append(arrow).append(line);
            arrow.css('left', 0.5*item.width()-3); // 3 - half-width of the arrow
            line.width(item.width() + parseInt(item.css('margin-left')) + parseInt(item.css('margin-right')) );
        }
    });
}

/* Menu "More" */
fx_more_menu = function ( data ) {
    this.container = $('#fx_admin_more_menu');
    this.data = data;
    this.name = $fx.lang('More');
    this.is_hide = true;
}

fx_more_menu.prototype.set_name = function (name) {
    this.name = name;
}

fx_more_menu.prototype.load = function () {
    this.load_items();
    this.load_handlers();
}

fx_more_menu.prototype.load_items = function () {
    this.prepend = $('<span>').addClass('fx_admin_more_menu_prepend').appendTo(this.container);
    this.button = $('<span>').addClass('fx_admin_more_menu_button').text( this.name).appendTo(this.container);
    this.after = $('<span>').addClass('fx_admin_more_menu_after').appendTo(this.container);
    this.menu = $('<div/>').appendTo(this.container);
    var self = this;
    
    $.each(this.data, function(k, item){
        var element = $('<span>').text(item.name).appendTo(self.menu);
        element.click( function(){
            self.hide();
            if (item.button && typeof item.button == 'object') {
                item.button._base_url = document.location.href;
                $fx.post(item.button,
                function(json) {
                    $fx.front_panel.show_form(json, {
                        'onfinish': function () {
                            $fx.front.reload_layout();
                        }
                    });
                });
            }
            return false;
        });
    });
}

fx_more_menu.prototype.load_handlers = function () {
    var self = this;
    
    this.button.click( function() {
        if ( self.is_hide ) {
            self.show();
        }
        else {
            self.hide();
        }
        
        $fx.panel.trigger('fx.click', 'more_menu');
        return false;
    });
    
    $fx.panel.bind('fx.click', function(event,owner){
        if ( owner != 'more_menu') {
            self.hide();
        } 
    });
}

fx_more_menu.prototype.show = function () {
    this.is_hide = false;
    this.menu.show();
    this.prepend.css("visibility", "hidden");
    this.button.addClass('fx_admin_more_menu_button_active');
    this.after.addClass('fx_admin_more_menu_after_active');
}

fx_more_menu.prototype.hide = function () {
    this.is_hide = true;
    this.menu.hide();
    this.prepend.css("visibility", "visible");
    this.button.removeClass('fx_admin_more_menu_button_active');
    this.after.removeClass('fx_admin_more_menu_after_active');
}


fx_submenu = function ( ) {
    this.container = $('#fx_admin_submenu');
    this.type = '';
}

fx_submenu.prototype.load = function ( data ) {
    if ( !data ) {
        return;
    }
 
    if ( !data.not_update ) {
        this.show_items(data);
    }
    this.set_active(data.active, data.subactive);
    
}

fx_submenu.prototype.show_items = function ( data ) {
    var self = this;
    var href,cont = this.container;
    
    this.container.html('');
    
    if ( data.error ) {
        this.container.append( $('<b/>').text(data.error) ); 
    }
    
    this.type = data.type;
    if ( data.type == 'full' ) {
        if ( data.backlink ) {
            this.container.append( $('<a/>').addClass('fx_admin_submenu_backlink').attr('href', '#admin.'+data.backlink)); 
        }
        if ( data.title ) {
            this.container.append( $('<h2/>').text(data.title) ); 
        }
        
        cont = $('<div />').addClass('fx_admin_submenu_items_full').appendTo(this.container);
        var free_children = {};
        if ( data.items ) {
            $.each ( data.items, function(k, item){
                href = '#admin.'+item.href;
                var item_link = $("<a/>").attr('href', href).attr('id', 'fx_admin_submenu_'+item.id).data('id', item.id).text(item.name);
                if (item.parent) {
                	item_link.addClass('fx_admin_submenu_child');
                	var parent_node = $('#fx_admin_submenu_'+item.parent);
                	if (parent_node.length > 0) {
                		parent_node.addClass('fx_admin_submenu_parent').after(item_link);
                	} else {
                		if (!free_children[item.parent]) {
                			free_children[item.parent] = [];
                		}
                		free_children[item.parent].push(item_link);
                	}
                } else {
                	item_link.appendTo(cont);
                	if (free_children[item.id]) {
                		$.each(free_children[item.id], function(chk, child) {
                			item_link.after(child);	
                		});
                	}
                }
            });
        }
        
    }
    else {
        if ( data.items ) {
            $.each ( data.items, function(k, item){
                cont = self.get_container(item.parent);
                href = '#admin.'+item.href;
                $("<a/>").attr('href', href).data('id', item.id).text(item.name).appendTo(cont);
            });
        }

    }
}

fx_submenu.prototype.get_container = function( parent_id ) {
    var cont, id = '__fx__'+parent_id;
    if (!parent_id ) {
        if ( $('.fx_admin_submenu_level0').length > 0 ) {
            cont = $('.fx_admin_submenu_level0');
        }
        else {
            cont = $('<div />').addClass('fx_admin_submenu_level0').appendTo(this.container);
        }
    }
    else {
        if ( $("#"+id).length > 0 ) {
            cont = $("#"+id);
        }
        else {
            cont = $('<div id="'+id+'" />').addClass('fx_admin_submenu_level1').appendTo(this.container);
        }
    }
    
    return cont;
}

fx_submenu.prototype.set_active = function( active, subactive ) {
    $('.fx_admin_submenu_active').removeClass('fx_admin_submenu_active');
    $('a', $('.fx_admin_submenu_level0')).each(function(){
        if ( $(this).data('id') == active ) {
            $(this).addClass('fx_admin_submenu_active');
        } 
    });
    
    $('a', $('.fx_admin_submenu_level1')).each(function(){
        if ( $(this).data('id') == subactive ) {
            $(this).addClass('fx_admin_submenu_active');
        } 
    });
    if ( this.type == 'full' ) {
        $('a', this.container).each(function(){
            if ( $(this).data('id') == subactive ) {
                $(this).addClass('fx_admin_submenu_active');
            } 
        });
    }
    
    $('.fx_admin_submenu_level1', this.container).hide();
    if ( active ) {
        $('#__fx__'+active).show();
    } 
    
}



     
 })($fxj);