<div class="news_record">
    <div class="news" fx:item>
        <div class="date">
            {$publish_date|'d.m.Y'} {if $metatype}&bull; {$metatype}{/if}
        </div>
        <div class="anounce">{$anounce}</div>
        <div class="text">{$text}</div>
        {call id="component_classifier.entity_classifier"}{$items select="$item['tags']" /}{/call}
    </div>
</div>