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


{template id="panel"}
<div id="fx_admin_panel" class="fx_overlay">
    <div id="fx_admin_panel_logo"><div class="fx_preloader"></div></div>
    <div id="fx_admin_main_menu" class="fx_button_group">
        <div fx:each="$main_menu" 
           class="fx_button fx_button-in_group {if $children}fx_button-has_dropdown fx_button-has_own_action{/if}" 
           data-href="{$href}" 
           data-key="{$key}">
            {$name /}
            <span fx:if="$children" class="fx_button__arrow"></span>
            <div fx:with-each="$children" class="fx_dropdown">
                <div fx:item data-href="{$href}" class="fx_button fx_button-in_dropdown">
                    {$name /}
                </div>
            </div>
        </div>
    </div>
    <div fx:if="$is_front" id="fx_admin_front_menu">
        <div fx:each="$modes" 
           class="fx_button fx_button-in_group fx_front_mode" 
           data-key="{$key}">
            {$name /}
        </div><div 
            fx:with-each="$more_menu" class="fx_button fx_button-in_group fx_button-has_dropdown">
            More
            <span class="fx_button__arrow"></span>
            <div  class="fx_dropdown">
                <div fx:item data-href="{$href}" class="fx_button fx_button-in_dropdown">
                    {$name /}
                </div>
            </div>
        </div>
    </div>
    <div id="fx_admin_additional_menu" fx:with="$profile.logout">
        <a class="fx_button" data-href="$url">{$name}</a>
    </div>
</div>
<div fx:if="$is_front" id="fx_admin_control" class="fx_overlay">
    <div id="fx_admin_extra_panel">
        <div class="fx_admin_panel_title"></div>
        <div class="fx_admin_panel_body"></div>
        <div class="fx_admin_panel_footer"></div>
    </div>
    <div class="fx_side_panel">
        <div class="fx_side_panel__title"></div>
        <div class="fx_side_panel__body"></div>
        <div class="fx_side_panel__footer"></div>
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