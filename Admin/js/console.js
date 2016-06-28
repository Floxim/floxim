(function($) {
    function console_exec() {
        var $b = $('.fx_button-class-execute');
        if ($b.length === 0 || $b.data('is_pending')) {
            return;
        }
        var $button_text = $('span', $b);
        var init_button_text = $button_text.text();
        $b.data('is_pending', true);
        
        $('span', $b).text('...');
        var $form = $b.closest('form');
        $form.off('.fx_submit').trigger('fx_form_submit');
        $form.ajaxSubmit(function(data) {
            console.log('ress!', data);
            var $container = $('.fx_admin_console_container');
            if (!$container.length) {
                $container = $('<div class="fx_admin_console_container"></div>');
                $b.parent().after($container);
            }
            try {
                data = $.parseJSON( data );
                res = data.result;
            } catch (e) {
                var res = data;
            }
            console.log(res);
            $container.html(res);
            $container.trigger('fx_render');
            $b.data('is_pending', false);
            $button_text.text(init_button_text);
        });
    }
    $('html').off('.execute').on('click.execute', '.fx_button-class-execute', console_exec);
    $('html').on('keyup.execute', function(e) {
        if (e.ctrlKey && e.which === 13) {
            console_exec();
        }
    });
})(jQuery);