$('html').on('click', '.blog_calendar .year_title', function() {
    if ($(this).parent().hasClass('year_active')) {
        return;
    }
    $(this).closest('.blog_calendar').find('.year_active').removeClass('year_active');
    $(this).parent().addClass('year_active');
});