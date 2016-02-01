/**
 * misc scripts related to infoblock management
 */


(function ($) {

window.fx_controller_tree = function(html) {
    var b = 'fx-block-select',
        $tab_labels = $('.'+b+'__tab-label', html),
        $tabs = $('.'+b+'__tab', html);

    $tab_labels.click(function() {
        var $tab_label = $(this),
            c_index = $tab_labels.index( $tab_label);
        
        $tab_labels.removeClass(b+'__tab-label_active');
        $tabs.removeClass(b+'__tab_active');
        $tab_label.addClass(b+'__tab-label_active');
        $tabs.eq( c_index ).addClass(b+'__tab_active');
    });
    
    $(html).on('click', '.'+b+'__item', function() {
        var $item = $(this),
            id = $item.data('id'),
            $form = $item.closest('form'),
            $inp = $form.find('.tree_value_input');
        $inp.val(id);
        $form.submit();
    });
};

$('html').on('fx_panel_form_ready', function(e) {
    var $form = $(e.target),
        $scope_inp = $('input[name="scope[type]"]', $form),
        $scope_params_inp = $('input[name="scope[params]"]', $form);
        
    if ($scope_inp.length === 0 ){ 
        return;
    }
    var $scope_ls = $scope_inp.closest('.livesearch'),
        infoblock_id = $form.find('input[name="id"]').val();
    
    
    function show_scope_dialog() {
        $fx.front_panel.load_form({
            entity:'infoblock',
            action:'scope',
            infoblock_id:infoblock_id,
            fx_admin:true
        }, {
            style:'alert',
            onsubmit: function(e) {
                var $scope_form = $(e.target),
                    form_data = $scope_form.formToHash();
                
                $scope_params_inp.val( JSON.stringify(form_data.scope) );
                $fx.front_panel.hide();
                e.stopImmediatePropagation();
                return false;
            }
        });
    }
    
    $scope_ls.on('change', function() {
        var ls_value = $scope_ls.data('livesearch').getValue();
        if (ls_value !== 'custom') {
            return;
        }
        show_scope_dialog();
    });
    
});

})($fxj);