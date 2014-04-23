<div 
        fx:template="calendar" 
        class="blog_calendar" 
        data-expand="{%expand}false{/%}">
    {js}
        FX_JQUERY_PATH
        calendar.js
    {/js}
    {css}
        calendar.css
    {/css}
    <h2 fx:if="$show_header">{%header}Blog archive:{/%}</h2>
    <?
    $month_names = explode(
        ',', 
        ',January,February,March,April,May,June,'.
        'July,August,September,October,November,December'
    );
    ?>
    <div fx:item class="year{if $active || $expand == 'true'} year_active{/if}">
        <div class="year_title">{$year}</div>
        <div class="months">
            <div fx:each="$months">
                <a fx:omit="$active" href="{$url}">
                    {%month_$month}<?=$month_names[ (int) $month]?>{/%}
                </a>&nbsp;<sup class="counter">{$count}</sup>
            </div>
        </div>
    </div>
</div>