<div fx:template="record" class="vacancy_record_wrap">
	{css}listing.css{/css}
	<div fx:item class="vacancy_record">
	   <h2>{$position}</h2>
	   <div class="text-block">
	       <h3>Responsibilities</h3>
	       {$responsibilities}
	   </div>
	   <div class="text-block">
	       <h3>Requirements</h3>
	       {$requirements}
	   </div>
	   <div class="text-block">
	       <h3>Work Conditions</h3>
	       {$work_conditions}
	   </div>
	   <div class="salary" fx:if="$salary_from || $salary_to">
	       {if $salary_from}From {$salary_from} {/if}
	       {if $salary_to}To {$salary_to}{/if}
	   </div>
	   <div class="text-block">
	       <h3>Contacts</h3>
	       <div fx:if="$phone">Phone: {$phone}</div>
	       <div fx:if="$email">Email: {$email}</div>
	       <div fx:if="$contacts_name">Contact's name: {$contacts_name}</div>
	   </div>
	</div>
</div>