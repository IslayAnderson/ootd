<?php
/**
 *
 ** Invite requests from /signup, approved by an admin into a one-use code for /invite
 *
 * @since 0.4.0
 *
 */


class Invite
{
    /**
     * @param $email
     * @return void
     * @throws Exception
     */
    public function request_invite($email)
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email');
        }

        //quietly ignore repeats and existing users so the form doesn't reveal who has an account
        $user = new User();
        if ($user->check_exists($email)['email'] || $this->find_by_email($email)) {
            return;
        }

        $params = array(':a' => $email);
        $sql = "INSERT INTO `invites` (`email`, `state`) VALUES (:a, 'requested')";

        $db = new Mysql();
        $db->Fetch($sql, $params);
    }

    /**
     * @param $email
     * @return object|null open (requested or approved) invite for the email
     */
    public function find_by_email($email)
    {
        $params = array(':a' => $email);
        $sql = "SELECT * FROM `invites` WHERE `email` = :a AND `state` IN ('requested', 'approved')";

        $db = new Mysql();
        $row = $db->Fetch($sql, $params);

        return $row[0] != '00000' ? $row[0] : null;
    }

    /**
     * @param $code
     * @return object|null approved, unused invite
     */
    public function find_by_code($code)
    {
        if (empty($code)) {
            return null;
        }

        $params = array(':a' => $code);
        $sql = "SELECT * FROM `invites` WHERE `code` = :a AND `state` = 'approved'";

        $db = new Mysql();
        $row = $db->Fetch($sql, $params);

        return $row[0] != '00000' ? $row[0] : null;
    }

    /**
     * @param $invite_num
     * @return string invite code
     */
    public function approve($invite_num)
    {
        $code = md5(id_gen());

        $params = array(':a' => $code, ':b' => (int)$invite_num);
        $sql = "UPDATE `invites` SET `code` = :a, `state` = 'approved' WHERE `invite_num` = :b AND `state` = 'requested'";

        $db = new Mysql();
        $db->Fetch($sql, $params);

        $invite = $db->Fetch("SELECT `email` FROM `invites` WHERE `invite_num` = :a AND `code` = :b", array(':a' => (int)$invite_num, ':b' => $code));
        if ($invite[0] != '00000') {
            Mailer::queue($invite[0]->email, "Your invite to " . instance_name(),
                "Your request to join " . instance_name() . " has been approved.\n\n"
                . "Finish signing up here:\n" . absolute_url("/invite?code=" . $code) . "\n");
        }

        return $code;
    }

    /**
     * @param $invite_num
     * @return void
     */
    public function decline($invite_num)
    {
        $params = array(':a' => (int)$invite_num);
        $sql = "UPDATE `invites` SET `code` = NULL, `state` = 'declined' WHERE `invite_num` = :a AND `state` IN ('requested', 'approved')";

        $db = new Mysql();
        $db->Fetch($sql, $params);
    }

    /**
     * Invite someone directly from /admin
     *
     * @param $email
     * @return void
     * @throws Exception
     */
    public function create_approved($email)
    {
        $this->request_invite($email);
        $invite = $this->find_by_email($email);
        if (!$invite) {
            throw new Exception('That email already has an account');
        }
        if ($invite->state == 'requested') {
            $this->approve($invite->invite_num);
        }
    }

    /**
     * @param $code
     * @param $user_id
     * @return void
     */
    public function mark_used($code, $user_id)
    {
        $params = array(':a' => (int)$user_id, ':b' => $code);
        $sql = "UPDATE `invites` SET `state` = 'used', `used_by` = :a WHERE `code` = :b AND `state` = 'approved'";

        $db = new Mysql();
        $db->Fetch($sql, $params);
    }

    /**
     * @return array open invites, newest first
     */
    public static function open_invites(): array
    {
        $sql = "SELECT * FROM `invites` WHERE `state` IN ('requested', 'approved') ORDER BY `timestamp` DESC";

        $db = new Mysql();
        $row = $db->Fetch($sql, null);

        return $row[0] != '00000' ? $row : array();
    }
}
