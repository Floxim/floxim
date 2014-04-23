<div fx:template="list" class="person_list">
    <div fx:item class="person">
        <h2><a href="{$url}">{$full_name}Unnamed article{/$}</a></h2>
        
        <div class="photo">
            <img fx:if="$photo" src="{$photo | 'max-width:100,max-height:100'}" alt="{$name}" />
        </div>
        <div class = "company_name">Postion: {$company}</div>
        <div class = "department">Department: {$department}</div>
        <div class = "birthday">Birthday: {$birthday|'d.m.Y'}</div>
        <div class="short_description">{$short_description}</div>
        {call id="component_contact.entity_contact"}{$items select="$contacts" /}{/call}
    </div>

    {call id="component_content.pagination" /}
</div>