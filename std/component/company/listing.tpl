<div fx:template="list" class="company_list">
	{css}listing.css{/css}
	<div fx:item class="company_item">
		<div class="image">
                    <img src="{$logo}" alt="" />
		</div>
		<div class="description">
		    <a href="{$url}"><h2>{$name}Name{/$}</h2></a>
			{$short_description}Description{/$}
		</div>
	    <div class="clear"></div>
	</div>

    {call id="component_content.pagination" /}
</div>