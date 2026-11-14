<?php
require $_SERVER['DOCUMENT_ROOT'] . "/init.php";

try {
    CSRF_validate($_POST['csrf'] ?? null);

    $user = new User($_SESSION['user_id']);
    $path = Export::build($_SESSION['user_id']);

    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9_.-]/', '', $user->username) . '-ootd-' . date("Y-m-d") . '.zip"');
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: no-store');
    readfile($path);
    unlink($path);
    exit();
} catch (Exception $e) {
    echo json_encode(array(
        "state" => "brokey",
        "reason" => $e->getMessage()
    ));
}
