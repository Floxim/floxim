<div class="fx_auth_form" fx:template="auth_form" fx:of="user.auth_form">
    {apply helper_form.form with $form /}
</div>

<div class="fx_recover_form" fx:template="recover_form" fx:of="user.recover_form">
    {apply helper_form.form with $form /}
</div>

<div fx:template="greet" fx:of="user.greet" class="fx_user_greet">
    {%hello}Hello, {/%} 
    <a class="fx_profile_link" href="{$profile_url}" fx:omit="!$profile_url">{$user.name}</a>
    <a class="fx_logout_link" href="{$logout_url}">{%logout}Log out{/%}</a>
</div>

<div 
    class="crossite_auth_form" 
    fx:template="crossite_auth_form" 
    fx:of="user._crossite_auth_form"
    data-target_location="{$target_location}">
        <script type="text/javascript" src="<?= FX_JQUERY_PATH ?>"></script>
        <script type="text/javascript" src="<?= $template_dir ?>crossite_auth.js"></script>
        <style type="text/css">
            .crossite_auth_form iframe {width:1000px; height:50px;}
        </style>
        <form 
            fx:each="$hosts as $host" 
            method="post"
            action="http://{$host}{$auth_url}" 
            target="iframe_{$position}">
                <iframe name="iframe_{$position}"></iframe><br />
                <input type="hidden" name="session_key" value="{$session_key /}" />
                <input type="submit" />
        </form>
</div>