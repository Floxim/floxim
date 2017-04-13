<?php

namespace Floxim\Floxim\Asset;

use \Floxim\Floxim\System\Fx as fx;

class Fonts {
    
    
    public static function getLoaderJS($fonts)
    {
        fx::log($fonts);
        static $is_loaded = false;
        if (count($fonts) === 0) {
            return '';
        }
        ob_start();
        if (!$is_loaded) {
            $is_loaded = true;
            ?>
            <script src="<?= fx::path()->http('@floxim_js') ?>/webfont.js"></script>
            <?php
        }
        $avail_google = self::getGoogleFonts();
        $google = [];
        foreach ($fonts as $font) {
            if (!isset($avail_google[$font])) {
                continue;
            }
            $google []= $font.':'.join(',', $avail_google[$font]).':latin,cyrillic';
        }
        
        $theme_fonts = fx::env('theme')->getThemeFonts();
        foreach ($theme_fonts as $theme_font) {
            if (isset($theme_font['css'])) {
                fx::page()->addCssBundle(array($theme_font['css']));
            }
        }
        ?>
        <script>
        if (window.WebFont) {
          WebFont.load({
            google: {
              families: <?= json_encode($google) ?>
            }
          });
          }
        </script>
        <?php
        return ob_get_clean();
    }
    
    public static function loadFontMeta()
    {
        $url = 'https://www.googleapis.com/webfonts/v1/webfonts?key='.fx::config('dev.google_fonts_api_key');

        $all = json_decode(file_get_contents($url),1);

        $cyr = array();

        foreach ($all['items'] as $font) {
            
            if (!in_array('cyrillic', $font['subsets'])) {
                continue;
            }
            $cyr[$font['family']] = $font['variants'];
        }
        return $cyr;
    }
    
