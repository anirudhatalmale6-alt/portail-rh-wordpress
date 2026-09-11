<?php
/**
 * Noyau : configuration, base de donnees, journal, audit, utilitaires.
 * Aucune dependance externe, aucun composer. Le dossier se depose tel quel.
 */

declare(strict_types=1);

/**
 * Casse-cache des fichiers statiques.
 *
 * A BUMPER A CHAQUE MODIFICATION de style.css ou de app.js. Sans cela le
 * navigateur ressert l'ancien fichier depuis son cache, la correction n'est
 * pas visible, et on refait le meme test deux fois en croyant que le code
 * n'a pas marche. C'est le seul casse-cache du projet : il n'y en a pas un
 * deuxieme ailleurs qui pourrait rester en arriere.
 */
const VERSION_ACTIFS = '1.0.1';

/**
 * Vide le cache de configuration.
 *
 * Un seul appelant legitime : l'installeur, juste apres avoir ECRIT
 * config.local.php. Il a forcement lu la configuration avant (ne serait-ce
 * que pour connaitre le pilote de base), donc le cache statique contient la
 * version SANS la cle de coffre ; sans cette remise a zero, l'installeur
 * verifie le coffre avec une cle vide et annonce qu'il est indisponible
 * alors qu'il vient de le creer. Trouve exactement comme ca.
 */
function cfg_recharge(): void
{
    $GLOBALS['rh_cfg_generation'] = ($GLOBALS['rh_cfg_generation'] ?? 0) + 1;
}

function cfg(?string $cle = null)
{
    static $c = null;
    static $generation = 0;
    $courante = $GLOBALS['rh_cfg_generation'] ?? 0;
    if ($generation !== $courante) { $c = null; $generation = $courante; }
    if ($c === null) {
        $c = require __DIR__ . '/config.php';
        // config.local.php n'est PAS dans le depot (.gitignore). C'est lui
        // qui porte les identifiants de base, le sel de session et la cle
        // de chiffrement des donnees bancaires.
        $local = __DIR__ . '/config.local.php';
        if (is_file($local)) {
            $c = array_replace_recursive($c, require $local);
        }
    }
    if ($cle === null) return $c;
    return $c[$cle] ?? null;
}

function bd(): PDO
{
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $b = cfg('bd');
    if ($b['pilote'] === 'mysql') {
        /*
         * Beaucoup d'hebergements ne passent PAS par TCP : MySQL ecoute sur
         * une SOCKET UNIX, et l'adresse ressemble alors a
         * « localhost:/var/lib/mysql/mysql.sock ». Un DSN host+port dans ce
         * cas se connecte a la socket PAR DEFAUT de PHP — souvent un autre
         * serveur — et rend un « Access denied » qui fait chercher un
         * probleme de mot de passe pendant une heure. Rencontre exactement
         * comme ca en installant l'extension WordPress.
         */
        if (!empty($b['socket'])) {
            $dsn = sprintf('mysql:unix_socket=%s;dbname=%s;charset=utf8mb4',
                $b['socket'], $b['base']);
        } else {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $b['hote'], $b['port'] ?? 3306, $b['base']);
        }
        $pdo = new PDO($dsn, $b['user'], $b['passe']);
        // Toutes les dates sont ecrites et lues en UTC. Sans cette ligne,
        // NOW() cote MySQL suit le fuseau du serveur et gmdate() cote PHP
        // suit UTC : deux horodatages a quelques heures d'ecart dans la
        // meme table, et personne ne s'en apercoit avant l'audit.
        $pdo->exec("SET time_zone = '+00:00'");
    } else {
        $chemin = $b['sqlite'];
        if (!is_dir(dirname($chemin))) mkdir(dirname($chemin), 0775, true);
        $pdo = new PDO('sqlite:' . $chemin);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA journal_mode = WAL');
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    return $pdo;
}

/* Toutes les requetes passent par ici, et toutes sont preparees. Il n'y a
 * pas une seule concatenation de valeur dans une chaine SQL du projet ;
 * c'est la seule defense contre l'injection qui ne demande pas d'y penser. */
function q(string $sql, array $params = []): PDOStatement
{
    $st = bd()->prepare($sql);
    $st->execute($params);
    return $st;
}
function qun(string $sql, array $params = []): ?array
{
    $r = q($sql, $params)->fetch();
    return $r === false ? null : $r;
}
function qtous(string $sql, array $params = []): array { return q($sql, $params)->fetchAll(); }
function qval(string $sql, array $params = [])
{
    $r = q($sql, $params)->fetch(PDO::FETCH_NUM);
    return $r === false ? null : $r[0];
}
function insere(string $table, array $donnees): int
{
    $cols = array_keys($donnees);
    $sql = sprintf('INSERT INTO `%s` (`%s`) VALUES (%s)',
        $table, implode('`, `', $cols),
        implode(', ', array_fill(0, count($cols), '?')));
    q($sql, array_values($donnees));
    return (int) bd()->lastInsertId();
}
function maj(string $table, int $id, array $donnees): void
{
    if (!$donnees) return;
    $sets = implode(', ', array_map(fn($c) => "`$c` = ?", array_keys($donnees)));
    q("UPDATE `$table` SET $sets WHERE id = ?", [...array_values($donnees), $id]);
}

