<div fx:template="list" class="std_person_list" fx:name="Default person list">
    {css}person.less{/css}
    <div fx:item class="person">
        <div class="photo" fx:if="$photo">
            <img src="{$photo | 'max-width:150,max-height:150'}" alt="{$name}" />
        </div>
        <div class="info">
            <h2><a href="{$url}">{$full_name}{$name /}{/$}</a></h2>
            <div fx:if="$short_description" class="short_description">{$short_description}</div>
            <div fx:if="$department" class="department">Department: <b>{$department}</b></div>
            <div fx:if="$birthday" class="birthday">Birthday: <b>{$birthday|'F, d'}</b></div>
        </div>
    </div>
    {apply component_content.pagination}
</div>