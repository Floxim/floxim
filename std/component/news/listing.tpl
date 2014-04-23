<div fx:template="list" class="news_list">
    <div fx:item class="news">
        <h2><a href="{$url}">{$name}Unnamed article{/$}</a></h2>
        <div class="date">
            <span>{$publish_date | 'd.m.Y'}</span>
        </div>
        
        <div fx:if="$image" class="pic">
            <img src="{$image | 'max-width:100,max-height:100'}" alt="{$name}" />
        </div>
        
        <div class="anounce">{$anounce}</div>
        {call id="component_classifier.entity_classifier"}{$items select="$tags" /}{/call}
        <div fx:if="$comments_counter" class="comments_counter">Comments: {$comments_counter}0{/$}</div>
    </div>

    {call component_content.pagination /}
</div>