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
    {$content}
        <div id="fx_admin_main_menu"></div>
        <div fx:if="$is_front" id="fx_admin_page_modes"></div>
        <div fx:if="$is_front" id="fx_admin_more_menu"></div>
        <div id="fx_admin_additional_menu">
            <a class="fx_logout" data-url="<?php echo fx::user()->getLogoutUrl() ?>">
                <?php echo fx::alang('Sign out','system')?>
            </a>
        </div>
        <div id="fx_admin_clear"></div>
    {/$}
</div>
<div fx:if="$is_front" id="fx_admin_control" class="fx_overlay">
    <div id="fx_admin_extra_panel">
        <div class="fx_admin_panel_title"></div>
        <div class="fx_admin_panel_body"></div>
        <div class="fx_admin_panel_footer"></div>
    </div>
</div>
{/template}

{template id="back_office"}
    {call back_office_layout}
        {call panel /}
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