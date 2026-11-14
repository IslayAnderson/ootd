<?php
/**
 * Turn user input like "#Autumn, streetwear warm" into "autumn,streetwear,warm"
 *
 * @param $input
 * @return string
 */
function tags_normalise($input): string
{
    $tags = preg_split('/[\s,]+/', strtolower((string)$input), -1, PREG_SPLIT_NO_EMPTY);
    $tags = array_map(function ($tag) {
        return preg_replace('/[^a-z0-9\-_]/', '', ltrim($tag, '#'));
    }, $tags);

    return implode(",", array_unique(array_filter($tags)));
}

/**
 * @param $tags stored tag string
 * @return array
 */
function tags_split($tags): array
{
    return array_filter(explode(",", (string)$tags));
}

/**
 * @param $tags stored comma separated tags
 * @return string "#one #two", for edit forms
 */
function tags_display($tags): string
{
    return implode(" ", array_map(function ($tag) {
        return "#" . $tag;
    }, tags_split($tags)));
}
