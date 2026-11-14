<?php
/**
 * Themes (0.15.0, D66–D69). The stylesheet is the same for every blog; a theme only sets its
 * colours and fonts (CSS custom properties) and adds checked rules from ThemeCss, in one block
 * that the server writes into the page's head. The blog's theme (owner 0) is set in /admin; each
 * person may set one for their own profile, outfit, garment and article pages, which never
 * reaches the navigation, footer, login or admin pages.
 */

//colour setting => [label, CSS custom property, default]
const THEME_COLOURS = array(
    "paper" => array("Background", "--paper", "#ffffff"),
    "ink" => array("Text", "--ink", "#000000"),
    "soft_ink" => array("Muted text (dates, captions)", "--soft-ink", "#555555"),
    "line" => array("Lines and borders", "--line", "#000000"),
    "accent" => array("Buttons", "--accent", "#000000")
);
const THEME_FONT_DEFAULTS = array(
    "serif_font" => '"Newsreader", Georgia, "Times New Roman", serif',
    "sans_font" => '"Inter Tight", "Helvetica Neue", Helvetica, Arial, sans-serif'
);
//text needs this much contrast against the background (WCAG AA for body text)
const THEME_MIN_CONTRAST = 4.5;
const THEME_BLOG = 0;

/**
 * @param int $owner_id THEME_BLOG or a user_id
 * @return array|null colours (all five), serif_font, sans_font, css; null when there's no theme
 */
function theme_get($owner_id)
{
    static $cache = array();
    $owner_id = (int)$owner_id;
    if (array_key_exists($owner_id, $cache)) {
        return $cache[$owner_id];
    }
    $db = new Mysql();
    try {
        $row = $db->Fetch("SELECT * FROM `themes` WHERE `owner_id` = :a", array(":a" => $owner_id));
    } catch (Throwable $e) {
        //not upgraded yet
        return $cache[$owner_id] = null;
    }
    if (!is_array($row) || $row[0] == '00000') {
        return $cache[$owner_id] = null;
    }
    $stored = json_decode((string)$row[0]->colours, true);
    $colours = array();
    foreach (THEME_COLOURS as $key => $colour) {
        $value = is_array($stored) ? (string)($stored[$key] ?? "") : "";
        $colours[$key] = theme_hex($value) ?? $colour[2];
    }
    return $cache[$owner_id] = array(
        "colours" => $colours,
        "serif_font" => theme_font_name($row[0]->serif_font),
        "sans_font" => theme_font_name($row[0]->sans_font),
        "css" => (string)$row[0]->css
    );
}

/**
 * @return string|null "#rrggbb" when the value is exactly that (any case), else null
 */
function theme_hex($value)
{
    $value = strtolower(trim((string)$value));
    return preg_match('/^#[0-9a-f]{6}$/', $value) ? $value : null;
}

/**
 * @return string a Google Font name that's safe to write into CSS and a URL, "" for none
 */
function theme_font_name($name): string
{
    $name = preg_replace('/\s+/', " ", trim((string)$name));
    return preg_match('/^[A-Za-z0-9][A-Za-z0-9 ]{0,39}$/', $name) ? $name : "";
}

/**
 * @param string $hex #rrggbb
 * @return float relative luminance (WCAG)
 */
function theme_luminance($hex): float
{
    $total = 0.0;
    foreach (array(1 => 0.2126, 3 => 0.7152, 5 => 0.0722) as $offset => $weight) {
        $c = hexdec(substr($hex, $offset, 2)) / 255;
        $c = $c <= 0.03928 ? $c / 12.92 : pow(($c + 0.055) / 1.055, 2.4);
        $total += $weight * $c;
    }
    return $total;
}

function theme_contrast($a, $b): float
{
    $la = theme_luminance($a);
    $lb = theme_luminance($b);
    return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
}

/**
 * Does Google Fonts have this family? Checked once, when a theme is saved.
 *
 * @param string $name
 * @return bool
 * @throws Exception when Google couldn't be asked
 */
function theme_font_exists($name): bool
{
    $base = (string)($_ENV['GOOGLE_FONTS_URL'] ?? "https://fonts.googleapis.com/css2");
    try {
        list($status) = SafeHttp::get($base . "?family=" . str_replace(" ", "+", $name) . "&display=swap");
    } catch (Exception $e) {
        throw new Exception("Google Fonts couldn't be checked just now, so the font wasn't changed. Try again later.");
    }
    return $status == 200;
}

