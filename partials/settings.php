<?php ?>

<form action="/API/admin/settings.php" method="post" class="settings" enctype="multipart/form-data">
    <?php CSRF_input(); ?>
    <?php foreach (settings_editable() as $setting): ?>
        <label for="setting_<?= escape($setting->setting) ?>">
            <span><?= escape(SETTING_LABELS[$setting->setting] ?? ucfirst(str_replace("_", " ", $setting->setting))) ?></span>
            <?php if ($setting->type == "bool"): ?>
                <input type="checkbox" id="setting_<?= escape($setting->setting) ?>"
                       name="<?= escape($setting->setting) ?>" <?= $setting->value == 1 ? "checked" : "" ?>/>
            <?php elseif (count(setting_choices($setting->type)) > 0): ?>
                <select id="setting_<?= escape($setting->setting) ?>" name="<?= escape($setting->setting) ?>">
                    <?php foreach (setting_choices($setting->type) as $choice): ?>
                        <option value="<?= escape($choice) ?>"<?= $choice == $setting->value ? " selected" : "" ?>><?= escape(SETTING_CHOICE_LABELS[$setting->setting][$choice] ?? ucfirst($choice)) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php elseif ($setting->type == "int"): ?>
                <input type="number" min="0" step="1" id="setting_<?= escape($setting->setting) ?>"
                       name="<?= escape($setting->setting) ?>" value="<?= escape($setting->value) ?>"/>
            <?php elseif ($setting->type == "text"): ?>
                <textarea id="setting_<?= escape($setting->setting) ?>" name="<?= escape($setting->setting) ?>" rows="4"><?= escape($setting->value) ?></textarea>
            <?php else: ?>
                <input type="text" id="setting_<?= escape($setting->setting) ?>"
                       name="<?= escape($setting->setting) ?>" value="<?= escape($setting->value) ?>"/>
            <?php endif; ?>
        </label>
        <?php if ($setting->setting == "logo"): ?>
            <label for="logo_image" class="logo-upload">
                <span>Logo image (PNG, JPEG or WebP; choosing one switches the logo to it)</span>
                <?php if (setting_get("logo_media", "") !== ""): ?>
                    <span class="current"><?= site_logo() ?></span>
                <?php endif; ?>
                <input type="file" id="logo_image" name="logo_image" accept="image/png,image/jpeg,image/webp">
            </label>
        <?php endif; ?>
    <?php endforeach; ?>
    <button type="submit">Save settings</button>
</form>
