<div 
    class="img-list" 
    fx:template="listing_slider" 
    fx:of="component_photo.list">
	
    {js}
	FX_JQUERY_PATH
        script.js
    {/js}
    {css}listing_slider.css{/css}
    <div class="images fx_not_sortable">
        <div 
            fx:each="$items" 
            class="img-block {if $item_is_first}img-block-active{/if} pic_{$id}">
            <img src="{$photo}" alt="{$description editable="false"}" />
                <span class="left">{$description}</span>
                <span class="right" fx:if="$copy">&copy; {$copy}</span>
        </div>
    </div>
    <div class="img-slider">
    	<div 
            fx:each="$items" 
            class="preview{if $item_is_first} preview-active{/if} pic_preview_{$id}" data-pic_id="{$id}">
                <img src="{$photo | 'height:100'}" />
    	</div>
    </div>
</div>