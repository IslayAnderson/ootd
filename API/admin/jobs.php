<?php
require $_SERVER['DOCUMENT_ROOT'] . "/init.php";

try {
    CSRF_validate($_POST['csrf'] ?? null);

    if (!is_admin()) {
        http_response_code(403);
        throw new Exception('Admins only');
    }
    Jobs::retry_failed();

    header('Location: /admin#jobs');
    exit();
} catch (Exception $e) {
    echo json_encode(array(
        "state" => "brokey",
        "reason" => $e->getMessage()
    ));
}
