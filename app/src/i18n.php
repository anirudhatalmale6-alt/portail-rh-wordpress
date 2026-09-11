<?php
/**
 * Traduction.
 *
 * Les fichiers de langue portent EXACTEMENT les memes cles. Un test de la
 * suite compare les listes et echoue si l'une derive : sans lui, une cle
 * ajoutee en francais s'affiche en anglais sous la forme brute
 * « conges_solde_restant », et personne ne le voit avant un client.
 *
 * Aucune traduction automatique. Un texte RH mal traduit engage
 * l'employeur : « conge sans solde » et « unpaid leave » ne recouvrent pas
 * les memes droits selon le pays.
 */

declare(strict_types=1);

function langue(): string
{
    static $l = null;
    if ($l !== null) return $l;

    $dispo = cfg('langues');
    // 1. le parametre explicite, qui gagne toujours et qui est memorise
    $p = $_GET['lang'] ?? null;
    if ($p && in_array($p, $dispo, true)) {
        if (!headers_sent()) {
            setcookie('rh_lang', $p, ['expires' => time() + 31536000, 'path' => '/', 'samesite' => 'Lax']);
        }
        return $l = $p;
    }
    // 2. la preference enregistree sur la fiche du salarie
    $u = function_exists('utilisateur') ? utilisateur() : null;
    if ($u && in_array($u['langue'] ?? '', $dispo, true)) return $l = $u['langue'];
    // 3. le cookie
    $c = $_COOKIE['rh_lang'] ?? null;
    if ($c && in_array($c, $dispo, true)) return $l = $c;
    // 4. le navigateur
    foreach (explode(',', (string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')) as $morceau) {
        $code = strtolower(substr(trim($morceau), 0, 2));
        if (in_array($code, $dispo, true)) return $l = $code;
    }
    return $l = (string) cfg('langue_defaut');
}

function textes(?string $lang = null): array
{
    static $cache = [];
    $lang = $lang ?: langue();
    if (isset($cache[$lang])) return $cache[$lang];
    $f = __DIR__ . '/lang/' . preg_replace('/[^a-z]/', '', $lang) . '.php';
    $cache[$lang] = is_file($f) ? require $f : [];
    return $cache[$lang];
}

function t(string $cle, array $vars = []): string
{
    $tx = textes();
    $s = $tx[$cle] ?? null;
    if ($s === null) {
        // Repli sur la langue par defaut, puis sur la cle elle-meme. Une cle
        // affichee brute est laide, et c'est voulu : elle se remarque.
        $s = textes((string) cfg('langue_defaut'))[$cle] ?? $cle;
    }
    foreach ($vars as $k => $v) {
        $s = str_replace('{' . $k . '}', (string) $v, $s);
    }
    return $s;
}

/**
 * Pluriel.
 *
 * Le francais emploie le singulier a 0 ET a 1 (« 0 demande »), l'anglais
 * non (« 0 requests »). Deux cles par nombre, `_un` et `_autre`.
 */
function pluriel(int $n): string
{
    return match (langue()) {
        'fr'    => $n <= 1 ? 'un' : 'autre',
        default => $n === 1 ? 'un' : 'autre',
    };
}

function tn(string $cle, int $n, array $vars = []): string
{
    return t($cle . '_' . pluriel($n), $vars + ['n' => nombre($n)]);
}

function nombre($n, int $decimales = 0): string
{
    return match (langue()) {
        'fr'    => number_format((float) $n, $decimales, ',', ' '),
        default => number_format((float) $n, $decimales, '.', ','),
    };
}

/** Le nom d'une colonne bilingue : nom_fr / nom_en selon la langue lue. */
function col_langue(array $ligne, string $prefixe): string
{
    $l = langue();
    return (string) ($ligne[$prefixe . '_' . $l] ?? $ligne[$prefixe . '_' . cfg('langue_defaut')] ?? '');
}

function langue_rtl(?string $l = null): bool
{
    return in_array($l ?: langue(), ['ar', 'fa', 'ur', 'he'], true);
}
