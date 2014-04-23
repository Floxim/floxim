$('html').on('click', '.publication_calendar .year_title', function() {
    if ($(this).parent().hasClass('year_active')) {
        return;
    }
    $(this).closest('.publication_calendar').find('.year_active').removeClass('year_active');
    $(this).parent().addClass('year_active');
});