/**
 * The page's theme belongs to this person (their profile, outfit, garment or article pages).
 *
 * @param int $user_id
 * @return void
 */
function theme_for_user($user_id): void
{
    $GLOBALS['theme_user'] = (int)$user_id;
}

/**
 * @param array|null $theme
 * @return string CSS declarations for the custom properties ("--ink: #000000; …")
 */
function theme_properties($theme): string
{
    $out = array();
    foreach (THEME_COLOURS as $key => $colour) {
        $out[] = $colour[1] . ": " . ($theme ? $theme['colours'][$key] : $colour[2]);
    }
    //the greys for bands and placeholders follow the theme: a little of the text colour in the background
    $out[] = "--light-grey: color-mix(in srgb, var(--ink) 4%, var(--paper))";
    $out[] = "--mid-grey: color-mix(in srgb, var(--ink) 35%, var(--paper))";
    foreach (array("serif_font" => "--serif", "sans_font" => "--sans") as $key => $property) {
        $name = $theme ? $theme[$key] : "";
        //the family name only ever holds letters, digits and spaces (theme_font_name)
        $fallback = $key == "serif_font" ? 'Georgia, "Times New Roman", serif' : '"Helvetica Neue", Helvetica, Arial, sans-serif';
        $out[] = $property . ": " . ($name !== "" ? '"' . $name . '", ' . $fallback : THEME_FONT_DEFAULTS[$key]);
    }
    return implode("; ", $out) . ";";
}

/**
 * @return string the <link>s and <style> for this page's head, "" when nothing is themed
 */
function theme_head(): string
{
    $blog = theme_get(THEME_BLOG);
    $user_id = (int)($GLOBALS['theme_user'] ?? 0);
    $person = $user_id > 0 ? theme_get($user_id) : null;
    if (!$blog && !$person) {
        return "";
    }
    $fonts = array();
    foreach (array($blog, $person) as $theme) {
        foreach (array("serif_font", "sans_font") as $key) {
            if ($theme && $theme[$key] !== "") {
                $fonts[$theme[$key]] = true;
            }
        }
    }
    $html = "";
    if (count($fonts) > 0) {
        $families = array();
        foreach (array_keys($fonts) as $name) {
            //the plain family: asking for weights a font doesn't have makes Google refuse the lot
            $families[] = "family=" . str_replace(" ", "+", $name);
        }
        $html .= '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n  ";
        $html .= '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?' . escape(implode("&", $families)) . '&amp;display=swap">' . "\n  ";
    }
    $css = "";
    if ($blog) {
        $css .= ":root { " . theme_properties($blog) . " }\n" . $blog['css'] . "\n";
    }
    if ($person) {
        //the person's colours for the page, the blog's kept on the bars and footer around it
        $css .= "body { " . theme_properties($person) . " }\n";
        $css .= ".top-bar, .main-nav, footer { " . theme_properties($blog) . " }\n";
        $css .= "footer { background: var(--paper); color: var(--ink); }\n";
        $css .= ThemeCss::scope($person['css']) . "\n";
    }
    //nothing in $css came from anyone as typed: colours are #rrggbb, font names letters and
    //spaces, rules rebuilt by ThemeCss; this is a last guard
    $css = str_ireplace("</style", "", $css);
    return $html . "<style>\n" . $css . "</style>";
}

/**
 * Save a theme from the posted form.
 *
 * @param int $owner_id THEME_BLOG or a user_id
 * @param array $input colours, serif_font, sans_font, css
 * @param int $saved_by who is saving (owns imported images)
 * @return array problems to show (empty when everything was kept)
 * @throws Exception when nothing could be saved
 */
