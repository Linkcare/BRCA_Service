<?php

class ScoringOntario {
    const Q_RELATIVE = '$ONT_SCORING_SUMMARY1';
    const Q_NAME = '$ONT_SCORING_SUMMARY2';
    const Q_SIDE = '$ONT_SCORING_SUMMARY3';
    const Q_CRITERIA = '$ONT_SCORING_SUMMARY4';
    const Q_SCORE = '$ONT_SCORING_SUMMARY5';
    const Q_PATERNAL = '$ONT_SCORING_PATERNAL';
    const Q_MATERNAL = '$ONT_SCORING_MATERNAL';
    const Q_ASSESSMENT = '$ONT_SCORING_ASSESS';
    const Q_SCORING_RESULT = '$ONT_SCORING_RESULT';

    // Criteria Id
    const OPT_BREAST_AND_OVARIAN = 1; // 1 - Breast and ovarian cancer
    const OPT_FEMALE_BREAST = 2; // 2 - Female breast cancer (and not ovarian)
    const OPT_MALE_BREAST = 3; // 3 - Male breast cancer
    const OPT_BREAST_ONSET_20_29 = 4; // 4 - Breast cancer onset at 20-29 y
    const OPT_BREAST_ONSET_30_39 = 5; // 5 - Breast cancer onset at 30-39 y
    const OPT_BREAST_ONSET_40_50 = 6; // 6 - Breast cancer onset at 40-50 y
    const OPT_BREAST_ONSET_NOT_RELEVANT = 7; // 7 - Breast cancer onset not rellevant
    const OPT_BILATERAL_BREAST = 8; // 8 - Bilateral breast cancer
    const OPT_OVARIAN = 9; // 9 - Ovarian cancer (and not breast cancer)
    const OPT_OVARIAN_40 = 10; // 10 - Ovarian cancer onset <40
    const OPT_OVARIAN_40_60 = 11; // 11 - Ovarian cancer onset at 40-60 y
    const OPT_OVARIAN_60 = 12; // 12 - Ovarian cancer onset >60 y
    const OPT_OVARIAN_NOT_RELEVANT = 13; // 13 - Ovarian cancert onset not rellevant
    const OPT_PROSTATE_50 = 14; // 14 - Prostate cancer onset <50 y
    const OPT_PROSTATE_NOT_RELEVANT = 15; // 15 - Prostate cancer onset not rellevant
    const OPT_COLON_50 = 16; // 16 - Colon cancer onset <50 y
    const OPT_COLON_NOT_RELEVANT = 17; // 17 - Colon cancer onset not rellevant
    const OPT_NOT_AFFECTED = 18; // 18 - Not affected
    private $_relatives = []; /* @var BRCARelative[] $relatives */
    private $_ashkenazi;

    /* @var bool[] $_ashkenazi */

    /**
     *
     * @param BRCARelative[] $relatives
     * @param bool[] $ashkenazi
     */
    function __construct($relatives, $ashkenazi) {
        $this->_relatives = $relatives;
        $this->_ashkenazi = $ashkenazi;
    }

    /**
     * Scoring is positive if
     * - Total score of paternal side of family ≥10
     * - Total score of maternal side of family ≥10
     *
     * @return boolean
     */
    function isPositive(&$assessment) {
        $result = false;
        $score = $this->score();
        $criteria = [];

        if ($score["paternal"] >= 10) {
            $criteria[] = 1;
            $result = true;
        }
        if ($score["maternal"] >= 10) {
            $criteria[] = 2;
            $result = true;
        }

        if (!$result)
            $criteria[] = 3; // Result is negative ==> no assessment
        $assessment = implode("|", $criteria);
        return $result;
    }

