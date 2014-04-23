<div fx:template="list" class="vacancy_list" fx:name="Vacancy list">
    {css}listing.css{/css}
    <div fx:item class="vacancy">
       <a href="{$url}"><h2>{$position}Position{/$}</h2></a>
    </div>
    {call id="component_content.pagination" /}
</div>