<?php
/**
 * Session-based auth helpers for the admin panel.
 * Include on every protected admin page and call requireLogin().
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Redirects to the login page if no admin is signed in.
 * Pass the relative path to login.php from the calling script's location.
 */
function requireLogin(string $loginPath = 'login.php'): void
{
    if (empty($_SESSION['admin_id'])) {
        header('Location: ' . $loginPath);
        exit;
    }
}

function loginAdmin(array $admin): void
{
    session_regenerate_id(true);
    $_SESSION['admin_id']    = $admin['id'];
    $_SESSION['admin_name']  = $admin['name'];
    $_SESSION['admin_email'] = $admin['email'];
    $_SESSION['admin_role']  = $admin['role'];
}

function logoutAdmin(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function currentAdmin(): ?array
{
    if (empty($_SESSION['admin_id'])) {
        return null;
    }

    return [
        'id'    => $_SESSION['admin_id'],
        'name'  => $_SESSION['admin_name'],
        'email' => $_SESSION['admin_email'],
        'role'  => $_SESSION['admin_role'],
    ];
}
