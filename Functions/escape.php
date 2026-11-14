<?php
/**
 * Escape a value for output in HTML text or attributes
 *
 * @param $value
 * @return string
 */
function escape($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
