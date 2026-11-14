<?php
// Unit tests load only the code they exercise; nothing here touches the database.
$root = dirname(__DIR__);
require $root . "/vendor/autoload.php";
foreach (array("visibility", "guest", "url", "environment", "escape", "settings", "version", "frames", "theme") as $file) {
    if (file_exists($root . "/Functions/" . $file . ".php")) {
        require_once $root . "/Functions/" . $file . ".php";
    }
}
require_once $root . "/Classes/media/Storage.php";
require_once $root . "/Classes/media/StorageS3.php";
require_once $root . "/Classes/social/Follow.php";
require_once $root . "/Classes/federation/HttpSignature.php";
require_once $root . "/Classes/federation/SafeHttp.php";
require_once $root . "/Classes/federation/Federation.php";
require_once $root . "/Classes/federation/Inbox.php";
require_once $root . "/Classes/federation/RemoteActor.php";
require_once $root . "/Classes/federation/RemotePost.php";
require_once $root . "/Classes/mail/Mailer.php";
require_once $root . "/Classes/theme/ThemeCss.php";
require_once $root . "/Classes/theme/ThemeImage.php";
