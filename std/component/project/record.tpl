<div fx:template="record" class="project_record_wrap">
	{css}listing.css{/css}
	<div fx:item class="project_record">
		<div class="left_block">
		  <div class="year">{$date|'d.m.Y'}</div>
		</div>
		<div class="right_block">
		  <div class="image">
		      <img src="{$image}" alt="" />
		  </div>
          <div class="description">
            <h3>{$client}Client{/$}</h3>
            {$description}Description{/$}
          </div>		  
		</div>
		<div class="clear"></div>
	</div>
</div>