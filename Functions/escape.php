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

/**
 * Text from a plain-text field (descriptions, excerpts, titles) as plain text. Before 0.7.0 these
 * fields were printed as HTML, so some hold <br> tags and entities like &rsquo;: line breaks become
 * newlines, entities become characters and any other tags are dropped.
 *
 * @param mixed $value
 * @return string
 */
function plain_text($value): string
{
    $text = (string)$value;
    if (strpos($text, "<") === false && strpos($text, "&") === false) {
        return $text;
    }
    $text = preg_replace('#<br\s*/?>|</p>\s*<p[^>]*>#i', "\n", $text);
    //only real tags (a letter after the <), so "I <3 this" and "1<2" survive
    $text = preg_replace('#<(script|style)\b[^>]*>.*?</\1\s*>#is', "", $text);
    $text = preg_replace('#</?[a-z][a-z0-9]*\b[^<>]*>#i', "", $text);
    return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * A plain-text field ready for the page: escaped, with its line breaks kept
 *
 * @param mixed $value
 * @return string HTML
 */
function text_html($value): string
{
    return nl2br(escape(plain_text($value)), false);
}
