(function(jQuery) {
jQuery.extend({
    ajaxFileUpload: function(s) {
        var data = new FormData(),
            files = s.input.files;
        if (typeof s.data === 'object') {
            $.each(s.data, function(k, v) {
                data.append(k, v)
            });
        }
        for (var i = 0; i < files.length; i++) {
            data.append('file', files[i])
        }
        jQuery.ajax({
            url: s.url,
            type: 'POST',
            data: data,
            dataType: 'json',
            processData: false,
            contentType: false,
            success: s.success,
            error: function () {
                console.log('faild file upload', arguments);
            }
        });
    }
})
})(jQuery)