/** Tout l'horodatage du portail est en UTC. La conversion vers le fuseau
 *  du salarie se fait a l'AFFICHAGE, et nulle part ailleurs. */
function maintenant(): string { return gmdate('Y-m-d H:i:s'); }
function aujourdhui(): string { return gmdate('Y-m-d'); }

/* ------------------------------------------------------------------ */
/* Echappement                                                         */
/* ------------------------------------------------------------------ */

function h($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Un slug ASCII, toujours, et qui ne rend JAMAIS une chaine vide.
 *
 * Les motifs de la table de routage s'ecrivent `[\w\-]+`, et \w sans le
 * drapeau /u ne couvre que l'ASCII. Un titre non latin produirait un slug
 * que le routeur ne reconnait plus : la page repondrait 404 alors que la
 * ligne existe en base, et un 404 ne s'inscrit dans aucun journal.
 *
 * La translitteration ne suffit pas : « Any-Latin; Latin-ASCII » laisse
 * passer des lettres modificatives comme « ʿ » (U+02BF), qui ressemblent a
 * de l'ASCII sans en etre. D'ou le filtre final sur [a-z0-9], sans
 * exception.
 */
function slug(string $s, int $max = 120): string
{
    $s = trim($s);
    if (function_exists('transliterator_transliterate')) {
        $t = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $s);
        if ($t !== false && $t !== '') $s = $t;
    }
    $s = mb_strtolower($s, 'UTF-8');
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
    $s = trim($s, '-');
    $s = trim(substr($s, 0, $max), '-');     // ASCII : substr() est sur ici
    return $s === '' ? 'n-' . substr(bin2hex(random_bytes(4)), 0, 8) : $s;
}

function slug_unique(string $table, string $base, string $col = 'slug'): string
{
    $s = $base; $n = 1;
    while (qval("SELECT id FROM `$table` WHERE `$col` = ?", [$s]) !== null) {
        $n++;
        $s = $base . '-' . $n;
    }
    return $s;
}

/** Numero lisible : DEM-2026-0007, CAND-2026-0031. Sequence par annee. */
function numero(string $prefixe, string $table): string
{
    $annee = (int) gmdate('Y');
    $like = $prefixe . '-' . $annee . '-%';
    $dernier = (string) (qval(
        "SELECT numero FROM `$table` WHERE numero LIKE ? ORDER BY numero DESC LIMIT 1",
        [$like]) ?? '');
    $n = $dernier === '' ? 0 : (int) substr($dernier, strrpos($dernier, '-') + 1);
    return sprintf('%s-%d-%04d', $prefixe, $annee, $n + 1);
}

/* ------------------------------------------------------------------ */
/* Journal technique                                                   */
/* ------------------------------------------------------------------ */

function journal(string $niveau, string $message, array $ctx = []): void
{
    $dir = cfg('chemin_journal');
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $ligne = json_encode([
        'ts'      => maintenant(),
        'niveau'  => $niveau,
        'message' => $message,
        'uri'     => $_SERVER['REQUEST_URI'] ?? 'cli',
        'ip'      => ip_client(),
        'ctx'     => $ctx,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    @file_put_contents($dir . '/' . gmdate('Y-m-d') . '.log', $ligne . "\n", FILE_APPEND | LOCK_EX);
}

function installe_gestion_erreurs(): void
{
    set_error_handler(function ($no, $msg, $f, $l) {
        journal('erreur', $msg, ['fichier' => $f, 'ligne' => $l, 'no' => $no]);
        return false;
    });
    set_exception_handler(function (Throwable $e) {
        journal('critique', $e->getMessage(), [
            'classe' => get_class($e),
            'fichier' => $e->getFile(), 'ligne' => $e->getLine(),
        ]);
        http_response_code(500);
        // Le detail va au journal, pas a l'ecran : un message d'exception
        // affiche publiquement raconte le chemin des fichiers et la requete.
        echo '<!doctype html><meta charset="utf-8"><title>500</title>'
           . '<p style="font:16px/1.5 system-ui;padding:40px">'
           . 'Une erreur est survenue. Elle est enregistree dans le journal.</p>';
    });
    register_shutdown_function(function () {
        $e = error_get_last();
        if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            journal('fatale', $e['message'], ['fichier' => $e['file'], 'ligne' => $e['line']]);
        }
    });
}

function ip_client(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'cli'), 0, 64);
}

