<div 
    fx:template="entity_classifier" 
    fx:of="list" 
    fx:name="Classifier" 
    class="classifiers">
    <span class="tags_label">{%tags_label}Tags:{/%} </span>
    {*{$items | fx::debug}*}
    <a fx:item href="{$url}">{$name}</a>

    <span fx:separator>, </span>
        
</div>