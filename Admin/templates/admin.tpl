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
    class="fx_menu_item {$class} {if $.icon}fx_menu_item-has_icon{/if}" 
    {if $href}href="{$href}"{/if} 
    {if $key}data-key="{$key}"{/if}>
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
<div id="fx_admin_panel" class="fx_overlay">
    <div id="fx_admin_panel_logo"><div class="fx_preloader"></div></div>
    <div fx:if="$is_front" id="fx_admin_front_menu">
        {apply menu_item each $modes with $class = 'fx_front_mode', $icon = $key /}
        {apply menu_item with $more_menu /}
    </div>
    <div id="fx_admin_main_menu" class="fx_button_group">
        <div class="fx_main_menu_expander"></div>
        <div class="fx_main_menu_items">
            {$main_menu || :menu_item /}
            {apply menu_item with $profile.logout /}
        </div>
    </div>
</div>
<div fx:if="$is_front" id="fx_admin_control" class="fx_overlay">
    <div id="fx_admin_extra_panel">
        <!--<div class="fx_admin_panel_title"></div>-->
        <div class="fx_admin_panel_body"></div>
        <!--<div class="fx_admin_panel_footer"></div>-->
    </div>
    <div class="fx_side_panel">
        <!--<div class="fx_side_panel__title"></div>-->
        <div class="fx_side_panel__body"></div>
        <!--<div class="fx_side_panel__footer"></div>-->
    </div>
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
            <div class="fx_backend_login_title">Welcome to Floxim CMS! Please sign in.</div>
        {/call}
        <div class="fx_backend_login">
            <div class="auth_block">
                {$auth_form}
                <a class="srv recover_link">I've lost my password</a>
            </div>
            <div class="recover_block">
                {$recover_form}
                <a class="srv login_link">Back to log in</a>
            </div>
        </div>
    {/call}
{/template}