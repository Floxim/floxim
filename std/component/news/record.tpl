<div 
    class="news_record" 
    fx:template="default_record" 
    fx:of="news.record" 
    fx:name="Default news record">
    <div class="news" fx:with="$item">
        <div class="date">
            {$publish_date|'d.m.Y'}
        </div>
        <div class="pic">
            <img src="{$image | 'max-width:500px;max-height:500px;'}" alt="" />
        </div>
        <div class="anounce">{$description}</div>
        <div class="text">{$text}</div>
        {call component_classifier.entity_classifier with $tags as $items}
        
    </div>
</div>