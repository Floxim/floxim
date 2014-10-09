<div fx:template="record" class="std_record product_record" fx:name="Default product record" fx:with="$item">
    <div class="image">
        <img src="{$image | 'max-width:500,max-height:500'}" alt="{$name}" />
    </div>
    <div class="data">
        <div class="name">{$name /}</div>
        <div class="tagline">{$short_description}</div>
        <div class="price" fx:aif="$price">
            <span class="field_title">{%price_title}Price:{/%}</span>
            <span class="field_value">{$price}</span>&nbsp;<span class="currency">{%currency}USD{/%}</span>
        </div>
        <div class="description">{$description}</div>
    </div>
</div>