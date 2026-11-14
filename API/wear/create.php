<?php
require $_SERVER['DOCUMENT_ROOT'] . "/init.php";

try {
    CSRF_validate($_POST['csrf'] ?? null);

    $insights = new Insights($_SESSION['user_id']);
    $insights->log_wear($_POST['outfit_num'] ?? null, $_POST['garment_num'] ?? null);

    //back to wherever the button was pressed
    $back = parse_url($_SERVER['HTTP_REFERER'] ?? "/insights", PHP_URL_PATH) ?: "/insights";
    header('Location: ' . $back . '?posted=wear');
    exit();
} catch (Exception $e) {
    echo json_encode(array(
        "state" => "brokey",
        "reason" => $e->getMessage()
    ));
}
