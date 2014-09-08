<div class="fx_neighbours" fx:template="neighbours" fx:of="page.neighbours">
    {css}
        neighbours.less as neighbours
    {/css}
    <div fx:with-each="$prev" class="fx_prev">
        <span class="arrow">{%prev_arrow}&larr;{/%}</span>
        <div fx:item><a href="{$url}">{$name}</a></div>
    </div>
    <div fx:with-each="$next" class="fx_next">
        <div fx:item><a href="{$url}">{$name}</a></div>
        <span class="arrow">{%next_arrow}&rarr;{/%}</span>
    </div>
</div>