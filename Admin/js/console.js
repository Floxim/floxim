(function($) {
    
    $('html').on('fx_adm_form_created', function(e, data) {
        if (data.request.entity !== 'console') {
            return;
        }
        var $form = $(e.target),
            $textarea = $('#console_text', $form),
            cm = $textarea.data('codemirror');
            
        function get_stored_snippets() {
            return JSON.parse(localStorage.getItem('fx_console_snippets') || '[]');
        }
            
        var snippets  = get_stored_snippets(),
            c_snippet_key = snippets.length === 0 ? 0 : snippets.length - 1;
            
        if (snippets.length > 0) {
            cm.setValue(snippets[c_snippet_key]);
        }
        
        cm.on('change', function() {
            var val = cm.getValue(),
                snippets = get_stored_snippets();
            
            snippets[c_snippet_key] = val;
            localStorage.setItem('fx_console_snippets', JSON.stringify(snippets));
        });
    });
    
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
        
        function append_result(res) {
            var $container = $('.fx_admin_console_container');
            if (!$container.length) {
                $container = $('<div class="fx_admin_console_container"></div>');
                $b.parent().after($container);
            }

            $container.html(res);
            $container.trigger('fx_render');
            $b.data('is_pending', false);
            $button_text.text(init_button_text);
        }
        
        $form.ajaxSubmit({
            success: function(data) {
                var res;
                if (typeof data === 'object') {
                    res = data.result;
                } else {
                    try {
                        data = $.parseJSON( data );
                        res = data.result;
                    } catch (e) {
                        var res = data;
                    }
                }
                append_result(res);
            },
            error: function(xhr, status, error) {
                error = (error || 'Error')+' #'+xhr.status;
                append_result('<p style="color:#F00;">'+error+'</p>');
            }
        });
    }
    $('html').off('.execute').on('click.execute', '.fx_button-class-execute', console_exec);
    $('html')
        .on('keydown.execute', function(e) {
            if (e.metaKey && e.which === 13) {
                console_exec();
                return false;
            }
        })
        .on('keyup.execute', function(e) {
            if ( e.ctrlKey && e.which === 13) {
                console_exec();
            }
        });
})(jQuery);