<?php
require $_SERVER['DOCUMENT_ROOT'] . "/init.php";

try {
    if (!is_admin()) {
        http_response_code(403);
        throw new Exception('Admins only');
    }
    CSRF_validate($_POST['csrf'] ?? null);
    $cloud_id = (string)($_POST['cloud_id'] ?? "");
    $action = (string)($_POST['action'] ?? "");
    switch ($action) {
        case "retry":
            CloudBlog::retry($cloud_id);
            break;
        case "suspend":
        case "resume":
            CloudBlog::set_active($cloud_id, $action == "resume");
            break;
        case "delete":
            $blog = CloudBlog::get($cloud_id);
            if (!$blog || trim((string)($_POST['confirm'] ?? "")) !== $blog->domain) {
                throw new Exception("Type the blog's address to confirm");
            }
            CloudBlog::delete($cloud_id);
            break;
        default:
            throw new Exception('Unknown action');
    }
    header('Location: /admin?cloud=' . $action . '#cloud');
    exit();
} catch (Exception $e) {
    echo json_encode(array(
        "state" => "brokey",
        "reason" => $e->getMessage()
    ));
}
