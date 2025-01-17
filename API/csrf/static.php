<?php
require $_SERVER['DOCUMENT_ROOT']."/init.php";

return CSRF_validate($_POST['token']);