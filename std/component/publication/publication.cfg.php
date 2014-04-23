<?php
return array(
    'actions' => array(
        'list*' => array(
            'icon' => 'Pub',
        ),
        '*listing_by_tag' => array(
            'name' => $component['name'].' by tag',
        	'icon' => 'Pub',
            'check_context' => function($page) {
                return $page->is_instanceof('tag');
            }
        ),
        '*calendar' => array(
        	'icon_extra' => 'cal',
            'icon' => self::_get_abbr($component['name']),
        ),
        'calendar*' => array(
        	'icon' => 'Pub',
        ),
    )
);