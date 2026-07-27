<?php
session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getActiveUserId() {
    return $_SESSION['user_id'] ?? null;
}

function getActiveUsername() {
    return $_SESSION['username'] ?? null;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /login.php');
        exit;
    }
}
