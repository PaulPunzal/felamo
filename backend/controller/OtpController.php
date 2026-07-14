<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include_once(__DIR__ . '/../db/db.php');
date_default_timezone_set('Asia/Manila');

class OtpController extends db_connect
{
    public function __construct()
    {
        $this->connect();
    }

    public function test()
    {
        echo "login";
    }

    public function StoreOTP($email, $user_type, $otpType, $otp)
    {
        $expirationDate = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        $sql = "INSERT INTO user_otps (email, user_type, otp_type, otp, expiration_date)
            VALUES (?, ?, ?, ?, ?)";

        $q = $this->conn->prepare($sql);
        $q->bind_param("sssss", $email, $user_type, $otpType, $otp, $expirationDate);

        if ($q->execute()) {
            return true;
        }

        return false;
    }
}
