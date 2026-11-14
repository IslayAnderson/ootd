<?php
const IMAGE_UPLOAD_MAX_BYTES = 8388608; //8MB, also check upload_max_filesize / post_max_size in php.ini
const IMAGE_UPLOAD_TYPES = array("image/jpeg", "image/png", "image/webp", "image/gif");

/**
 * Validate an uploaded image and return its contents for a BLOB column
 *
 * @param $file entry from $_FILES
 * @param bool $required
 * @return string|null binary image, null when optional and nothing was uploaded
 * @throws Exception
 */
function image_upload($file, $required = true): ?string
{
    if (empty($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        if ($required) {
            throw new Exception('Image missing');
        }
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        throw new Exception('Image upload failed');
    }

    if ($file['size'] > IMAGE_UPLOAD_MAX_BYTES) {
        throw new Exception('Image too large');
    }

    $fi = new finfo(FILEINFO_MIME_TYPE);
    if (!in_array($fi->file($file['tmp_name']), IMAGE_UPLOAD_TYPES, true)) {
        throw new Exception('Image must be a jpeg, png, webp or gif');
    }

    return file_get_contents($file['tmp_name']);
}
