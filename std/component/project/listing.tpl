<div fx:template="list" class="project_list">
    <div fx:item class="project">
        <div class="image">
            <img src="{$image}" alt="" />
        </div>
        <div class="description">
            <h2><a href="{$url}">{$name}</a></h2>
            <div class="year">{$date|'Y'}</div>
            <div class="short_description">{$short_description}</div>
        </div>
    </div>
</div>