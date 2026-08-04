<?php
/**
 * Digital Ali Pro OMS — Database configuration
 *
 * Placeholders only. On the live cPanel server, edit the values below
 * with the real database credentials created in cPanel > MySQL Databases.
 * Do not commit real production credentials to this file in git history.
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');

// App-wide constants
define('APP_NAME', 'Digital Ali Pro OMS');
define('APP_URL', 'https://www.digitalalipro.in/office');

// PHP session/error settings suitable for shared hosting
date_default_timezone_set('Asia/Kolkata');
