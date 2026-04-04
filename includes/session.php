<?php
session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getCurrentUser() {
    return $_SESSION['user'] ?? null;
}

function isAdmin() {
    return ($_SESSION['role'] ?? '') === 'admin';
}

function isCreator() {
    $role = $_SESSION['role'] ?? '';
    return $role === 'creator' || $role === 'admin';
}