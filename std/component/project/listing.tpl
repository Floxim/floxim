<div fx:template="list" fx:name="Default project list" class="project_list" fx:size="wide,high">
    <div fx:item class="project">
        <div class="image">
            <img src="{$image | 'max-width:500'}" alt="" />
        </div>
        <div class="description">
            <h2><a href="{$url}">{$name}</a></h2>
            <div fx:if="$date" class="year">{$date|'Y'}</div>
            <div class="short_description">{$short_description}</div>
        </div>
    </div>
</div>