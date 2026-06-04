<?php
const DUREE_SESSION = 10 * 3600; // 10 heures en secondes

function initialiserSession(): void
{
    ini_set('session.gc_maxlifetime', DUREE_SESSION);

    session_set_cookie_params([
        'lifetime' => DUREE_SESSION,
        'path'     => '/',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);

    session_start();
    verifierExpiration();
}

function verifierExpiration(): void
{
    if (isset($_SESSION['derniere_activite'])) {
        if ((time() - $_SESSION['derniere_activite']) > DUREE_SESSION) {
            detruireSession();
            header('Location: /login.php?raison=expiration');
            exit;
        }
    }
    $_SESSION['derniere_activite'] = time();
}

function detruireSession(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

// Utilisation
//initialiserSession();
