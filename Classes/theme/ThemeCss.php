<?php

/**
 * The "limited CSS" box of a theme (0.15.0, D66/D70). What someone types is never stored or shown
 * as typed: it is read rule by rule, and only listed selectors with listed properties whose values
 * fit a strict grammar are kept. The kept rules are written out again from those checked pieces,
 * so nothing else (at-rules, comments, escapes, `</style>`, expressions) can reach the page.
 *
 * `url()` is allowed in background-image only. Each address is handed to an importer (ThemeImage)
 * that checks the image and stores a copy; the rule then points at that copy (/media/<id>/display).
 */
class ThemeCss
{
    const MAX_LENGTH = 5000;
    const MAX_RULES = 80;
    const MAX_IMAGES = 5;

    /** selectors anyone may style (people's themes are limited to their own pages, see scope()) */
    const SELECTORS = array(
        "h1", "h2", "h3", "p", "a", "a:hover", "em", "strong", "blockquote", "hr", "img",
        ".header", ".header h1", ".header .kicker", ".kicker", ".handle", ".address", ".avatar", ".avatar img",
        ".blog", ".blog h1", ".blog h2", ".blog p", ".blog img", ".blog blockquote",
        ".feed", ".feed article", ".post", ".outfit", ".garment", ".wardrobe", ".notice",
        ".btn", "button", ".btn:hover", "button:hover", ".secondary"
    );
    /** only a blog's own theme may style these: they are on every page, navigation included */
    const BLOG_SELECTORS = array("body", ".top-bar", ".main-nav", "footer", ".site-logo", ".site-logo--wordmark");

    const COLOUR_PROPERTIES = array("color", "background-color", "border-color", "border-top-color", "border-bottom-color",
        "border-left-color", "border-right-color", "text-decoration-color", "outline-color");
    const LENGTH_PROPERTIES = array("margin", "margin-top", "margin-bottom", "margin-left", "margin-right",
        "padding", "padding-top", "padding-bottom", "padding-left", "padding-right", "border-radius", "border-width");
    const BORDER_PROPERTIES = array("border", "border-top", "border-bottom", "border-left", "border-right", "outline");
    const KEYWORD_PROPERTIES = array(
        "font-style" => array("normal", "italic"),
        "font-weight" => array("normal", "bold", "100", "200", "300", "400", "500", "600", "700", "800", "900"),
        "text-transform" => array("none", "uppercase", "lowercase", "capitalize"),
        "text-align" => array("left", "right", "center", "justify", "start", "end"),
        "text-decoration" => array("none", "underline", "overline", "line-through"),
        "border-style" => array("none", "solid", "dashed", "dotted", "double"),
        "font-family" => array("var(--serif)", "var(--sans)", "serif", "sans-serif", "monospace"),
        "background-repeat" => array("repeat", "no-repeat", "repeat-x", "repeat-y", "space", "round"),
        "background-size" => array("auto", "cover", "contain"),
        "background-attachment" => array("scroll", "local"),
        "display" => array("none"),
        "background-image" => array("none")
    );
    const NAMED_COLOURS = array("transparent", "currentcolor", "inherit", "white", "black");
    const THEME_VARIABLES = array("--ink", "--paper", "--soft-ink", "--line", "--accent", "--light-grey", "--mid-grey");

