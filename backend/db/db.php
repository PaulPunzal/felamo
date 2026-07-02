<?php
require_once __DIR__ . '/../config/env.php';

define("db_host", app_env('DB_HOST', 'localhost'));
define("db_user", app_env('DB_USER', 'devuser'));
define("db_pass", app_env('DB_PASS'));
define("db_name", app_env('DB_NAME', 'felamo'));


class db_connect
{
    public $host = db_host;
    public $user = db_user;
    public $pass = db_pass;
    public $name = db_name;
    public $conn;
    public $error;
    public $mysqli;

    public function __construct()
    {
        $this->connect();
    }

    public function connect()
    {
        try {
            $this->conn = new mysqli($this->host, $this->user, $this->pass, $this->name);
    
            // Check connection
            if ($this->conn->connect_error) {
                $this->error = "Connection failed: " . $this->conn->connect_error;
                return false;
            } else {
                return $this->conn;
            }
        } catch (\Throwable $th) {
            $this->error = "Connection error: " . $th->getMessage();
            return false;
        }
    }
    
}