(function($) {
    window.fx_patch = {

        installed_patches: [],

        install_chain: function() {
            fx_patch.get_next(function(res){
                if (res.id && $.inArray(res.id,fx_patch.installed_patches)) {
                    fx_patch.installed_patches.push(res.id);
                    // install patch
                    fx_patch.install(res.id,function(res){
                        if (!res.error) {
                            // install next
                            fx_patch.install_chain();
                        } else {
                            alert('Error install: '+res.error);
                            $fx.reload('#admin.patch.all(1)');
                        }
                    });
                } else {
                    $fx.reload('#admin.patch.all(1)');
                }
            });
        },

        get_next: function(callback) {
            $fx.post({action: 'get_next_for_install',  essence: 'patch'},callback);
        },

        install: function(id,callback) {

            var row=$('#patch_id_'+id);
            row.addClass('fx_patch_row_installing');

            $fx.post({action: 'install_silent',  essence: 'patch', params: [ id ] },function(res){
                row.removeClass('fx_patch_row_installing');
                callback.call(fx_patch,res);
            });
        }

    };
})(jQuery);