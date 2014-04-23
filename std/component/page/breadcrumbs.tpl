<div fx:template="breadcrumbs" class="breadcrumbs">
    <a fx:item href="{$url}">{$name}</a>
    <span class="separator" fx:separator>{%separator} / {/%}</span>
    <h1 fx:item="$is_current">{$h1}{$name /}{/$}</h1>!
</div>