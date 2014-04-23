<div fx:template="list" class="video_list">
    {css}listing.css{/css}
    <div fx:item class="video">
        <div class="embed_code">
            {$embed_html}Code Here{/$}
        </div>
        <div class="description">
            {$description}Description{/$}
        </div>
    </div>
    {call id="component_content.pagination" /}
</div>