    /**
     * Score calculation.
     * Returns an array of scores indexed by "paternal" and "maternal" side
     *
     * @return int[],
     */
    public function score() {
        $score["paternal"] = 0;
        $score["maternal"] = 0;

        foreach ($this->_relatives as $r) {
            $s = null;
            $total = 0;
            if ($s = $this->breastAndOvarianCancer($r)) {
                $total += $s["score"];
            }
            if ($s = $this->femaleBreastCancer($r)) {
                $total += $s["score"];
            }
            if ($s = $this->maleBreastCancer($r)) {
                $total += $s["score"];
            }
            if ($s = $this->ageBreastCancerOnset($r)) {
                $total += $s["score"];
            }
            if ($s = $this->bilateralBreastCancer($r)) {
                $total += $s["score"];
            }
            if ($s = $this->ovarianCancer($r)) {
                $total += $s["score"];
            }
            if ($s = $this->ageOvarianCancerOnset($r)) {
                $total += $s["score"];
            }
            if ($s = $this->ageProstateCancerOnset($r)) {
                $total += $s["score"];
            }
            if ($s = $this->ageColonCancerOnset($r)) {
                $total += $s["score"];
            }

            if ($r->isPaternal())
                $score["paternal"] += $total;
            if ($r->isMaternal())
                $score["maternal"] += $total;
        }

        return $score;
    }

    /**
     * 1.Breast and ovarian cancer
     * - Mother 10
     * - Sibling 7
     * - Second-/third-degree relative 5
     * - Not affected 0
     *
     * @param BRCARelative $r
     * @return [string, int]
     */
    public function breastAndOvarianCancer($r) {
        if (!$r->ovarianCancer() || !$r->breastCancer())
            return null;

        if ($r->isParent("mother")) {
            $answer = ["answer" => "Mother", "score" => 10, "option" => ScoringOntario::OPT_BREAST_AND_OVARIAN];
        } elseif ($r->isSibling()) {
            $answer = ["answer" => "Sibling", "score" => 7, "option" => ScoringOntario::OPT_BREAST_AND_OVARIAN];
        } elseif ($r->degree() >= 2) {
            $answer = ["answer" => "2nd/3d degree relative", "score" => 5, "option" => ScoringOntario::OPT_BREAST_AND_OVARIAN];
        } else {
            $answer = ["answer" => "Not affected", "score" => 0, "option" => ScoringOntario::OPT_NOT_AFFECTED];
        }

        return $answer;
    }

    /**
     * 2.Female breast cancer (and not ovarian)
     * - Mother 4
     * - Sibling 3
     * - 2nd/3d degree relative 2
     * - Not affected 0
     *
     * @param BRCARelative $r
     * @return [string, int]
     */
    public function femaleBreastCancer($r) {
        /* @var BRCARelative $r */
        if (!$r->isFemale() || !$r->breastCancer() || $r->ovarianCancer())
            return null;

        if ($r->isParent("mother")) {
            $answer = ["answer" => "Mother", "score" => 4, "option" => ScoringOntario::OPT_FEMALE_BREAST];
        } elseif ($r->isSibling()) {
            $answer = ["answer" => "Sibling", "score" => 3, "option" => ScoringOntario::OPT_FEMALE_BREAST];
        } elseif ($r->degree() >= 2) {
            $answer = ["answer" => "2nd/3d degree relative", "score" => 2, "option" => ScoringOntario::OPT_FEMALE_BREAST];
        } else {
            $answer = ["answer" => "Not affected", "score" => 0, "option" => ScoringOntario::OPT_NOT_AFFECTED];
        }

        return $answer;
    }

    /**
     * 3.Male breast cancer
     * - Father 6
     * - Sibling 5
     * - 2nd/3d degree relative 4
     * - Not affected 0
     *
     * @param BRCARelative $r
     * @return [string, int]
     */
    public function maleBreastCancer($r) {
        /* @var BRCARelative $r */
        if (!$r->isMale() || !$r->breastCancer())
            return null;

        if ($r->isParent("father")) {
            $answer = ["answer" => "Father", "score" => 6, "option" => ScoringOntario::OPT_MALE_BREAST];
        } elseif ($r->isSibling()) {
            $answer = ["answer" => "Sibling", "score" => 5, "option" => ScoringOntario::OPT_MALE_BREAST];
        } elseif ($r->degree() >= 2) {
            $answer = ["answer" => "2nd/3d degree relative", "score" => 4, "option" => ScoringOntario::OPT_MALE_BREAST];
        } else {
            $answer = ["answer" => "Not affected", "score" => 0, "option" => ScoringOntario::OPT_NOT_AFFECTED];
        }

        return $answer;
    }

