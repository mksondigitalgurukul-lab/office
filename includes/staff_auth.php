<?php
/**
 * Session-based auth helpers for the staff-facing pages.
 *
 * Uses its own session name (separate cookie from the admin session in
 * includes/auth.php) so an admin and a staff account never share a
 * session store, even if both are logged in from the same browser.
 * Include on every protected staff page and call requireStaffLogin().
 */

if (session_status() === PHP_SESSION_NONE) {
    session_name('office_staff_sess');
    session_start();
}

/**
 * Redirects to the staff login page if no staff member is signed in.
 * Pass the relative path to login.php from the calling script's location.
 */
function requireStaffLogin(string $loginPath = 'login.php'): void
{
    if (empty($_SESSION['staff_id'])) {
        header('Location: ' . $loginPath);
        exit;
    }
}

function loginStaff(array $staff): void
{
    session_regenerate_id(true);
    $_SESSION['staff_id']    = $staff['id'];
    $_SESSION['staff_name']  = $staff['full_name'];
    $_SESSION['staff_email'] = $staff['email'];
}

function logoutStaff(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function currentStaff(): ?array
{
    if (empty($_SESSION['staff_id'])) {
        return null;
    }

    return [
        'id'    => $_SESSION['staff_id'],
        'name'  => $_SESSION['staff_name'],
        'email' => $_SESSION['staff_email'],
    ];
}
