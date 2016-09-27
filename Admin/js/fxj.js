/** 
 * jQuery alias for using inside floxim javascript code
 * This script is loaded right after system jQuery
 * All the system jQuery-powered code should be wrapped like this:
 (function($) {
    // use $ as usual, e.g.:
    $.ready(...)
})($fxj);
*/
if (jQuery) {
    $fxj = jQuery;
    
    jQuery.fn.extend({
        onElem: function(event, elem_name, callback) {
            var block_class = jQuery.BEM.getBlockClass(this),
                elem_class = jQuery.BEM.buildElemClass(block_class, elem_name);
                
            this.on(
                event, 
                '.'+elem_class, 
                callback
            );
            return this;
        }
    });
    
    $fxj.valHooks.input = {
        get: function( elem ) {
            if (elem.getAttribute('data-json-val') !== 'true') {
                return undefined;
            }
            try {
                var res = JSON.parse(elem.value);
            } catch (e) {
                console.log(e, elem, elem.value);
            }
            return res;
        }
    };

}