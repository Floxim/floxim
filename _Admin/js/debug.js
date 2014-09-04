(function($) {
$('html').on('click', '.fx_debug_collapser span', function(e) {
    var $node = $(this.parentNode.nextSibling);
    // no control - toggle just current level
    if (!e.ctrlKey){
        $node.toggle();
        return;
    }
    // control pressed - toggle this level and all children
    if ($node.is(':visible')) {
        $node.hide();
        $('.fx_debug_collapse', $node).hide();
    } else{
        $node.show();
        $('.fx_debug_collapse', $node).show();
    }
    
});

})($fxj);