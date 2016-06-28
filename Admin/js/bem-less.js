function BemLessPlugin() {};
                
BemLessPlugin.prototype = {
    install: function(less, pluginManager) {
        pluginManager.addVisitor(
            new BemLessVisitor(less)
        );
    }
};

var BemLessVisitor = function(less) {
    this._visitor = new less.visitors.Visitor(this);
}

var BemLessStack = function() {
    this.stack = [];
    this.has_special_rules = false;
    this.c_block = null;
    this.last_bem_index =  null;
};

BemLessStack.prototype = {

    push: function(el) {
        var v = el.value;
        if (typeof v !== 'string') {
            this.stack.push(el);
            return;
        }
        var is_special = v.match(/^#_/);
        if (is_special) {
            this.has_special_rules = true;
        }
        if (!is_special && !v.match(/^\./)) {
            this.stack.push(el);
            return;
        }
        el = $.extend({}, el);
        var found_parts = v.split(/(\.|_+[^_]+)/),
            parts = [];
        for (var i = 0 ; i < found_parts.length; i++) {
            if (found_parts[i]) {
                parts.push(found_parts[i]);
            }
        }
        if (parts[0] === '.') {
            parts.shift();
            var block_name = parts.shift();
            this.addBlock(
                block_name,
                el,
                parts.length > 0 && parts[0].match(/^__(.+)/)
            );
        } else if (parts[0] === '#') {
            parts.shift();
        }
        if (parts.length > 0 ) {
            var part = parts.shift(),
                el_name = part.match(/^__(.+)/);

            if (el_name) {
                this.addElement( el_name[1], el );
                part = parts.shift();
            }
            var mod_name = part ? part.match(/^_(.+)/) : false;
            if ( mod_name ) {
                mod_name = mod_name[1];
                var mod_val = parts.length > 0 ? parts[0].replace(/^_/, '') : true;
                this.setMod( mod_name, mod_val, el );
            }
        }
    },
    addBlock: function(name, el, is_transparent) {
        this.c_block = name;
        this.stack.push({
            name: name,
            is_transparent: is_transparent, // true if block added together with element
            el: el,
            type: 'block',
            mods: []
        });
        this.last_bem_index = this.stack.length - 1;
    },

    addElement: function (name, el) {
        this.stack.push({
            name: this.c_block+ '__' + name,
            el: el,
            type: 'element',
            mods: []
        });
        this.last_bem_index = this.stack.length - 1;
    },

    setMod: function(name, value, el) {
        this.stack[ this.last_bem_index ]['mods'].push([name, value, el]);
    },

    getModSelector: function (mod, base) {
        return base + '_' + mod[0] + (mod[1] === true ? '' : '_' + mod[1]);
    },

    getPath: function() {
        var res = [];
        for (var i = 0; i < this.stack.length; i++) {
            var level = this.stack[i];
            if (! level.el ) {
                res.push(level);
                continue;
            }
            var base = '.' + level.name,
                first_mod = level.mods.shift(),
                level_el = level.el;

            if (first_mod) {
                var level_combinator = level_el.combinator;

                var first_mod_el = first_mod[2];
                first_mod_el.value = this.getModSelector(first_mod, base);
                first_mod_el.combinator = level_combinator;

                res.push(first_mod_el);
                for (var j = 0; j < level.mods.length; j++) {
                    var mod = level.mods[j];
                    var mod_el = mod[2];
                    mod_el.value = this.getModSelector(mod, base);
                    mod_el.combinator = new less.tree.Combinator('');
                    res.push( mod_el );
                }
            } else {
                if (!level.is_transparent) {
                    level_el.value = base;
                    res.push(level_el);
                }
            }
        }
        return res;
    }
};

BemLessVisitor.prototype = {
    run: function(root) {
        this._visitor.visit(root);
    },
    visitRuleset: function(rulesetNode, visitArgs) {
        var that = this;
        $.each(rulesetNode.rules, function(index, rule) {
            if (!rule.paths) {
                if (rule.rules) {
                    that.run(rule);
                }
                return false;
            }
            $.each(rule.paths, function (index, path) {
                var res = that.processPath(path);
                if (res) {
                    rule.paths[index] = res;
                }
            });
        });
        return rulesetNode;
    },
    processPath: function (path) {
        var stack = new BemLessStack();

        for (var i = 0; i < path.length; i++) {
            var c_path = path[i];
            for (var j = 0; j < c_path.elements.length; j++) {
                stack.push( c_path.elements[j] );
            }
        }

        if (stack.has_special_rules) {
            return stack.getPath();
        }

    }
};