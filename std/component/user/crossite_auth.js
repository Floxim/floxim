(function($) {
    $(function() {
        var $container = $('.crossite_auth_form');
        var $forms = $('form', $container);
        $forms.each(function() {
            $(this).submit();
        });
        setTimeout(function() {
            var target_location = $container.data('target_location');
            document.location.href = target_location;
        }, 1000);
    });
})(jQuery);