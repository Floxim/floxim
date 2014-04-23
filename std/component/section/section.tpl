<div 
    fx:template="list" 
    fx:name="Standard menu"
    id="menu" 
    class="std_menu">
    <ul>
        <li fx:item>
            <a href="{$url}">
                <span class="mw"><span class="mw">
                    <span>{$name}</span>
                </span></span>
            </a>
        </li>
        <li fx:item="$is_active">
            <a href="{$url}">
                <span class="mw"><span class="mw">
                    <span style="color:#F00;">{$name}</span>
                </span></span>
            </a>
            <ul fx:with-each="$submenu" class="submenu">
                <li fx:item><a href="{$url}">{$name}</a></li>
            </ul>
        </li>
    </ul>
</div>