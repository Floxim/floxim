<?php

namespace Floxim\Floxim\Asset;

class Fonts {
    
    
    public static function getLoaderJS($fonts)
    {
        static $is_loaded = false;
        ob_start();
        if (!$is_loaded) {
            $is_loaded = true;
            ?>
            <script src="https://ajax.googleapis.com/ajax/libs/webfont/1.6.16/webfont.js"></script>
            <?php
        }
        foreach ($fonts as &$font) {
            $font = $font.':latin,cyrillic';
        }
        ?>
        <script>
          WebFont.load({
            google: {
              families: <?= json_encode($fonts) ?>
            }
          });
        </script>
        <?php
        return ob_get_clean();
    }
    
    public static function getAvailableFonts()
    {
        return array (
            'Open Sans',
            'Roboto',
            'Roboto Condensed',
            'Lora',
            'PT Sans',
            'Open Sans Condensed',
            'Ubuntu',
            'Roboto Slab',
            'Arimo',
            'PT Sans Narrow',
            'Merriweather',
            'Noto Sans',
            'PT Serif',
            'Poiret One',
            'Playfair Display',
            'Lobster',
            
            'Verdana',
            'Tahoma',
            'Times',
            'Arial',
            'Georgia',
            
            'Noto Serif',
            'Fira Sans',
            'Exo 2',
            'Ubuntu Condensed',
            'Cuprum',
            'Play',
            'PT Sans Caption',
            'Istok Web',
            'Comfortaa',
            'EB Garamond',
            'Russo One',
            'Philosopher',
            'Tinos',
            'Roboto Mono',
            'Bad Script',
            'Didact Gothic',
            'Rubik',
            'Jura',
            'Marck Script',
            'Playfair Display SC',
            'Marmelad',
            'Scada',
            'PT Serif Caption',
            'PT Mono',
            'Ubuntu Mono',
            'Neucha',
            'Press Start 2P',
            'Oranienbaum',
            'Forum',
            'Kelly Slab',
            'Tenor Sans',
            'Cousine',
            'Anonymous Pro',
            'Prosto One',
            'Andika',
            'Yeseva One',
            'Fira Mono',
            'Ledger',
            'Rubik One',
            'Ruslan Display',
            'Kurale',
            'Rubik Mono One',
            'Seymour One',
            'Underdog',
            'Stalinist One'
        );
    }
    
    public static function getAvailableFontValues()
    {
        $res = array();
        $fonts = self::getAvailableFonts();
        foreach ($fonts as $f) {
            $res[] = array($f, $f);
        }
        return $res;
    }
}
