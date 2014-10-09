<nav
    fx:template="listing_deep" 
    fx:of="component_section.list"
    fx:name="Deep menu"
    class="deep_menu">
        {css}deep.css{/css}
        {apply recursive_menu with $lv = 1 /}
        <ul fx:template="recursive_menu" fx:with-each="$items">
            <li fx:item class="menu_item_{$lv}">
                <a href="{$url}" {if $active}class="active"{/if}>{$name}</a>
                {call recursive_menu with $items = $submenu, $lv = $lv+1 /}
            </li>
        </ul>
</nav>