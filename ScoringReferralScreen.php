<?php

class ScoringReferralScreen {
    const Q_RELATIVE = '$RST_SCORING_SUMMARY1';
    const Q_NAME = '$RSTT_SCORING_SUMMARY2';
    const Q_SIDE = '$RST_SCORING_SUMMARY3';
    const Q_CRITERIA = '$RST_SCORING_SUMMARY4';
    const Q_SCORE = '$RST_SCORING_SUMMARY5';
    const Q_PAT_OVER50 = '$RST_SCORING_SUMMARY6';
    const Q_MAT_OVER50 = '$RST_SCORING_SUMMARY7';
    const Q_MALE_BREAST = '$RST_SCORING_SUMMARY8';
    const Q_ASHKENAZI = '$RST_SCORING_SUMMARY9';
    const Q_TOTAL_SCORE = '$RST_SCORING_ASSESS';
    const Q_SCORING_RESULT = '$RST_SCORING_RESULT';

    // Criteria Id
    const OPT_BREAST_UNDER_50 = 1; // 1 - Breast cancer under 50
    const OPT_OVARIAN = 2; // 2 - Ovarian cancer at any age
    const OPT_NOT_AFFECTED = 3; // 3 - Not affected

    // Num cases Id
    const OPT_GE_2 = 1;
    const OPT_LT_2 = 2;

    // List of relatives included in the scoring
    const AFFECTED_RELATIVES = ["Mother" => "PARENT(F)", "Sister" => "SIBLING(F)", "Daughter" => "CHILD(F)",
            "Maternal grandmother" => "PARENT(F).PARENT(F)", "Maternal aunt" => "PARENT(F).SIBLING(F)",
            "Paternal grandmother" => "PARENT(M).PARENT(F)", "Paternal aunt" => "PARENT(M).SIBLING(F)"];

    // Private members
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
     * Scoring is positive if RST Score ≥2
     *
     * @return boolean
     */
    function isPositive() {
        if ($this->score() >= 2) {
            return true;
        }

        return false;
    }

    /**
     * Score calculation.
     * Returns an array of scores indexed by "paternal" and "maternal" side
     *
     * @return int,
     */
    public function score() {
        $score = 0;
        /* @var BRCARelative $r */
        foreach ($this->_relatives as $r) {
            if (in_array($r->relativeCode, ScoringReferralScreen::AFFECTED_RELATIVES)) {
                // Mother
                $score += $this->scoreRelative($r);
            }
        }

        $score += $this->femaleBreastOver50Global(true)["score"]; // Breast over 50 on the paternal side
        $score += $this->femaleBreastOver50Global(false)["score"]; // Breast over 50 on the maternal side
        $score += $this->maleBreast()["score"]; // Breast over 50 on the maternal side
        $score += $this->ashkenaziScore()["score"];

        return $score;
    }

    /**
     * Check if a relative has breast cancer under 50 or ovarian cancer at any age
     *
     * @param BRCARelative $r
     * @return int
     */
    private function scoreRelative($r) {
        $score = 0;
        $s = $this->breastUnder50($r);
        if ($s)
            $score += $s["score"];
        $s = $this->ovarian($r);
        if ($s)
            $score += $s["score"];
        return $score;
    }

    /**
     * Check Breast cancer under 50 y
     * - Affected 1
     * - Not affected 0
     *
     * @param BRCARelative $r
     * @return [string, int, int]
     */
    private function breastUnder50($r) {
        if (!$r->isFemale() || !$r->breastCancer())
            return null;

        $answer = null;
        $onset = $r->getOnset(BRCACancerTable::BREAST_CANCER);
        if ($onset <= 50) {
            $answer = ["answer" => "Breast cancer at age ≤50 y", "score" => 1, "option" => ScoringReferralScreen::OPT_BREAST_UNDER_50];
        }

        return $answer;
    }

    /**
     * Check Ovarian cancer at any age
     * - Affected 1
     * - Not affected 0
     *
     * @param BRCARelative $r
     * @return [string, int, int]
     */
    private function ovarian($r) {
        if (!$r->isFemale() || !$r->ovarianCancer())
            return null;

        return ["answer" => "Ovarian cancer at any age", "score" => 1, "option" => ScoringReferralScreen::OPT_OVARIAN];
    }

