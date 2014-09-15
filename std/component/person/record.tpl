<div fx:template="record" class="std_record person_record" fx:name="Default person record" fx:with="$item">
    <div class="image">
        <img src="{$photo | 'max-width:500,max-height:500'}" alt="{$name}" />
    </div>
    <div class="data">
        <div class="name">{$full_name}{$name /}{/$}</div>
        <div class="tagline">{$short_description}</div>
        <div class="department" fx:aif="$department">
            <span class="field_title">{%department_title}Department:{/%}</span>
            <span class="field_value">{$department}</span>
        </div>
        <div class="birthday" fx:aif="$birthday">
            <span class="field_title">{%birthday_title}Birthday:{/%}</span>
            <span class="field_value">{$birthday|'d.m.Y'}</span>
        </div>
        <div class="description">{$description}</div>
    </div>
</div>