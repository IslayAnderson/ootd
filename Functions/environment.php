<?php
/**
 * Load .env into $_ENV before Composer's autoloader (and phpdotenv) is available.
 * Not parse_ini_file(): it rejects or truncates values containing = ; ! or quotes, which real
 * database passwords do. Each line is KEY=value, split on the first =, optional surrounding quotes.
 */
function environment_load($path): void
{
    if (!is_readable($path)) {
        return;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line == "" || $line[0] == "#" || strpos($line, "=") === false) {
            continue;
        }
        list($key, $value) = explode("=", $line, 2);
        $key = trim($key);
        $value = trim($value);
        if (strlen($value) > 1 && ($value[0] == '"' || $value[0] == "'") && substr($value, -1) == $value[0]) {
            $value = substr($value, 1, -1);
        }
        $_ENV[$key] = $value;
    }
}

//.env doesn't exist until install_actions.php writes it
environment_load($_SERVER['DOCUMENT_ROOT'] . '/.env');
