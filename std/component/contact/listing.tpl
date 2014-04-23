<div 
    fx:template="entity_contact" 
    fx:of="list" 
    fx:name="Contacts" 
    class="contact">
        {%tags_label}Contacts:{/%} 
        <ul>
        <li fx:item>{$contact_type}: {$value}</li>
        </ul>
</div>