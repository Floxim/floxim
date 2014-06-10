<?
class fx_data_content_contact extends fx_data_content {

    public function livesearch($term = null, $limit = null) {
        if (!isset($term)) {
            return;
        }
        $terms = explode(" ", $term);
        if (count($terms)>0) {
            foreach ($terms as $tp) {
                $this->where_or(array('value', '%'.$tp.'%', 'LIKE'), array('contact_type', '%'.$tp.'%', 'LIKE'));
            }
        }
        if ($limit) {
            $this->limit($limit);
        }
        $this->calc_found_rows(true);
        $items = $this->all();
        $count = $this->get_found_rows();
        $res = array('meta' => array('total'=>$count), 'results' => array());
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