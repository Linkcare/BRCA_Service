<?php

class ScoringManchester {
    const Q_RELATIVE = '$MAN_SCORING_SUMMARY1';
    const Q_NAME = '$MAN_SCORING_SUMMARY2';
    const Q_SIDE = '$MAN_SCORING_SUMMARY3';
    const Q_CANCER = '$MAN_SCORING_SUMMARY4';
    const Q_ONSET = '$MAN_SCORING_SUMMARY5';
    const Q_RANGE = '$MAN_SCORING_SUMMARY6';
    const Q_BRCA1 = '$MAN_SCORING_SUMMARY7';
    const Q_BRCA2 = '$MAN_SCORING_SUMMARY8';
    const Q_PATERNAL_BRCA1 = '$MAN_SCORING_PAT_1';
    const Q_PATERNAL_BRCA2 = '$MAN_SCORING_PAT_2';
    const Q_PATERNAL_COMBINED = '$MAN_SCORING_PAT_3';
    const Q_MATERNAL_BRCA1 = '$MAN_SCORING_MAT_1';
    const Q_MATERNAL_BRCA2 = '$MAN_SCORING_MAT_2';
    const Q_MATERNAL_COMBINED = '$MAN_SCORING_MAT_3';
    const Q_ASSESSMENT = '$MAN_SCORING_ASSESS';
    const Q_SCORING_RESULT = '$MAN_SCORING_RESULT';
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
     * - BRCA1 total score of paternal side of family ≥10
     * - BRCA2 total score of paternal side of family ≥10
     * - BRCA1 total score of maternal side of family ≥10
     * - BRCA2 total score of maternal side of family ≥10
     * - Combined total score of paternal side of family ≥15
     * - Combined total score of maternal side of family ≥15
     *
     * @param string $assessment
     * @return boolean
     */
    function isPositive(&$assessment) {
        $result = false;
        $criteria = [];
        $score = $this->score();

        if ($score["paternal"]["brca1"] >= 10) {
            $result = true;
            $criteria[] = 1;
        }
        if ($score["paternal"]["brca2"] >= 10) {
            $result = true;
            $criteria[] = 2;
        }

        if ($score["maternal"]["brca1"] >= 10) {
            $result = true;
            $criteria[] = 3;
        }
        if ($score["maternal"]["brca2"] >= 10) {
            $result = true;
            $criteria[] = 4;
        }

        if ($score["paternal"]["combined"] >= 15) {
            $result = true;
            $criteria[] = 5;
        }
        if ($score["maternal"]["combined"] >= 15) {
            $result = true;
            $criteria[] = 6;
        }

        if (!$result)
            $criteria[] = 7; // Result is negative ==> no assessment
        $assessment = implode("|", $criteria);
        return $result;
    }

    /**
     * Score calculation.
     * Returns an array of scores indexed by "paternal" and "maternal" side
     * Each score is an array with three different calculations: "brca1","brca2" and "combined"
     *
     * @return int[][],
     */
    public function score() {
        $score["paternal"] = ["brca1" => 0, "brca2" => 0, "combined" => 0];
        $score["maternal"] = ["brca1" => 0, "brca2" => 0, "combined" => 0];

        foreach ($this->_relatives as $r) {
            foreach ($r->antecedents as $a) {
                $brca1 = 0;
                $brca2 = 0;

                if ($s = $this->AgeAtOnsetFemaleBreast($r, $a)) {
                    $brca1 += $s["brca1"];
                    $brca2 += $s["brca2"];
                }

                if ($s = $this->AgeAtOnsetMaleBreast($r, $a)) {
                    $brca1 += $s["brca1"];
                    $brca2 += $s["brca2"];
                }

                if ($s = $this->AgeAtOnsetOvarian($r, $a)) {
                    $brca1 += $s["brca1"];
                    $brca2 += $s["brca2"];
                }

                if ($s = $this->PancreaticCancer($r, $a)) {
                    $brca1 += $s["brca1"];
                    $brca2 += $s["brca2"];
                }

                if ($s = $this->AgeAtOnsetProstate($r, $a)) {
                    $brca1 += $s["brca1"];
                    $brca2 += $s["brca2"];
                }

                if ($r->isPaternal()) {
                    $score["paternal"]["brca1"] += $brca1;
                    $score["paternal"]["brca2"] += $brca2;
                    $score["paternal"]["combined"] += $brca1 + $brca2;
                }
                if ($r->isMaternal()) {
                    $score["maternal"]["brca1"] += $brca1;
                    $score["maternal"]["brca2"] += $brca2;
                    $score["maternal"]["combined"] += $brca1 + $brca2;
                }
            }
        }

        return $score;
    }