/* ------------------------------------------------------------------ */
/* Journal d'audit (section 32)                                        */
/* ------------------------------------------------------------------ */

/**
 * CHAMPS DONT LA VALEUR N'EST JAMAIS ECRITE DANS L'AUDIT.
 *
 * L'audit doit repondre a « qui a change quoi, quand ». Sur ces champs-la
 * il repond a « qui a change QUE ce champ a change ». La difference est
 * qu'un journal d'audit se consulte, s'exporte et se sauvegarde : y ecrire
 * un numero de compte en fait une deuxieme copie, dans un endroit ou
 * personne ne pense a la proteger.
 */
const CHAMPS_OPAQUES = [
    'banque_chiffre', 'banque_masque', 'banque_institution',
    'mot_de_passe', 'jeton', 'jeton_examen', 'sel_session', 'cle_coffre',
];

/**
 * Qui agit, quand la session n'est pas encore lisible.
 *
 * A la connexion, ouvrir_session() pose un cookie — mais $_COOKIE n'est pas
 * repeuple dans la MEME requete. utilisateur() rend donc null, et la ligne
 * d'audit la plus utile de tout le journal, « untel s'est connecte »,
 * s'ecrivait « systeme ». Vu sur une capture d'ecran du journal, pas dans le
 * code : la colonne QUI disait « systeme » sur toutes les connexions.
 */
function acteur_audit(?int $id = null): ?int
{
    if ($id !== null) $GLOBALS['rh_acteur'] = $id;
    return $GLOBALS['rh_acteur'] ?? null;
}

function audit(string $action, string $objet = '', ?int $objet_id = null,
               array $changements = [], array $detail = []): void
{
    try {
        $uid = fonction_existe_utilisateur() ? (utilisateur()['id'] ?? null) : null;
        $uid = $uid ?? acteur_audit();
        if (!$changements) {
            insere('journal_audit', [
                'utilisateur_id' => $uid, 'action' => $action, 'objet' => $objet,
                'objet_id' => $objet_id,
                'detail' => $detail ? json_encode($detail, JSON_UNESCAPED_UNICODE) : null,
                'ip' => ip_client(), 'cree_le' => maintenant(),
            ]);
            return;
        }
        foreach ($changements as $champ => $paire) {
            $opaque = in_array($champ, CHAMPS_OPAQUES, true);
            insere('journal_audit', [
                'utilisateur_id' => $uid, 'action' => $action, 'objet' => $objet,
                'objet_id' => $objet_id, 'champ' => $champ,
                'ancienne' => $opaque ? null : tronque_audit($paire[0]),
                'nouvelle' => $opaque ? null : tronque_audit($paire[1]),
                'detail' => $opaque ? 'valeur non journalisee (champ sensible)' : null,
                'ip' => ip_client(), 'cree_le' => maintenant(),
            ]);
        }
    } catch (Throwable $e) {
        journal('erreur', 'audit impossible : ' . $e->getMessage());
    }
}

function tronque_audit($v): ?string
{
    if ($v === null) return null;
    $s = is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE);
    return mb_substr((string) $s, 0, 500);
}

function fonction_existe_utilisateur(): bool { return function_exists('utilisateur'); }

/** Compare avant/apres et ne garde que ce qui a REELLEMENT change. */
function changements(array $avant, array $apres): array
{
    $out = [];
    foreach ($apres as $c => $v) {
        $a = $avant[$c] ?? null;
        if ((string) $a !== (string) $v) $out[$c] = [$a, $v];
    }
    return $out;
}

/* ------------------------------------------------------------------ */
/* Limitation de debit                                                  */
/* ------------------------------------------------------------------ */

function limite_ok(string $action, string $sujet): bool
{
    if (limite_atteinte($action, $sujet)) return false;
    limite_incremente($action, $sujet);
    return true;
}

/**
 * Consulte le compteur SANS l'incrementer.
 *
 * La distinction n'est pas cosmetique. Pour la connexion, on ne veut
 * compter que les ECHECS : compter aussi les reussites bloque tout un
 * bureau derriere une seule adresse IP de sortie des la cinquieme personne
 * qui se connecte le matin — et cette personne-la n'a rien fait de mal.
 * C'est exactement ce que faisait la premiere version, et c'est la suite de
 * tests qui l'a montre en n'arrivant plus a connecter le cinquieme compte.
 */
