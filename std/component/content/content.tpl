<div fx:template="pagination" class="pagination" fx:with-each="$links">
    <a 
        fx:if="$prev" 
        class="prev" 
        href="{$prev}">{%prev_page}&lt;{/%}</a>
    &nbsp;
    <a fx:item href="{$url}">{$page}</a>
    <b fx:item="$active">{$page}</b>
    <span fx:separator>{%pagination_separator} | {/%}</span>
    &nbsp;
    <a 
        fx:if="$next" 
        href="{$next}" 
        class="next">{%next_page}&gt;{/%}</a>
</div>