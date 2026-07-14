<?php
include_once(__DIR__ . '/../db/db.php');
date_default_timezone_set('Asia/Manila');

class TestController extends db_connect
{
    public function __construct()
    {
        $this->connect();
    }

    public function test()
    {
        echo "test";
    }
    
}
