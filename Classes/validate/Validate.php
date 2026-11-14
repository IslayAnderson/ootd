<?php

class Validate
{
    private $input;

    public function __construct($input = null)
    {
        $this->input = $input;
    }

    /**
     * Every value must be a non-empty string without markup
     *
     * @param $input string or array of strings
     * @return array state, and reason/content when it fails
     */
    public function basic($input = null)
    {
        if ($input === null) {
            $input = $this->input;
        }

        switch (gettype($input)) {
            case "string":
                if (trim($input) === "") {
                    return array("state" => false, "reason" => "empty", "content" => $input);
                } elseif ($input !== strip_tags($input)) {
                    return array("state" => false, "reason" => "markup not allowed", "content" => $input);
                } else {
                    return array("state" => true);
                }
            case "array":
                foreach ($input as $key => $value) {
                    $state = $this->basic((string)$value);
                    if (!$state['state']) {
                        return array("state" => false, "reason" => $state['reason'], "content" => $key);
                    }
                }
                return array("state" => true);
            default:
                return array("state" => false, "reason" => "unhandled type", "content" => $input);
        }

    }

    /**
     * @param array $request username, email, password
     * @return array state, and reason/content when it fails
     */
    public function signup($request)
    {
        //passwords can contain anything, they are only ever hashed
        $state = $this->basic(array_diff_key($request, array("password" => true)));
        if (!$state['state']) {
            return $state;
        }
        if (!filter_var($request['email'], FILTER_VALIDATE_EMAIL)) {
            return array("state" => false, "reason" => "invalid email", "content" => "email");
        }
        if (!preg_match('/^[A-Za-z0-9_.\-]{3,32}$/', $request['username'])) {
            return array("state" => false, "reason" => "username must be 3-32 letters, numbers, . _ or -", "content" => "username");
        }
        if (strlen($request['password']) < 8) {
            return array("state" => false, "reason" => "password must be at least 8 characters", "content" => "password");
        }
        return array("state" => true);
    }
}
