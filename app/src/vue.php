<?php
/**
 * Couche de presentation : gabarit, fil d'Ariane, pagination.
 * Les vues ne parlent jamais a la base directement ; elles recoivent un
 * tableau. C'est ce qui rend l'API JSON possible sans dupliquer la logique.
 */

declare(strict_types=1);

/**
 * Le prefixe sous lequel l'application est montee.
 *
 * Vaut '' quand elle est seule sur son domaine — le cas normal. Vaut '/rh'
 * quand elle tourne derriere WordPress via l'extension : les requetes
 * arrivent alors sur /rh/quelque-chose, et un lien qui renverrait vers
 * /quelque-chose sortirait de l'application pour tomber sur WordPress.
 * Tous les liens, toutes les redirections et tous les fichiers statiques
 * passent par ici.
 */
function base_uri(): string
{
    return rtrim((string) cfg('base_uri'), '/');
}

function url(string $chemin = '/'): string
{
    $base = rtrim((string) cfg('domaine'), '/');
    return $base . base_uri() . $chemin;
}

/** Un fichier statique. Pas de langue ajoutee : ce n'est pas une page. */
function actif(string $chemin): string
{
    return base_uri() . $chemin;
}

/** Conserve la langue choisie en la propageant dans les liens internes. */
function lien(string $chemin): string
{
    $chemin = base_uri() . $chemin;
    if (langue() === cfg('langue_defaut')) return $chemin;
    return $chemin . (str_contains($chemin, '?') ? '&' : '?') . 'lang=' . langue();
}

function redirige(string $chemin): never
{
    header('Location: ' . lien($chemin), true, 303);
    exit;
}

$GLOBALS['rh_meta'] = [];
$GLOBALS['rh_messages'] = [];

function meta(array $m): void
{
    $GLOBALS['rh_meta'] = array_merge($GLOBALS['rh_meta'], $m);
}

/** Message flash. Stocke en session, lu une fois, puis efface. */
function flash(string $type, string $texte): void
{
    $_SESSION['flash'][] = ['type' => $type, 'texte' => $texte];
}

function flashs(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function rendre(string $vue, array $donnees = []): void
{
    $donnees['vue'] = $vue;
    extract($donnees, EXTR_SKIP);
    $contenu_vue = __DIR__ . '/vues/' . $vue . '.php';
    if (!is_file($contenu_vue)) {
        journal('erreur', 'vue introuvable', ['vue' => $vue]);
        http_response_code(500);
        echo 'vue introuvable';
        return;
    }
    ob_start();
    include $contenu_vue;
    $corps_page = ob_get_clean();
    include __DIR__ . '/vues/gabarit.php';
}

/** Une page qui n'a pas de gabarit : l'examen en plein ecran, le PDF. */
function rendre_nu(string $vue, array $donnees = []): void
{
    extract($donnees, EXTR_SKIP);
    include __DIR__ . '/vues/' . $vue . '.php';
}

function fil(array $items): string
{
    $html = '<nav class="fil" aria-label="' . h(t('fil_ariane')) . '"><ol>';
    foreach ($items as $it) {
        $nom = (string) $it[0];
        $u   = $it[1] ?? null;
        $html .= '<li>' . ($u
            ? '<a href="' . h(lien($u)) . '">' . h($nom) . '</a>'
            : '<span aria-current="page">' . h($nom) . '</span>') . '</li>';
    }
    return $html . '</ol></nav>';
}

function pagination(int $page, int $total, int $par_page, string $base): string
{
    $pages = max(1, (int) ceil($total / max(1, $par_page)));
    if ($pages <= 1) return '';
    $out = '<nav class="pagination" aria-label="' . h(t('page')) . '">';
    $lien_page = function (int $p) use ($base) {
        $sep = str_contains($base, '?') ? '&' : '?';
        return h(lien($p <= 1 ? $base : $base . $sep . 'page=' . $p));
    };
    if ($page > 1) $out .= '<a rel="prev" href="' . $lien_page($page - 1) . '">' . h(t('precedent')) . '</a>';
    $debut = max(1, $page - 2); $fin = min($pages, $page + 2);
    if ($debut > 1) $out .= '<a href="' . $lien_page(1) . '">1</a><span class="ellipse">…</span>';
    for ($p = $debut; $p <= $fin; $p++) {
        $out .= $p === $page
            ? '<span class="courante" aria-current="page">' . $p . '</span>'
            : '<a href="' . $lien_page($p) . '">' . $p . '</a>';
    }
    if ($fin < $pages) $out .= '<span class="ellipse">…</span><a href="' . $lien_page($pages) . '">' . $pages . '</a>';
    if ($page < $pages) $out .= '<a rel="next" href="' . $lien_page($page + 1) . '">' . h(t('suivant')) . '</a>';
    return $out . '</nav>';
}

/**
 * Avatar : initiales sur un fond derive du nom. Aucune image distante.
 *
 * La teinte passe par une CLASSE, pas par un attribut style : la politique
 * de securite interdit le style en ligne, et un `style="--h:210"` est
 * SILENCIEUSEMENT bloque — la page s'affiche, l'avatar perd sa couleur, et
 * rien n'apparait dans les journaux.
 */
function avatar(array $u, int $taille = 36): string
{
    $nom = trim(($u['prenom'] ?? '') . ' ' . ($u['nom'] ?? ''));
    if ($nom === '') $nom = (string) ($u['email'] ?? '?');
    $ini = mb_strtoupper(mb_substr((string) ($u['prenom'] ?? $nom), 0, 1)
                       . mb_substr((string) ($u['nom'] ?? ''), 0, 1));
    $seau = (int) (hexdec(substr(md5($nom), 0, 4)) % 12);
    $t = $taille >= 64 ? 'xl' : ($taille >= 48 ? 'l' : ($taille >= 34 ? 'm' : 's'));
    return '<span class="avatar avatar--h' . $seau . ' avatar--' . $t . '" aria-hidden="true">'
         . h($ini) . '</span>';
}

function nom_complet(?array $u): string
{
    if (!$u) return '';
    $n = trim(($u['prenom'] ?? '') . ' ' . ($u['nom'] ?? ''));
    return $n !== '' ? $n : (string) ($u['email'] ?? '');
}

function badge_statut(string $statut, string $famille = 'demande'): string
{
    return '<span class="badge badge--' . h($famille . '-' . $statut) . '">'
         . h(t('statut_' . $statut)) . '</span>';
}

function badge_role(string $cle): string
{
    return '<span class="badge-role badge-' . h($cle) . '">' . h(t('role_' . $cle)) . '</span>';
}

/** Barre de progression accessible : la valeur est aussi dans le texte. */
function jauge(float $valeur, float $max, string $libelle = ''): string
{
    $pc = $max > 0 ? max(0, min(100, (int) round($valeur / $max * 100))) : 0;
    $classe = 'jauge__barre jauge--p' . (int) (round($pc / 5) * 5);
    return '<div class="jauge" role="img" aria-label="' . h($libelle) . '">'
         . '<span class="' . $classe . '"></span></div>';
}