    /**
     * 1.Age at onset of female breast cancer
     * - <30 6 5
     * - 30–39 4 4
     * - 40–49 3 3
     * - 50–59 2 2
     * - ≥60 1 1
     * - Not affected 0 0
     *
     * @param BRCARelative $r
     * @param BRCAAntecedent $a
     * @return [string, int, int]
     */
    private function AgeAtOnsetFemaleBreast($r, $a) {
        if (!$r->isFemale() || ($a->getCancerType() != BRCACancerTable::BREAST_CANCER))
            return null;

        $onset = $a->onsetAge;
        if (!$onset) {
            $answer = ["answer" => "Not affected", "brca1" => 0, "brca2" => 0];
        } elseif ($onset < 30) {
            $answer = ["answer" => "<30", "brca1" => 6, "brca2" => 5];
        } elseif ($onset <= 39) {
            $answer = ["answer" => "30-39", "brca1" => 4, "brca2" => 4];
        } elseif ($onset <= 49) {
            $answer = ["answer" => "40-49", "brca1" => 3, "brca2" => 3];
        } elseif ($onset <= 59) {
            $answer = ["answer" => "50-59", "brca1" => 2, "brca2" => 2];
        } else {
            $answer = ["answer" => ">=60", "brca1" => 1, "brca2" => 1];
        }

        return $answer;
    }

    /**
     * 2.Age at onset of male breast cancer
     * - <60 y 5 8
     * - ≥60 y 5 5
     * - Not affected 0 0
     *
     * @param BRCARelative $r
     * @param BRCAAntecedent $a
     * @return [string, int, int]
     */
    private function AgeAtOnsetMaleBreast($r, $a) {
        if (!$r->isMale() || ($a->getCancerType() != BRCACancerTable::BREAST_CANCER))
            return null;

        $onset = $a->onsetAge;
        if (!$onset) {
            $answer = ["answer" => "Not affected", "brca1" => 0, "brca2" => 0];
        } elseif ($onset < 60) {
            $answer = ["answer" => "<60", "brca1" => 5, "brca2" => 8];
        } else {
            $answer = ["answer" => ">=60", "brca1" => 5, "brca2" => 5];
        }

        return $answer;
    }

    /**
     * 3.Age at onset of ovarian cancer
     * - <60 8 5
     * - ≥60 5 5
     * - Not affected 0 0
     *
     * @param BRCARelative $r
     * @param BRCAAntecedent $a
     * @return [string, int, int]
     */
    private function AgeAtOnsetOvarian($r, $a) {
        if (!$r->isFemale() || ($a->getCancerType() != BRCACancerTable::OVARIAN_CANCER))
            return null;

        $onset = $a->onsetAge;
        if (!$onset) {
            $answer = ["answer" => "Not affected", "brca1" => 0, "brca2" => 0];
        } elseif ($onset < 60) {
            $answer = ["answer" => "<60", "brca1" => 8, "brca2" => 5];
        } else {
            $answer = ["answer" => ">=60", "brca1" => 5, "brca2" => 5];
        }

        return $answer;
    }

    /**
     * 4.Pancreatic cancer
     * - Yes 0 1
     * - No 0 0
     *
     * @param BRCARelative $r
     * @param BRCAAntecedent $a
     * @return [string, int, int]
     */
    private function PancreaticCancer($r, $a) {
        if ($a->getCancerType() != BRCACancerTable::PANCREATIC_CANCER) {
            return null;
        }
        $answer = ["answer" => "y", "brca1" => 0, "brca2" => 1];

        return $answer;
    }

    /**
     * 5.Age at onset of prostate cancer
     * - <60 0 2
     * - ≥60 0 1
     * - Not affected 0 0
     *
     * @param BRCARelative $r
     * @param BRCAAntecedent $a
     * @return [string, int, int]
     */
    public function AgeAtOnsetProstate($r, $a) {
        if (!$r->isMale() || ($a->getCancerType() != BRCACancerTable::PROSTATE_CANCER))
            return null;

        $onset = $a->onsetAge;
        if ($onset < 60) {
            $answer = ["answer" => "<60", "brca1" => 0, "brca2" => 2];
        } else {
            $answer = ["answer" => ">=60", "brca1" => 0, "brca2" => 1];
        }

        return $answer;
    }

