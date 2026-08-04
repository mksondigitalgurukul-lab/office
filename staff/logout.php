<?php
require_once __DIR__ . '/../includes/staff_auth.php';

logoutStaff();
header('Location: login.php');
exit;
