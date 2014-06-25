<div fx:template="list" fx:name="Default news list" class="news_list">
    <div fx:item class="news">
        <h2><a href="{$url}">{$name}Unnamed article{/$}</a></h2>
        <div class="date">
            <span>{$publish_date | 'd.m.Y'}</span>
        </div>
        
        <div fx:if="$image" class="pic">
            <img src="{$image | 'max-width:100,max-height:100'}" alt="{$name}" />
        </div>
        
        <div class="anounce">{$description}</div>
        {call id="component_classifier.entity_classifier"}{$items select="$tags" /}{/call}
        <div fx:if="$comments_counter" class="comments_counter">Comments: {$comments_counter}0{/$}</div>
    </div>
    {$pagination | component_content.pagination}
</div>

<div fx:template="news_tiles" fx:of="news.list" fx:name="News tiles" class="news_tiles_list">
    <div class="material_tiles">
        <div class="material" fx:item>
            <div class="title">
                <a href="{$url}">{$name}</a>
            </div>
            <div class="date">{$publish_date | 'd.m.Y'}</div>
            <div class="description">{$description}</div>
            <div class="read_more"><a href="{$url}">{%read_more}Read more{/%}</a></div>
        </div>
     </div>
</div>