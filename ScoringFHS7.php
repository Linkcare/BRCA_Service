<?php

/**
 * ***************************************************
 */
class ScoringFHS7 {
    const Q_FDR_BREAST_OR_OVARIAN = '$FHS7_FDR_BREAST_OVARIAN';
    const Q_BILATERAL = '$FHS7_R_BILATERAL_BREAST';
    const Q_M_BREAST = '$FHS7_MAN_BREAST';
    const Q_W_BREAST_AND_OVARIAN = '$FHS7_WOMAN_BOTH_BREAST_OVARIAN';
    const Q_W_BREAST_UNDER_50 = '$FHS7_WOMAN_BREAST_50';
    const Q_2R_BREAST_OVARIAN = '$FHS7_2R_BREAST_OVARIAN';
    const Q_2R_BREAST_COLON = '$FHS7_2R_BREAST_COLON';
    const Q_SCORING_RESULT = '$FHS7_SCORING';
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
     * Scoring is positive if Score ≥1
     *
     * @return boolean
     */
    function isPositive() {
        $score = $this->score();

        if ($score >= 1)
            return true;
        return false;
    }

    /**
     * Score calculation.
     *
     * @return int,
     */
    function score() {
        $score = $this->any1stDegreeHaveBreastOrOvarian()["score"];
        $score += $this->anyRelativeHaveBilateralBreast()["score"];
        $score += $this->anyRelativeDegreeManHaveBreast()["score"];
        $score += $this->anyRelativeWomanHaveBreastAndOvarian()["score"];
        $score += $this->anyRelativeWomanHaveBreastCUnder50()["score"];
        $score += $this->twoRelativesHaveBreastOvarian()["score"];
        $score += $this->twoRelativesHaveBreastOrColon()["score"];

        return $score;
    }

    // 1.Did any of your First Degree Relatives have breast or ovarian cancer?
    public function any1stDegreeHaveBreastOrOvarian() {
        /* @var BRCARelative $r */
        foreach ($this->_relatives as $r) {
            if ($r->degree() != 1)
                continue;
            if ($r->breastCancer() || $r->ovarianCancer()) {
                return (["answer" => "Y", "score" => 1]);
            }
        }

        return ["answer" => "N", "score" => 0];
    }

    // 2.Did any of your relatives have bilateral breast cancer?
    public function anyRelativeHaveBilateralBreast() {
        /* @var BRCARelative $r */
        foreach ($this->_relatives as $r) {
            if ($r->bilateralBreast()) {
                return (["answer" => "Y", "score" => 1]);
            }
        }

        return ["answer" => "N", "score" => 0];
    }

    // 3.Did any man in your family have male breast cancer?
    public function anyRelativeDegreeManHaveBreast() {
        /* @var BRCARelative $r */
        foreach ($this->_relatives as $r) {
            if ($r->isMale() && $r->breastCancer()) {
                return (["answer" => "Y", "score" => 1]);
            }
        }

        return ["answer" => "N", "score" => 0];
    }

    // 4.Did any woman in your family have breast and ovarian cancer?
    public function anyRelativeWomanHaveBreastAndOvarian() {
        /* @var BRCARelative $r */
        foreach ($this->_relatives as $r) {
            if ($r->isFemale() && $r->breastCancer() && $r->ovarianCancer()) {
                return (["answer" => "Y", "score" => 1]);
            }
        }

        return ["answer" => "N", "score" => 0];
    }

    // Did any woman in your family (first, second or third degree) have breast cancer at age <50 y
    public function anyRelativeWomanHaveBreastCUnder50() {
        /* @var BRCARelative $a */
        foreach ($this->_relatives as $r) {
            $onset = $r->getOnset(BRCACancerTable::BREAST_CANCER);
            if ($r->isFemale() && $r->breastCancer() && ($onset < 50)) {
                return (["answer" => "Y", "score" => 1]);
            }
        }

        return ["answer" => "N", "score" => 0];
    }

    // 6. Do you have ≥2 relatives of the family with breast cancer and/or ovarian cancer?
    public function twoRelativesHaveBreastOvarian() {
        $total = 0;
        /* @var BRCARelative $a */
        foreach ($this->_relatives as $r) {
            if ($r->breastCancer() || $r->ovarianCancer()) {
                $total++;
            }
        }

        if ($total >= 2) {
            return (["answer" => "Y", "score" => 1]);
        }
        return ["answer" => "N", "score" => 0];
    }

    // Do you have ≥2 relatives (first, second or third degree) with breast cancer and/or colon cancer?
    public function twoRelativesHaveBreastOrColon() {
        $total = 0;
        /* @var BRCARelative $a */
        foreach ($this->_relatives as $r) {
            if ($r->breastCancer() || $r->colonCancer()) {
                $total++;
            }
        }

        if ($total >= 2) {
            return (["answer" => "Y", "score" => 1]);
        }
        return ["answer" => "N", "score" => 0];
    }

