<div fx:template="list" class="award_list">
    {css}listing.css{/css}
    <div fx:item class="award">
       <div class="year">{$year}2000{/$}</div>
            <div class="image">
                    <img src="{$image}" alt="" />
            </div>
            <div class="description">
                <a href="{$url}"><h2>{$name}Name{/$}</h2></a>
                    {$description}Description{/$}
            </div>
        <div class="clear"></div>
    </div>
    {call component_content.pagination}
</div>