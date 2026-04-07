<?php
require_once 'includes/session.php';

// Destroy session
session_destroy();

// Redirect to home
header('Location: /~u202202670/vaultgg/index.php');
exit;

