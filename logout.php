<?php
require_once 'includes/session.php';
require_once 'includes/config.php';

// Destroy session
session_destroy();

// Redirect to home
header('Location: ' . BASE_URL . '/index.php');
exit;

