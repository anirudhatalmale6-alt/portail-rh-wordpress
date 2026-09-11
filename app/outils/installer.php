<?php
/**
 * Installation : cree les tables, les roles, les permissions, la structure
 * de base et le compte administrateur, puis ecrit src/config.local.php.
 *
 *   php outils/installer.php
 *
 * Rejouable sans risque : tout est en CREATE TABLE IF NOT EXISTS et en
 * INSERT conditionnel. On ne teste PAS l'existence du fichier SQLite avant
 * de se connecter — ouvrir un fichier SQLite le CREE, donc le test
 * « le fichier existe-t-il » repond faux puis vrai dans la meme seconde et
 * la deuxieme execution croirait avoir affaire a une base neuve.
 *
 * Le mot de passe administrateur est TIRE AU HASARD et affiche UNE FOIS.
 * Il n'est ecrit dans aucun fichier du depot : un mot de passe par defaut
 * livre dans une archive est un compte ouvert.
 */

declare(strict_types=1);

/*
 * Ferme au web par defaut. L'extension WordPress, elle, a le droit de le
 * lancer : elle definit RH_INSTALL_AUTORISE juste avant de l'inclure, apres
 * avoir verifie que l'appelant est un administrateur et que le nonce est bon.
 * On garde UNE seule logique d'installation — une copie dans l'extension
 * aurait diverge de celle-ci a la premiere correction.
 */
if (PHP_SAPI !== 'cli' && !defined('RH_INSTALL_AUTORISE')) {
    http_response_code(403);
    exit("A lancer en ligne de commande.\n");
}

$racine = dirname(__DIR__);
require $racine . '/src/noyau.php';
require $racine . '/src/i18n.php';
require $racine . '/src/vue.php';
require $racine . '/src/coffre.php';
require $racine . '/src/auth.php';
require $racine . '/src/balisage.php';
require $racine . '/src/schema.php';
foreach (glob($racine . '/src/domaine/*.php') as $f) require $f;

$pilote = cfg('bd')['pilote'];
echo "Pilote : $pilote\n";

/* --- 1. Secrets locaux ------------------------------------------------
 * On les ecrit AVANT le reste : la cle du coffre doit exister avant qu'une
 * donnee bancaire puisse etre enregistree, et le sel avant la premiere
 * session. Le fichier n'est jamais ecrase s'il existe deja — regenerer la
 * cle du coffre rendrait illisibles TOUS les comptes bancaires en base. */
$local = $racine . '/src/config.local.php';
if (!is_file($local)) {
    $contenu = "<?php\n"
        . "/* Genere par outils/installer.php. NE PAS COMMITTER.\n"
        . " *\n"
        . " * 'cle_coffre' dechiffre les coordonnees bancaires de tous les\n"
        . " * salaries. La perdre les rend illisibles ; la regenerer aussi.\n"
        . " * Sauvegarde ce fichier separement de la base de donnees : les\n"
        . " * garder ensemble annule tout l'interet du chiffrement.\n"
        . " */\n"
        . "return [\n"
        . "    'sel_session' => " . var_export(bin2hex(random_bytes(32)), true) . ",\n"
        . "    'cle_coffre'  => " . var_export(base64_encode(random_bytes(32)), true) . ",\n"
        . "    // 'bd' => ['pilote' => 'mysql', 'hote' => 'localhost',\n"
        . "    //          'base' => '', 'user' => '', 'passe' => ''],\n"
        . "    // 'cookie_secure' => true,   // derriere HTTPS\n"
        . "];\n";
    file_put_contents($local, $contenu);
    @chmod($local, 0600);
    // La configuration a deja ete lue plus haut (ne serait-ce que pour
    // connaitre le pilote). Sans cette remise a zero, le cache statique
    // garde la version SANS la cle et l'etape 8 annonce un coffre
    // indisponible alors qu'on vient de le creer.
    cfg_recharge();
    echo "src/config.local.php cree (sel de session + cle de coffre).\n";
} else {
    echo "src/config.local.php deja present : conserve tel quel.\n";
}

