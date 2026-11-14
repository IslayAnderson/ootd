<?php

/**
 * Where uploaded media files live. Pick a driver with MEDIA_DRIVER in .env, see media_storage().
 * Files are named by key, e.g. "<media_id>/display.jpg".
 */
interface Storage
{
    /**
     * @param string $key
     * @param string $contents
     * @param string $mime
     * @return void
     * @throws Exception when the file can't be written
     */
    public function put($key, $contents, $mime): void;

    /**
     * @param string $key
     * @return string|null file contents, null when it doesn't exist
     */
    public function get($key): ?string;

    /**
     * @param string $key
     * @return void
     */
    public function delete($key): void;
}
