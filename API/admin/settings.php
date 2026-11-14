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
            setting_set($setting->setting, setting_clean($setting->type, $_POST[$setting->setting], $setting->value));
        }
    }

    //a new logo image replaces the old one and becomes the logo
    if (($_FILES['logo_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $previous = (string)setting_get("logo_media", "");
        $media_id = Media::store_upload($_FILES['logo_image'], $_SESSION['user_id'], "Logo of " . instance_name());
        setting_set("logo_media", $media_id, "media");
        setting_set("logo", "image");
        if ($previous !== "" && $previous !== $media_id) {
            Media::remove($previous);
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
