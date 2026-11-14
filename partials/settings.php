<?php ?>

<form action="/API/admin/settings.php" method="post" class="settings">
    <?php CSRF_input(); ?>
    <?php foreach (settings_editable() as $setting): ?>
        <label for="setting_<?= escape($setting->setting) ?>">
            <span><?= escape(ucfirst(str_replace("_", " ", $setting->setting))) ?></span>
            <?php if ($setting->type == "bool"): ?>
                <input type="checkbox" id="setting_<?= escape($setting->setting) ?>"
                       name="<?= escape($setting->setting) ?>" <?= $setting->value == 1 ? "checked" : "" ?>/>
            <?php else: ?>
                <input type="text" id="setting_<?= escape($setting->setting) ?>"
                       name="<?= escape($setting->setting) ?>" value="<?= escape($setting->value) ?>"/>
            <?php endif; ?>
        </label>
    <?php endforeach; ?>
    <button type="submit">Save settings</button>
</form>
