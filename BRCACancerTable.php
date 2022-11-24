<?php

class BRCACancerTable {
    const BREAST_CANCER = 'breast';
    const OVARIAN_CANCER = 'ovary';
    const PANCREATIC_CANCER = 'pancreatic';
    const PROSTATE_CANCER = 'prostate';
    const COLON_CANCER = 'colon';
    private $_cancerTable;

    public function __construct() {
        $this->_cancerTable['C50911'] = ['code' => 'C50.911', 'description' => 'Female right breast', 'type' => BRCACancerTable::BREAST_CANCER,
                "lateral" => "right"];
        $this->_cancerTable['C50912'] = ['code' => 'C50.912', 'description' => 'Female left breast', 'type' => BRCACancerTable::BREAST_CANCER,
                "lateral" => "left"];
        $this->_cancerTable['C56900'] = ['code' => 'C56.9', 'description' => 'Ovary', 'type' => BRCACancerTable::OVARIAN_CANCER, "lateral" => "no"];
        $this->_cancerTable['C50921'] = ['code' => 'C50.921', 'description' => 'Male right breast', 'type' => BRCACancerTable::BREAST_CANCER,
                "lateral" => "right"];
        $this->_cancerTable['C50922'] = ['code' => 'C50.922', 'description' => 'Male left breast', 'type' => BRCACancerTable::BREAST_CANCER,
                "lateral" => "left"];
        $this->_cancerTable['C61000'] = ['code' => 'C61', 'description' => 'Prostate', 'type' => BRCACancerTable::PROSTATE_CANCER, "lateral" => "no"];
        $this->_cancerTable['C25900'] = ['code' => 'C25.9', 'description' => 'Pancreatic', 'type' => BRCACancerTable::PANCREATIC_CANCER,
                "lateral" => "no"];
        $this->_cancerTable['C18900'] = ['code' => 'C18.9', 'description' => 'Colon', 'type' => BRCACancerTable::COLON_CANCER, "lateral" => "no"];
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
        $code = self::normalizeICDCode($code);
        $lateral = null;
        if (!array_key_exists($code, $this->_cancerTable))
            return null;

        $lateral = $this->_cancerTable[$code]['lateral'];
        return $this->_cancerTable[$code]['type'];
    }

    public function isMale($code) {
        $code = self::normalizeICDCode($code);
        if (!array_key_exists($code, $this->_cancerTable))
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
        $code = self::normalizeICDCode($code);
        if (!array_key_exists($code, $this->_cancerTable))
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
        $code = self::normalizeICDCode($code);
        if (!array_key_exists($code, $this->_cancerTable))
            return "";

        return $this->_cancerTable[$code]['description'];
    }

    static private function normalizeICDCode($code) {
        $code = strtoupper(str_replace('.', '', $code));
        return str_pad($code, 6, '0', STR_PAD_RIGHT);
    }
}