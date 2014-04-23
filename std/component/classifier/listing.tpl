<div 
    fx:template="entity_classifier" 
    fx:of="list" 
    fx:name="Classifier" 
    class="classifier">
        {%tags_label}Classifieres:{/%} 
        <a fx:item href="{$url}">
             {$name}
        </a>
        <span fx:separator>, </span>
</div>