<?php

/**
 * Media on the server's disk, in STORAGE_PATH (default <project>/storage, which .htaccess keeps private).
 * Files are only ever served through /media/<media_id>/<variant>, which checks who can see them.
 */
class StorageLocal implements Storage
{
    private $root;

    public function __construct($root = null)
    {
        $this->root = rtrim($root ?: ($_ENV['STORAGE_PATH'] ?? "") ?: $_SERVER['DOCUMENT_ROOT'] . "/storage", "/");
    }

    /**
     * @param string $key
     * @return string absolute path, keys can't climb out of the storage folder
     * @throws Exception
     */
    private function path($key): string
    {
        if (!preg_match('#^[A-Za-z0-9_\-]+(/[A-Za-z0-9_\-]+)*\.[a-z0-9]+$#', $key)) {
            throw new Exception('Bad media key');
        }
        return $this->root . "/" . $key;
    }

    public function put($key, $contents, $mime): void
    {
        $path = $this->path($key);
        if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0775, true)) {
            throw new Exception('Media folder not writable');
        }
        if (file_put_contents($path, $contents) === false) {
            throw new Exception('Media file not writable');
        }
    }

    public function get($key): ?string
    {
        try {
            $path = $this->path($key);
        } catch (Exception $e) {
            return null;
        }
        return is_file($path) ? file_get_contents($path) : null;
    }

    public function delete($key): void
    {
        try {
            $path = $this->path($key);
        } catch (Exception $e) {
            return;
        }
        if (is_file($path)) {
            unlink($path);
        }
        @rmdir(dirname($path));
    }
}