    /**
     *
     * @param SoapClient $client
     * @param string $sessionToken
     * @param int $manchesterFormId
     * @return string: Empty if no error. Otherwise an error description
     */
    function updateScoringForm($client, $sessionToken, $manchesterFormId) {
        $result = $client->form_get_summary($sessionToken, $manchesterFormId);

        if (!$result || $result["ErrorMsg"]) {
            // ERROR
            return $result["ErrorMsg"];
        }

        $xml = simplexml_load_string($result["result"]);
        if (!$xml->data || !$xml->data->questions) {
            return "The Manchester Scoring Form ($manchesterFormId) does not contain questions";
        }

        $score = $this->score();

        list($xmlSetAnswers, $root) = createAnswersXML();

        // Create array of relatives
        $arrayRef = getArrayRef($xml, ScoringManchester::Q_RELATIVE);
        if (!$arrayRef) {
            return "Not found array question for datacode " . ScoringManchester::Q_RELATIVE;
        }
        $arrayNode = createSubnode($xmlSetAnswers, $root, "array");
        createSubnode($xmlSetAnswers, $arrayNode, "ref", $arrayRef);

        /* @var BRCARelative $r */
        /* @var BRCAAntecedent $a */
        foreach ($this->_relatives as $r) {
            foreach ($r->antecedents as $a) {
                $rowNode = createSubnode($xmlSetAnswers, $arrayNode, "row");
                // In array questions, only the fist question of a row is returned until it has any value
                addNodeQuestion($xmlSetAnswers, $rowNode, ScoringManchester::Q_RELATIVE, $r->relativeCode);

                addNodeQuestion($xmlSetAnswers, $rowNode, ScoringManchester::Q_NAME, $r->relativeName);
                if ($r->isPaternal() && $r->isMaternal())
                    $side = "3";
                else
                    $side = $r->isPaternal() ? "1" : "2";
                addNodeQuestion($xmlSetAnswers, $rowNode, ScoringManchester::Q_SIDE, $side);
                addNodeQuestion($xmlSetAnswers, $rowNode, ScoringManchester::Q_CANCER, $a->cancerCode);
                addNodeQuestion($xmlSetAnswers, $rowNode, ScoringManchester::Q_ONSET, $a->onsetAge);

                $s = $this->AgeAtOnsetFemaleBreast($r, $a);
                if (!$s)
                    $s = $this->AgeAtOnsetMaleBreast($r, $a);
                if (!$s)
                    $s = $this->AgeAtOnsetOvarian($r, $a);
                if (!$s)
                    $s = $this->PancreaticCancer($r, $a);
                if (!$s)
                    $s = $this->AgeAtOnsetProstate($r, $a);

                if ($s) {
                    $range = $s["answer"];
                    $brca1 = $s["brca1"];
                    $brca2 = $s["brca2"];

                    addNodeQuestion($xmlSetAnswers, $rowNode, ScoringManchester::Q_RANGE, $range);
                    addNodeQuestion($xmlSetAnswers, $rowNode, ScoringManchester::Q_BRCA1, $brca1);
                    addNodeQuestion($xmlSetAnswers, $rowNode, ScoringManchester::Q_BRCA2, $brca2);
                }
            }
        }

        // Create array of global scores
        $arrayRef = getArrayRef($xml, ScoringManchester::Q_PATERNAL_BRCA1);
        if (!$arrayRef) {
            return "Not found array question for datacode " . ScoringManchester::Q_PATERNAL_BRCA1;
        }
        $arrayNode = createSubnode($xmlSetAnswers, $root, "array");
        createSubnode($xmlSetAnswers, $arrayNode, "ref", $arrayRef);
        $rowNode = createSubnode($xmlSetAnswers, $arrayNode, "row");

        addNodeQuestion($xmlSetAnswers, $rowNode, ScoringManchester::Q_PATERNAL_BRCA1, $score["paternal"]["brca1"]);
        addNodeQuestion($xmlSetAnswers, $rowNode, ScoringManchester::Q_PATERNAL_BRCA2, $score["paternal"]["brca2"]);
        addNodeQuestion($xmlSetAnswers, $rowNode, ScoringManchester::Q_PATERNAL_COMBINED, $score["paternal"]["combined"]);

        // Create array of global scores
        $arrayRef = getArrayRef($xml, ScoringManchester::Q_MATERNAL_BRCA1);
        if (!$arrayRef) {
            return "Not found array question for datacode " . ScoringManchester::Q_MATERNAL_BRCA1;
        }
        $arrayNode = createSubnode($xmlSetAnswers, $root, "array");
        createSubnode($xmlSetAnswers, $arrayNode, "ref", $arrayRef);
        $rowNode = createSubnode($xmlSetAnswers, $arrayNode, "row");

        addNodeQuestion($xmlSetAnswers, $rowNode, ScoringManchester::Q_MATERNAL_BRCA1, $score["maternal"]["brca1"]);
        addNodeQuestion($xmlSetAnswers, $rowNode, ScoringManchester::Q_MATERNAL_BRCA2, $score["maternal"]["brca2"]);
        addNodeQuestion($xmlSetAnswers, $rowNode, ScoringManchester::Q_MATERNAL_COMBINED, $score["maternal"]["combined"]);

        $assessment = null;
        $finalResult = $this->isPositive($assessment);
        addNodeQuestion($xmlSetAnswers, $root, ScoringManchester::Q_ASSESSMENT, $assessment);
        addNodeQuestion($xmlSetAnswers, $root, ScoringManchester::Q_SCORING_RESULT, $finalResult ? "1" : "2");

        $xmlStr = $xmlSetAnswers->SaveXML();
        $client->form_set_all_answers($sessionToken, $manchesterFormId, $xmlStr);
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
        $str .= '<td style="text-align:right">' . $results["brca1"] . '</td>';
        $str .= '<td style="text-align:right">' . $results["brca2"] . '</td>';
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
            if ($s = $this->AgeAtOnsetFemaleBreast($r, $a)) {
                $str .= $this->htmlOutputQuestion($s);
                $rowSpan++;
            }
            if ($s = $this->AgeAtOnsetMaleBreast($r, $a)) {
                $str .= $this->htmlOutputQuestion($s);
                $rowSpan++;
            }
            if ($s = $this->AgeAtOnsetOvarian($r, $a)) {
                $str .= $this->htmlOutputQuestion($s);
                $rowSpan++;
            }
            if ($s = $this->PancreaticCancer($r, $a)) {
                $str .= $this->htmlOutputQuestion($s);
                $rowSpan++;
            }
            if ($s = $this->AgeAtOnsetProstate($r, $a)) {
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
        $str = '<h1>MANCHESTER SCORING</h1>';
        $str .= '<table cellpadding="5" border="1">';
        $str .= '<tr>';
        $str .= '<th>Relative</th>';
        $str .= '<th>Name</th>';
        $str .= '<th>Side</th>';
        $str .= '<th>Cancer</th>';
        $str .= '<th>Onset</th>';
        $str .= '<th>Range</th>';
        $str .= '<th>brca1</th>';
        $str .= '<th>brca2</th>';
        $str .= '</tr>';

        foreach ($this->_relatives as $r) {
            $str .= $this->htmlOutputRow($r);
        }
        $str .= '</table><br/>';

        $score = $this->score();
        $str .= '<table cellpadding="5" border="1">';
        $str .= '<tr>';
        $str .= '<td></td>';
        $str .= '<td colspan="3">Paternal</td>';
        $str .= '<td colspan="3">Maternal</td>';
        $str .= '</tr><tr>';
        $str .= '<td>Score</td>';
        $str .= '<td>brca1</td><td>brca2</td><td>combined</td><td>brca1</td><td>brca2</td><td>combined</td>';
        $str .= '</tr><tr>';
        $str .= '<td>Score</td>';
        $str .= '<td>' . $score["paternal"]["brca1"] . '</td>';
        $str .= '<td>' . $score["paternal"]["brca2"] . '</td>';
        $str .= '<td>' . $score["paternal"]["combined"] . '</td>';
        $str .= '<td>' . $score["maternal"]["brca1"] . '</td>';
        $str .= '<td>' . $score["maternal"]["brca2"] . '</td>';
        $str .= '<td>' . $score["maternal"]["combined"] . '</td>';
        $str .= '</tr>';
        $str .= '</table>';

        $assesment = null;
        $str .= '<h3>Final result: ' .
                ($this->isPositive($assesment) ? '<span style="color:#ff0000">Positive</span>' : '<span style="color:#00ff00">Negative</span>') .
                '</h3><br/>';

        return $str;
    }
}