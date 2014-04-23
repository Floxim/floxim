<div
    fx:template="listing_deep" 
    fx:of="component_section.list"
    fx:name="Deep menu"
    class="deep_menu">
        {css}deep.css{/css}
        
        {apply recursive_menu}{$level select="1"}{/apply}
        <ul fx:template="recursive_menu" fx:with-each="$items">
            <li fx:item class="menu_item_{$level}">
                <a href="{$url}" {if $active}class="active"{/if}>{$name}</a>
                {call recursive_menu}
                    {$items select="$submenu"}
                    {$level select="$level+1"}
                {/call}
            </li>
        </ul>
</div>