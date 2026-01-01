<?php
// /Business_only/config.php

class Config {
    private PDO $dbh;

    // DB
    private string $server   = "localhost";
    private string $username = "root";
    private string $password = "root";
    private string $dbname   = "gospel";

    // SMTP (Gmail)
    public string $SMTP_HOST = "smtp.gmail.com";
    public int    $SMTP_PORT = 587;
    public string $SMTP_USER = "YOUR_GMAIL@gmail.com";
    public string $SMTP_PASS = "YOUR_GMAIL_APP_PASSWORD"; // Gmail App Password
    public string $SMTP_FROM = "YOUR_GMAIL@gmail.com";
    public string $SMTP_FROM_NAME = "Gospel App";

    // When receiver is 'Admin', we email this address:
    public string $ADMIN_ALERT_EMAIL = "YOUR_GMAIL@gmail.com";

    public function __construct()
    {
        try {
            $this->dbh = new PDO(
                "mysql:host={$this->server};dbname={$this->dbname};charset=utf8mb4",
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        } catch (PDOException $e) {
            die("Database could not be connected: " . $e->getMessage());
        }
    }

    public function pdo(): PDO
    {
        return $this->dbh;
    }
}
