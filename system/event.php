<?
class fx_event {
    public $name = 'event';
    public function __construct($name, $params) {
        $this->name = $name;
        foreach ($params as $p => $v) {
            $this->$p = $v;
        }
    }
}
?>