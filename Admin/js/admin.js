(function($) {
fx_admin = function () {
    this.submenu = new fx_submenu();
    this.breadcrumb = new fx_breadcrumb();
    this.entity = '';
    this.action = '';
    this.menu_id = '';
    this.last_post = {};
    this.bind_actions();
};

fx_admin.prototype.bind_actions = function () {
    var self = this;
    $fx.panel.bind('fx.dialog.ok', function(event, data) {
        if ( $fx.mode === 'admin' ) {
            $fx_dialog.main.dialog('close');
            var post = self.last_post;
            post.menu_id = '';
            $fx.post(post, self.load_page );
        }
    });
};

fx_admin.prototype.load = function () {
    var self = this;
    
    if ( this.entity && this.action ) {
        var post = {
            entity: this.entity,
            action: this.action, 
            menu_id: this.menu_id,
            params: $fx.hash_param
        };
        self.last_post = post;
        $fx.post(post, self.load_page );
    }
};

fx_admin.prototype.load_page = function ( data ) {
    var self = $fx.admin;
    
    $fx.admin.submenu.load(data.submenu);
    /*
    if ( data.main_menu  ) {
        $fx.main_menu.set_active_item(data.main_menu.active);
    }
    */
    self.menu_id = data.submenu.menu_id;

    $fx.admin.breadcrumb.load(data.breadcrumb);
    $fx.panel.trigger('fx.admin_load_page', data);
                                     
    if (data.fields === undefined) data = {
        fields:[data]
    };
    
    data.form = {
        id:'fx_admin_content_form'
    };
    var $container = $('#fx_admin_content');
    $container.fx_create_form(data);
    $container.trigger('fx_render');
    function set_admin_content_height() {
        $('#fx_admin_content').height(
                $(window).height() - $("#fx_admin_content").offset().top
        );
        document.body.scrollTop = 0;
    }
    setTimeout(
        set_admin_content_height,
        100
    );
    $(window).resize(set_admin_content_height);
    $('html').scrollTop(0);
};


fx_admin.prototype.set_entity = function ( entity ) {
    this.entity = entity;
};
fx_admin.prototype.get_entity = function () {
    return this.entity;
};

fx_admin.prototype.set_action = function ( action ) {
    this.action = action;
};
fx_admin.prototype.get_action = function () {
    return this.action;
};

$(function() {
    var cl = 'fx-backend-login';
    var $block = $('.'+cl);
    if ($block.length === 0) {
        return;
    }
    var $auth = $block.find('.'+cl+'__auth');
    var $recover = $block.find('.'+cl+'__recover');
    
    function show_recover() {
        $auth.hide();
        $recover.show().find(':input:visible').first().focus();
    }
    function show_login() {
        $auth.show().find(':input:visible').first().focus();
        $recover.hide();
    }
    $block
        .on('click', '.'+cl+'__recover-link', show_recover)
        .on('click', '.'+cl+'__login-link', show_login);
    
    if ( $('.fx_form_sent', $recover).length > 0) {
        show_recover();
    } else {
        show_login();
    }
});

})($fxj);