    /**
     * 4.Age at breast cancer onset
     * - Onset at age 20–29 y 6
     * - Onset at age 30–39 y 4
     * - Onset at age 40–59 y 2
     * - Other age/ not affected 0
     *
     * @param BRCARelative $r
     * @return [string, int]
     */
    public function ageBreastCancerOnset($r) {
        /* @var BRCARelative $r */
        if (!$r->breastCancer())
            return null;

        $onset = $r->getOnset(BRCACancerTable::BREAST_CANCER);
        if ($onset >= 20 && $onset <= 29) {
            $answer = ["answer" => "20–29", "score" => 6, "option" => ScoringOntario::OPT_BREAST_ONSET_20_29];
        } elseif ($onset >= 30 && $onset <= 39) {
            $answer = ["answer" => "30-39", "score" => 4, "option" => ScoringOntario::OPT_BREAST_ONSET_30_39];
        } elseif ($onset >= 40 && $onset <= 49) {
            $answer = ["answer" => "40-49", "score" => 2, "option" => ScoringOntario::OPT_BREAST_ONSET_40_50];
        } else {
            $answer = ["answer" => "Other age", "score" => 0, "option" => ScoringOntario::OPT_BREAST_ONSET_NOT_RELEVANT];
        }

        return $answer;
    }

    /**
     * 5.
     * Breast cancer is bilateral?
     * - Yes 3
     * - No 0
     *
     * @param BRCARelative $r
     * @return [string, int]
     */
    public function bilateralBreastCancer($r) {
        /* @var BRCARelative $r */
        if ($r->bilateralBreast()) {
            return (["answer" => "Y", "score" => 3, "option" => ScoringOntario::OPT_BILATERAL_BREAST]);
        }

        return null;
    }

    /**
     * 6.Ovarian cancer (and not breast cancer)
     * - Mother 7
     * - Sibling 4
     * - 2nd/3d degree relative 3
     * - Not affected 0
     *
     * @param BRCARelative $r
     * @return [string, int]
     */
    public function ovarianCancer($r) {
        /* @var BRCARelative $r */
        if (!$r->ovarianCancer() || $r->breastCancer())
            return null;

        if ($r->isParent("mother")) {
            $answer = ["answer" => "Mother", "score" => 7, "option" => ScoringOntario::OPT_OVARIAN];
        } elseif ($r->isSibling()) {
            $answer = ["answer" => "Sibling", "score" => 4, "option" => ScoringOntario::OPT_OVARIAN];
        } elseif ($r->degree() >= 2) {
            $answer = ["answer" => "2nd/3d degree relative", "score" => 3, "option" => ScoringOntario::OPT_OVARIAN];
        } else {
            $answer = ["answer" => "Not affected", "score" => 0, "option" => ScoringOntario::OPT_NOT_AFFECTED];
        }

        return $answer;
    }

    /**
     * 7.Age at ovarian cancer onset
     * - Onset at age <40 y 6
     * - Onset at age 40–60 y 4
     * - Onset at age >60 y 2
     * - Other age/ not affected 0
     *
     * @param BRCARelative $r
     * @return [string, int]
     */
    public function ageOvarianCancerOnset($r) {
        /* @var BRCARelative $r */
        if (!$r->ovarianCancer())
            return null;

        $onset = $r->getOnset(BRCACancerTable::OVARIAN_CANCER);
        if ($onset < 40) {
            $answer = ["answer" => "<40", "score" => 6, "option" => ScoringOntario::OPT_OVARIAN_40];
        } elseif ($onset >= 40 && $onset <= 60) {
            $answer = ["answer" => "40-60", "score" => 4, "option" => ScoringOntario::OPT_OVARIAN_40_60];
        } elseif ($onset > 60) {
            $answer = ["answer" => ">60", "score" => 2, "option" => ScoringOntario::OPT_OVARIAN_60];
        } else {
            $answer = ["answer" => "Not affected", "score" => 0, "option" => ScoringOntario::OPT_OVARIAN_NOT_RELEVANT];
        }

        return $answer;
    }

