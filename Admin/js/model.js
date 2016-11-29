(function() {
    
function model (type, data) {
    this.type = type;
    this.data = data;
    this.changes = [];
    this.init();
};

model.items = [];

model.prototype.init = function() {
    model.items.push( this );
};

model.prototype.set = function(k, v) {
    this.data[k] = v;
    this.changes.push([k, v]);
};

model.prototype.get = function(k) {
    return this.data[k];
};

})();