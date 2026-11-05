<?php

require_once __DIR__ . '/../config/config.php';

session_start();
$roleLandingPages = [
    1 => 'pages/admin/dashboard.html',
    2 => 'pages/doctor/doctor.html',
    3 => 'pages/nurse/nurse.html',
    4 => 'pages/cashier/cashier.html',
    5 => 'pages/department/department.html',
];
if (!empty($_SESSION['user'])) {
    $roleId = (int)($_SESSION['user']['role_id'] ?? 0);
    header('Location: ' . BASE_URL . '/' . ($roleLandingPages[$roleId] ?? 'index.html'));
    exit;
}

if (!defined('STAFF_LOGIN_ROLE_ID') || !in_array(STAFF_LOGIN_ROLE_ID, [1, 2, 3, 4, 5], true)) {
    header('Location: ' . BASE_URL . '/index.html');
    exit;
}

define('APP_RENDERER', true);
$loginRoleId = STAFF_LOGIN_ROLE_ID;
require __DIR__ . '/../controllers/page-templates/login.php';