    /**
     * 8.Age at prostate cancer onset
     * - Onset at age <50 y 1
     * - Other age/ not affected 0
     *
     * @param BRCARelative $r
     * @return [string, int]
     */
    public function ageProstateCancerOnset($r) {
        /* @var BRCARelative $r */
        if (!$r->prostateCancer())
            return null;

        $onset = $r->getOnset(BRCACancerTable::PROSTATE_CANCER);
        if ($onset < 50) {
            $answer = ["answer" => "<50", "score" => 1, "option" => ScoringOntario::OPT_PROSTATE_50];
        } else {
            $answer = ["answer" => "Other age", "score" => 0, "option" => ScoringOntario::OPT_PROSTATE_NOT_RELEVANT];
        }

        return $answer;
    }

    /**
     * 9.Age at colon cancer onset
     * - Onset at age <50 y 1
     * - Other age/ not affected 0
     *
     * @param BRCARelative $r
     * @return [string, int]
     */
    public function ageColonCancerOnset($r) {
        /* @var BRCARelative $r */
        if (!$r->colonCancer())
            return null;

        $onset = $r->getOnset(BRCACancerTable::COLON_CANCER);
        if ($onset < 50) {
            $answer = ["answer" => "<50", "score" => 1, "option" => ScoringOntario::OPT_COLON_50];
        } else {
            $answer = ["answer" => "Other age", "score" => 0, "option" => ScoringOntario::OPT_COLON_NOT_RELEVANT];
        }

        return $answer;
    }

    /**
     *
     * @param DOMDocument $xmlSetAnswers
     * @param DOMNode $arrayNode
     * @param BRCARelative $r
     * @param array $s
     * @param SimpleXMLElement $xml
     */
    private function updateRow($xmlSetAnswers, $arrayNode, $r, $s) {
        $rowNode = createSubnode($xmlSetAnswers, $arrayNode, "row");

        // In array questions, only the fist question of a row is returned until it has any value
        addNodeQuestion($xmlSetAnswers, $rowNode, ScoringOntario::Q_RELATIVE, $r->relativeCode);
        addNodeQuestion($xmlSetAnswers, $rowNode, ScoringOntario::Q_NAME, $r->relativeName);
        if ($r->isPaternal() && $r->isMaternal())
            $side = "3";
        else
            $side = $r->isPaternal() ? "1" : "2";
        addNodeQuestion($xmlSetAnswers, $rowNode, ScoringOntario::Q_SIDE, $side);
        addNodeQuestion($xmlSetAnswers, $rowNode, ScoringOntario::Q_CRITERIA, $s["option"]);
        addNodeQuestion($xmlSetAnswers, $rowNode, ScoringOntario::Q_SCORE, $s["score"]);
    }

