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

var BemLessStack = function(path) {
    this.stack = [];
    this.has_special_rules = false;
    this.c_block = null;
    this.last_bem_index =  null;
    this.pushPath(path);
};

BemLessStack.prototype = {
    
    pushPath: function(path) {
        var s = ' ';
        for (var i = 0; i < path.length; i++) {
            var p = path[i],
                chunk = p.toCSS({});
            //$chunk = $this->output->get($p, false);
            //$s .= $chunk;
            s += chunk;
        }
        if (s.indexOf('#_') === -1) {
            return;
        }
        var parts = s.match(/[\.\s\#]+[^\.\s\#]+/g);
        if (!parts) {
            return;
        }
        for (var  i = 0; i < parts.length; i++) {
            this.pushPart(parts[i]);
        }
        this.has_special_rules = true;
    },
    
    pushPart: function(v) {
        var combinator = /^\s+/.test(v) ? ' ' : '';
        v = v.replace(/^\s+|\s+$/, '');
        
        var is_special = /^#_/.test(v),
            is_class = /^\./.test(v);
            
        if (!is_special && !is_class) {
            this.stack.push({
               combinator: combinator,
               value: v
            });
            return;
        }
        
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
                combinator
            );
            combinator = '';
        } else if (parts[0] === '#') {
            parts.shift();
        }
        if (parts.length === 0) {
            return;
        }
        
        var part = parts.shift(),
            el_name = part.match(/^__(.+)/);
        
        if (el_name) {
            this.addElement(el_name[1], combinator);
            part = parts.shift();
        }
        
        if (!part) {
            return;
        }
        
        var mod_name = part.match(/^_(.+)/),
            mod_val = null;
        
        if (mod_name) {
            mod_name = mod_name[1];
            if ( parts.length > 0 ) {
                mod_val = parts[0].replace(/^_/, '');
            } else {
                mod_val = true;
            }
            this.setMod(mod_name, mod_val);
        }
    },

    addBlock: function(name, combinator) {
        this.c_block = name;
        this.stack.push({
            name: name,
            combinator: combinator,
            type: 'block',
            mods: []
        });
        this.last_bem_index = this.stack.length - 1;
    },

    addElement: function (name, combinator) {
        this.stack.push({
            name: this.c_block+ '__' + name,
            combinator: combinator,
            type: 'element',
            mods: []
        });
        this.last_bem_index = this.stack.length - 1;
    },

    setMod: function(name, value) {
        this.stack[ this.last_bem_index ]['mods'].push([name, value]);
    },

    getModSelector: function (mod, base) {
        return base + '_' + mod[0] + (mod[1] === true ? '' : '_' + mod[1]);
    },
    
    getPath: function() {
        
        var res = '';
        
        for (var i = 0 ; i <  this.stack.length; i++) {
            var level = this.stack[i];
            res += level.combinator;
            if (typeof level.value !== 'undefined') {
                res += level.value;
                continue;
            }
            if (level.type === 'block' && level.mods.length === 0 && this.stack[ i + 1 ]) {
                var next = this.stack[ i + 1];
                if (next.type === 'element') {
                    continue;
                }
            }
            var base = '.'+level.name;
            res += base;
            for (var j = 0; j < level.mods.length; j++) {
                var mod = level.mods[j];
                res += this.getModSelector( mod, j > 0 ? base : '');
            }
        }
        
        var el = new less.tree.Element('', res ),
            sel = new less.tree.Selector( [el] );
            
        return [sel];
    },
        

    _getPath: function() {
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
        var stack = new BemLessStack(path);
        if (stack.has_special_rules) {
            return stack.getPath();
        }
    }
};