(function($) {

$('html').on('click', '.fx_refresh_captcha', function() {
    var $pic = $(this).closest('.fx_form_row').find('.fx_captcha_image');
    var src = $pic.attr('src');
    var new_src = src.replace(/rand=\d+/, 'rand='+Math.round(Math.random()*10000));
    $pic.attr('src', new_src);
    $pic.closest('.fx_captcha_input').find('.fx_input_type_captcha').val('').focus();
});

$('html').on('submit', 'form.fx_form_ajax', function() {
    var $form = $(this);
    $.ajax({
        type:'post',
        url:$form.attr('action'),
        dataType:'html',
        data:$form.serialize(),
        success: function(data) {
            var $data = $(data);
            var $ib = $form.closest('.fx_infoblock');
            var $container = $ib.parent();
            $ib.before($data);
            $ib.remove();
            $('form.fx_form_sent .fx_form_row_error :input', $container).first().focus();
            $data.trigger('fx_form_loaded');
        }
    });
    return false;
});

})(window.jQuery);