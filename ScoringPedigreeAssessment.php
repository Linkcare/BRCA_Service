<?php

class ScoringPedigreeAssessment {
    const Q_RELATIVE = '$PAT_SCORING_SUMMARY1';
    const Q_NAME = '$PAT_SCORING_SUMMARY2';
    const Q_SIDE = '$PAT_SCORING_SUMMARY3';
    const Q_CANCER = '$PAT_SCORING_SUMMARY4';
    const Q_ONSET = '$PAT_SCORING_SUMMARY5';
    const Q_RANGE = '$PAT_SCORING_SUMMARY6';
    const Q_SCORE = '$PAT_SCORING_SUMMARY7';
    const Q_PATERNAL_ASHKENAZI = '$PAT_PATERNAL_ASHKENAZI';
    const Q_PATERNAL_SCORE = '$PAT_PATERNAL_TOTAL';
    const Q_MATERNAL_ASHKENAZI = '$PAT_MATERNAL_ASHKENAZI';
    const Q_MATERNAL_SCORE = '$PAT_MATERNAL_TOTAL';
    const Q_ASSESSMENT = '$PAT_SCORING_ASSESS';
    const Q_SCORING_RESULT = '$PAT_SCORING_RESULT';
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
     * - PAT total score of paternal side of family ≥8
     * - PAT total score of maternal side of family ≥8
     *
     * @return boolean
     */
    function isPositive(&$assessment) {
        $result = false;
        $criteria = [];
        $score = $this->score();

        if ($score["paternal"] >= 8) {
            $criteria[] = 1;
            $result = true;
        }
        if ($score["maternal"] >= 8) {
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
    private function score() {
        $score["paternal"] = $this->ashkenaziScore("paternal")["score"];
        $score["maternal"] = $this->ashkenaziScore("maternal")["score"];

        foreach ($this->_relatives as $r) {
            foreach ($r->antecedents as $a) {
                $total = 0;

                if ($s = $this->femaleBreastCancerOver50($r, $a)) {
                    $total += $s["score"];
                }

                if ($s = $this->femaleBreastCancerUnder50($r, $a)) {
                    $total += $s["score"];
                }

                if ($s = $this->ovarianCancer($r, $a)) {
                    $total += $s["score"];
                }

                if ($s = $this->maleBreastCancer($r, $a)) {
                    $total += $s["score"];
                }

                if ($r->isPaternal())
                    $score["paternal"] += $total;
                if ($r->isMaternal())
                    $score["maternal"] += $total;
            }
        }

        return $score;
    }

    /**
     * 1.Breast cancer at age ≥50 y
     * - Yes 3
     * - No 0
     *
     * @param BRCARelative $r
     * @param BRCAAntecedent $a
     * @return [string, int]
     */
    private function femaleBreastCancerOver50($r, $a) {
        /* @var BRCARelative $r */
        if (!$r->isFemale() || ($a->getCancerType() != BRCACancerTable::BREAST_CANCER))
            return null;

        $onset = $a->onsetAge;
        if ($onset >= 50) {
            $answer = ["answer" => "Yes", "score" => 3];
        } else {
            $answer = null;
        }

        return $answer;
    }

    /**
     * 2.Breast cancer at age <50 y
     * - Yes 4
     * - No 0
     *
     * @param BRCARelative $r
     * @param BRCAAntecedent $a
     * @return [string, int]
     */
    private function femaleBreastCancerUnder50($r, $a) {
        if (!$r->isFemale() || ($a->getCancerType() != BRCACancerTable::BREAST_CANCER))
            return null;

        $onset = $a->onsetAge;
        if ($onset < 50) {
            $answer = ["answer" => "Yes", "score" => 4];
        } else {
            $answer = null;
        }

        return $answer;
    }

    /**
     * 3.Ovarian cancer at any age
     * - Yes 5
     * - No 0
     *
     * @param BRCARelative $r
     * @param BRCAAntecedent $a
     * @return [string, int]
     */
    private function ovarianCancer($r, $a) {
        if (!$r->isFemale() || ($a->getCancerType() != BRCACancerTable::OVARIAN_CANCER))
            return null;

        return ["answer" => "Yes", "score" => 5];
    }

    /**
     * 4.Male breast cancer at any age
     * - Yes 8
     * - No 0
     *
     * @param BRCARelative $r
     * @param BRCAAntecedent $a
     * @return [string, int]
     */
    private function maleBreastCancer($r, $a) {
        if (!$r->isMale() || ($a->getCancerType() != BRCACancerTable::BREAST_CANCER))
            return null;

        return ["answer" => "Yes", "score" => 8];
    }

    /**
     * Score for Ashkenazi Hewish Heritage
     * - Yes => 4
     * - No => 0
     *
     * @param string $side
     * @return [string, int]
     */
    private function ashkenaziScore($side) {
        if ($this->_ashkenazi[$side])
            return ["answer" => "Yes", "score" => 4];

        return ["answer" => "No", "score" => 0];
    }

    /**
     *
     * @param SoapClient $client
     * @param string $sessionToken
     * @param int $pedigreeFormId
     * @return string: Empty if no error. Otherwise an error description
     */
    function updateScoringForm($client, $sessionToken, $pedigreeFormId) {
        $result = $client->form_get_summary($sessionToken, $pedigreeFormId);

        if (!$result || $result["ErrorMsg"]) {
            // ERROR
            return $result["ErrorMsg"];
        }

        $xml = simplexml_load_string($result["result"]);
        if (!$xml->data || !$xml->data->questions) {
            return "The Manchester Scoring Form ($pedigreeFormId) does not contain questions";
        }

        $score = $this->score();
        list($xmlSetAnswers, $root) = createAnswersXML();

        // Create array of relatives
        $arrayRef = getArrayRef($xml, ScoringPedigreeAssessment::Q_RELATIVE);
        if (!$arrayRef) {
            return "Not found array question for datacode " . ScoringPedigreeAssessment::Q_RELATIVE;
        }
        $arrayNode = createSubnode($xmlSetAnswers, $root, "array");
        createSubnode($xmlSetAnswers, $arrayNode, "ref", $arrayRef);

        /* @var BRCARelative $r */
        /* @var BRCAAntecedent $a */
        foreach ($this->_relatives as $r) {
            foreach ($r->antecedents as $a) {
                $rowNode = createSubnode($xmlSetAnswers, $arrayNode, "row");
                // In array questions, only the fist question of a row is returned until it has any value
                addNodeQuestion($xmlSetAnswers, $rowNode, ScoringPedigreeAssessment::Q_RELATIVE, $r->relativeCode);
                addNodeQuestion($xmlSetAnswers, $rowNode, ScoringPedigreeAssessment::Q_NAME, $r->relativeName);
                if ($r->isPaternal() && $r->isMaternal())
                    $side = "3";
                else
                    $side = $r->isPaternal() ? "1" : "2";
                addNodeQuestion($xmlSetAnswers, $rowNode, ScoringPedigreeAssessment::Q_SIDE, $side);
                addNodeQuestion($xmlSetAnswers, $rowNode, ScoringPedigreeAssessment::Q_CANCER, $a->cancerCode);
                addNodeQuestion($xmlSetAnswers, $rowNode, ScoringPedigreeAssessment::Q_ONSET, $a->onsetAge);

                $s = $this->femaleBreastCancerOver50($r, $a);
                if (!$s)
                    $s = $this->femaleBreastCancerUnder50($r, $a);
                if (!$s)
                    $s = $this->ovarianCancer($r, $a);
                if (!$s)
                    $s = $this->maleBreastCancer($r, $a);

                if ($s) {
                    addNodeQuestion($xmlSetAnswers, $rowNode, ScoringPedigreeAssessment::Q_RANGE, $s["answer"]);
                    addNodeQuestion($xmlSetAnswers, $rowNode, ScoringPedigreeAssessment::Q_SCORE, $s["score"]);
                }
            }
        }

        addNodeQuestion($xmlSetAnswers, $root, ScoringPedigreeAssessment::Q_PATERNAL_ASHKENAZI, $this->_ashkenazi["paternal"] ? 1 : 2);
        addNodeQuestion($xmlSetAnswers, $root, ScoringPedigreeAssessment::Q_MATERNAL_ASHKENAZI, $this->_ashkenazi["maternal"] ? 1 : 2);
        addNodeQuestion($xmlSetAnswers, $root, ScoringPedigreeAssessment::Q_PATERNAL_SCORE, $score["paternal"]);
        addNodeQuestion($xmlSetAnswers, $root, ScoringPedigreeAssessment::Q_MATERNAL_SCORE, $score["maternal"]);

        $assessment = null;
        $finalResult = $this->isPositive($assessment);
        addNodeQuestion($xmlSetAnswers, $root, ScoringPedigreeAssessment::Q_ASSESSMENT, $finalResult);
        addNodeQuestion($xmlSetAnswers, $root, ScoringPedigreeAssessment::Q_SCORING_RESULT, $finalResult ? "1" : "2");

        $xmlStr = $xmlSetAnswers->SaveXML();
        $client->form_set_all_answers($sessionToken, $pedigreeFormId, $xmlStr);

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
        $str = '<td >' . $results["answer"] . '</td>';
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
        $ix = 0;
        $rowSpan = 0;
        foreach ($r->antecedents as $a) {
            $ix++;
            $str .= "<tr>";
            if ($ix == 1) {
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
            }
            $str .= '<td>' . BRCACancerTable::getInstance()->getCancerDescription($a->cancerCode) . '</td>';
            $str .= '<td>' . $a->onsetAge . '</td>';
            if ($s = $this->femaleBreastCancerOver50($r, $a)) {
                $str .= $this->htmlOutputQuestion($s);
                $rowSpan++;
            }
            if ($s = $this->femaleBreastCancerUnder50($r, $a)) {
                $str .= $this->htmlOutputQuestion($s);
                $rowSpan++;
            }
            if ($s = $this->ovarianCancer($r, $a)) {
                $str .= $this->htmlOutputQuestion($s);
                $rowSpan++;
            }
            if ($s = $this->maleBreastCancer($r, $a)) {
                $str .= $this->htmlOutputQuestion($s);
                $rowSpan++;
            }
            $str .= "</tr>";
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
        $str = '<h1>PEDIGREE SCORING</h1>';
        $str .= '<table cellpadding="5" border="1">';
        $str .= '<tr>';
        $str .= '<th>Relative</th>';
        $str .= '<th>Name</th>';
        $str .= '<th>Side</th>';
        $str .= '<th>Cancer</th>';
        $str .= '<th>Onset</th>';
        $str .= '<th>Range</th>';
        $str .= '<th>Score</th>';
        $str .= '</tr>';

        foreach ($this->_relatives as $r) {
            $str .= $this->htmlOutputRow($r);
        }
        $str .= '</table><br/>';

        $score = $this->score();
        $str .= "Ashkenazi Jewish Heritage (paternal side): " . $this->ashkenaziScore("paternal")["answer"] . " => Score = " .
                $this->ashkenaziScore("paternal")["score"] . "<br/>";
        $str .= "Ashkenazi Jewish Heritage (maternal side): " . $this->ashkenaziScore("maternal")["answer"] . " => Score = " .
                $this->ashkenaziScore("maternal")["score"] . "<br/>";

        $score = $this->score();
        $str .= "<h3>PAT Paternal: " . $score["paternal"] . "<br/>";
        $str .= "PAT Maternal: " . $score["maternal"] . "<br/>";
        $assessment = null;
        $str .= 'Final result: ' .
                ($this->isPositive($assessment) ? '<span style="color:#ff0000">Positive</span>' : '<span style="color:#00ff00">Negative</span>');
        $str .= '</h3><br/>';

        return $str;
    }
}