    /**
     * 9.Female breast cancer at age >50 y on the paternal side of the family (first and second degree relatives)
     * 10.Female breast cancer at age >50 y on the maternal side of the family (first and second degree relatives)
     * - If >= 2 Cases => 1
     * - Otherwise => 0
     *
     * @param bool $paternal
     * @return [string, int, int]
     */
    public function femaleBreastOver50Global($paternal) {
        $cases = 0;
        foreach ($this->_relatives as $r) {
            if (!$r->isFemale())
                continue;
            if ($paternal && $r->isMaternal())
                continue; // It is not paternal side
            if (!$paternal && $r->isPaternal())
                continue; // It is not maternal side

            if (!$r->breastCancer() || ($r->getOnset(BRCACancerTable::BREAST_CANCER) <= 50) || ($r->degree() > 2))
                continue;
            $cases++;
        }

        if ($cases >= 2) {
            $answer = ["answer" => "≥2 cases", "score" => 1, "option" => ScoringReferralScreen::OPT_GE_2];
        } else {
            $answer = ["answer" => "<2 cases", "score" => 0, "option" => ScoringReferralScreen::OPT_LT_2];
        }

        return $answer;
    }

    /**
     * 11.Male breast cancer at any age in any relative (first and second degree)
     * - Yes => 1
     * - No => 0
     *
     * @return [string, int, int]
     */
    public function maleBreast() {
        $cases = 0;
        foreach ($this->_relatives as $r) {
            if (!$r->breastCancer() || !$r->isMale() || ($r->degree() > 2))
                continue;
            $cases++;
        }

        if ($cases >= 1) {
            $answer = ["answer" => "Yes", "score" => 1, "option" => 1];
        } else {
            $answer = ["answer" => "No", "score" => 0, "option" => 2];
        }

        return $answer;
    }

    /**
     * Score for Ashkenazi Hewish Heritage
     * - Yes => 4
     * - No => 0
     *
     * @param string $side
     * @return [string, int, int]
     */
    private function ashkenaziScore($side = null) {
        if (!$side) {
            if ($this->_ashkenazi["paternal"] || $this->_ashkenazi["maternal"]) {
                return ["answer" => "Yes", "score" => 1, "option" => 1];
            }
            return ["answer" => "No", "score" => 0, "option" => 2];
        }

        if ($this->_ashkenazi[$side])
            return ["answer" => "Yes", "score" => 1, "option" => 1];

        return ["answer" => "No", "score" => 0, "option" => 2];
    }

    /**
     *
     * @param DOMDocument $xmlSetAnswers
     * @param DOMNode $arrayNode
     * @param BRCARelative $r
     * @param array $s
     */
    private function updateRow($xmlSetAnswers, $arrayNode, $r, $s) {
        $rowNode = createSubnode($xmlSetAnswers, $arrayNode, "row");
        // In array questions, only the fist question of a row is returned until it has any value
        addNodeQuestion($xmlSetAnswers, $rowNode, ScoringReferralScreen::Q_RELATIVE, $r->relativeCode);
        addNodeQuestion($xmlSetAnswers, $rowNode, ScoringReferralScreen::Q_NAME, $r->relativeName);
        if ($r->isPaternal() && $r->isMaternal())
            $side = "3";
        else
            $side = $r->isPaternal() ? "1" : "2";

        addNodeQuestion($xmlSetAnswers, $rowNode, ScoringReferralScreen::Q_SIDE, $side);
        addNodeQuestion($xmlSetAnswers, $rowNode, ScoringReferralScreen::Q_CRITERIA, $s["option"]);
        addNodeQuestion($xmlSetAnswers, $rowNode, ScoringReferralScreen::Q_SCORE, $s["score"]);
    }

