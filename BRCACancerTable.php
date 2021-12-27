<?php
class BRCACancerTable {
    const BREAST_CANCER = 'breast';
    const OVARIAN_CANCER = 'ovary';
    const PANCREATIC_CANCER = 'pancreatic';
    const PROSTATE_CANCER = 'prostate';
    const COLON_CANCER = 'colon';
    private $_cancerTable;
    public function __construct() {
        $this->_cancerTable['C50911'] = ['code' => 'C50911','description' => 'Female right breast','type' => BRCACancerTable::BREAST_CANCER,"lateral" => "right"];
        $this->_cancerTable['C50912'] = ['code' => 'C50912','description' => 'Female left breast','type' => BRCACancerTable::BREAST_CANCER,"lateral" => "left"];
        $this->_cancerTable['C569'] = ['code' => 'C569','description' => 'Ovary','type' => BRCACancerTable::OVARIAN_CANCER,"lateral" => "no"];
        $this->_cancerTable['C50921'] = ['code' => 'C50921','description' => 'Male right breast','type' => BRCACancerTable::BREAST_CANCER,"lateral" => "right"];
        $this->_cancerTable['C50922'] = ['code' => 'C50922','description' => 'Male left breast','type' => BRCACancerTable::BREAST_CANCER,"lateral" => "left"];
        $this->_cancerTable['C61'] = ['code' => 'C61','description' => 'Prostate','type' => BRCACancerTable::PROSTATE_CANCER,"lateral" => "no"];
        $this->_cancerTable['C259'] = ['code' => 'C259','description' => 'Pancreatic','type' => BRCACancerTable::PANCREATIC_CANCER,"lateral" => "no"];
        $this->_cancerTable['C189'] = ['code' => 'C189','description' => 'Colon','type' => BRCACancerTable::COLON_CANCER,"lateral" => "no"];
    }
    
    /**
     * 
     * @return BRCACancerTable
     */
    public static function getInstance() {
        static $instance = null;
        if (null === $instance) {
            $instance = new static();
        }
        
        return $instance;
    }
    public function getCancerType($code, &$lateral) {
        $lateral = null;
        if (! array_key_exists($code, $this->_cancerTable))
            return null;
        
        $lateral = $this->_cancerTable[$code]['lateral'];
        return $this->_cancerTable[$code]['type'];
    }
    public function isMale($code) {
        if (! array_key_exists($code, $this->_cancerTable))
            return null;
        $desc = $this->_cancerTable[$code]['description'];
        if (strpos($desc, "Male") !== false) {
            return (true);
        } elseif (strpos($desc, "Female") === false) {
            // Not specified Male nor Female
            return (true);
        }
        return false;
    }
    public function isFemale($code) {
        if (! array_key_exists($code, $this->_cancerTable))
            return null;
        $desc = $this->_cancerTable[$code]['description'];
        if (strpos($desc, "Female") !== false) {
            return (true);
        } elseif (strpos($desc, "Male") === false) {
            // Not specified Male nor Female
            return (true);
        }
        return false;
    }
    public function getCancerDescription($code) {
        if (! array_key_exists($code, $this->_cancerTable))
            return "";
        
        return $this->_cancerTable[$code]['description'];
    }
}