<?php
/**
 * Petit outil en ligne de commande pour la recette.
 *
 * Il existe pour une raison precise : certaines regles ne se testent qu'en
 * MANIPULANT L'ETAT, pas en lisant le code. Revoquer une permission en base
 * et verifier que le serveur refuse quand meme, remettre a zero un
 * compteur de debit pour pouvoir rejouer la suite, forcer une date de
 * publication dans le passe. Sans cet outil, la suite se contenterait de
 * constater que les pages repondent 200.
 *
 *   php tests/rh.php compte
 *   php tests/rh.php limites-raz
 *   php tests/rh.php revoque <role> <permission>
 *   php tests/rh.php rend <role> <permission>
 *   php tests/rh.php permissions <role>
 *   php tests/rh.php slugs
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI seulement.\n"); }

$racine = dirname(__DIR__);
require $racine . '/src/noyau.php';
require $racine . '/src/i18n.php';
require $racine . '/src/vue.php';
require $racine . '/src/coffre.php';
require $racine . '/src/auth.php';
require $racine . '/src/balisage.php';
require $racine . '/src/schema.php';
foreach (glob($racine . '/src/domaine/*.php') as $f) require $f;

$cmd = $argv[1] ?? 'compte';

switch ($cmd) {

case 'compte':
    foreach (['utilisateurs', 'demandes_conges', 'absences', 'bulletins', 'primes',
              'demandes_rh', 'feuilles_temps', 'actualites', 'offres', 'candidatures',
              'documents', 'fichiers', 'notifications', 'journal_audit',
              'limites_taux'] as $t) {
        printf("%-20s %d\n", $t, (int) qval("SELECT COUNT(*) FROM `$t`"));
    }
    break;

case 'limites-raz':
    // Les compteurs de debit sont volontairement persistants : c'est ce qui
    // les rend efficaces. Ils empechent donc de rejouer la suite dans
    // l'heure. On les vide ICI, explicitement, et jamais depuis le code de
    // l'application.
    $n = (int) qval('SELECT COUNT(*) FROM limites_taux');
    q('DELETE FROM limites_taux');
    echo "Compteurs de debit remis a zero : $n\n";
    break;

case 'permissions':
    $role = $argv[2] ?? 'employe';
    $p = permissions_de_role($role);
    echo "Role $role — permissions APPLIQUEES par le serveur :\n";
    if ($p === '*') { echo "  (toutes)\n"; break; }
    foreach ($p as $c) echo "  $c\n";
    break;

case 'revoque':
case 'rend':
    $role = $argv[2] ?? '';
    $perm = $argv[3] ?? '';
    $rid = (int) qval('SELECT id FROM roles WHERE cle = ?', [$role]);
    $pid = (int) qval('SELECT id FROM permissions WHERE cle = ?', [$perm]);
    if (!$rid || !$pid) exit("Role ou permission inconnu.\n");
    if ($cmd === 'revoque') {
        q('DELETE FROM role_permissions WHERE role_id = ? AND permission_id = ?', [$rid, $pid]);
        echo "Retire : $role n'a plus $perm EN BASE.\n";
        echo "Si le serveur laisse encore faire l'action, c'est que rien ne lit cette table.\n";
    } else {
        if (qval('SELECT id FROM role_permissions WHERE role_id = ? AND permission_id = ?',
                 [$rid, $pid]) === null) {
            insere('role_permissions', ['role_id' => $rid, 'permission_id' => $pid]);
        }
        echo "Rendu : $role a de nouveau $perm.\n";
    }
    break;

case 'slugs':
    // Tout identifiant stocke doit respecter l'alphabet du routeur.
    $mauvais = 0;
    foreach (['offres' => 'slug', 'actualites' => 'slug'] as $table => $col) {
        foreach (qtous("SELECT id, `$col` AS s FROM `$table`") as $r) {
            if (!preg_match('/^[a-z0-9\-]+$/', (string) $r['s'])) {
                echo "HORS ALPHABET : $table#{$r['id']} = {$r['s']}\n";
                $mauvais++;
            }
        }
    }
    echo $mauvais === 0
        ? "Toutes les adresses respectent [a-z0-9-].\n"
        : "$mauvais adresse(s) hors alphabet — elles repondront 404.\n";
    break;

default:
    echo "Commandes : compte | limites-raz | permissions <role> | revoque <role> <perm> | rend <role> <perm> | slugs\n";
}
