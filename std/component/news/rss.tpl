<div fx:omit="true" fx:template="listing_rss">
<?='<'.'?xml version="1.0"?'.'>'?>
<?
$_is_admin = false;
?>
    <rss version="2.0" xmlns:dc="http://purl.org/dc/elements/1.1/">
        <channel>
            {each select="$blog"}
                <title>{$blog_name}{$name /}{/$}</title>
                <link>{$blog_url}{$base_url /}{$url /}{/$}</link>
                <description>{$blog_description}{$description /}{/$}</description>
            {/each}
            <item fx:each="$items">
                <title>{$name}</title>
                <link>{$base_url}{$url}</link>
                <pubDate>{$publish_date | 'r'}</pubDate>
                <description>
                    <?ob_start();?>
                        {$anounce}
                        <p fx:if="$tags">
                            {$tags_label}Posted under:{/$} 
                            <a fx:each="$tags" fx:separator=", " href="{$base_url}{$url}">
                                {$name}
                            </a>
                        </p>
                    <?=htmlspecialchars(ob_get_clean());?>
                </description>
            </item>
        </channel>
    </rss>
</div>

<div fx:template="listing_rss_configurator">
    {each select="$blog"}
        Feed title: 
            {%blog_name}
                {$name editable="false" /}
            {/%}
            <br />
        Blog url: 
            {%blog_link}
                {$base_url /}{$url editable="false" /}
            {/%}
            <br />
        Blog description: 
            {%blog_description}
                {$description editable="false"}Put description here{/$}
            {/%}
    {/each}
</div>