    /**
     *
     * @param SoapClient $client
     * @param string $sessionToken
     * @param int $ontarioFormId
     * @return string: Empty if no error. Otherwise an error description
     */
    function updateScoringForm($client, $sessionToken, $ontarioFormId) {
        $result = $client->form_get_summary($sessionToken, $ontarioFormId);

        if (!$result || $result["ErrorMsg"]) {
            // ERROR
            return $result["ErrorMsg"];
        }

        $xml = simplexml_load_string($result["result"]);
        if (!$xml->data || !$xml->data->questions) {
            return "The Ontario Scoring Form ($ontarioFormId) does not contain questions";
        }

        $score = $this->score();

        list($xmlSetAnswers, $root) = createAnswersXML();

        // Create array of relatives
        $arrayRef = getArrayRef($xml, ScoringOntario::Q_RELATIVE);
        if (!$arrayRef) {
            return "Not found array question for datacode " . ScoringOntario::Q_RELATIVE;
        }
        $arrayNode = createSubnode($xmlSetAnswers, $root, "array");
        createSubnode($xmlSetAnswers, $arrayNode, "ref", $arrayRef);

        /* @var BRCARelative $r */
        foreach ($this->_relatives as $r) {
            $affected = false;
            if ($s = $this->breastAndOvarianCancer($r)) {
                $this->updateRow($xmlSetAnswers, $arrayNode, $r, $s);
                $affected = true;
            }
            if ($s = $this->femaleBreastCancer($r)) {
                $this->updateRow($xmlSetAnswers, $arrayNode, $r, $s);
                $affected = true;
            }
            if ($s = $this->maleBreastCancer($r)) {
                $this->updateRow($xmlSetAnswers, $arrayNode, $r, $s);
                $affected = true;
            }
            if ($s = $this->ageBreastCancerOnset($r)) {
                $this->updateRow($xmlSetAnswers, $arrayNode, $r, $s);
                $affected = true;
            }
            if ($s = $this->bilateralBreastCancer($r)) {
                $this->updateRow($xmlSetAnswers, $arrayNode, $r, $s);
                $affected = true;
            }
            if ($s = $this->ovarianCancer($r)) {
                $this->updateRow($xmlSetAnswers, $arrayNode, $r, $s);
                $affected = true;
            }
            if ($s = $this->ageOvarianCancerOnset($r)) {
                $this->updateRow($xmlSetAnswers, $arrayNode, $r, $s);
                $affected = true;
            }
            if ($s = $this->ageProstateCancerOnset($r)) {
                $this->updateRow($xmlSetAnswers, $arrayNode, $r, $s);
                $affected = true;
            }
            if ($s = $this->ageColonCancerOnset($r)) {
                $this->updateRow($xmlSetAnswers, $arrayNode, $r, $s);
                $affected = true;
            }
            if (!$affected) {
                // The relative has no antecedents that contribute to the scoring
                $s = ["answer" => "", "score" => 0, "option" => ScoringOntario::OPT_NOT_AFFECTED];
                $this->updateRow($xmlSetAnswers, $arrayNode, $r, $s);
            }
        }

        addNodeQuestion($xmlSetAnswers, $root, ScoringOntario::Q_PATERNAL, $score["paternal"]);
        addNodeQuestion($xmlSetAnswers, $root, ScoringOntario::Q_MATERNAL, $score["maternal"]);

        $assessment = null;
        $finalResult = $this->isPositive($assessment);
        addNodeQuestion($xmlSetAnswers, $root, ScoringOntario::Q_ASSESSMENT, $assessment);
        addNodeQuestion($xmlSetAnswers, $root, ScoringOntario::Q_SCORING_RESULT, $finalResult ? "1" : "2");

        $xmlStr = $xmlSetAnswers->SaveXML();
        $client->form_set_all_answers($sessionToken, $ontarioFormId, $xmlStr);

        return "";
    }

    /**
     * ***********************************************************************
     * ***************** HTML OUTPUT FUNCTIONS *******************************
     * ***********************************************************************
     */

    /**
     * Outputs the three scores (brca1, brca2 and combined) as cells of a table
     *
     * @param int[] $results
     * @param int $rowSpan
     * @return string
     */
    private function htmlOutputQuestion($criteria, $results) {
        $str = '<td>' . $criteria . '</td>';
        $str .= '<td style="text-align:right">' . $results["score"] . '</td>';
        return $str;
    }

