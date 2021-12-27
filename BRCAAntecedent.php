<?php

/**
 * ***************************************************
 */
class BRCAAntecedent {
    public $cancerCode;
    public $relativeCode;
    public $relativeName;
    public $onsetAge;

    /**
     * Returns a key that uniquely identifies the relative
     *
     * @return string
     */
    public function getRelativeKey() {
        return $this->relativeCode . "@" . $this->relativeName;
    }

    /**
     * Returns a key that uniquely identifies the cancer type
     *
     * @return string
     */
    public function getCancerKey() {
        return $this->cancerCode;
    }

    public function getCancerType() {
        $lateral = null;
        return BRCACancerTable::getInstance()->getCancerType($this->cancerCode, $lateral);
    }

    public function breastCancer(&$lateral) {
        $lateral = null;
        return (BRCACancerTable::getInstance()->getCancerType($this->cancerCode, $lateral) == BRCACancerTable::BREAST_CANCER);
    }

    public function colonCancer() {
        $lateral = null;
        return (BRCACancerTable::getInstance()->getCancerType($this->cancerCode, $lateral) == BRCACancerTable::COLON_CANCER);
    }

    public function ovarianCancer() {
        $lateral = null;
        return (BRCACancerTable::getInstance()->getCancerType($this->cancerCode, $lateral) == BRCACancerTable::OVARIAN_CANCER);
    }

    public function pancreaticCancer() {
        $lateral = null;
        return (BRCACancerTable::getInstance()->getCancerType($this->cancerCode, $lateral) == BRCACancerTable::PANCREATIC_CANCER);
    }

    public function prostateCancer() {
        $lateral = null;
        return (BRCACancerTable::getInstance()->getCancerType($this->cancerCode, $lateral) == BRCACancerTable::PROSTATE_CANCER);
    }
}
