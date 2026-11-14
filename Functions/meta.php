<?php
/**
 * Page titles and descriptions are swapped into partials/head.php when index.php flushes its buffer
 */
function title_update($title)
{
    $GLOBALS['meta']['title'] = $title;
}

function description_update($description)
{
    $GLOBALS['meta']['description'] = $description;
}

/**
 * @deprecated misspelt original name, kept for existing callers
 */
function decsription_update($description)
{
    description_update($description);
}

/**
 * ob_start() callback used by index.php
 *
 * @param $buffer
 * @return string
 */
function meta_replace($buffer)
{
    $title = $GLOBALS['meta']['title'] ?? "outfit of the day";
    $description = $GLOBALS['meta']['description'] ?? "outfit of the day";

    //first, and only the first: the head's (a title or post containing the placeholder text can't move it)
    $at = strpos($buffer, "{theme_placeholder}");
    if ($at !== false) {
        $buffer = substr_replace($buffer, function_exists("setting_get") ? theme_head() : "", $at, strlen("{theme_placeholder}"));
    }
    $buffer = str_replace("{instance_placeholder}", escape(function_exists("setting_get") ? instance_name() : DEFAULT_NAME), $buffer);
    $buffer = str_replace("{title_placeholder}", escape($title), $buffer);
    return str_replace("{description_placeholder}", escape($description), $buffer);
}
