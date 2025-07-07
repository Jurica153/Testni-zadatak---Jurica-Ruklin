<?php
session_start();

$host = 'localhost';
$db = 'newsletter_db';
$user = 'root'; 
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    error_log("Greška pri spajanju na bazu podataka: " . $e->getMessage());
    die("Došlo je do greške pri spajanju na bazu podataka. Molimo pokušajte kasnije.");
}

define('BASE_URL', rtrim('/user_newsletter_dashboard', '/'));
function redirect($url) {
    $clean_url = '/' . ltrim($url, '/');
    header("Location: " . BASE_URL . $clean_url);
    exit();
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function is_admin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function require_admin_login() {
    if (!is_logged_in()) {
        redirect('/login.php?error=access_denied');
    } elseif (!is_admin()) {
        redirect('/index.php?error=unauthorized_access');
    }
}