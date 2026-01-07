<?php
$host = "smtp.gmail.com";
$port = 587;
$timeout = 10;

$fp = @fsockopen($host, $port, $errno, $errstr, $timeout);

if (!$fp) {
    echo "❌ Socket connect failed: $errstr ($errno)";
} else {
    echo "✅ Socket connected to $host:$port";
    fclose($fp);
}
