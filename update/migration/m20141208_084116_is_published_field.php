<?php
class m20141208_084116_is_published_field extends \Floxim\Floxim\System\Migration {

    // Run for up migration
    protected function up() {
        $new_fields = array (
            array(
                'keyword' => 'is_published',
                'name_en' => 'Is published?',
                'name_ru' => '',
                'type' => '5',
                'not_null' => '0',
                'priority' => '267',
                'searchable' => '0',
                'default' => '1',
                'type_of_edit' => '1',
                'checked' => '1',
                'form_tab' => '0',
              ),
            array (
              'keyword' => 'is_branch_published',
              'name_en' => 'Is branch published?',
              'name_ru' => '',
              'type' => '5',
              'not_null' => '0',
              'priority' => '268',
              'searchable' => '0',
              'default' => '1',
              'type_of_edit' => '3',
              'checked' => '1',
              'form_tab' => '0',
            )
        );
        $content_id = fx::data('component', 'content')->get('id');
        foreach ($new_fields as $field_props) {
            $field_props['component_id'] = $content_id;
            $field = fx::data('field')->create($field_props);
            $field->save();
            fx::log('add field', $field);
        }
        fx::data('component')->dropStoredStaticCache();
        fx::db()->query('update {{floxim_main_content}} set is_published = 1, is_branch_published = 1');
    }

    // Run for down migration
    protected function down() {

    }

}