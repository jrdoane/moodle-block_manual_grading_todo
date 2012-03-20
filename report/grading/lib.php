<?php

# cmp_function for sorting attempts
function mgtl_attempts_cmp($a, $b) {
    if ($a->timefinish == $b->timefinish) {
        return 0;
    }
    return ($a->timefinish < $b->timefinish) ? -1 : 1;
}

# cmp_function for sorting quizzes/assignments for displaying
function mgtl_cmp($a, $b) {
    $a_earliest = reset($a->attempts)->timefinish;
    $b_earliest = reset($b->attempts)->timefinish;

    if ($a_earliest == $b_earliest) {
        return 0;
    }
    return ($a_earliest < $b_earliest) ? -1 : 1;
}

?>