    /**
     * Outputs the information of a relative as a table row
     *
     * @param BRCARelative $r
     * @return string
     */
    private function htmlOutputRow($r) {
        /* @var BRCARelative $r */
        /* @var BRCAAntecedent $a */
        $str = "";
        $rowSpan = 0;

        $str .= '<td rowspan="rowspanplaceholder">' . $r->relativeCode . '</td>';
        $str .= '<td rowspan="rowspanplaceholder">' . $r->relativeName . '</td>';
        if ($r->isPaternal() && $r->isMaternal()) {
            $side = "both";
        } elseif ($r->isPaternal()) {
            $side = "paternal";
        } else {
            $side = "maternal";
        }
        $str .= '<td rowspan="rowspanplaceholder">' . $side . '</td>';
        $rowStart = "";
        if ($s = $this->breastAndOvarianCancer($r)) {
            $str .= $rowStart . $this->htmlOutputQuestion("Breast and ovarian cancer", $s) . "</tr>";
            $rowStart = "<tr>";
            $rowSpan++;
        }
        if ($s = $this->femaleBreastCancer($r)) {
            $str .= $rowStart . $this->htmlOutputQuestion("Female breast cancer (and not ovarian)", $s) . "</tr>";
            ;
            $rowSpan++;
        }
        if ($s = $this->maleBreastCancer($r)) {
            $str .= $rowStart . $this->htmlOutputQuestion("Male breast cancer", $s) . "</tr>";
            $rowSpan++;
        }
        if ($s = $this->ageBreastCancerOnset($r)) {
            $str .= $rowStart . $this->htmlOutputQuestion("Age at breast cancer onset " . $s["answer"], $s) . "</tr>";
            $rowSpan++;
        }
        if ($s = $this->bilateralBreastCancer($r)) {
            $str .= $rowStart . $this->htmlOutputQuestion("Breast cancer is bilateral", $s) . "</tr>";
            $rowSpan++;
        }
        if ($s = $this->ovarianCancer($r)) {
            $str .= $rowStart . $this->htmlOutputQuestion("Ovarian cancer (and not breast cancer)", $s) . "</tr>";
            $rowSpan++;
        }
        if ($s = $this->ageOvarianCancerOnset($r)) {
            $str .= $rowStart . $this->htmlOutputQuestion("Age at ovarian cancer onset " . $s["answer"], $s) . "</tr>";
            $rowSpan++;
        }
        if ($s = $this->ageProstateCancerOnset($r)) {
            $str .= $rowStart . $this->htmlOutputQuestion("Age at prostate cancer onset " . $s["answer"], $s) . "</tr>";
            $rowSpan++;
        }
        if ($s = $this->ageColonCancerOnset($r)) {
            $str .= $rowStart . $this->htmlOutputQuestion("Age at colon cancer onset " . $s["answer"], $s) . "</tr>";
            $rowSpan++;
        }
        if ($rowSpan == 0) {
            // The relative has no antecedents that contribute to the scoring
            $s = ["answer" => "", "score" => 0];
            $str .= $rowStart . $this->htmlOutputQuestion("Age at colon cancer onset " . $s["answer"], $s) . "</tr>";
            $rowSpan++;
        }

        $str = str_replace("rowspanplaceholder", $rowSpan, $str);
        return $str;
    }

    /**
     * Returns a summary of the scoring process in HTML format
     *
     * @return string
     */
    public function htmlSummary() {
        $str = '<h1>ONTARIO FAMILY HISTORY ASSESSMENT TOOL</h1>';
        $str .= '<table cellpadding="5" border="1">';
        $str .= '<tr>';
        $str .= '<th>Relative</th>';
        $str .= '<th>Name</th>';
        $str .= '<th>Side</th>';
        $str .= '<th>Criteria</th>';
        $str .= '<th>Score</th>';
        $str .= '</tr>';

        foreach ($this->_relatives as $r) {
            $str .= $this->htmlOutputRow($r);
        }
        $str .= '</table><br/>';

        $score = $this->score();
        $str .= "<h3>FHAT Paternal: " . $score["paternal"] . "<br/>";
        $str .= "FHAT Maternal: " . $score["maternal"] . "<br/>";
        $assessment = null;
        $str .= 'Final result: ' .
                ($this->isPositive($assessment) ? '<span style="color:#ff0000">Positive</span>' : '<span style="color:#00ff00">Negative</span>');
        $str .= '</h3><br/>';

        return $str;
    }
}