    /**
     *
     * @param SoapClient $client
     * @param string $sessionToken
     * @param int $fh7FormId
     * @return string: Empty if no error. Otherwise an error description
     */
    function updateScoringForm($client, $sessionToken, $fh7FormId) {
        $result = $client->form_get_summary($sessionToken, $fh7FormId);

        if (!$result || $result["ErrorMsg"]) {
            // ERROR
            return $result["ErrorMsg"];
        }

        list($xmlSetAnswers, $root) = createAnswersXML();

        /*
         * In array questions, only the fist question of a row is returned until it has any value. We first fill the first questions of PATERNAL &
         * MATERNAL ARRAYS
         * so that the rest of fields become visible. The rest of fields will be filled after completing the list of antecedents
         */
        $ans = $this->any1stDegreeHaveBreastOrOvarian()["score"];
        addNodeQuestion($xmlSetAnswers, $root, ScoringFHS7::Q_FDR_BREAST_OR_OVARIAN, ($ans > 0) ? 1 : 2);

        $ans = $this->anyRelativeHaveBilateralBreast()["score"];
        addNodeQuestion($xmlSetAnswers, $root, ScoringFHS7::Q_BILATERAL, ($ans > 0) ? 1 : 2);

        $ans = $this->anyRelativeDegreeManHaveBreast()["score"];
        addNodeQuestion($xmlSetAnswers, $root, ScoringFHS7::Q_M_BREAST, ($ans > 0) ? 1 : 2);

        $ans = $this->anyRelativeWomanHaveBreastAndOvarian()["score"];
        addNodeQuestion($xmlSetAnswers, $root, ScoringFHS7::Q_W_BREAST_AND_OVARIAN, ($ans > 0) ? 1 : 2);

        $ans = $this->anyRelativeWomanHaveBreastCUnder50()["score"];
        addNodeQuestion($xmlSetAnswers, $root, ScoringFHS7::Q_W_BREAST_UNDER_50, ($ans > 0) ? 1 : 2);

        $ans = $this->twoRelativesHaveBreastOvarian()["score"];
        addNodeQuestion($xmlSetAnswers, $root, ScoringFHS7::Q_2R_BREAST_OVARIAN, ($ans > 0) ? 1 : 2);

        $ans = $this->twoRelativesHaveBreastOrColon()["score"];
        addNodeQuestion($xmlSetAnswers, $root, ScoringFHS7::Q_2R_BREAST_COLON, ($ans > 0) ? 1 : 2);

        $finalResult = $this->isPositive();
        addNodeQuestion($xmlSetAnswers, $root, ScoringFHS7::Q_SCORING_RESULT, $finalResult ? "1" : "2");

        $xmlStr = $xmlSetAnswers->SaveXML();
        $client->form_set_all_answers($sessionToken, $fh7FormId, $xmlStr);

        return "";
    }

    /**
     * ***********************************************************************
     * ***************** HTML OUTPUT FUNCTIONS *******************************
     * ***********************************************************************
     */
    private function htmlOutputRow($title, $row) {
        $str = '<tr>';
        $str .= '<td>' . $title . '</td>';
        $str .= '<td>' . $row['answer'] . '</td>';
        $str .= '<td style="text-align:right">' . $row['score'] . '</td>';
        $str .= '</tr>';
        return $str;
    }

    public function htmlSummary() {
        $str = '<h1>FAMILY HISTORY SCREEN-7</h1>';
        $str .= '<table cellpadding="5" border="1">';
        $str .= $this->htmlOutputRow("any1stDegreeHaveBreastOrOvarian", $this->any1stDegreeHaveBreastOrOvarian());
        $str .= $this->htmlOutputRow("anyRelativeHaveBilateralBreast", $this->anyRelativeHaveBilateralBreast());
        $str .= $this->htmlOutputRow("anyRelativeDegreeManHaveBreast", $this->anyRelativeDegreeManHaveBreast());
        $str .= $this->htmlOutputRow("anyRelativeWomanHaveBreastAndOvarian", $this->anyRelativeWomanHaveBreastAndOvarian());
        $str .= $this->htmlOutputRow("anyRelativeWomanHaveBreastCUnder50", $this->anyRelativeWomanHaveBreastCUnder50());
        $str .= $this->htmlOutputRow("twoRelativesHaveBreastOvarian", $this->twoRelativesHaveBreastOvarian());
        $str .= $this->htmlOutputRow("twoRelativesHaveBreastOrColon", $this->twoRelativesHaveBreastOrColon());
        $str .= '</table><br/>';
        $str .= '<h3>Global score: ' . $this->score() . '<br/>';
        $str .= 'Final result: ' .
                ($this->isPositive() ? '<span style="color:#ff0000">Positive</span>' : '<span style="color:#00ff00">Negative</span>') . '</h3><br/>';
        return $str;
    }
}