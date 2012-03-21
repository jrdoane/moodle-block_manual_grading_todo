<?php

function mgtl_get_string($name) { return get_string($name, 'block_manual_grading_todo'); }

function mgtl_anchor($text, $url, $attr=array()) {
    $str = "<a href=\"$url\"";
    foreach($attr as $key => $a) {
        $str .= " $key=\"" . htmlspecialchars($a) . "\"";
    }
    $str .= ">$text</a>";
    return $str;
}

