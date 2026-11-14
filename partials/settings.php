<?php ?>

<form action="/API/admin/settings.php" method="post" class="settings">
    <?php CSRF_input(); ?>
    <?php foreach (settings_editable() as $setting): ?>
        <label for="setting_<?= escape($setting->setting) ?>">
            <span><?= escape(ucfirst(str_replace("_", " ", $setting->setting))) ?></span>
            <?php if ($setting->type == "bool"): ?>
                <input type="checkbox" id="setting_<?= escape($setting->setting) ?>"
                       name="<?= escape($setting->setting) ?>" <?= $setting->value == 1 ? "checked" : "" ?>/>
            <?php elseif (count(setting_choices($setting->type)) > 0): ?>
                <select id="setting_<?= escape($setting->setting) ?>" name="<?= escape($setting->setting) ?>">
                    <?php foreach (setting_choices($setting->type) as $choice): ?>
                        <option value="<?= escape($choice) ?>"<?= $choice == $setting->value ? " selected" : "" ?>><?= escape(ucfirst($choice)) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php elseif ($setting->type == "text"): ?>
                <textarea id="setting_<?= escape($setting->setting) ?>" name="<?= escape($setting->setting) ?>" rows="4"><?= escape($setting->value) ?></textarea>
            <?php else: ?>
                <input type="text" id="setting_<?= escape($setting->setting) ?>"
                       name="<?= escape($setting->setting) ?>" value="<?= escape($setting->value) ?>"/>
            <?php endif; ?>
        </label>
    <?php endforeach; ?>
    <button type="submit">Save settings</button>
</form>
