{template id="header"}
    {js}
        codekit.js
        http://yandex.st/highlightjs/8.0/highlight.min.js
        http://yandex.st/highlightjs/8.0/languages/php.min.js
    {/js}
    {if $_is_admin}
        {js}
            codekit_redactor.js
        {/js}
    {/if}
    {css}
        codekit.less
        http://yandex.st/highlightjs/8.0/styles/github.min.css
    {/css}
{/template}