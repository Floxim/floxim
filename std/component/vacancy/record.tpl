<div fx:template="record" class="std_record vacancy_record" fx:name="Default vacancy record" fx:with="$item">
    <div class="image" fx:aif="$image">
        <img src="{$image | 'max-width:500,max-height:500'}" alt="{$name}" />
    </div>
    <div class="data">
        <div class="name">{$name /}</div>
        <div class="tagline">{$short_description}</div>
        <div class="salary" fx:aif="$salary_from || $salary_to">
            <span class="field_title">{%salary_title}Salary:{/%}</span>
            <span class="field_value">
                <span fx:aif="$salary_from">
                    {$salary_from}
                </span>
                <span fx:aif="$salary_from || $salary_to">{%salary_separator}&nbsp;&ndash;&nbsp;{/%}</span>
                <span fx:aif="$salary_to">
                    {$salary_to}
                </span>
                <span class="currency">{$currency}</span>
            </span>
        </div>
        <div 
            fx:each="array('requirements', 'responsibilities', 'work_conditions', 'description') as $prop" 
            class="{$prop}"
            fx:aif="$item.$prop">
            <h3 class="field_title">{%title_$prop}{$prop | ucfirst | str_replace : '_' : '&nbsp;' : self /}{/%}</h3>
            <div class="field_value">{$item.$prop}</div>
        </div>
    </div>
</div>