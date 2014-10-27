(function($) {

$(function() {    
    function hilight_code(){
        $('.fx_codekit').each(function(i, node) {
            var $node = $(node);
            if (!$node.data('init_source')) {
                $node.data('init_source', $node.text());
                $node.data('init_class', $node.attr('class'));
                hljs.highlightBlock(node);
            }
        });
    }
    hilight_code();
    if (window.$fxj) {
        $fxj('html').on('fx_create_redactor', function(e) {
            if ( $(e.target).closest('#fx_admin_extra_panel').length) {
                return;
            }
            var opts = e.redactor_options;
            if (!opts.plugins) {
                opts.plugins = [];
            }
            opts.plugins.push('codekit');
        });
        $fxj('html').on('fx_infoblock_loaded fx_editable_restored', hilight_code);
        $fxj('html').on('fx_before_editing', function(e) {
            $('.fx_codekit', e.target).each(function() {
                var $node = $(this);
                $node.attr('class', $node.data('init_class'));
                var source = $node.data('init_source');
                if (source) {
                    $node.text(source);
                    $node.data('init_source', false);
                }
            });
        });
    }
});

})(jQuery);