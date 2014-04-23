<div fx:template="record" class="award_record_wrap">
	{css}listing.css{/css}
	<div fx:item class="award_record">
		<div class="left_block">
		  <div class="year">{$year}</div>
		</div>
		<div class="right_block">
		  <div class="image">
		      <img src="{$image}" alt="" />
		  </div>
          <div class="description">
            {$description}Description{/$}
          </div>		  
		</div>
		<div class="clear"></div>
	</div>
</div>