    /**
     * @param string $css what was typed
     * @param bool $blog true for the blog's theme, false for a person's (scoped to their pages)
     * @param callable|null $import fn(string $url): string local path; throws when the image isn't accepted
     * @param string[] $kept_images /media/<id>/display paths this theme already has, allowed again as they are
     * @return array [string cleaned CSS, string[] what was left out and why, string[] local image paths used]
     */
    public static function clean($css, $blog, $import = null, $kept_images = array()): array
    {
        $problems = array();
        $css = (string)$css;
        if (strlen($css) > self::MAX_LENGTH) {
            $css = substr($css, 0, self::MAX_LENGTH);
            $problems[] = "Only the first " . self::MAX_LENGTH . " characters were read.";
        }
        //escapes can spell anything; nothing on the lists needs them
        if (strpos($css, "\\") !== false) {
            $problems[] = "Backslashes aren't allowed, so the rules that use them were left out.";
        }
        $css = preg_replace('#/\*.*?(\*/|$)#s', " ", $css);
        $css = str_replace(array("\r", "\0"), array("", ""), $css);

        //@import, @charset and other one-line at-rules; @media/@font-face blocks are left over below
        if (preg_match('/@[^;{}]*;/', $css)) {
            $css = preg_replace('/@[^;{}]*;/', " ", $css);
            $problems[] = "@-rules (like @import) aren't allowed and were left out.";
        }

        $out = array();
        $images = array();
        $imports = 0;
        preg_match_all('/([^{}]*)\{([^{}]*)\}/', $css, $rules, PREG_SET_ORDER);
        $leftover = trim(preg_replace('/([^{}]*)\{([^{}]*)\}/', "", $css));
        if ($leftover !== "") {
            $problems[] = "Some text wasn't a rule (or was an @-rule) and was left out.";
        }
        foreach (array_slice($rules, 0, self::MAX_RULES) as $rule) {
            $selector_text = trim($rule[1]);
            if (strpos($selector_text, "@") !== false || strpos($rule[0], "\\") !== false) {
                $problems[] = "Left out: " . self::short($selector_text) . " (not allowed).";
                continue;
            }
            $selectors = array();
            foreach (explode(",", $selector_text) as $selector) {
                $selector = preg_replace('/\s+/', " ", trim($selector));
                if (in_array($selector, self::SELECTORS, true) || ($blog && in_array($selector, self::BLOG_SELECTORS, true))) {
                    $selectors[] = $selector;
                } elseif ($selector !== "") {
                    $problems[] = "Left out the selector " . self::short($selector) . ": it isn't one that can be styled.";
                }
            }
            if (count($selectors) == 0) {
                continue;
            }
            $declarations = array();
            foreach (explode(";", $rule[2]) as $declaration) {
                if (trim($declaration) === "") {
                    continue;
                }
                $parts = explode(":", $declaration, 2);
                $property = strtolower(trim($parts[0]));
                $value = trim($parts[1] ?? "");
                $important = false;
                if (preg_match('/\s*!\s*important$/i', $value)) {
                    $important = true;
                    $value = trim(preg_replace('/\s*!\s*important$/i', "", $value));
                }
                if ($property == "background-image" && preg_match('/^url\(/i', $value)) {
                    $url = self::url_of($value);
                    if ($url === null) {
                        $problems[] = "Left out a background-image: write it as url(\"https://…\").";
                        continue;
                    }
                    if (in_array($url, $kept_images, true)) {
                        $local = $url;
                    } elseif ($import === null || $imports >= self::MAX_IMAGES) {
                        $problems[] = "Left out " . self::short($url) . ": at most " . self::MAX_IMAGES . " images.";
                        continue;
                    } else {
                        $imports++;
                        try {
                            $local = $import($url);
                        } catch (Exception $e) {
                            $problems[] = "Left out the image " . self::short($url) . ": " . $e->getMessage();
                            continue;
                        }
                    }
                    if (!preg_match('#^/media/[0-9a-f]{32}/display$#', (string)$local)) {
                        $problems[] = "Left out the image " . self::short($url) . ".";
                        continue;
                    }
                    $images[] = $local;
                    $declarations[] = 'background-image: url("' . $local . '")' . ($important ? " !important" : "");
                    continue;
                }
                $clean = self::value($property, $value);
                if ($clean === null) {
                    $problems[] = "Left out " . self::short($property . ": " . $value) . ": that property or value isn't allowed.";
                    continue;
                }
                $declarations[] = $property . ": " . $clean . ($important ? " !important" : "");
            }
            if (count($declarations) > 0) {
                $out[] = implode(", ", $selectors) . " { " . implode("; ", $declarations) . "; }";
            }
        }
        if (count($rules) > self::MAX_RULES) {
            $problems[] = "Only the first " . self::MAX_RULES . " rules were kept.";
        }
        return array(implode("\n", $out), array_values(array_unique($problems)), array_values(array_unique($images)));
    }

    /**
     * A person's rules only reach their own content: every selector is put inside the page's
     * content (body's children other than the navigation bars and footer).
     *
     * @param string $css cleaned CSS from clean(..., false)
     * @return string
     */
    public static function scope($css): string
    {
        $prefix = "body > :not(.top-bar):not(.main-nav):not(footer)";
        $lines = array();
        foreach (explode("\n", (string)$css) as $line) {
            if (!preg_match('/^([^{]+)\{(.*)\}$/', $line, $m)) {
                continue;
            }
            $selectors = array();
            foreach (explode(",", $m[1]) as $selector) {
                $selector = trim($selector);
                //the content block itself (".header" is one), and anything inside it
                $parts = explode(" ", $selector, 2);
                $selectors[] = $prefix . ":is(" . $parts[0] . ")" . (isset($parts[1]) ? " " . $parts[1] : "") . ", " . $prefix . " " . $selector;
            }
            $lines[] = implode(", ", $selectors) . " {" . $m[2] . "}";
        }
        return implode("\n", $lines);
    }

