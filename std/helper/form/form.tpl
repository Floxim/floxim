<form 
    fx:template="form" 
    fx:if="$ instanceof fx_form"
    action="{$action}" 
    method="{$method}" 
    class="fx_form {$class} {if $is_sent} fx_form_sent{/if}">
    <input type="hidden" name="{$.get_id()}" value="1" />
    {$.content}
        {apply messages /}
        {apply errors /}
        {$fields.find('type', 'submit', '!=') || .row /}
        <div class="fx_submit_row">
            {$fields.find('type', 'submit') || .input_block /}
        </div>
    {/$}
</form>

<form fx:template="form[$is_finished]" class="fx_form fx_form_sent fx_form_finished {$class}">
    {apply messages with $messages->find('after_finish') as $messages /}
</form>

<div fx:template='messages' class='fx_form_messages' fx:with-each='$messages'>
    <div fx:item class="fx_form_message">{$message /}</div>
</div>

<div 
    fx:template="row" 
    class="
        fx_form_row fx_form_row_type_{$type} fx_form_row_name_{$name} 
        {if $.errors} fx_form_row_error{/if}
        {if $required} fx_form_row_required{/if}">
    {apply label /}
    {apply errors  /}
    {apply input_block /}
</div>

<div fx:template="errors" fx:each="$.errors as $error" class="fx_form_error">
    {$error}
</div>

<label fx:template="label" class="fx_label" for="{$id}" fx:if="!in_array($type, array('hidden', 'submit'))">
    {%label_$name}{$label /}{/%}
    <span fx:if="$required" class="required">*</span>
</label>

<div fx:template="input_block" class="fx_input_block">
    {apply input /}
</div>

{template id="input_atts"}
    class="fx_input fx_input_type_{$type}"
    id="{$id}"
    name="{$name}"
    {if $is_disabled}disabled="disabled"{/if}
    {if $value && in_array($type, array('text', 'number', 'password'))}
        value="{$value | htmlspecialchars}"
    {/if}
    {if $placeholder && in_array($type, array('text', 'number', 'password', 'textarea'))}
        placeholder="{$placeholder}" 
    {/if}
    
{/template}

<input 
    fx:template="input[in_array($type, array('text', 'password'))]"
    type="{$type}"
    {apply input_atts /} />

<input 
    fx:template="input[$type == 'checkbox']"
    type="checkbox"
    {apply input_atts /}
    {if $value}checked="checked"{/if} />

<textarea
    fx:template="input[$type == 'textarea']"
    {apply input_atts /}>{$value | htmlentities}</textarea>

<input 
    fx:template="input[$type == 'submit']"
    type="submit"
    class="fx_input fx_input_type_submit"
    value="{$label /}" />

<select 
    fx:template="input[$type == 'select']"
    {apply input_atts /}>
    <option 
        fx:each="$values as $key => $name" 
        value="{$key}" 
        {if $value == $key}selected="selected"{/if}>{$name}</option>
</select>
    