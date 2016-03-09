(function($) {
    
    function fx_front_panel() {
        var $topbar = $('.fx-top-panel'),
            front_panel = this,
            duration = 300;
        
        var body_default_padding = null;
        
        this.panels = [];
        
        this.get_current_panel = function() {
            var c_len = this.panels.length;
            return c_len ? this.panels[c_len - 1] : null;
        };
        
        this.init_sidebar = function(params) {
            var style = params.current_panel_style || 'default',
                $sidebar = $('<div class="fx-side-panel fx-side-panel_style_'+style+'"></div>'),
                $sidebar_body = $('<div class="fx-side-panel__body"></div>');
            $sidebar.append($sidebar_body);
            $('#fx_admin_control').append($sidebar);
            params.$container = $sidebar;
            return $sidebar;
        };

        this.show_form = function(data, params) {
            params = params || {};
            var c_panel = {};
            this.panels.push(c_panel);
            
            if (!params.view) {
                params.view = data.view ? data.view : 'vertical';
            }
            if (!params.style) {
                params.style = 'default';
            }
            c_panel.is_visible = true;
            
            // disable hilight & select, hide node panel
            if (!params.keep_hilight_on) {
                $fx.front.freeze();
            }
            
            this.stop();
            
            if (!data.fields) {
                data.fields = [];
            }
            
            data.fields.push({type:'hidden', name:'_base_url', value:document.location.href});
            
            c_panel.current_panel_type = 'side';
            c_panel.current_panel_style = params.style;
            
            if (params.view === 'horizontal') {
                c_panel.current_panel_type = 'top';
                if (c_panel.current_panel_style !== 'finish') {
                    $.each(data.fields, function() {
                        this.view_context = 'panel';
                    });
                }
            }
            
            if (!data.form_button) {
                data.form_button = ['save'];
            }
            if (c_panel.current_panel_style !== 'finish') {
                var has_cancel = false;
                for (var i = 0; i < data.form_button.length; i++) {
                    var cb = data.form_button[i];
                    if (cb === 'cancel' || cb.key === 'cancel') {
                        has_cancel = true;
                        break;
                    }
                }
                if (!has_cancel) {
                    data.form_button.unshift('cancel');
                }
            }
            data.class_name = 'fx_form_'+params.view;
            if (params.form_class) {
                data.class_name += ' '+params.form_class;
            }
            
            if (params.onsubmit) {
                data.onsubmit = [
                    params.onsubmit,
                    $fx_form.submit_handler
                ];
            }
            
            c_panel.current_params = params;
            
            var $form = null;
            
            data.button_container = 'header';
            if (c_panel.current_panel_type === 'top') {
                if (c_panel.current_panel_style === 'finish') {
                    data.button_container = 'footer';
                }
                $topbar.css({height:'1px', 'visibility':'hidden'}).mod('overflow', null);
                $topbar.show();
                $topbar.mod('style', c_panel.current_panel_style);
                $form = $fx.form.create(data, $topbar);
            } else {
                data.button_container = 'footer';
                front_panel.init_sidebar(c_panel);
                $form = $fx.form.create(data, c_panel.$container.find('.fx-side-panel__body'));
            }
            
            if (params.is_fluid) {
                c_panel.$container.addClass('fx-side-panel_fluid');
            }
            
            
            c_panel.$form = $form;
            
            $form.on('fx_form_cancel', function() {
                if (params.oncancel) {
                    params.oncancel($form);
                }
                $fx.front_panel.hide();
            }).on('fx_form_sent', function(e, data) {
                if (data.status === 'error') {
                    
                } else {
                    $fx.front_panel.hide();
                    if (data.resume) {
                        $fx.front_panel.show_form(data, params);
                    } else {
                        if (params.onfinish) {
                            params.onfinish(data);
                        }
                    }
                }
            });
            setTimeout(function() {
                var callback = function() {
                    if (!params.skip_focus) {
                        var $first_inp = $(':input:visible, .monosearch_has-value .monosearch__container', $form).first();
                        if ($first_inp.length > 0 && $first_inp.attr('type') !== 'submit') {
                            $first_inp.focus();
                        }
                    }
                    if (params.onready) {
                        params.onready($form);
                    }
                    $form.trigger('fx_panel_form_ready');
                };
                if (c_panel.current_panel_type === 'top') {
                    $topbar.css('visibility', 'visible');
                    $fx.front_panel.animate_panel_height(null, function () {
                        $form.resize(function() {
                            $fx.front_panel.animate_panel_height();
                        });
                        $form.resize();
                        callback();
                    });
                } else {
                    front_panel.show_sidebar(callback);
                }
            }, 100);
            
            $('body').off('.fx_front_panel').on('keydown.fx_front_panel', function(e) {
                if (e.which === 27) {
                    //$form.trigger('fx_form_cancel');
                    $fx.front_panel.get_current_panel().$form.trigger('fx_form_cancel');
                    return false;
                }
            });
            return $form;
        };
        
        
        this.recount_sidebar = function() {
            var c_panel = front_panel.get_current_panel(),
                $sidebar = c_panel.$container,
                $form = $('.fx_admin_form', $sidebar),
                $form_header = $('.fx_admin_form__header', $form),
                $form_footer = $('.fx_admin_form__footer', $form),
                $form_body = $('.fx_admin_form__body', $form);
            
            $form_body.css('height', 'auto');
            $form_footer.removeClass('fx_admin_form__footer-sticky');
            
            var total_height = $(window).outerHeight(),
                header_height = $form_header.outerHeight(),
                footer_height = $form_footer.outerHeight(),
                form_height = $form_body.outerHeight(),
                counted_body_height = total_height - header_height - footer_height;
            
            $sidebar.css({
                'height':total_height
            });
            
            if (form_height >= counted_body_height || true) {
                $form_body.css({
                    height: counted_body_height+'px'
                });
                $form_footer.addClass('fx_admin_form__footer-sticky');
            }
        };
        
        this.show_sidebar = function(callback) {
            var c_panel = this.get_current_panel(),
                $body = $('body'),
                style = c_panel.current_panel_style,
                that = this,
                $sidebar = c_panel.$container;
            
            $sidebar.show();
            
            switch (style) {
                case 'default':
                    $body.css({
                        overflow:'hidden',
                        width: $body.width()
                    });

                    this.recount_sidebar();
                    $(window).on(
                        'resize.fx_recount_sidebar', 
                        function(e) {
                            if (e.target === window) {
                                that.recount_sidebar(e);
                            }
                        }
                    );
                    $sidebar.css({
                        right:'-' + ($sidebar.outerWidth() + 30)+'px',
                        'z-index': that.get_sidebars().length + 1
                    }).animate({
                        right:0
                    }, duration);
                    var that = this;
                    $('.fx_admin_form__body', $sidebar).on('resize.fx_recount_sidebar', function(e) {
                        that.recount_sidebar();
                    });
                    if (callback){
                        setTimeout(callback, duration);
                    }
                    break;
                case 'alert':
                    $sidebar.attr('style', '');
                    if (callback) {
                        callback();
                    }
                    return;
            }
        };
        this.get_sidebars = function() {
            var res = [];
            $.each(this.panels, function() {
                if (this.current_panel_type === 'side') {
                    res.push(this);
                }
            });
            return res;
        };
        
        this.hide_sidebar = function() {
            var is_last_sidebar = (this.get_sidebars().length === 1);
            
            if (is_last_sidebar) {
                $('body').css({
                    width:'',
                    overflow:''
                });
            }
            
            var c_panel = this.get_current_panel(),
                $sidebar = c_panel.$container;
            
            if (c_panel.current_panel_style === 'default') {
                $sidebar.animate(
                    {
                        right:'-' + ($sidebar.outerWidth() + 30)+'px'
                    }, 
                    duration,
                    function() {
                        $sidebar.remove();
                    });
            } else {
                $sidebar.hide().remove();
            }
            if (is_last_sidebar) {
                $(window).off('resize.fx_recount_sidebar');
                $('.fx_admin_form__body', $sidebar).off('resize');
            }
        };
        
        this.animate_panel_height = function(panel_height, callback) {
            //return;
            if (this.is_moving) {
                return;
            }
            
            var max_height = Math.round(
                $(window).height() * 0.75
            );
            var $form = $('form', $topbar);
                        
            var form_height = $form.outerHeight();
            
            if (typeof panel_height === 'undefined' || panel_height === null) {
                panel_height = Math.min(form_height, max_height);
            }
            
            if (panel_height > 0) {
                $topbar.mod('overflow', form_height <= panel_height ? 'hidden' : null);
            }
            
            $form.css({'box-sizing':'border-box', 'width': '101%'});
            $form.css('width', '100%');
            
            if (panel_height === $topbar.height()) {
                return;
            }
            if (body_default_padding === null) {
                body_default_padding = parseInt($('body').css('padding-top'));
            }
            var body_offset = panel_height === 0 ? body_default_padding : panel_height;
            
            var height_delta = body_offset - parseInt($('body').css('padding-top'));
            this.is_moving = true;
            
            $topbar.animate(
                {height: panel_height+'px'}, 
                {
                    duration: duration,
                    complete: function() {
                        $fx.front_panel.is_moving = false;
                        setTimeout(function() {
                            //$fx.front.scrollTo( $($fx.front.get_selected_item()) );
                        }, 100);
                    }
                }
            );
            
            var body_animate_props = {'padding-top':body_offset + 'px'};
            var $selected = $($fx.front.get_selected_item());

            if ($selected.length) {
                var scroll_top = $selected.offset().top - (panel_height + 50) + height_delta;
                body_animate_props.scrollTop = scroll_top;
            }
            
            // animate body within a named queue to avoid stopping other animations 
            // (mainly, opacity when layout is reloaded)
            $('body').animate(
                body_animate_props,
                {
                    complete:callback,
                    duration:duration,
                    queue:'fx_panel_queue'
                }
            ).dequeue('fx_panel_queue');
    
            function css_delta(val) {
                return (val > 0 ? '+=' : '-=') + Math.abs(val);
            }
            
            var top_delta = css_delta(height_delta);
                
            $('.fx_top_fixed').animate(
                {'top': top_delta}, 
                duration
            );
    
            $fx.front.get_front_overlay().animate({
                    top: top_delta
                }, {
                    duration:duration,
                    complete: function() {
                        $fx.front_panel.is_moving = false;
                    }
                }
            );
        };
        
        this.stop = function() {
            $topbar.stop(true,false);
            $('body').stop('fx_panel_queue', true,false);
            $fx.front.get_front_overlay().stop(true,false);
            $('.fx_top_fixed').stop(true,false);
            this.is_moving =  false;
        };
        this.load_form = function(form_options, params) {
            if (form_options._base_url === undefined) {
                form_options._base_url = document.location.href;
            }
            $fx.post(
                form_options, 
                function(json) {
                    $fx.front_panel.show_form(json, params);
                }
            );
        };
        this.hide = function(callback_final) {
            var that = this,
                c_panel = this.get_current_panel();
            if (!c_panel) {
                if (callback_final) {
                    callback_final();
                }
                return;
            }
            var is_last = this.panels.length === 1;
            
            // $fx.front.enable_select(); 
            if (is_last) {
                $fx.front.enable_select();
                $('body').off('.fx_front_panel');
            }
            
            
            var callback = function() {
                var reset_hilight = true;
                if (!is_last) {
                    var prev_panel = that.panels[that.panels.length - 2];
                    reset_hilight = prev_panel.current_params.keep_hilight_on;
                } else {
                    reset_hilight = !c_panel.current_params.keep_hilight_on;
                }
                
                if (reset_hilight) {
                    $fx.front.unfreeze();
                }
                if (callback_final) {
                    callback_final();
                }
                that.panels.pop();
            };
            if (c_panel.current_panel_type === 'top') {
                this.animate_panel_height(
                    0, 
                    function () {
                        $topbar.hide().html('');
                        callback();
                        c_panel.is_visible = false;
                    }
                );
            } else {
                this.hide_sidebar();
                callback();
            }
        };
    };
    
    $(function() {
        $fx.front_panel = new fx_front_panel();
    });
})($fxj);