<?php
//.env doesn't exist until install_actions.php writes it
if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/.env')) {
    foreach (parse_ini_file($_SERVER['DOCUMENT_ROOT'] . '/.env') ?: array() as $key => $value) {
        $_ENV[$key] = $value;
    }
}