function theme_save($owner_id, $input, $saved_by): array
{
    $owner_id = (int)$owner_id;
    $current = theme_get($owner_id);
    $problems = array();

    $colours = array();
    foreach (THEME_COLOURS as $key => $colour) {
        $value = theme_hex($input[$key] ?? "");
        if ($value === null) {
            $problems[] = $colour[0] . " wasn't a colour, so it stayed as it was.";
            $value = $current ? $current['colours'][$key] : $colour[2];
        }
        $colours[$key] = $value;
    }
    foreach (array("ink" => "Text", "soft_ink" => "Muted text", "accent" => "Buttons") as $key => $label) {
        if (theme_contrast($colours[$key], $colours['paper']) < THEME_MIN_CONTRAST) {
            $problems[] = $label . " was too hard to read on that background, so it stayed as it was.";
            $colours[$key] = $current && theme_contrast($current['colours'][$key], $colours['paper']) >= THEME_MIN_CONTRAST
                ? $current['colours'][$key] : (theme_luminance($colours['paper']) > 0.18 ? "#000000" : "#ffffff");
        }
    }

    $fonts = array();
    foreach (array("serif_font" => "Headline and text font", "sans_font" => "Label font") as $key => $label) {
        $typed = preg_replace('/\s+/', " ", trim((string)($input[$key] ?? "")));
        $before = $current ? $current[$key] : "";
        if ($typed === "" || $typed === $before) {
            $fonts[$key] = $typed;
            continue;
        }
        $name = theme_font_name($typed);
        if ($name === "") {
            $problems[] = $label . ": a font name is letters, numbers and spaces (at most 40), so it stayed as it was.";
            $fonts[$key] = $before;
            continue;
        }
        try {
            $exists = theme_font_exists($name);
        } catch (Exception $e) {
            $problems[] = $label . ": " . $e->getMessage();
            $fonts[$key] = $before;
            continue;
        }
        if (!$exists) {
            $problems[] = $label . ": Google Fonts has no font called \"" . $name . "\", so it stayed as it was.";
            $fonts[$key] = $before;
            continue;
        }
        $fonts[$key] = $name;
    }

    $old_images = theme_images($current ? $current['css'] : "");
    list($css, $css_problems, $images) = ThemeCss::clean((string)($input['css'] ?? ""), $owner_id === THEME_BLOG,
        function ($url) use ($saved_by) {
            return ThemeImage::import($url, $saved_by);
        }, $old_images);
    $problems = array_merge($problems, $css_problems);

    $db = new Mysql();
    $db->Fetch("INSERT INTO `themes` (`owner_id`, `colours`, `serif_font`, `sans_font`, `css`, `updated_at`)
                VALUES (:a, :b, :c, :d, :e, NOW())
                ON DUPLICATE KEY UPDATE `colours` = VALUES(`colours`), `serif_font` = VALUES(`serif_font`),
                `sans_font` = VALUES(`sans_font`), `css` = VALUES(`css`), `updated_at` = NOW()",
        array(":a" => $owner_id, ":b" => json_encode($colours), ":c" => $fonts['serif_font'], ":d" => $fonts['sans_font'], ":e" => $css));

    //images the theme no longer uses
    foreach (array_diff($old_images, $images) as $path) {
        Media::remove(explode("/", $path)[2]);
    }
    return $problems;
}

/**
 * @param int $owner_id
 * @return void
 */
function theme_reset($owner_id): void
{
    $current = theme_get($owner_id);
    $db = new Mysql();
    $db->Fetch("DELETE FROM `themes` WHERE `owner_id` = :a", array(":a" => (int)$owner_id));
    foreach (theme_images($current ? $current['css'] : "") as $path) {
        Media::remove(explode("/", $path)[2]);
    }
}

/**
 * @param string $css cleaned CSS
 * @return string[] the /media/<id>/display paths it uses
 */
function theme_images($css): array
{
    preg_match_all('#/media/[0-9a-f]{32}/display#', (string)$css, $m);
    return array_values(array_unique($m[0]));
}

/**
 * @param string $media_id
 * @return bool is this image used by a theme (shown to everyone)
 */
function theme_uses_media($media_id): bool
{
    if (!preg_match('/^[0-9a-f]{32}$/', (string)$media_id)) {
        return false;
    }
    $db = new Mysql();
    try {
        $row = $db->Fetch("SELECT `owner_id` FROM `themes` WHERE `css` LIKE :a LIMIT 1", array(":a" => "%/media/" . $media_id . "/display%"));
    } catch (Throwable $e) {
        return false;
    }
    return is_array($row) && $row[0] != '00000';
}

/**
 * @return bool does any theme here load Google Fonts (said on /privacy)
 */
function theme_google_fonts_used(): bool
{
    $db = new Mysql();
    try {
        $row = $db->Fetch("SELECT `owner_id` FROM `themes` WHERE (`serif_font` IS NOT NULL AND `serif_font` <> '') OR (`sans_font` IS NOT NULL AND `sans_font` <> '') LIMIT 1", null);
    } catch (Throwable $e) {
        return false;
    }
    return is_array($row) && $row[0] != '00000';
}