function limite_atteinte(string $action, string $sujet): bool
{
    $l = cfg('limites')[$action] ?? null;
    if (!$l) return false;
    $r = qun('SELECT * FROM limites_taux WHERE cle = ?', [$action . ':' . $sujet]);
    if (!$r) return false;
    if (time() - strtotime($r['debut'] . ' UTC') > $l['fenetre']) return false;
    if ((int) $r['compte'] >= $l['nb']) {
        journal('alerte', 'limite de debit atteinte', ['action' => $action, 'sujet' => $sujet]);
        return true;
    }
    return false;
}

function limite_incremente(string $action, string $sujet): void
{
    $l = cfg('limites')[$action] ?? null;
    if (!$l) return;
    $cle = $action . ':' . $sujet;
    $r = qun('SELECT * FROM limites_taux WHERE cle = ?', [$cle]);
    if (!$r) {
        insere('limites_taux', ['cle' => $cle, 'compte' => 1, 'debut' => maintenant()]);
        return;
    }
    if (time() - strtotime($r['debut'] . ' UTC') > $l['fenetre']) {
        maj('limites_taux', (int) $r['id'], ['compte' => 1, 'debut' => maintenant()]);
        return;
    }
    maj('limites_taux', (int) $r['id'], ['compte' => (int) $r['compte'] + 1]);
}

/** Efface le compteur : appele apres une connexion REUSSIE. */
function limite_efface(string $action, string $sujet): void
{
    q('DELETE FROM limites_taux WHERE cle = ?', [$action . ':' . $sujet]);
}

/* ------------------------------------------------------------------ */
/* Reglages en base                                                     */
/* ------------------------------------------------------------------ */

function reglage(string $cle, $defaut = null)
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (qtous('SELECT cle, valeur FROM reglages') as $r) {
                $cache[$r['cle']] = $r['valeur'];
            }
        } catch (Throwable) { /* avant installation */ }
    }
    return $cache[$cle] ?? $defaut;
}

/* ------------------------------------------------------------------ */
/* Valeur absente = pastille visible, jamais un tiret discret           */
/* ------------------------------------------------------------------ */

function valeur($v, string $cle = ''): string
{
    if ($v !== null && $v !== '') return h($v);
    return '<span class="vide" data-vide="' . h($cle) . '">' . t('a_renseigner') . '</span>';
}

/** Champ vide qui n'est pas une anomalie : un brouillon sans date, une
 *  absence sans justificatif encore deposee. Gris, pas ambre. */
function sans_valeur(string $libelle = ''): string
{
    return '<span class="sans-valeur">' . h($libelle !== '' ? $libelle : t('non_renseigne')) . '</span>';
}

/* ------------------------------------------------------------------ */
/* Dates et fuseaux                                                     */
/* ------------------------------------------------------------------ */

/**
 * Rend un horodatage UTC dans le fuseau de la personne qui LIT.
 *
 * Un salarie decentralise a Casablanca et son manager a Montreal ne
 * doivent pas lire la meme ligne a deux dates differentes sans le savoir.
 * La date affichee porte donc son fuseau en attribut title.
 */
function fuseau_lecteur(): string
{
    $u = function_exists('utilisateur') ? utilisateur() : null;
    $f = $u['fuseau'] ?? null;
    if ($f && in_array($f, timezone_identifiers_list(), true)) return $f;
    return (string) (reglage('fuseau_defaut') ?: 'UTC');
}

function date_locale(?string $ts_utc, bool $avec_heure = true, ?string $fuseau = null): string
{
    if (!$ts_utc) return '';
    $fuseau = $fuseau ?: fuseau_lecteur();
    try {
        $d = new DateTimeImmutable($ts_utc, new DateTimeZone('UTC'));
        $d = $d->setTimezone(new DateTimeZone($fuseau));
    } catch (Throwable) {
        return $ts_utc;
    }
    return $avec_heure ? $d->format('Y-m-d H:i') : $d->format('Y-m-d');
}

/** Nombre de jours ouvres entre deux dates, hors samedi/dimanche et hors
 *  jours feries chomes du pays. Les demi-journees sont retirees ensuite. */
function jours_ouvres(string $debut, string $fin, string $pays = ''): float
{
    $d = new DateTimeImmutable($debut);
    $f = new DateTimeImmutable($fin);
    if ($f < $d) return 0.0;
    $feries = [];
    if ($pays !== '') {
        foreach (qtous('SELECT date FROM jours_feries WHERE pays = ? AND chome = 1', [$pays]) as $r) {
            $feries[$r['date']] = true;
        }
    }
    $n = 0;
    for ($c = $d; $c <= $f; $c = $c->modify('+1 day')) {
        $jw = (int) $c->format('N');
        if ($jw >= 6) continue;
        if (isset($feries[$c->format('Y-m-d')])) continue;
        $n++;
    }
    return (float) $n;
}

function argent(?float $m, string $devise): string
{
    if ($m === null) return '';
    return number_format($m, 2, ',', ' ') . ' ' . $devise;
}
