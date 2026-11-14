<?php
/**
 * @param $timestamp
 * @return string "14 November 2026"
 */
function date_display($timestamp): string
{
    $time = strtotime((string)$timestamp);
    return $time ? date("j F Y", $time) : "";
}
