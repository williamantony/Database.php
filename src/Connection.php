<?php

namespace WA\Database;

class Connection
{
    public $mysqli;

    private $db_host;
    private $db_port;
    private $db_username;
    private $db_password;
    private $db_name;

    function __construct()
    {
        $this->db_host = getenv('DB_HOST');
        $this->db_port = getenv('DB_PORT');
        $this->db_username = getenv('DB_USERNAME');
        $this->db_password = getenv('DB_PASSWORD');
        $this->db_name = getenv('DB_NAME');
        
        $this->mysqli = new \mysqli(
            $this->db_host,
            $this->db_username,
            $this->db_password,
            $this->db_name,
            $this->db_port,
        );

        if ($this->mysqli->connect_error)
        {
            die('Connect Error ('
                .($this->mysqli->connect_errno).') '
                .($this->mysqli->connect_error));
        }
    }

    public function setDatabase($db_name)
    {
        $this->db_name = $db_name;
    }
}
