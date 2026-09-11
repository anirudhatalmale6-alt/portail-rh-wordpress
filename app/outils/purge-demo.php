<?php
/**
 * Efface le jeu de demonstration.
 *
 *   php outils/purge-demo.php            # simulation : compte, ne supprime rien
 *   php outils/purge-demo.php --executer # supprime pour de bon
 *
 * CE QUI EST CONSERVE : les roles, les permissions, les departements, les
 * localisations, les types de conges, les reglages et le compte
 * administrateur. Autrement dit tout ce qui a ete CONFIGURE, par
 * opposition a tout ce qui a ete INVENTE pour la demonstration.
 *
 * La simulation est le comportement par defaut, et ce n'est pas de la
 * prudence de facade : cet outil supprime des lignes de paie et des
 * dossiers salaries. Un utilisateur qui le lance « pour voir » doit voir un
 * decompte, pas une base vide.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("A lancer en ligne de commande.\n"); }

$racine = dirname(__DIR__);
require $racine . '/src/noyau.php';
require $racine . '/src/i18n.php';
require $racine . '/src/vue.php';
require $racine . '/src/coffre.php';
require $racine . '/src/auth.php';
require $racine . '/src/balisage.php';
require $racine . '/src/schema.php';
foreach (glob($racine . '/src/domaine/*.php') as $f) require $f;

$executer = in_array('--executer', $argv, true);
echo $executer ? "MODE REEL : les lignes vont etre supprimees.\n\n"
               : "SIMULATION : rien ne sera supprime. Ajoute --executer pour agir.\n\n";

$uids = array_map('intval', array_column(
    qtous('SELECT id FROM utilisateurs WHERE demo = 1'), 'id'));

$compte = function (string $sql, array $p = []) { return (int) qval($sql, $p); };
$rapport = [];

$rapport['salaries']       = count($uids);
$rapport['conges']         = $compte('SELECT COUNT(*) FROM demandes_conges WHERE demo = 1');
$rapport['absences']       = $compte('SELECT COUNT(*) FROM absences WHERE demo = 1');
$rapport['bulletins']      = $compte('SELECT COUNT(*) FROM bulletins WHERE demo = 1');
$rapport['periodes_paie']  = $compte('SELECT COUNT(*) FROM periodes_paie WHERE demo = 1');
$rapport['primes']         = $compte('SELECT COUNT(*) FROM primes WHERE demo = 1');
$rapport['demandes_rh']    = $compte('SELECT COUNT(*) FROM demandes_rh WHERE demo = 1');
$rapport['feuilles_temps'] = $compte('SELECT COUNT(*) FROM feuilles_temps WHERE demo = 1');
$rapport['actualites']     = $compte('SELECT COUNT(*) FROM actualites WHERE demo = 1');
$rapport['offres']         = $compte('SELECT COUNT(*) FROM offres WHERE demo = 1');
$rapport['candidatures']   = $compte('SELECT COUNT(*) FROM candidatures WHERE demo = 1');
$rapport['documents']      = $compte('SELECT COUNT(*) FROM documents WHERE demo = 1');
$rapport['inscriptions']   = $compte('SELECT COUNT(*) FROM inscriptions_formation WHERE demo = 1');

foreach ($rapport as $quoi => $n) printf("  %-16s %d\n", $quoi, $n);

if (!$executer) {
    echo "\nRelance avec --executer pour supprimer.\n";
    exit(0);
}

/* --- Suppression ------------------------------------------------------
 * L'ordre compte : on part des lignes qui referencent, vers celles qui
 * sont referencees. Et on supprime AUSSI ce qui est rattache aux comptes
 * de demonstration sans porter demo = 1 soi-meme (soldes, notifications,
 * sessions), sinon la base garde des lignes orphelines qui pointent vers
 * des identifiants qui n'existent plus. */

$in = $uids ? implode(',', array_fill(0, count($uids), '?')) : null;

