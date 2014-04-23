<div fx:template="tag_list" fx:of="list" fx:name="Tag list" class="tag_list">
    <span fx:item class="tag">
        <a style="white-space:nowrap;" href="{$url}">{$name}</a>
        <sup class="counter">{$counter}</sup>
    </span>
    {separator} {/separator}
</div>
    
<div 
    fx:template="entity_tags" 
    fx:of="list" 
    fx:name="Tags for entity" 
    class="entity_tags">
        {%tags_label}Tags:{/%} 
        <a fx:item href="{$url}">
             {$name}
        </a>
        <span fx:separator>, </span>
</div>