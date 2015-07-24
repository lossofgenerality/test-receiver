<?php

require_once '../../../wp-config.php';

$message = urldecode(file_get_contents('php://input'));
error_log("LoG: $message");

$db = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8', DB_USER, DB_PASSWORD);
$stmt = $db->prepare("INSERT INTO test_results (message, submitted, ip) VALUES (?, ?, ?)");

date_default_timezone_set('America/Toronto');
$when = new DateTime();

if ($stmt->execute(array($message, $when->format("Y-m-d H:i:s"), $_SERVER['REMOTE_ADDR']))) {
    $headers = $headers = 'From: no-reply@lossofgenerality.com' . "\r\n";
    $headers .= 'Cc: anna@lossofgenerality.com' . "\r\n";
    mail('symbolicexams@gmail.com', 'message from lossofgenerality', "$message\n" . $_SERVER['REMOTE_ADDR'], $headers);
    echo "OK";
} else {
    $err = $stmt->errorInfo();
    error_log("LoG: " . $err[2]);
    echo "ERR";
}
