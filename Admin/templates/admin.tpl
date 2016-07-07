{template id="back_office_layout"}
<!DOCTYPE html>
<html class="fx_overlay fx_admin_html">
    <head>
    </head>
    <body>
        {$content}
    </body>
</html>
{/template}
        
<a 
    fx:template="menu_item" 
    class="fx_menu_item {$class} {if $.icon}fx_menu_item-has_icon{/if} {if $.code}fx-menu-item_code_{$.code /}{/if}" 
    {if $href}href="{$href}"{/if} 
    {if $key}data-key="{$key}"{/if}
    {if $.button}data-button='{$.button | json_encode}{/if}'
    {if $.data}data-data='{$.data | json_encode}'{/if}>
    <span fx:if="$.icon" class="fx_icon fx_icon-type-{$icon}"></span>
    <span class="fx_menu_item__name">{$name}</span>
</a>

<div fx:template="menu_item[$.children && count($.children) > 0]" class="fx_menu_item fx_menu_item-has_dropdown">
    <a class="fx_menu_item__link" {if $href}href="{$href}"{/if}>{$name}</a>
    <span class="fx_menu_item__arrow"></span>
    <div class="fx_dropdown">
        {apply menu_item each $.children with $class = 'fx_dropdown__item' /}
    </div>
</div>

{template id="panel"}
<div class="fx-progress-line"></div>
<div fx:b="fx-admin-panel" class="fx_overlay">
    <div fx:e="logo"><div class="fx_preloader"></div></div>
    <div fx:if="$panel_title" fx:e="title">{$panel_title}</div>
    <div id="fx_admin_main_menu" class="fx_button_group">
        <div class="fx_main_menu_expander"></div>
        <div class="fx_main_menu_items">
            {$main_menu || :menu_item /}
            {apply menu_item with $profile /}
        </div>
    </div>
    <div fx:if="$is_front" id="fx_admin_front_menu">
        {apply menu_item each $modes with $class = 'fx_front_mode' /}
        {apply menu_item with $more_menu /}
    </div>
</div>
<div fx:if="$is_front" id="fx_admin_control" class="fx_overlay">
    <div class="fx-top-panel"></div>
    <!--
    <div fx:b="fx-side-panel">
        <div fx:e="body"></div>
    </div>
    -->
</div>
{/template}

{template id="back_office"}
    {call back_office_layout}
        {$panel /}
        <div id="fx_admin_left">
            <div id="fx_admin_submenu"></div>
        </div>
        <div id="fx_admin_right">
            <div id="fx_admin_control" class="fx_admin_control_admin">
                <div id="fx_admin_buttons"></div>
                <div id="fx_admin_status_block"></div>
             </div>
             <div id="fx_admin_breadcrumb"></div>
             <div id="fx_admin_content" class="fx_overlay"></div>
        </div>
    {/call}
{/template}
    
{template id="authorize"}
    {call back_office_layout}
        {call panel}
            {$panel_title}{lang}Welcome to Floxim CMS! Please sign in.{/lang}{/$}
        {/call}
        <div fx:b="fx-backend-login">
            <div fx:e="auth" class="fx_admin_form">
                {$auth_form}
                <a fx:e="recover-link">{lang}I've lost my password{/lang}</a>
            </div>
            <div fx:e="recover"  class="fx_admin_form">
                {$recover_form}
                <a fx:e="login-link">{lang}Back to log in{/lang}</a>
            </div>
        </div>
    {/call}
{/template}