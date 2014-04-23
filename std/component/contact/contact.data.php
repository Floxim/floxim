<?
class fx_data_content_contact extends fx_data_content {

    public function quicksearch($term = null) {
        if (!isset($term)) {
            return;
        }
        $terms = explode(" ", $term);
        if (count($terms)>0) {
            foreach ($terms as $tp) {
                $this->where_or(array('value', '%'.$tp.'%', 'LIKE'), array('contact_type', '%'.$tp.'%', 'LIKE'));
            }
        }
        $items = $this->all();
        $res = array('meta' => array(), 'results' => array());
        foreach ($items as $i) {
            $res['results'][]= array(
                'name' => $i['contact_type'].': '.$i['value'],
                'id' => $i['id']
            );
        }
        return $res;
    }

}
?>