    public static function getGoogleFonts()
    {
        return array (
            'Andika' => 
            array (
              'regular',
            ),
            'Anonymous Pro' => 
            array (
              'regular',
              'italic',
              '700',
              '700italic',
            ),
            'Arimo' => 
            array (
              'regular',
              'italic',
              '700',
              '700italic',
            ),
            'Bad Script' => 
            array (
              'regular',
            ),
            'Comfortaa' => 
            array (
              '300',
              'regular',
              '700',
            ),
            'Cormorant' => 
            array (
              '300',
              '300italic',
              'regular',
              'italic',
              '500',
              '500italic',
              '600',
              '600italic',
              '700',
              '700italic',
            ),
            'Cormorant Garamond' => 
            array (
              '300',
              '300italic',
              'regular',
              'italic',
              '500',
              '500italic',
              '600',
              '600italic',
              '700',
              '700italic',
            ),
            'Cormorant Infant' => 
            array (
              '300',
              '300italic',
              'regular',
              'italic',
              '500',
              '500italic',
              '600',
              '600italic',
              '700',
              '700italic',
            ),
            'Cormorant SC' => 
            array (
              '300',
              'regular',
              '500',
              '600',
              '700',
            ),
            'Cormorant Unicase' => 
            array (
              '300',
              'regular',
              '500',
              '600',
              '700',
            ),
            'Cousine' => 
            array (
              'regular',
              'italic',
              '700',
              '700italic',
            ),
            'Cuprum' => 
            array (
              'regular',
              'italic',
              '700',
              '700italic',
            ),
            'Didact Gothic' => 
            array (
              'regular',
            ),
            'EB Garamond' => 
            array (
              'regular',
            ),
            'El Messiri' => 
            array (
              'regular',
              '500',
              '600',
              '700',
            ),
            'Exo 2' => 
            array (
              '100',
              '100italic',
              '200',
              '200italic',
              '300',
              '300italic',
              'regular',
              'italic',
              '500',
              '500italic',
              '600',
              '600italic',
              '700',
              '700italic',
              '800',
              '800italic',
              '900',
              '900italic',
            ),
            'Fira Mono' => 
            array (
              'regular',
              '700',
            ),
            'Fira Sans' => 
            array (
              '300',
              '300italic',
              'regular',
              'italic',
              '500',
              '500italic',
              '700',
              '700italic',
            ),
            'Forum' => 
            array (
              'regular',
            ),
            'Istok Web' => 
            array (
              'regular',
              'italic',
              '700',
              '700italic',
            ),
            'Jura' => 
            array (
              '300',
              'regular',
              '500',
              '600',
            ),
            'Kelly Slab' => 
            array (
              'regular',
            ),
            'Kurale' => 
            array (
              'regular',
            ),
            'Ledger' => 
            array (
              'regular',
            ),
            'Lobster' => 
            array (
              'regular',
            ),
            'Lora' => 
            array (
              'regular',
              'italic',
              '700',
              '700italic',
            ),
            'Marck Script' => 
            array (
              'regular',
            ),
            'Marmelad' => 
            array (
              'regular',
            ),
            'Merriweather' => 
            array (
              '300',
              '300italic',
              'regular',
              'italic',
              '700',
              '700italic',
              '900',
              '900italic',
            ),
            'Neucha' => 
            array (
              'regular',
            ),
            'Noto Sans' => 
            array (
              'regular',
              'italic',
              '700',
              '700italic',
            ),
            'Noto Serif' => 
            array (
              'regular',
              'italic',
              '700',
              '700italic',
            ),
            'Open Sans' => 
            array (
              '300',
              '300italic',
              'regular',
              'italic',
              '600',
              '600italic',
              '700',
              '700italic',
              '800',
              '800italic',
            ),
            'Open Sans Condensed' => 
            array (
              '300',
              '300italic',
              '700',
            ),
            'Oranienbaum' => 
            array (
              'regular',
            ),
            'PT Mono' => 
            array (
              'regular',
            ),
            'PT Sans' => 
            array (
              'regular',
              'italic',
              '700',
              '700italic',
            ),
            'PT Sans Caption' => 
            array (
              'regular',
              '700',
            ),
            'PT Sans Narrow' => 
            array (
              'regular',
              '700',
            ),
            'PT Serif' => 
            array (
              'regular',
              'italic',
              '700',
              '700italic',
            ),
            'PT Serif Caption' => 
            array (
              'regular',
              'italic',
            ),
            'Pattaya' => 
            array (
              'regular',
            ),
            'Philosopher' => 
            array (
              'regular',
              'italic',
              '700',
              '700italic',
            ),
            'Play' => 
            array (
              'regular',
              '700',
            ),
            'Playfair Display' => 
            array (
              'regular',
              'italic',
              '700',
              '700italic',
              '900',
              '900italic',
            ),
            'Playfair Display SC' => 
            array (
              'regular',
              'italic',
              '700',
              '700italic',
              '900',
              '900italic',
            ),
            'Poiret One' => 
            array (
              'regular',
            ),
            'Press Start 2P' => 
            array (
              'regular',
            ),
            'Prosto One' => 
            array (
              'regular',
            ),
            'Roboto' => 
            array (
              '100',
              '100italic',
              '300',
              '300italic',
              'regular',
              'italic',
              '500',
              '500italic',
              '700',
              '700italic',
              '900',
              '900italic',
            ),
            'Roboto Condensed' => 
            array (
              '300',
              '300italic',
              'regular',
              'italic',
              '700',
              '700italic',
            ),
            'Roboto Mono' => 
            array (
              '100',
              '100italic',
              '300',
              '300italic',
              'regular',
              'italic',
              '500',
              '500italic',
              '700',
              '700italic',
            ),
            'Roboto Slab' => 
            array (
              '100',
              '300',
              'regular',
              '700',
            ),
            'Rubik' => 
            array (
              '300',
              '300italic',
              'regular',
              'italic',
              '500',
              '500italic',
              '700',
              '700italic',
              '900',
              '900italic',
            ),
            'Rubik Mono One' => 
            array (
              'regular',
            ),
            'Rubik One' => 
            array (
              'regular',
            ),
            'Ruslan Display' => 
            array (
              'regular',
            ),
            'Russo One' => 
            array (
              'regular',
            ),
            'Scada' => 
            array (
              'regular',
              'italic',
              '700',
              '700italic',
            ),
            'Seymour One' => 
            array (
              'regular',
            ),
            'Stalinist One' => 
            array (
              'regular',
            ),
            'Tenor Sans' => 
            array (
              'regular',
            ),
            'Tinos' => 
            array (
              'regular',
              'italic',
              '700',
              '700italic',
            ),
            'Ubuntu' => 
            array (
              '300',
              '300italic',
              'regular',
              'italic',
              '500',
              '500italic',
              '700',
              '700italic',
            ),
            'Ubuntu Condensed' => 
            array (
              'regular',
            ),
            'Ubuntu Mono' => 
            array (
              'regular',
              'italic',
              '700',
              '700italic',
            ),
            'Underdog' => 
            array (
              'regular',
            ),
            'Yeseva One' => 
            array (
              'regular',
            ),
          );
    }
    
    public static function getCommonFonts()
    {
        $styles = array (
            '300',
            '300italic',
            'regular',
            'italic',
            '500',
            '500italic',
            '700',
            '700italic',
        );
        $fonts = ['Arial', 'Times', 'Tahoma', 'Verdana', 'Georgia'];
        $res = [];
        foreach ($fonts as $f) {
            $res[$f] = ['styles' => $styles];
        }
        return $res;
    }
    
    public static function getAvailableFonts()
    {
        $res = self::getGoogleFonts();
        $theme_fonts = fx::env('theme')->getThemeFonts();
        foreach ($theme_fonts as $font => $params) {
            $res[$font] = $params['styles'];
        }
        $common_fonts = self::getCommonFonts();
        foreach ($common_fonts as $font => $params) {
            $res[$font] = $params['styles'];
        }
        return $res;
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
