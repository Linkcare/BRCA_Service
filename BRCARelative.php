<?php

/**
 * ***************************************************
 */
class BRCARelative {
    /* @var BRCAAntecedent[] $antecedents */
    public $antecedents = []; // each relative can have had many types of cancer
    public $relativeCode;
    public $relativeName;
    private $_gender;
    private $_degree = 0;
    private $_firstRelative;

    /**
     * Generates an array or BRCARelatives from an array of BRCAAntecedents
     * Remember that a relative could have more than one antecedent
     *
     * @param BRCAAntecedent[] $antecedents
     */
    static function generateRelativeList($antecedents) {
        $relatives = [];
        foreach ($antecedents as $a) {
            if (array_key_exists($a->getRelativeKey(), $relatives)) {
                $r = $relatives[$a->getRelativeKey()];
            } else {
                $r = new BRCARelative();
                $relatives[$a->getRelativeKey()] = $r;
            }
            $r->addAntecedent($a);
        }

        return $relatives;
    }

    /**
     *
     * @param BRCAAntecedent $a
     */
    public function addAntecedent($a) {
        if (!array_key_exists($a->getCancerKey(), $this->antecedents)) {
            $this->antecedents[$a->getCancerKey()] = $a; // Avoid duplication of antecedents
            $this->relativeCode = trim($a->relativeCode);
            $this->relativeName = trim($a->relativeName);

            // TODO: this calculation should be done obtaining the list of RELATIVE CODES from Linkcare and the information about the degree
            $tree = explode(".", $this->relativeCode);
            if (strpos($tree[0], 'RELATIVE') === 0) {
                // If the first part of the RELATIVE CODE == "RELATIVE", it refers to myself (e.g. RELATIVE(M).PARENT(F) is my father
                $this->_degree = count($tree) - 1;
                $this->_firstRelative = $tree[1];
                $tree = array_slice($tree, 1); // remove the first part of the relative code, because it refers to myself
                $this->relativeCode = implode(".", $tree); // compose again the relative code without the reference to myself
            } else {
                $this->_degree = count($tree);
                $this->_firstRelative = $tree[0];
            }

            // The gender is indicated in the last part of the relative code
            if (strpos($tree[count($tree) - 1], "(M)") !== false) {
                $this->_gender = "M";
            } else {
                $this->_gender = "F";
            }
        }
    }

    public function degree() {
        return ($this->_degree);
    }

    public function isMale() {
        return ($this->_gender == "M");
    }

    public function isFemale() {
        return ($this->_gender == "F");
    }

    public function rightBreastCancer() {
        /* @var BRCAAntecedent $a */
        foreach ($this->antecedents as $a) {
            $lateral = null;
            if ($a->breastCancer($lateral) && ($lateral == "right")) {
                return true;
            }
        }
        return false;
    }

    public function leftBreastCancer() {
        /* @var BRCAAntecedent $a */
        foreach ($this->antecedents as $a) {
            $lateral = null;
            if ($a->breastCancer($lateral) && ($lateral == "left")) {
                return true;
            }
        }
        return false;
    }

    public function breastCancer() {
        /* @var BRCAAntecedent $a */
        foreach ($this->antecedents as $a) {
            $lateral = null;
            if ($a->breastCancer($lateral)) {
                return true;
            }
        }
        return false;
    }

    public function colonCancer() {
        /* @var BRCAAntecedent $a */
        foreach ($this->antecedents as $a) {
            if ($a->colonCancer()) {
                return true;
            }
        }
        return false;
    }

    public function ovarianCancer() {
        /* @var BRCAAntecedent $a */
        foreach ($this->antecedents as $a) {
            if ($a->ovarianCancer()) {
                return true;
            }
        }
        return false;
    }

    public function pancreaticCancer() {
        /* @var BRCAAntecedent $a */
        foreach ($this->antecedents as $a) {
            if ($a->pancreaticCancer()) {
                return true;
            }
        }
        return false;
    }

    public function prostateCancer() {
        /* @var BRCAAntecedent $a */
        foreach ($this->antecedents as $a) {
            if ($a->prostateCancer()) {
                return true;
            }
        }
        return false;
    }

    public function bilateralBreast() {
        /* @var BRCAAntecedent $a */
        return ($this->rightBreastCancer() && $this->leftBreastCancer());
    }

    public function isPaternal() {
        /*
         * Relationship is maternal if:
         * - first relative is PARENT (male)
         * - first relative is not a parent (SIBLING or CHILD)
         */
        if ($this->_firstRelative == "PARENT(M)" || $this->_firstRelative != "PARENT(F)") {
            return true;
        }
        return false;
    }

    public function isMaternal() {
        /*
         * Relationship is maternal if:
         * - first relative is PARENT (female)
         * - first relative is not a parent (SIBLING or CHILD)
         */
        if ($this->_firstRelative == "PARENT(F)" || $this->_firstRelative != "PARENT(M)") {
            return true;
        }
        return false;
    }

    /**
     * Returns the minimun onset age for a cancer type
     *
     * @param mixed $cancerType (e.g. BRCACancerTable::BREAST_CANCER)
     * @return int
     */
    public function getOnset($cancerType) {
        $onset = 10000;
        /* @var BRCAAntecedent $a */
        foreach ($this->antecedents as $a) {
            if ($a->getCancerType() == $cancerType) {
                $onset = $a->onsetAge < $onset ? $a->onsetAge : $onset;
            }
        }
        return ($onset < 10000 ? $onset : null);
    }

    /**
     * Returns true if the relative code corresponds to any of the parents (father, mother)
     * It is possible to ask for a specific parent (mother, father) using the parameter $which.
     * The admitted values for $which are ("father", "mother")
     *
     * @param string $which
     * @return boolean
     */
    public function isParent($which = null) {
        if (!in_array($this->relativeCode, ["PARENT(M)", "PARENT(F)"])) {
            return false;
        }

        if (!$which)
            return true;
        if (($which == "father") && ($this->relativeCode == "PARENT(M)"))
            return true;
        if (($which == "mother") && ($this->relativeCode == "PARENT(F)"))
            return true;

        return false;
    }

    /**
     * Returns true if the relative code corresponds to any of the parents (father, mother)
     * It is possible to ask for a specific parent (brother, sister) using the parameter $which.
     * The admitted values for $which are ("brother", "sister")
     *
     * @param string $which
     * @return boolean
     */
    public function isSibling($which = null) {
        if (!in_array($this->relativeCode, ["SIBLING(M)", "SIBLING(F)"])) {
            return false;
        }

        if (!$which)
            return true;
        if (($which == "brother") && ($this->relativeCode == "SIBLING(M)"))
            return true;
        if (($which == "sister") && ($this->relativeCode == "SIBLING(F)"))
            return true;

        return false;
    }
}

