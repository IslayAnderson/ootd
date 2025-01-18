<?php


class Mysql
{
    private $host;
    private $user;
    private $password;
    private $schema;
    private $connection;

    public function __construct()
    {

        $this->host = $_ENV['DB_HOST'];
        $this->user = $_ENV['DB_USER'];
        $this->password = $_ENV['DB_PASS'];
        $this->schema = $_ENV['DB_SCHEMA'];
        $this->connection = $this->BuildConnection();
    }

    private function BuildConnection()
    {
        return new PDO("mysql:host=" . $this->host . ";mysql:charset=utf8mb4;" . "dbname=" . $this->schema, $this->user, $this->password);
    }

    public function Fetch($sql, $params = null)
    {
        $con = $this->connection->prepare($sql);

        if ($params != null) {
            foreach ($params as $key => $val) {
                $con->bindValue($key, $val, PDO::PARAM_STR);
            }
        }

        $con->execute($params);

        $tables = $con->fetchAll(PDO::FETCH_OBJ);
        if (!empty($tables)) {
            return $tables;
        } else {
            return $con->errorInfo();
        }
    }

    public function DBOps($sql, $params)
    {
        return $this->Fetch($sql, $params);
    }

    public function lastInsertId($name = NULL)
    {

        return $this->connection->lastInsertId($name);

    }


}