/* --- 2. Tables -------------------------------------------------------- */
$n = 0;
foreach (schema_ddl($pilote) as $sql) {
    try {
        bd()->exec($sql);
        $n++;
    } catch (PDOException $e) {
        // 1061 = index deja present sur MySQL, qui n'a pas de
        // « CREATE INDEX IF NOT EXISTS ». C'est le seul code tolere.
        if (str_contains($e->getMessage(), '1061') || str_contains($e->getMessage(), 'Duplicate key name')) {
            continue;
        }
        fwrite(STDERR, "ECHEC : $sql\n" . $e->getMessage() . "\n");
        exit(1);
    }
}
echo "Instructions DDL executees : $n\n";

/* --- 3. Permissions et roles ------------------------------------------ */
foreach (PERMISSIONS as $cle => $desc) {
    if (qval('SELECT id FROM permissions WHERE cle = ?', [$cle]) === null) {
        insere('permissions', ['cle' => $cle, 'description' => $desc]);
    }
}
foreach (ROLES as $cle => $def) {
    $id = qval('SELECT id FROM roles WHERE cle = ?', [$cle]);
    if ($id === null) {
        $id = insere('roles', ['cle' => $cle, 'rang' => $def['rang'],
                               'nom_fr' => $def['fr'], 'nom_en' => $def['en']]);
    }
    $perms = $def['perms'] === '*' ? array_keys(PERMISSIONS) : $def['perms'];
    foreach ($perms as $p) {
        $pid = (int) qval('SELECT id FROM permissions WHERE cle = ?', [$p]);
        if (!$pid) continue;
        if (qval('SELECT id FROM role_permissions WHERE role_id = ? AND permission_id = ?',
                 [(int) $id, $pid]) === null) {
            insere('role_permissions', ['role_id' => (int) $id, 'permission_id' => $pid]);
        }
    }
}
echo 'Roles : ' . count(ROLES) . ' — permissions : ' . count(PERMISSIONS) . "\n";

/* --- 4. Types de conges ------------------------------------------------
 * Les SOLDES par defaut sont volontairement NULL. Le nombre de jours de
 * conges annuels est fixe par la loi du pays et par le contrat ; ecrire
 * « 20 » ici en ferait un chiffre plausible que personne n'a valide, et il
 * s'afficherait sur l'ecran de chaque salarie comme un droit acquis. */
foreach ([
    ['annuel',    'Conge annuel',        'Annual leave',      1, 0],
    ['maladie',   'Conge de maladie',    'Sick leave',        1, 1],
    ['sans_solde','Conge sans solde',    'Unpaid leave',      0, 0],
    ['parental',  'Conge parental',      'Parental leave',    0, 1],
    ['familial',  'Obligations familiales', 'Family duties',  0, 0],
] as $i => [$cle, $fr, $en, $paye, $justif]) {
    if (qval('SELECT id FROM types_conges WHERE cle = ?', [$cle]) === null) {
        insere('types_conges', [
            'cle' => $cle, 'nom_fr' => $fr, 'nom_en' => $en,
            'paye' => $paye, 'justificatif' => $justif,
            'solde_defaut' => null,      // voir le commentaire ci-dessus
            'actif' => 1, 'rang' => $i + 1,
        ]);
    }
}
echo "Types de conges : " . (int) qval('SELECT COUNT(*) FROM types_conges') . "\n";

/* --- 5. Structure minimale --------------------------------------------
 * Deux localisations, une par pays d'exploitation, avec leur fuseau. Les
 * ADRESSES restent vides : je ne connais pas les bureaux. */
foreach (cfg('pays') as $code => $p) {
    if (qval('SELECT id FROM localisations WHERE code = ?', [$code]) === null) {
        insere('localisations', [
            'code' => $code, 'nom' => $p['nom_fr'], 'pays' => $code,
            'fuseau' => $p['fuseau'], 'adresse' => '',
        ]);
    }
}
foreach ([['direction', 'Direction', 'Management'],
          ['rh', 'Ressources humaines', 'Human resources'],
          ['ventes', 'Ventes', 'Sales'],
          ['operations', 'Operations', 'Operations'],
          ['technique', 'Technique', 'Engineering'],
          ['administration', 'Administration', 'Administration']] as $i => [$c, $fr, $en]) {
    if (qval('SELECT id FROM departements WHERE code = ?', [$c]) === null) {
        insere('departements', ['code' => $c, 'nom_fr' => $fr, 'nom_en' => $en, 'rang' => $i + 1]);
    }
}

