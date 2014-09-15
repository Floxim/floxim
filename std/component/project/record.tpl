<div fx:template="record" class="std_record project_record" fx:name="Default project record" fx:with="$item">
    <div class="image">
        <img src="{$image | 'max-width:500,max-height:500'}" alt="{$name}" />
    </div>
    <div class="data">
        <div class="name">{$name}</div>
        <div class="date">{$date|'d.m.Y'}</div>
        <div class="short_description">{$short_description}</div>
        <div class="description">{$description}</div>
    </div>
</div>