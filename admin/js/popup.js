(function($) {
$fx.popup = function(params) {
    var popup = this;
    this.params = params;
    this.$target = params.target ? $(params.target) : null;
    this.create = function() {
        this.$node = $('<div class="fx_overlay fx_popup" />');
        $('body').append(this.$node);
        /*this.$node.css({
            'z-index':'3010',
            background:'#FFF',
            overflow:'auto',
            'box-shadow':'0px 0px 12px rgba(50, 50, 50, 0.77)'
        });*/
        if (this.params.maxWidth) {
            this.$node.css('max-width', this.params.maxWidth +"px");
        }
        this.$header = $('<div class="fx_popup_header />');
        this.$node.append(this.$header);
        this.$body = $('<div class="fx_popup_body" />');
        this.$node.append(this.$body);
        this.$footer = $('<div class="fx_popup_footer" />');
        this.$node.append(this.$footer);
        this.$arrow = $('<div class="fx_popup_arrow" />');
        this.$node.append(this.$arrow);
        /*var cancel_button = $t.jQuery('input', {
           'type':'button',
           'is_submit':false,
           'class':'cancel',
           'name':'cancel',
           'label':'cancel'
        });
        cancel_button.on('click', function() {popup.destroy();});
        $('html').on('keyup.fx_popup', function(e) {
            if (e.which === 27) {
                popup.destroy();
            }
        });
        this.$footer.append(cancel_button);*/
        var ok_button = $t.jQuery('input', {
           type:'button',
           is_submit:true,
           name:'apply',
           label:'apply'
        });
        ok_button.on('click', function() {
            //this.$node.trigger()
            if (popup.params.onfinish) {
                popup.params.onfinish(popup);
            }
            popup.destroy();
        });
        this.$footer.append(ok_button);
        if (this.$target) {
            this.$target.data('popup', this);
        }
    };
    this.destroy = function() {
        if (params.onclose) {
            params.onclose(popup);
        }
        this.$node.remove();
        $('html').off('.fx_popup');
        if (this.$target) {
            this.$target.data('popup', null);
        }
    };
    this.position = function() {
        this.$node.css('left', 0);
        if (this.$target) {
            var arrow = this.$node.find('.fx_popup_arrow');
            var to = this.$target.offset();
            var is_fixed = false;
            var $cp = this.$target;
            do {
                if ($cp.css('position') === 'fixed') {
                    is_fixed = true;
                    break;
                }
                $cp = $cp.parent();
            } while ( $cp.length > 0);
            
            this.$node.css('position', is_fixed ? 'fixed' : 'absolute');
            
            if (is_fixed) {
                to.top -= $(window).scrollTop();
            }
            var positions = {
                top:to.top + this.$target.height() + 10 ,
                //left: (to.left + this.$target.width()/2 - this.$node.width() / 2)
                left: to.left
            };
            if (positions.left < 0) {
                positions.left = 5;
            } else if (positions.left + this.$node.width() > $(window).width()) {
                arrow.css('left', to.left);
                positions.left = $(window).width() - this.$node.outerWidth() - 5;
            }
            this.$node.css({
                top: positions.top+ 'px',
                left: positions.left + 'px'
            });
        } else {
            
        }
    };
    this.create();
    this.position();
};
})($fxj);