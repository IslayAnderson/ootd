<?php
/**
 *
 ** Wear tracking and the numbers on /insights
 *
 * @since 0.5.0
 *
 */


class Insights
{
    /**
     * @var integer
     */
    public $user_id;

    /**
     * @param $user_id
     */
    public function __construct($user_id)
    {
        $this->user_id = $user_id;
    }

    /**
     * Log today's wear of an outfit (and each garment in it) or of a single garment, once per day
     *
     * @param int|null $outfit_num
     * @param int|null $garment_num
     * @return void
     * @throws Exception
     */
    public function log_wear($outfit_num = null, $garment_num = null)
    {
        $db = new Mysql();

        if (!empty($outfit_num)) {
            $params = array(":a" => (int)$outfit_num, ":b" => $this->user_id);
            $sql = "SELECT `garments` FROM `outfits` WHERE `outfit_num` = :a AND `user` = :b";
            $row = $db->Fetch($sql, $params);
            if ($row[0] == '00000') {
                throw new Exception('Outfit not found');
            }

            $this->insert_wear((int)$outfit_num, null);
            foreach (json_decode((string)$row[0]->garments) ?: array() as $outfit_garment) {
                $this->insert_wear((int)$outfit_num, (int)$outfit_garment);
            }
        } elseif (!empty($garment_num)) {
            if (count(Post\Garment::owned_garments($this->user_id, array($garment_num))) == 0) {
                throw new Exception('Garment not found');
            }
            $this->insert_wear(null, (int)$garment_num);
        } else {
            throw new Exception('Nothing to wear');
        }
    }

    /**
     * @param int|null $outfit_num
     * @param int|null $garment_num
     * @return void
     */
    private function insert_wear($outfit_num, $garment_num)
    {
        $params = array(":a" => $this->user_id, ":b" => $outfit_num, ":c" => $garment_num);
        $sql = "SELECT `wear_num` FROM `wears` WHERE `user` = :a AND `worn_on` = CURDATE()
                AND `outfit_num` <=> :b AND `garment_num` <=> :c";

        $db = new Mysql();
        $row = $db->Fetch($sql, $params);
        if ($row[0] != '00000') {
            return;
        }

        $sql = "INSERT INTO `wears` (`user`, `outfit_num`, `garment_num`, `worn_on`) VALUES (:a, :b, :c, CURDATE())";
        $db->Fetch($sql, $params);
    }

    /**
     * @param int $months
     * @return array labels (short month names) and series (outfits worn per month), oldest first
     */
    public function wears_per_month($months = 12): array
    {
        $params = array(":a" => $this->user_id, ":b" => (int)$months - 1);
        $sql = "SELECT DATE_FORMAT(`worn_on`, '%Y-%m') AS month, COUNT(DISTINCT `worn_on`, `outfit_num`) AS worn
                FROM `wears`
                WHERE `user` = :a AND `outfit_num` IS NOT NULL AND `garment_num` IS NULL
                  AND `worn_on` >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL :b MONTH)
                GROUP BY month";

        $db = new Mysql();
        $row = $db->Fetch($sql, $params);

        $counts = array();
        if ($row[0] != '00000') {
            foreach ($row as $month) {
                $counts[$month->month] = (int)$month->worn;
            }
        }

        $labels = array();
        $series = array();
        for ($i = $months - 1; $i >= 0; $i--) {
            $month = strtotime(date("Y-m-01") . " -" . $i . " months");
            $labels[] = date("M", $month);
            $series[] = $counts[date("Y-m", $month)] ?? 0;
        }