    /**
     * @return string|null the cleaned value, null when it isn't allowed
     */
    public static function value($property, $value)
    {
        $value = strtolower(preg_replace('/\s+/', " ", trim((string)$value)));
        if ($value === "" || strlen($value) > 120 || !preg_match('/^[a-z0-9#%.,() \-]+$/', $value)) {
            return null;
        }
        if (in_array($property, self::COLOUR_PROPERTIES, true)) {
            return self::colour($value);
        }
        if (isset(self::KEYWORD_PROPERTIES[$property])) {
            if (in_array($value, self::KEYWORD_PROPERTIES[$property], true)) {
                return $value;
            }
            if ($property == "background-size") {
                return self::lengths($value, 2, true);
            }
            return null;
        }
        if (in_array($property, self::LENGTH_PROPERTIES, true)) {
            return self::lengths($value, 4, $property != "border-width" && strpos($property, "margin") === 0);
        }
        if (in_array($property, self::BORDER_PROPERTIES, true)) {
            $parts = array();
            foreach (self::split_tokens($value) as $token) {
                if (in_array($token, self::KEYWORD_PROPERTIES['border-style'], true) || self::length($token) !== null) {
                    $parts[] = $token;
                } elseif (($colour = self::colour($token)) !== null) {
                    $parts[] = $colour;
                } else {
                    return null;
                }
            }
            return count($parts) >= 1 && count($parts) <= 3 ? implode(" ", $parts) : null;
        }
        switch ($property) {
            case "font-size":
            case "letter-spacing":
            case "max-width":
                if ($value === "normal" && $property == "letter-spacing") {
                    return $value;
                }
                return self::length($value);
            case "line-height":
                return preg_match('/^[0-9]{1,2}(\.[0-9]{1,3})?$/', $value) ? $value : self::length($value);
            case "background-position":
                $parts = array();
                foreach (explode(" ", $value) as $token) {
                    if (in_array($token, array("left", "right", "top", "bottom", "center"), true) || self::length($token) !== null) {
                        $parts[] = $token;
                    } else {
                        return null;
                    }
                }
                return count($parts) <= 2 ? implode(" ", $parts) : null;
        }
        return null;
    }

    /**
     * @return string|null #hex, a few keywords, or one of the theme's own colours
     */
    public static function colour($value)
    {
        $value = strtolower(trim((string)$value));
        if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/', $value) || in_array($value, self::NAMED_COLOURS, true)) {
            return $value;
        }
        if (preg_match('/^var\((--[a-z-]+)\)$/', $value, $m) && in_array($m[1], self::THEME_VARIABLES, true)) {
            return $value;
        }
        return null;
    }

    private static function length($token)
    {
        if ($token === "0" || $token === "auto") {
            return $token;
        }
        return preg_match('/^-?[0-9]{1,4}(\.[0-9]{1,3})?(px|rem|em|%|vw|vh)$/', $token) ? $token : null;
    }

    private static function lengths($value, $max, $auto)
    {
        $tokens = explode(" ", $value);
        if (count($tokens) > $max) {
            return null;
        }
        foreach ($tokens as $token) {
            if (self::length($token) === null || ($token === "auto" && !$auto)) {
                return null;
            }
        }
        return implode(" ", $tokens);
    }

    /** space-separated tokens, keeping var(...) whole */
    private static function split_tokens($value): array
    {
        preg_match_all('/var\([^)]*\)|[^ ]+/', $value, $m);
        return $m[0];
    }

    /**
     * @return string|null the address inside url(...)
     */
    private static function url_of($value)
    {
        if (!preg_match('/^url\(\s*(["\']?)([^"\'()\s]{1,2000})\1\s*\)$/i', trim($value), $m)) {
            return null;
        }
        return $m[2];
    }

    private static function short($text): string
    {
        $text = preg_replace('/\s+/', " ", trim((string)$text));
        return '"' . (mb_strlen($text) > 60 ? mb_substr($text, 0, 57) . "…" : $text) . '"';
    }
}
