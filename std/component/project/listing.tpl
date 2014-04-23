<div fx:template="list" class="project_list">
	{css}listing.css{/css}
	<div fx:item class="project">
	   <div class="year">{$date|'d.m.Y'}2000{/$}</div>
		<div class="image">
			<img src="{$image}" alt="" />
		</div>
		<div class="description">
		    <a href="{$url}"><h2>{$name}Name{/$}</h2></a>
		    <h3>{$client}Client{/$}</h3>
			{$short_description}Description{/$}
		</div>
	    <div class="clear"></div>
	</div>

    {call id="component_content.pagination" /}
</div>