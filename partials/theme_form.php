<?php
/**
 * The theme form, for the blog (/admin) or for yourself (/account). Expects $theme_owner:
 * "blog" or "me".
 */
$theme_owner_id = $theme_owner == "blog" ? THEME_BLOG : (int)$_SESSION['user_id'];
$theme_current = theme_get($theme_owner_id);
$theme_problems = $_SESSION['theme_problems'][$theme_owner] ?? null;
unset($_SESSION['theme_problems'][$theme_owner]);
?>
<form action="/API/theme/save.php" method="post" class="settings theme-form">
    <?php CSRF_input(); ?>
    <input type="hidden" name="owner" value="<?= escape($theme_owner) ?>">
    <?php if (is_array($theme_problems)): ?>
        <div class="notice">
            <p>Theme saved.<?= count($theme_problems) > 0 ? " Some of it wasn't kept:" : "" ?></p>
            <?php if (count($theme_problems) > 0): ?>
                <ul>
                    <?php foreach ($theme_problems as $problem): ?>
                        <li><?= escape($problem) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <p class="hint">
        <?php if ($theme_owner == "blog"): ?>
            How every page of the blog looks. People can also give their own pages a theme of their own.
        <?php else: ?>
            How your profile, outfits, garments and articles look to everyone. The menus and footer keep the blog's look.
        <?php endif; ?>
    </p>
    <?php foreach (THEME_COLOURS as $key => $colour): ?>
        <label for="theme_<?= $theme_owner ?>_<?= $key ?>" class="colour">
            <span><?= escape($colour[0]) ?></span>
            <input type="color" id="theme_<?= $theme_owner ?>_<?= $key ?>" name="<?= $key ?>"
                   value="<?= escape($theme_current ? $theme_current['colours'][$key] : $colour[2]) ?>">
        </label>
    <?php endforeach; ?>
    <label for="theme_<?= $theme_owner ?>_serif">
        <span>Headline and text font (a <a href="https://fonts.google.com" target="_blank" rel="noopener">Google Font</a> name, blank for Newsreader)</span>
        <input type="text" id="theme_<?= $theme_owner ?>_serif" name="serif_font" maxlength="40" placeholder="Newsreader"
               value="<?= escape($theme_current['serif_font'] ?? "") ?>">
    </label>
    <label for="theme_<?= $theme_owner ?>_sans">
        <span>Label font: menus, dates, buttons (blank for Inter Tight)</span>
        <input type="text" id="theme_<?= $theme_owner ?>_sans" name="sans_font" maxlength="40" placeholder="Inter Tight"
               value="<?= escape($theme_current['sans_font'] ?? "") ?>">
    </label>
    <label for="theme_<?= $theme_owner ?>_css">
        <span>Extra CSS (optional)</span>
        <textarea id="theme_<?= $theme_owner ?>_css" name="css" rows="8" spellcheck="false" maxlength="<?= ThemeCss::MAX_LENGTH ?>"
                  placeholder=".header h1 { letter-spacing: 0.05em; }"><?= escape($theme_current['css'] ?? "") ?></textarea>
    </label>
    <details class="hint">
        <summary>What extra CSS can do</summary>
        <p>Only some parts of the page and some properties can be styled; anything else is left out
            when you save, and you'll be told what.</p>
        <p><strong>Parts:</strong> <?= escape(implode(", ", array_merge(ThemeCss::SELECTORS, $theme_owner == "blog" ? ThemeCss::BLOG_SELECTORS : array()))) ?></p>
        <p><strong>Properties:</strong> colours (#hex, or var(--ink), var(--paper), var(--accent)…), borders,
            border-radius, margins and padding, font-size, font-weight, font-style, letter-spacing,
            line-height, text-transform, text-align, text-decoration, font-family (var(--serif) or
            var(--sans)), and background-image with <code>url("https://…")</code>.</p>
        <p>Background images are checked when you save (PNG, JPEG, GIF or WebP, up to 2 MB, nothing but
            the image) and a copy is kept here, so visitors never load them from the other site.</p>
        <?php if ($theme_owner == "blog"): ?>
            <p>Google Fonts are loaded from Google, so visitors' browsers contact Google when one is set.</p>
        <?php endif; ?>
    </details>
    <button type="submit">Save theme</button>
</form>
<?php if ($theme_current): ?>
    <form action="/API/theme/save.php" method="post" class="theme-reset"
          data-confirm="Go back to the standard look? The colours, fonts, CSS and background images will be removed.">
        <?php CSRF_input(); ?>
        <input type="hidden" name="owner" value="<?= escape($theme_owner) ?>">
        <input type="hidden" name="reset" value="1">
        <button type="submit" class="secondary">Back to the standard look</button>
    </form>
<?php endif; ?>
