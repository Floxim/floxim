<div fx:template="pagination" class="pagination">
    {each select="array($pagination)"}
        <a fx:if="$prev" class="prev" href="{$prev}">
            {%prev_page}&lt;&lt;&lt;{/%}
        </a>
        
        {each select="$links"}
            <a fx:if="!$active" href="{$url}">{$page}</a>
            <b fx:if="$active">{$page}</b>
            <span fx:template="separator">{%pagination_separator} | {/%}</span>
        {/each} 
        
        <a fx:if="$next" href="{$next}" class="next">
            {%next_page}&gt;&gt;&gt;{/%}
        </a>
    {/each}
</div>