// Fichiers des candidatures et des documents de demonstration : le fichier
// sur le disque doit partir avec la ligne, sinon le repertoire grossit avec
// des documents que plus rien ne reference et que personne ne relira.
$fichiers = [];
foreach (qtous('SELECT cv_id, lettre_id FROM candidatures WHERE demo = 1') as $c) {
    foreach ([$c['cv_id'], $c['lettre_id']] as $f) if ($f) $fichiers[] = (int) $f;
}
foreach (qtous('SELECT fichier_id FROM documents WHERE demo = 1') as $d) {
    if ($d['fichier_id']) $fichiers[] = (int) $d['fichier_id'];
}
if ($in) {
    foreach (qtous("SELECT fichier_id FROM documents WHERE utilisateur_id IN ($in)", $uids) as $d) {
        if ($d['fichier_id']) $fichiers[] = (int) $d['fichier_id'];
    }
}

q('DELETE FROM reponses_examen WHERE candidature_id IN (SELECT id FROM candidatures WHERE demo = 1)');
q('DELETE FROM candidatures WHERE demo = 1');
q('DELETE FROM questions_examen WHERE examen_id IN (SELECT id FROM examens WHERE code = ?)', ['export-base']);
q('DELETE FROM examens WHERE code = ?', ['export-base']);
q('DELETE FROM offres WHERE demo = 1');
q('DELETE FROM messages_demande WHERE demande_id IN (SELECT id FROM demandes_rh WHERE demo = 1)');
q('DELETE FROM demandes_rh WHERE demo = 1');
q('DELETE FROM lignes_bulletin WHERE bulletin_id IN (SELECT id FROM bulletins WHERE demo = 1)');
q('DELETE FROM bulletins WHERE demo = 1');
q('DELETE FROM primes WHERE demo = 1');
q('DELETE FROM periodes_paie WHERE demo = 1');
q('DELETE FROM demandes_conges WHERE demo = 1');
q('DELETE FROM absences WHERE demo = 1');
q('DELETE FROM feuilles_temps WHERE demo = 1');
q('DELETE FROM actualites WHERE demo = 1');
q('DELETE FROM inscriptions_formation WHERE demo = 1');
q('DELETE FROM documents WHERE demo = 1');

if ($in) {
    q("DELETE FROM soldes_conges WHERE utilisateur_id IN ($in)", $uids);
    q("DELETE FROM notifications WHERE utilisateur_id IN ($in)", $uids);
    q("DELETE FROM sessions WHERE utilisateur_id IN ($in)", $uids);
    q("DELETE FROM documents WHERE utilisateur_id IN ($in)", $uids);
    q("DELETE FROM journal_audit WHERE utilisateur_id IN ($in)", $uids);
    // On detache les rattachements AVANT de supprimer, sinon des fiches
    // conservees garderaient un manager_id qui ne designe plus personne, et
    // l'organigramme les traiterait comme des racines sans le dire.
    q("UPDATE utilisateurs SET manager_id = NULL WHERE manager_id IN ($in)", $uids);
    q("DELETE FROM utilisateurs WHERE id IN ($in)", $uids);
}

$efaces = 0;
foreach (array_unique($fichiers) as $fid) {
    $f = qun('SELECT * FROM fichiers WHERE id = ?', [$fid]);
    if (!$f) continue;
    $abs = repertoire_documents() . '/' . $f['chemin'];
    if (is_file($abs)) { @unlink($abs); $efaces++; }
    q('DELETE FROM fichiers WHERE id = ?', [$fid]);
}

echo "\nSupprime. Fichiers effaces du disque : $efaces\n";
echo "Passe 'mode_demo' a false dans src/config.php pour retirer le bandeau.\n";

/* Verification apres coup : on ne se fie pas au fait que les requetes se
 * soient executees sans erreur, on RECOMPTE. */
$restant = 0;
foreach (['utilisateurs', 'demandes_conges', 'absences', 'bulletins', 'primes',
          'demandes_rh', 'feuilles_temps', 'actualites', 'offres', 'candidatures',
          'documents', 'inscriptions_formation', 'periodes_paie'] as $t) {
    $restant += (int) qval("SELECT COUNT(*) FROM `$t` WHERE demo = 1");
}
echo $restant === 0
    ? "Verification : plus aucune ligne demo = 1.\n"
    : "ATTENTION : $restant ligne(s) demo = 1 subsistent.\n";
