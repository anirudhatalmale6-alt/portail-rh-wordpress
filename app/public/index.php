<?php
/**
 * Point d'entree unique. Tout passe par ici : il n'y a pas un seul autre
 * fichier .php accessible depuis le web, donc pas de page oubliee qui
 * n'aurait pas verifie les droits.
 */

declare(strict_types=1);

$racine = dirname(__DIR__);

require $racine . '/src/noyau.php';

installe_gestion_erreurs();

require $racine . '/src/i18n.php';
require $racine . '/src/vue.php';
require $racine . '/src/coffre.php';
require $racine . '/src/auth.php';
require $racine . '/src/balisage.php';
require $racine . '/src/schema.php';
foreach (glob($racine . '/src/domaine/*.php') as $f) require $f;
foreach (glob($racine . '/src/routes/*.php') as $f) require $f;

/* --- Session PHP : elle ne sert qu'au jeton anti-CSRF et aux messages
 *     flash. L'identite, elle, vient de la table `sessions` et du cookie
 *     rh_session : une session PHP sur un hebergement mutualise se stocke
 *     dans un repertoire partage entre les comptes. */
session_set_cookie_params([
    'lifetime' => 0, 'path' => '/', 'httponly' => true,
    'secure' => (bool) cfg('cookie_secure'), 'samesite' => 'Lax',
]);
session_name('rh_app');
session_start();

/* --- En-tetes de securite ------------------------------------------- */
$csp = "default-src 'self'; img-src 'self' data:; style-src 'self'; "
     . "script-src 'self'; frame-ancestors 'none'; form-action 'self'; "
     . "base-uri 'none'; object-src 'none'";
header('Content-Security-Policy: ' . $csp);
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('X-Frame-Options: DENY');
// Un portail RH n'a aucune raison d'etre indexe. Meme la page carrieres
// publique reste hors index tant que le domaine n'est pas renseigne : une
// offre indexee sur un domaine provisoire y reste des mois.
header('X-Robots-Tag: noindex, nofollow');
if (cfg('cookie_secure')) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

/* --- Routage --------------------------------------------------------- */
$uri = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$uri = '/' . trim($uri, '/');
if ($uri !== '/') $uri = rtrim($uri, '/');
$methode = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

// La base peut ne pas exister encore. On le detecte une fois, proprement,
// au lieu de laisser une exception PDO remonter sur chaque page.
try {
    qval('SELECT 1 FROM reglages LIMIT 1');
} catch (Throwable) {
    if (!str_starts_with($uri, '/installation')) {
        http_response_code(503);
        rendre('installation_requise', []);
        exit;
    }
}

$trouve = false;
foreach (routes() as [$motif, $verbes, $fonction, $permission]) {
    if (!preg_match($motif, $uri, $m)) continue;
    $trouve = true;
    if (!in_array($methode, (array) $verbes, true)) continue;

    if ($permission !== null) exige($permission);
    if ($methode === 'POST') verifie_csrf();

    array_shift($m);
    $fonction(...$m);
    exit;
}

http_response_code(404);
rendre('erreur', ['code' => 404, 'message' => t('refus_introuvable')]);
