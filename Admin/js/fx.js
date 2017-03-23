(function($) {
window.$fx = {
    history:{},       
        
    KEYCODE_ESC: 27,
        
    init: function(options) {
        
        $.ajaxSetup({
            dataFilter: function(data, type)  {
                var json = null;
                try {
                    json = $.parseJSON(data);
                } catch (e) {
                    return data;
                }
                
                if (!json || !json.format || json.format !== 'fx-response') {
                    return data;
                }
                
                var js_assets = json.js,
                    pending_js = [];
                
                if (js_assets) {
                    if (!window.fx_assets_js) {
                        window.fx_assets_js = [];
                    }
                    for (var i = 0; i < js_assets.length; i++) {
                        var asset = js_assets[i];
                        var is_loaded = $.inArray(asset, window.fx_assets_js);
                        if (is_loaded !== -1) {
                            continue;
                        }
                        
                        
                        (function(asset) {
                            pending_js.push(new Promise(function(resolve) {
                                $.ajax({
                                    url:asset,
                                    dataType: 'script',
                                    success: function() {
                                        window.fx_assets_js.push(asset);
                                        resolve();
                                    },
                                    error: function() {
                                        console.log('whatsup', arguments, this);
                                        resolve();
                                    }
                                });
                            }));
                        })(asset);
                    }
                }
                
                this.pending_scripts_loaded = Promise.all(pending_js);
                
                var css_assets = json.css;
                if (css_assets) {
                    var has_updates = false;
                    for (var i = 0; i < css_assets.length; i++) {
                        var asset = css_assets[i];
                        if (typeof asset === 'string') {
                            if ($('link[href="'+asset+'"]').length === 0) {
                                $('head').append('<link type="text/css" rel="stylesheet" href="'+asset+'" />');
                            }
                        } else if (typeof asset === 'object') {
                            if (asset.declarations) {
                                if (typeof $fx.less_block_declarations === 'undefined') {
                                    $fx.less_block_declarations = {};
                                }
                                $.each(asset.declarations, function(k,v) {
                                    $fx.less_block_declarations[k] = v;
                                });
                            }
                            if (asset.styles) {
                                for (var j = 0; j < asset.styles.length; j++) {
                                    var style = asset.styles[j];
                                    if ($('link[href="'+style+'"]').length === 0) {
                                        $('head').append('<link type="text/css" rel="stylesheet" href="'+style+'" />');
                                    }
                                }
                            }
                            if (asset.blocks) {
                                for (var j = 0 ; j < asset.blocks.length; j++) {
                                    var block = asset.blocks[j];
                                    var $ss = $('style#'+block.style_class);
                                    
                                    if (!$ss.length) {
                                        $ss = $('<style type="text/css" id="'+block.style_class+'"></style>');
                                        $ss.attr('data-declaration', block.declaration_keyword);
                                        
                                        $('head').append($ss);
                                        has_updates = true;
                                    }
                                    //if (true || !$ss.data('is_tweaked')) {
                                    
                                    if ($ss.text() !== block.css || $ss.data('file') !== block.file) {
                                        has_updates = true;
                                        $ss.data('created_by', 'ajax');
                                        $ss.data('file', block.file);
                                        $ss.data('filemtime', block.filemtime);
                                        $ss.text(block.css);
                                    }
                                }
                            }
                        }
                    }
                    if (has_updates) {
                        window.style_watcher.update();
                    }
                }
                
                var css_inline = json.inline_css;
                if (css_inline) {
                    $fx.merge_inline_styles(css_inline);
                }
                
                var response = json.response;
                if ( typeof response !== 'string') {
                    response = JSON.stringify(response);
                }
                return response;
            }
        });
        
        $fx.settings = options;    
        $fx.buttons_map = options.buttons.map;
        $fx.history = options.history;
        $fx.panel = $('.fx-admin-panel');
        
        $fx.main_menu = new fx_main_menu($fx.settings.mainmenu);
        
        
        
        $(function () {
            var ajax_counter = 0;
            $(document).ajaxSend(function() {
                ajax_counter++;
                if (ajax_counter > 0) {
                    $('.fx-progress-line').animate({
                        opacity:1
                    }, 100);
                }
            });
            
            $fx.admin = false;
            $fx.buttons = new fx_buttons($fx.settings.buttons.source);
            
            $fx.additional_menu = new fx_additional_menu();
            $fx.additional_menu.load();
            
            $(window).hashchange($fx.set_mode);
            
            $(window).hashchange();
            
            if ($fx.mode === 'page') {
                $fx.front = new fx_front();
                $fx.front.load();
            }
            
            $('html')
                .on('click.fx', '.fx_button', $fx.buttons.form_button_click)
                .on('click.fx', 'a[href]', function() {
                    if (this.getAttribute('href') === document.location.hash) {
                        $(window).trigger('hashchange');
                    }
                })
                .on('click.fx', '.fx_menu_item-has_dropdown', function(e) {
                    var $t = $(e.target),
                        $p = $t.parent(),
                        $dd = $(this).find('.fx_dropdown').first();
                    
                    if ( (!$t.is('.fx_menu_item-has_dropdown') && !$p.is('.fx_menu_item-has_dropdown')) || $t.closest('a').attr('href')) {
                        return;
                    }
                    if ($t.closest('.livesearch').length > 0) {
                        return;
                    }
                    if (!$dd.is(':visible')) {
                        $dd.show();
                        $dd.css('left', 0);
                        var rect =  $dd[0].getBoundingClientRect(),
                            win_width = $(window).outerWidth();
                        if (rect.right > win_width) {
                            $dd.css('left', '-'+ (rect.right - win_width + 10)+'px');
                        }
                        var closer = $fx.close_stack.push(
                            function() {
                                setTimeout(function() {
                                    $dd.hide();
                                }, 50);
                            },
                            $dd
                        );
                        $dd.data('closer', closer);
                    }
                    return false;
                })
                .on('click.fx', '.fx_main_menu_expander', function() {
                    $('.fx_main_menu_items').toggle();
                })
                .on('click.fx', '.fx_menu_item[data-button]', function() {
                    var $item = $(this),
                        button_data = $item.data('button');
                        
                     $fx.post(
                        button_data,
                        function(json) {
                            $fx.front_panel.show_form(
                                json, 
                                {
                                    onfinish: function () {
                                        $fx.front.reload_layout();
                                    }
                                }
                            );
                        }
                    );
                });
            $(document).ajaxComplete(function(e, jqXHR) {
                ajax_counter--;
                if (ajax_counter === 0) {
                    $('.fx-progress-line').stop().animate({
                        opacity:0
                    }, 100);
                }
                if (!jqXHR || !jqXHR.getResponseHeader) {
                    return;
                }
                var js_assets = jqXHR.getResponseHeader('fx_assets_js');
                
                if (js_assets) {
                    if (!window.fx_assets_js) {
                        window.fx_assets_js = [];
                    }
                    js_assets = $.parseJSON(js_assets);
                    for (var i = 0; i < js_assets.length; i++) {
                        var asset = js_assets[i];
                        var is_loaded = $.inArray(asset, window.fx_assets_js);
                        
                        if (is_loaded !== -1) {
                            continue;
                        }
                        (function(asset) {
                            $.ajax({
                                url:asset,
                                async:false,
                                dataType: 'script',
                                success: function() {
                                    window.fx_assets_js.push(asset);
                                }
                            });
                        })(asset);
                    }
                }
                
                var css_assets = jqXHR.getResponseHeader('fx_assets_css');
                if (css_assets) {
                    css_assets = $.parseJSON(css_assets);
                    for (var i = 0; i < css_assets.length; i++) {
                        var asset = css_assets[i];
                        if ($('link[href="'+asset+'"]').length === 0) {
                            $('head').append('<link type="text/css" rel="stylesheet" href="'+asset+'" />');
                        }
                    }
                }
                var css_inline = jqXHR.getResponseHeader('fx_inline_css');
                if (css_inline) {
                    css_inline = $.parseJSON(css_inline);
                    $fx.merge_inline_styles(css_inline);
                }
            });
        });

        // $(document).bind('keydown',$fx.key_down);
            
        $('html').click(function(){
            $fx.panel.trigger('fx.click', 'main');
        });
    },

    merge_inline_styles: function(css) {
        var $inline_stylesheet = $('.fx_inline_styles');
        if ($inline_stylesheet.length === 0) {
            $inline_stylesheet = $('<style type="text/css" class="fx_inline_styles"></style>');
            $('head').append($inline_stylesheet);
        }
        $inline_stylesheet.text( $inline_stylesheet.text() + css);
    },

    set_mode: function() {
        $fx.admin_buttons_action = {};
        $fx.parse_hash();
        $fx.panel.trigger('fx.startsetmode');
            
        // admin
        if ( $fx.mode === 'admin' ) {
            if ( !$fx.admin ) {
                $fx.admin = new fx_admin();
            }
            var len = $fx.hash.length;
            if ( len > 2 ) {
                $fx.admin.set_entity($fx.hash[len-2]);
                $fx.admin.set_action($fx.hash[len-1]);
            }
            $fx.admin.load();
        }
    },
        
    parse_hash: function() {
        var fx_path = $fx.settings.action_link;
        if (window.location.pathname.slice(0, fx_path.length) !== fx_path) {
            $fx.mode = 'page';
            return;
        }
        var hash_to_parse = $fx.settings.hash !== undefined ? $fx.settings.hash : window.location.hash.slice(1);
        
        if (hash_to_parse === '' && window.location.pathname === fx_path) {
            hash_to_parse = 'admin.administrate.site.all';
        }
        
        var s_pos = hash_to_parse.indexOf('(');
            
        $fx.hash_param = [];
            
        if ( s_pos > 1 ) {
            $fx.hash_param = hash_to_parse.substr(s_pos).slice(1,-1).split(',');
            hash_to_parse = hash_to_parse.substr(0,s_pos);
        }
        $fx.hash = hash_to_parse.split('.');
          
        if ( $fx.hash[0] === '' || ($fx.hash[0] !== 'page' && $fx.hash[0] !== 'admin' )  ) {
            $fx.hash[0] = 'page';
        }
               
        if ( $fx.hash[1] === undefined || $fx.hash[1] === '' ) {
            $fx.hash[1] = 'view';
        }
        $fx.mode = $fx.hash[0]; // page or admin
    },

    draw_additional_text: function (data) {
        var text = data.text;
        $("#fx_admin_additionl_text").html(text).show();
        var current = 0;
        $("#fx_admin_additionl_text a").each(function(){
            var link = data.link[current];
            $(this).click(function(){
                if ( typeof link === 'function' ) {
                    link();
                }
                else {
                    $fx.post(link);
                }
                return false;
            });
                
            current++;
        });
            
    },
    
    draw_additional_panel: function (data) {
    	$("#fx_admin_buttons").fx_create_form(data);
    },
        
    clear_additional_text: function ( ) {
        $("#fx_admin_additionl_text").html('');
    },
          
    /*
    key_down: function ( e ) {
        if ( e.keyCode === 46 ) {
            e.stopPropagation();
        }

        return true;
    },
    */
   
    alert: function(data, status, expire) {
        var b = 'fx-admin-alert',
            $body = $('body'),
            $container = $body.data('fx_alert_container'),
            $alert = $(
                '<div class="'+b+'">'+
                    '<span class="'+b+'__close">&times;</span>'+
                    '<div class="'+b+'__data"></div>'+
                '</div>'
            ),
            $data = $alert.elem('data');
        
        if (!$container) {
            $container = $('<div class="fx-alert-container fx_overlay"></div>').appendTo($body);
            $body.data('fx_alert_container', $container);
        }
        $data.append(data);
        $alert.setMod('status', status);
        $container.append($alert);
        var destroy = function() {
            $alert.css({
                height:$alert.outerHeight(),
                opacity:1,
                overflow:'hidden'
            }).animate({
                height:0,
                opacity:0,
                'padding-top':0,
                'padding-bottom':0,
                'margin-bottom':0
            }, 500, function() {
                $alert.remove();
            });
        };
        $alert.elem('close').click(destroy);
        if (expire) {
            var expire_timer = setTimeout(destroy, expire*1000);
            $alert.one('mouseover', function() {
                clearTimeout(expire_timer);
            });
        }
    },

    show_status_text: function ( text, status ) { 
        this.alert(text, status, 3);
    },
    
    show_error: function(json) {
        var errors = [];
        if (!json.errors) {
            errors.push(json.text || "unknown error");
        } else {
            $.each(json.errors, function(i, e) {
                errors.push(typeof e === 'string' ? e : e.text);
            });
        }
        $fx.show_status_text(errors.join("<br />"), 'error');
    },
    
    reload: function(new_location) {
        if (/^#/.test(new_location)) {
            if (new_location === document.location.hash) {
                $(window).trigger('hashchange');
            } else {
                document.location.hash = new_location.replace(/^#/, '');
            }
            
            return;
        }
        $('body').html('').css({
            background:'#EEE',
            color:'#000', 
            margin:'30px 0', 
            font:'bold 22px arial',
            textAlign:'center'
        }).html('<div class="fx_overlay fx_reloading">'+$fx.lang('reloading')+'...</div>');
        document.location.href = typeof new_location === 'string' ? new_location : document.location.href.replace(/#.*$/, '');  
    },
        
    post: function ( data, callback, error_callback ) {
        var post_data = {};
        post_data.fx_admin = true;
        
        
        
        $.each(data, function(k, v) {
            if (typeof v !== 'function') {
                post_data[k] = v;
            }
        });
        
        error_callback = error_callback || function() {};
        
        if (!post_data._base_url) {
            post_data._base_url = document.location.href.replace(/#.*$/, '');
        }
        if (!callback) {
            callback = function() {};
        }
        $.ajax({
            url: $fx.settings.action_link,
            type: "POST",
            data: post_data,
            dataType: "JSON",
            success: function(json) {
                if (json.reload) {
                    $fx.reload(json.reload);
                    return;
                }
                var that = this,
                    args = arguments,
                    go_on = function() {
                        if (json.status === 'error') {
                            $fx.show_error(json);
                            error_callback();
                            return;
                        }
                        if (callback) {
                            callback.apply(that, args);
                        }
                    };
                if (this.pending_scripts_loaded && this.pending_scripts_loaded instanceof Promise) {
                    this.pending_scripts_loaded.then(function() {
                        go_on();
                    });
                } else {
                    go_on();
                }
                
            },
            error: function(jqXHR, textStatus, errorThrown) {
                if ( textStatus === 'parsererror') {
                    $fx.show_status_text( $fx.lang('Server error:') + jqXHR.responseText, 'error');
                }
                error_callback();
                return false;
            }  
        });
    },
    
    post_front: function (data, callback  ) {
        if ( data.posting === undefined ) {
            data.posting = 1;
        }
        if ( !data.action ) data.action = 'edit';
        
        $fx.show_status_text($fx.lang('Saving...'), 'wait');
            
        $fx.post(data, function(json){
            $fx.show_status_text(json.text, json.status);
            if ( callback ) {
                callback(json);
            }  
        });
    },
    
    regexp: function(regexp, data) {
        return regexp.exec(data);
    },
            
    stop_add_mode: function () {
        $fx.buttons.show_panel();
        $fx.clear_additional_text();
        $('.fx_preview_placeholder').remove();
    },
              
    lang: function(str) {
        var res = this.dictionary && this.dictionary[str] ? this.dictionary[str] : str;
        if (arguments.length > 1) {
            for (var i = 1; i < arguments.length; i++) {
                res = res.replace(/\%s/, arguments[i]);
            }
        }
        return res;
    },
    do_log : true,
    log : function () {
        if (this.do_log) {
            console.log.apply(console, arguments);
        }
    },
    
    get_colors: function() {
        var res = {};
        $.each($fx.layout_vars, function(k, v) {
            var m = k.match(/^color-([a-z]+)-(\d+)/);
            if (!m) {
                return;
            }
            var c = m[1],
                l = m[2];
            if (typeof res[c] === 'undefined') {
                res[c] = {};
            }
            res[c][c+' '+l] = v;
        });
        return res;
    },
    
    uid: function() {
        var res = (new Date() * 1).toString(16);
        for (var i = 0; i < 21; i++) {
            res += Math.round(Math.random()*15).toString(16);
        }
        return res;
    },
    
    disableSelection: function($n) {
        return $n
                 .attr('unselectable', 'on')
                 .css('user-select', 'none')
                 .on('selectstart', false);
    },
    
    
    parse_css_value: function(s) {
        var seps = [' ', ','],
            res = [
                ['']
            ],
            index = [
                0,
                0
            ];

        s = s.replace(/^\s+|\s+$/, '');
        s = s.replace(/\s/g, ' ');

        var q = null,
            last_sep = null;

        for (var i = 0; i < s.length; i++) {
            var ch = s[i];
            var sep_level = seps.indexOf(ch);
            if (sep_level === -1 || q !== null) {

                if (last_sep !== null) {
                    if (last_sep === 1) {
                        index[0]++;
                        index[1] = 0;
                        res[index[0]] = [''];
                    } else {
                        index[1]++;
                        res[index[0]][index[1]] = '';
                    }
                    last_sep = null;
                }

                res[index[0]][index[1]] += ch;
                if (['"', "'"].indexOf(ch) !== -1 && q === null) {
                    q = ch;
                } else if (ch === q && (i !== 0 && s[i-1] !== '\\') ) {
                    q = null;
                }
                continue;
            }
            if (last_sep === null || last_sep < sep_level) {
                last_sep = sep_level;
            }
        }
        return res;
    }
    
};

var close_stack = function() {
    this.stack = [];
    
    var that = this;
    
    this.push = function(callback, $clickout_node, handle_escape) {
        var level = [
                callback, 
                $clickout_node, 
                typeof handle_escape  === 'undefined' ? true : handle_escape,
                new Date()
            ],
            handler;
        
        var c_index = that.stack.length;
        
        handler = function(e) {
            for (var i = that.stack.length - 1; i >= c_index; i--) {
                var level = that.stack[i],
                    res = level[0](e);
                if (res === false) {
                    return;
                }
                that.stack.pop();
            }
        };
        
        handler.index = c_index;
        
        // use timeout to avoid immediate close by click-out
        setTimeout(function() {
            that.stack.push(level);
        }, 10);
        return handler;
    };
    
    this.close = function(index) {
        for (var  i = this.stack.length - 1; i >= index; i--) {
            var level = this.stack[i];
            if (i > index) {
                level[0]();
            }
            this.stack.pop();
        }
    };
    
    this.closed = function(index) {
        console.log('set closd', index);
    };
    
    var last_mousedown = null;
    
    $(document.body).on('mousedown', function() {
        last_mousedown = new Date();
    }).on('click', function(e) {
        for (var i = that.stack.length - 1; i >= 0; i--) {
            var level = that.stack[i],
                $nodes = level[1],
                level_date = level[3];
            if (!$nodes || !$nodes.length) {
                return;
            }
            if ($(e.target).closest($nodes).length) {
                return;
            }
            //console.log(level_date, last_mousedown, level_date - last_mousedown);
            if( level_date > last_mousedown ) {
                return;
            }
            //console.log('closing', that.stack, e.target, $nodes);
            var res = level[0](e);
            if (res !== false) {
                that.stack.pop();
                //e.stopImmediatePropagation();
                //return false;
            }
        }
    }).on('keydown', function(e) {
        if (e.which !== 27) {
            return;
        }
        for (var i = that.stack.length - 1; i >= 0; i--) {
            var level = that.stack[i];
            if (level[2] === false) {
                return;
            }
            var res = level[0](e);
            if (res !== false) {
                that.stack.pop();
                e.stopImmediatePropagation();
                return false;
            }
        }
    });
};

$(function() {
    window.$fx.close_stack = new close_stack();
});

})($fxj);


// call debugger with Shift + S
function add_debugger_shortcut() {

    document.documentElement.addEventListener(
        'keydown', 
        function(e) {
            if (e.which === 68 && e.shiftKey) {
                debugger;
                return false;
            }
        },
        true
    );
}