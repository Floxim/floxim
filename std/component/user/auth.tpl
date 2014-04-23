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