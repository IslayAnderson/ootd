<?php
/**
 * Background job handlers, see Classes/jobs/Jobs.php. One function per job type: job_<type>($payload).
 */

/** days a deleted post's photo is kept before its files are removed */
const MEDIA_PURGE_AFTER_DAYS = 30;

/**
 * Remove image files nobody can see any more: photos of posts deleted more than 30 days ago,
 * and uploads that never got attached to anything (a failed post) after a day
 *
 * @param array $payload unused
 * @return void
 */
function job_media_purge($payload = array()): void
{
    $sql = "SELECT media.media_id FROM `media`
            LEFT JOIN `users` ON users.media_id = media.media_id
            LEFT JOIN `outfits` ON outfits.media_id = media.media_id
            LEFT JOIN `garments` ON garments.media_id = media.media_id
            WHERE media.deleted_at IS NULL AND users.user_id IS NULL
              AND (
                (outfits.outfit_num IS NULL AND garments.garment_num IS NULL AND media.timestamp < DATE_SUB(NOW(), INTERVAL 1 DAY))
                OR outfits.deleted_at < DATE_SUB(NOW(), INTERVAL " . MEDIA_PURGE_AFTER_DAYS . " DAY)
                OR garments.deleted_at < DATE_SUB(NOW(), INTERVAL " . MEDIA_PURGE_AFTER_DAYS . " DAY)
              )
            LIMIT 500";
    $db = new Mysql();
    $rows = $db->Fetch($sql, null);
    if ($rows[0] == '00000') {
        return;
    }
    foreach ($rows as $row) {
        Media::remove($row->media_id);
    }
}

/**
 * Send one queued email (Mailer::queue)
 *
 * @param array $payload to, subject, body
 * @return void
 * @throws Exception retried by the job runner when sending fails
 */
function job_mail($payload)
{
    Mailer::send($payload['to'] ?? "", $payload['subject'] ?? "", $payload['body'] ?? "");
}
