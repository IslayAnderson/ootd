<?php
/**
 * Strip rich text from TinyMCE down to a safe set of tags, attributes are dropped except http(s) links
 *
 * @param $html
 * @return string
 */
function sanitise_html($html): string
{
    $allowed = array("p", "br", "strong", "b", "em", "i", "u", "s", "ul", "ol", "li", "blockquote", "h2", "h3", "h4", "a");

    if (trim((string)$html) == "") {
        return "";
    }

    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8"><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    $root = $doc->getElementsByTagName("div")->item(0);
    sanitise_html_node($root, $allowed);

    $output = "";
    foreach ($root->childNodes as $child) {
        $output .= $doc->saveHTML($child);
    }
    return $output;
}

/**
 * @param DOMNode $node
 * @param array $allowed
 * @return void
 */
function sanitise_html_node($node, $allowed): void
{
    foreach (iterator_to_array($node->childNodes) as $child) {
        if ($child instanceof DOMElement) {
            $tag = strtolower($child->tagName);
            if (in_array($tag, array("script", "style", "iframe", "object", "embed"))) {
                $node->removeChild($child);
                continue;
            }

            sanitise_html_node($child, $allowed);

            if (!in_array($tag, $allowed)) {
                //keep the text, lose the tag
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }

            $href = $tag == "a" ? $child->getAttribute("href") : "";
            foreach (iterator_to_array($child->attributes) as $attribute) {
                $child->removeAttribute($attribute->name);
            }
            if (preg_match('#^https?://#i', $href)) {
                $child->setAttribute("href", $href);
                $child->setAttribute("rel", "nofollow noopener");
            }
        } elseif ($child instanceof DOMComment) {
            $node->removeChild($child);
        }
    }
}
