(function($) {
window.$t = {
    add: function(name, tpl, test, priority) {
        
        if (typeof tpl._test === 'undefined' && typeof test === 'function') {
            tpl._test = test;
        }
        
        if (typeof tpl._priority !== 'number' && typeof priority === 'number') {
            tpl._priority = priority;
        }
        
        // for example, form.fields.text
        name = name.split('.');
        var name_prefix = name.slice(0, -1); // text
        var func_name = name.slice(-1); // form.fields
        var c = $t;
        // after the loop c gets the current $t.form.fields
        // if it is not filled in empty objects
        for (var i = 0; i < name_prefix.length; i++) { 
            var chunk = name[i];
            if (typeof c[chunk] === 'undefined') {
                c[chunk] = {};
            }
            if (i < name.length - 1) {
                c = c[chunk];
            }
        }
        
        var c_type = typeof c[func_name];
        
        if (c_type === 'undefined') { // have not set
            c[func_name] = tpl;
        } else {
            
            var old_func = c[func_name]; // what is already sitting in form.fields.text
            
            if ( c_type !== 'function' ) { // object stub
                for (var i in old_func) { // move the properties to the new function
                    tpl[i] = old_func[i];
                    delete old_func[i];
                }
                // and install a new f-tion in place stubs
                c[func_name] = tpl;
            } else if ( typeof old_func._variants !== 'undefined') { // function-with-options, simply add a new option
                old_func._variants.push( tpl );
                old_func._variants._is_sorted = false;
            } else { // function without options, it should be replaced
                var var_func = function(obj, options) {
                    var res_func = $t.findVariant( arguments.callee._variants, obj, options );
                    return res_func (obj, options);
                };
                var_func._variants = [old_func, tpl];
                c[func_name] = var_func;
            }
        }
    },
    
    sortVariants:     function(vars) {
        if (!vars) {
            
        }
        if (typeof vars._is_sorted !== 'undefined' && vars._is_sorted) {
            return;
        }
        vars.sort( function(a, b) {
            if (typeof a._priority === 'undefined') {
                a._priority = (typeof a._test === 'undefined' ? 0 : 1);
            }
            if (typeof b._priority === 'undefined') {
                b._priority = (typeof b._test === 'undefined' ? 0 : 1);
            }
            return b._priority - a._priority;
        });
    },
    
    findVariant: function(vars, obj, options) {
        $t.sortVariants(vars);
        for (var i = 0; i < vars.length; i++) {
            if ( typeof vars[i]._test !== 'function' || vars[i]._test(obj, options)) {
                return vars[i];
            }
        }
        return $t.noFunc;
    },
    
    noFunc: function(obj, options) {
        console.log('no tpl to render', obj, options);
        return '';
    },
    
    find: function(name) {
        var c = $t;
        name = name.split('.');
        for (var i = 0; i < name.length; i++) {
            var cp = name[i];
            if (typeof c[cp] === 'undefined') {
                console.log('not found in '+cp);
                return $t.noFunc;
            }
            c = c[cp];
        }
        return c;
    },
    findFor: function(template_name, obj, options) {
        var tpl = $t.find(template_name);
        if (typeof tpl._variants !== 'undefined') {
            tpl = $t.findVariant(tpl._variants, obj, options);
        }
        return tpl;
    },
    jQuery: function(name, obj, options) {
            if (options === undefined) {
                options = {};
            }
        var tpl = $t.findFor(name, obj, options);
        var res = tpl(obj,options);
        if (!res) {
            return '';
        }
        res = res.replace(/^\s+|\s+$/, '');
        var html = $(res);
        if (typeof tpl.jquery === 'function') {
            tpl.jquery(html, obj, options);
        }
        return html;
    },
    addSlashes: function(str) {
        return str.replace(/([\"\'])/g, "\\$1").replace(/\0/g, "\\0");
    },
    htmlEntities: function(s) {   // Convert all applicable characters to HTML entities
        //
        // +   original by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
        
        var div = document.createElement('div');
        var text = document.createTextNode(s);
        div.appendChild(text);
        return div.innerHTML;
    },
    clear: function(s) {
        var res = $t.htmlEntities(s);
        res = res.replace(/\"/g, '&quot;');
        return res;
    },
    json_att: function(obj) {
        if (!obj) {
            return '';
        }
        var str = (typeof obj === 'string') ? obj : $.toJSON(obj);
        str = this.clear(str);
        return str;
    },
    inline_data: function(n) {
        var c_data = n.data('inline_data');
        if (c_data) {
            return c_data;
        }
        var inp = n.find('>input.data_input');
        
        if (inp.length > 0) {
            var json = inp.val();
            inp.remove();
        } else {
            var json = n.attr('data-inline');
            n.removeAttr('data-inline');
        }
        
        if (json === undefined) {
            return {};
        }
        var data = $.evalJSON(json);
        n.data('inline_data', data);
        inp.remove();
        return data;
    },
    countLength:function(obj) {
        if (obj instanceof Array) {
            return obj.length;
        }
        var c = 0;
        for (var i in obj) {
            c++;
        }
        return c;
    }
};
})($fxj);