        return array("labels" => $labels, "series" => $series);
    }

    /**
     * @param int $limit
     * @return array garments with a `worn` count, most worn first
     */
    public function most_worn($limit = 5): array
    {
        $params = array(":a" => $this->user_id);
        $sql = "SELECT garments.garment_id, garments.garment_name, garments.image, COUNT(DISTINCT wears.worn_on) AS worn
                FROM `wears` JOIN `garments` ON wears.garment_num = garments.garment_num
                WHERE wears.user = :a
                GROUP BY garments.garment_num
                ORDER BY worn DESC, garments.garment_name
                LIMIT " . (int)$limit;

        $db = new Mysql();
        $row = $db->Fetch($sql, $params);

        return $row[0] != '00000' ? $row : array();
    }

    /**
     * @param int $limit
     * @return array garments with `last_worn` (null if never), least recently worn first
     */
    public function least_recently_worn($limit = 5): array
    {
        $params = array(":a" => $this->user_id);
        $sql = "SELECT garments.garment_id, garments.garment_name, garments.description, garments.image,
                       garments.timestamp AS added, MAX(wears.worn_on) AS last_worn
                FROM `garments` LEFT JOIN `wears` ON wears.garment_num = garments.garment_num
                WHERE garments.user_id = :a
                GROUP BY garments.garment_num
                ORDER BY COALESCE(MAX(wears.worn_on), DATE(garments.timestamp)) ASC
                LIMIT " . (int)$limit;

        $db = new Mysql();
        $row = $db->Fetch($sql, $params);

        return $row[0] != '00000' ? $row : array();
    }

    /**
     * @param $garment_num
     * @return string|null date the garment was last worn
     */
    public static function last_worn($garment_num)
    {
        $params = array(":a" => (int)$garment_num);
        $sql = "SELECT MAX(`worn_on`) AS last_worn FROM `wears` WHERE `garment_num` = :a";

        $db = new Mysql();
        $row = $db->Fetch($sql, $params);

        return $row[0] != '00000' ? $row[0]->last_worn : null;
    }

    /**
     * @param $date
     * @return string "3 months", "12 days", "today"
     */
    public static function time_since($date): string
    {
        $diff = (new DateTime(date("Y-m-d", strtotime($date))))->diff(new DateTime(date("Y-m-d")));
        if ($diff->y > 0) {
            return $diff->y . ($diff->y == 1 ? " year" : " years");
        } elseif ($diff->m > 0) {
            return $diff->m . ($diff->m == 1 ? " month" : " months");
        } elseif ($diff->d > 0) {
            return $diff->d . ($diff->d == 1 ? " day" : " days");
        }
        return "today";
    }

    /**
     * The pinned garment card, the least recently worn garment
     *
     * @return void
     */
    public function render_daily_insight(): void
    {
        $garments = $this->least_recently_worn(1);
        if (count($garments) == 0) {
            ?>
            <p>Add some garments to your wardrobe to get insights.</p>
            <?php
            return;
        }
        $garment = $garments[0];
        ?>
        <div class="insight_pin">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                <path d="M6.166 16.943l1.4 1.4-4.622 4.657h-2.944l6.166-6.057zm11.768-6.012c2.322-2.322 4.482-.457 6.066-1.931l-8-8c-1.474 1.584.142 3.494-2.18 5.817-3.016 3.016-4.861-.625-10.228 4.742l9.6 9.6c5.367-5.367 1.725-7.211 4.742-10.228z"></path>
            </svg>
            <a href="/garment/<?= escape($garment->garment_id) ?>">
                <img loading="lazy"
                     src="<?= image_bin_encode($garment->image) ?>"
                     alt="<?= escape($garment->description) ?>"
                >
            </a>
        </div>
        <h3>Daily insights:</h3>
        <?php if (empty($garment->last_worn) && self::time_since($garment->added) == "today"): ?>
            <p>You haven't worn &ldquo;<?= escape($garment->garment_name) ?>&rdquo; yet.</p>
        <?php elseif (empty($garment->last_worn)): ?>
            <p>You haven't worn &ldquo;<?= escape($garment->garment_name) ?>&rdquo; since you added it <?= escape(self::time_since($garment->added)) ?> ago.</p>
        <?php elseif (self::time_since($garment->last_worn) == "today"): ?>
            <p>You've worn everything recently, &ldquo;<?= escape($garment->garment_name) ?>&rdquo; included.</p>
        <?php else: ?>
            <p>You haven't worn &ldquo;<?= escape($garment->garment_name) ?>&rdquo; in <?= escape(self::time_since($garment->last_worn)) ?>.</p>
        <?php endif;
    }
}
