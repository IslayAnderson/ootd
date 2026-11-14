<?php
require $_SERVER['DOCUMENT_ROOT'] . "/init.php";

try {
    if (!is_admin()) {
        http_response_code(403);
        throw new Exception('Admins only');
    }
    CSRF_validate($_POST['csrf'] ?? null);

    //only settings that are shown in /admin can be changed from here
    foreach (settings_editable() as $setting) {
        if ($setting->type == "bool") {
            setting_set($setting->setting, isset($_POST[$setting->setting]) ? 1 : 0);
        } elseif (isset($_POST[$setting->setting])) {
            setting_set($setting->setting, mb_substr((string)$_POST[$setting->setting], 0, 100));
        }
    }

    header('Location: /admin#settings');
    exit();
} catch (Exception $e) {
    echo json_encode(array(
        "state" => "brokey",
        "reason" => $e->getMessage()
    ));
}