/* --- 6. Reglages ------------------------------------------------------- */
foreach (['conservation_candidature_mois' => '12',
          'cv_obligatoire' => '1',
          'fuseau_defaut' => cfg('pays')[cfg('pays_defaut')]['fuseau']] as $c => $v) {
    if (qval('SELECT id FROM reglages WHERE cle = ?', [$c]) === null) {
        insere('reglages', ['cle' => $c, 'valeur' => $v]);
    }
}

/* --- 7. Compte administrateur ------------------------------------------ */
$role_admin = (int) qval("SELECT id FROM roles WHERE cle = 'admin'");
$existe = qval('SELECT id FROM utilisateurs WHERE role_id = ?', [$role_admin]);
if ($existe === null) {
    $mdp = mdp_temporaire() . '-' . substr(bin2hex(random_bytes(3)), 0, 5);
    $id = insere('utilisateurs', [
        'matricule' => 'ADMIN-001',
        'email' => 'admin@local',
        'mot_de_passe' => hache_mdp($mdp),
        'doit_changer' => 1, 'actif' => 1, 'role_id' => $role_admin,
        'prenom' => 'Administrateur', 'nom' => 'Systeme',
        'poste' => 'Administration du portail',
        'statut' => 'actif', 'temps_travail' => 'plein',
        'pays_travail' => (string) cfg('pays_defaut'),
        'fuseau' => cfg('pays')[cfg('pays_defaut')]['fuseau'],
        'langue' => (string) cfg('langue_defaut'),
        'devise' => cfg('pays')[cfg('pays_defaut')]['devise'],
        'frequence_paie' => cfg('pays')[cfg('pays_defaut')]['frequence_paie'],
        'annuaire_visible' => 0, 'annuaire_tel' => 0,
        'cree_le' => maintenant(), 'maj_le' => maintenant(),
    ]);
    echo "\n";
    echo "  ============================================================\n";
    echo "   COMPTE ADMINISTRATEUR CREE\n";
    echo "     identifiant : admin@local\n";
    echo "     mot de passe : $mdp\n";
    echo "   Ce mot de passe s'affiche UNE SEULE FOIS et devra etre change\n";
    echo "   a la premiere connexion. Change aussi l'adresse pour une vraie.\n";
    echo "  ============================================================\n\n";
} else {
    echo "Compte administrateur deja present.\n";
}

/* --- 8. Verification du coffre ------------------------------------------
 * On ne se contente pas de constater que la cle existe : on chiffre puis on
 * dechiffre une valeur temoin. Une cle de la mauvaise longueur, un OpenSSL
 * sans AES-GCM ou un base64 abime passeraient le test « la cle est
 * renseignee » et echoueraient au premier compte bancaire saisi. */
if (coffre_disponible()) {
    $temoin = 'temoin-' . bin2hex(random_bytes(6));
    $aller = coffre_chiffre($temoin);
    $retour = $aller !== null ? coffre_dechiffre($aller) : null;
    echo $retour === $temoin
        ? "Coffre de chiffrement : verifie (chiffre puis dechiffre).\n"
        : "ATTENTION : le coffre ne restitue pas la valeur temoin.\n";
} else {
    echo "ATTENTION : coffre de chiffrement indisponible — aucune donnee bancaire ne pourra etre enregistree.\n";
}

/* --- 9. Ce qui reste a renseigner -------------------------------------- */
$manque = reglages_manquants();
if ($manque) {
    echo "\nA renseigner dans src/config.php (visible sur /admin/a-renseigner) :\n";
    foreach ($manque as $m) echo "  - $m\n";
}

echo "\nInstallation terminee.\n";
echo "Etapes suivantes :\n";
echo "  php outils/semer.php            # jeu de demonstration (facultatif)\n";
echo "  php -S localhost:8000 -t public # serveur de developpement\n";