    /**
     *
     * @param SoapClient $client
     * @param string $sessionToken
     * @param int $rstFormId
     * @return string: Empty if no error. Otherwise an error description
     */
    function updateScoringForm($client, $sessionToken, $rstFormId) {
        $result = $client->form_get_summary($sessionToken, $rstFormId);

        if (!$result || $result["ErrorMsg"]) {
            // ERROR
            return $result["ErrorMsg"];
        }

        $xml = simplexml_load_string($result["result"]);
        if (!$xml->data || !$xml->data->questions) {
            return "The RST Scoring Form ($rstFormId) does not contain questions";
        }

        $score = $this->score();

        list($xmlSetAnswers, $root) = createAnswersXML();

        // Create array of relatives
        $arrayRef = getArrayRef($xml, ScoringReferralScreen::Q_RELATIVE);
        if (!$arrayRef) {
            return "Not found array question for datacode " . ScoringReferralScreen::Q_RELATIVE;
        }
        $arrayNode = createSubnode($xmlSetAnswers, $root, "array");
        createSubnode($xmlSetAnswers, $arrayNode, "ref", $arrayRef);

        /* @var BRCARelative $r */
        foreach ($this->_relatives as $r) {
            $affected = false;
            if (in_array($r->relativeCode, ScoringReferralScreen::AFFECTED_RELATIVES)) {
                $s = $this->breastUnder50($r);
                if ($s) {
                    $this->updateRow($xmlSetAnswers, $arrayNode, $r, $s);
                    $affected = true;
                }
                $s = $this->ovarian($r);
                if ($s) {
                    $this->updateRow($xmlSetAnswers, $arrayNode, $r, $s);
                    $affected = true;
                }
            }

            if (!$affected) {
                // The relative has no antecedents that contribute to the scoring
                $s = ["answer" => "", "score" => 0, "option" => ScoringReferralScreen::OPT_NOT_AFFECTED];
                $this->updateRow($xmlSetAnswers, $arrayNode, $r, $s);
            }
        }

        $s = $this->femaleBreastOver50Global(true);
        addNodeQuestion($xmlSetAnswers, $root, ScoringReferralScreen::Q_PAT_OVER50, $s["option"]);
        $s = $this->femaleBreastOver50Global(false);
        addNodeQuestion($xmlSetAnswers, $root, ScoringReferralScreen::Q_MAT_OVER50, $s["option"]);
        $s = $this->maleBreast();
        addNodeQuestion($xmlSetAnswers, $root, ScoringReferralScreen::Q_MALE_BREAST, $s["option"]);
        $s = $this->ashkenaziScore();
        addNodeQuestion($xmlSetAnswers, $root, ScoringReferralScreen::Q_ASHKENAZI, $s["option"]);

        addNodeQuestion($xmlSetAnswers, $root, ScoringReferralScreen::Q_TOTAL_SCORE, $score);
        $finalResult = $this->isPositive();
        addNodeQuestion($xmlSetAnswers, $root, ScoringReferralScreen::Q_SCORING_RESULT, $finalResult ? "1" : "2");

        $xmlStr = $xmlSetAnswers->SaveXML();
        $client->form_set_all_answers($sessionToken, $rstFormId, $xmlStr);

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
    private function htmlOutputQuestion($results) {
        $str = '<td>' . $results["answer"] . '</td>';
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
        if (in_array($r->relativeCode, ScoringReferralScreen::AFFECTED_RELATIVES)) {
            $s = $this->breastUnder50($r);
            if ($s) {
                $str .= $rowStart . $this->htmlOutputQuestion($s) . "</tr>";
                $rowStart = "<tr>";
                $rowSpan++;
            }
            $s = $this->ovarian($r);
            if ($s) {
                $str .= $rowStart . $this->htmlOutputQuestion($s) . "</tr>";
                $rowStart = "<tr>";
                $rowSpan++;
            }
        }

        if ($rowSpan == 0) {
            // The relative has no antecedents that contribute to the scoring
            $s = ["answer" => "Not affected", "score" => 0];
            $str .= $rowStart . $this->htmlOutputQuestion($s) . "</tr>";
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
        $str = '<h1>REFERRAL SCREENING TOOL</h1>';
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

        $s = $this->femaleBreastOver50Global(true);
        $str .= "Breast cancer at age >50 y on the paternal side of the family (first and second degree relatives) " . $s["answer"] . " => Score = " .
                $s["score"] . "<br/>";
        $s = $this->femaleBreastOver50Global(false);
        $str .= "Breast cancer at age >50 y on the maternal side of the family (first and second degree relatives) " . $s["answer"] . " => Score = " .
                $s["score"] . "<br/>";
        $s = $this->maleBreast();
        $str .= "Male breast cancer at any age in any relative (first and second degree): " . $s["answer"] . " => Score = " . $s["score"] . "<br/>";
        $str .= "Ashkenazi Jewish Heritage (paternal side): " . $this->ashkenaziScore("paternal")["answer"] . " => Score = " .
                $this->ashkenaziScore("paternal")["score"] . "<br/>";
        $str .= "Ashkenazi Jewish Heritage (maternal side): " . $this->ashkenaziScore("maternal")["answer"] . " => Score = " .
                $this->ashkenaziScore("maternal")["score"] . "<br/>";

        $score = $this->score();
        $str .= "<h3>RST Score: " . $score . "<br/>";
        $str .= 'Final result: ' .
                ($this->isPositive() ? '<span style="color:#ff0000">Positive</span>' : '<span style="color:#00ff00">Negative</span>');
        $str .= '</h3><br/>';

        return $str;
    }
}