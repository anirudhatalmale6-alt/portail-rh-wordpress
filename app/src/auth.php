<?php
/**
 * Identite, roles, permissions, reauthentification.
 *
 * ------------------------------------------------------------------------
 * LE RANG N'EST PAS L'AUTORISATION.
 *
 * Un manager (rang 40) est « au-dessus » d'un salarie (rang 20) dans
 * l'organigramme. Il n'a pour autant AUCUN acces au dossier bancaire, au
 * bulletin de paie ni au justificatif medical de son equipe. Ces droits
 * appartiennent a la RH et a personne d'autre. Le rang sert a trier une
 * liste ; il ne decide de rien.
 *
 * C'est pour cela que `peut()` interroge role_permissions et ne compare
 * jamais deux rangs.
 * ------------------------------------------------------------------------
 */

declare(strict_types=1);

const PERMISSIONS = [
    // --- profil ---------------------------------------------------------
    'profil.voir'            => 'Consulter son propre dossier',
    'profil.modifier'        => 'Modifier ses coordonnees personnelles',
    'profil.bancaire'        => 'Consulter et modifier ses coordonnees bancaires',
    'profil.tous.voir'       => 'Consulter le dossier de tout salarie',
    'profil.tous.modifier'   => 'Modifier le dossier de tout salarie',
    'profil.bancaire.tous'   => 'Consulter les coordonnees bancaires de tout salarie',

    // --- conges et absences ---------------------------------------------
    'conges.demander'        => 'Demander un conge',
    'conges.equipe.valider'  => 'Approuver ou refuser les conges de son equipe',
    'conges.administrer'     => 'Configurer les types de conges et les soldes',
    'absences.declarer'      => 'Declarer une absence',
    'absences.equipe.voir'   => 'Voir les absences de son equipe',
    'absences.administrer'   => 'Valider et administrer les absences',
    'absences.justificatif'  => 'Ouvrir le justificatif d\'une absence',

    // --- documents et paie ----------------------------------------------
    'documents.siens'        => 'Consulter ses propres documents',
    'documents.gerer'        => 'Deposer et classer les documents RH',
    'paie.sienne'            => 'Consulter ses bulletins et ses primes',
    'paie.gerer'             => 'Saisir les bulletins, les primes et les periodes',

    // --- demandes RH ----------------------------------------------------
    'demandes.creer'         => 'Soumettre une demande RH',
    'demandes.traiter'       => 'Traiter les demandes RH',

    // --- equipe, annuaire, communications --------------------------------
    'equipe.voir'            => 'Voir le tableau de bord de son equipe',
    'annuaire.voir'          => 'Consulter l\'annuaire interne',
    'actualites.publier'     => 'Publier une actualite RH',
    'formations.gerer'       => 'Gerer le catalogue et les inscriptions',
    'avantages.gerer'        => 'Gerer les avantages sociaux',

    // --- temps de travail ------------------------------------------------
    'temps.saisir'           => 'Declarer son temps de travail',
    'temps.equipe.valider'   => 'Valider les feuilles de temps de son equipe',
    'temps.administrer'      => 'Administrer les feuilles de temps',

    // --- recrutement ------------------------------------------------------
    'recrutement.gerer'      => 'Gerer les offres, les candidatures et les examens',

    // --- administration ---------------------------------------------------
    'rh.tableau'             => 'Voir le tableau de bord RH global',
    'admin.utilisateurs'     => 'Creer et desactiver les comptes',
    'admin.roles'            => 'Modifier les roles et les permissions',
    'admin.parametres'       => 'Modifier le parametrage du portail',
    'admin.journal'          => 'Consulter le journal d\'audit',
];

/**
 * Les cinq roles de la section 2, plus celui que la section 15 ajoute.
 *
 * « employe_decentralise » n'est pas un employe avec une case cochee : il a
 * un metier different. Il n'a pas d'horaire impose, il declare ses journees,
 * il travaille depuis un autre pays et un autre fuseau. D'ou une permission
 * que l'employe de bureau n'a pas — temps.saisir — et un tableau de bord
 * qui commence par sa feuille de temps et pas par son solde de vacances.
 */
const ROLES = [
    'employe' => [
        'rang' => 20, 'fr' => 'Employe', 'en' => 'Employee',
        'perms' => ['profil.voir', 'profil.modifier', 'profil.bancaire',
                    'conges.demander', 'absences.declarer',
                    'documents.siens', 'paie.sienne',
                    'demandes.creer', 'annuaire.voir'],
    ],
    'employe_decentralise' => [
        'rang' => 25, 'fr' => 'Employe decentralise', 'en' => 'Remote employee',
        'perms' => ['profil.voir', 'profil.modifier', 'profil.bancaire',
                    'conges.demander', 'absences.declarer',
                    'documents.siens', 'paie.sienne',
                    'demandes.creer', 'annuaire.voir', 'temps.saisir'],
    ],
    'manager' => [
        'rang' => 40, 'fr' => 'Manager', 'en' => 'Manager',
        // Pas de paie, pas de bancaire, pas de justificatif medical.
        'perms' => ['profil.voir', 'profil.modifier', 'profil.bancaire',
                    'conges.demander', 'absences.declarer',
                    'documents.siens', 'paie.sienne',
                    'demandes.creer', 'annuaire.voir', 'temps.saisir',
                    'equipe.voir', 'conges.equipe.valider',
                    'absences.equipe.voir', 'temps.equipe.valider'],
    ],
    'rh' => [
        'rang' => 60, 'fr' => 'Administrateur RH', 'en' => 'HR administrator',
        'perms' => ['profil.voir', 'profil.modifier', 'profil.bancaire',
                    'profil.tous.voir', 'profil.tous.modifier', 'profil.bancaire.tous',
                    'conges.demander', 'conges.equipe.valider', 'conges.administrer',
                    'absences.declarer', 'absences.equipe.voir', 'absences.administrer',
                    'absences.justificatif',
                    'documents.siens', 'documents.gerer',
                    'paie.sienne', 'paie.gerer',
                    'demandes.creer', 'demandes.traiter',
                    'equipe.voir', 'annuaire.voir', 'actualites.publier',
                    'formations.gerer', 'avantages.gerer',
                    'temps.saisir', 'temps.equipe.valider', 'temps.administrer',
                    'recrutement.gerer', 'rh.tableau',
                    'admin.utilisateurs', 'admin.journal'],
    ],
    'admin' => [
        // Administrateur SYSTEME. Il configure la plateforme et les roles.
        // Il n'a pas plus de droit sur les dossiers que la RH : « * » lui
        // donne tout parce que sans cela il pourrait s'enfermer dehors en
        // retirant une permission. C'est une decision assumee et c'est la
        // raison pour laquelle ses actions sont toutes auditees.
        'rang' => 90, 'fr' => 'Administrateur systeme', 'en' => 'System administrator',
        'perms' => '*',
    ],
];

/** Permissions qui exigent une reauthentification recente (section 4). */
const PERMISSIONS_SENSIBLES = ['profil.bancaire', 'profil.bancaire.tous'];

/* ------------------------------------------------------------------ */
/* Session                                                             */
/* ------------------------------------------------------------------ */

function utilisateur(): ?array
{
    static $u = false;
    if ($u === false) {
        $u = null;
        $jeton = $_COOKIE['rh_session'] ?? null;
        if ($jeton) {
            $s = qun('SELECT * FROM sessions WHERE jeton = ?', [hash('sha256', $jeton)]);
            if ($s && strtotime($s['expire_le'] . ' UTC') > time()) {
                $c = qun('SELECT * FROM utilisateurs WHERE id = ?', [(int) $s['utilisateur_id']]);
                if ($c && (int) $c['actif'] === 1 && ($c['statut'] ?? '') !== 'parti') {
                    $u = $c;
                    $GLOBALS['rh_session'] = $s;
                    // La derniere vue ne s'ecrit qu'une fois par heure : une
                    // ecriture a chaque page transforme chaque lecture en
                    // ecriture, et c'est ce qui met une base a genoux.
                    if (!$c['vu_le'] || time() - strtotime($c['vu_le'] . ' UTC') > 3600) {
                        maj('utilisateurs', (int) $c['id'], ['vu_le' => maintenant()]);
                    }
                }
            }
        }
    }
    return $u;
}

function connecte(): bool { return utilisateur() !== null; }

function ouvrir_session(int $utilisateur_id): string
{
    $jeton = bin2hex(random_bytes(32));
    insere('sessions', [
        'jeton'          => hash('sha256', $jeton),
        'utilisateur_id' => $utilisateur_id,
        'cree_le'        => maintenant(),
        'expire_le'      => gmdate('Y-m-d H:i:s', time() + cfg('duree_session')),
        'ip'             => ip_client(),
        'agent'          => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
    ]);
    if (!headers_sent()) {
        setcookie('rh_session', $jeton, [
            'expires'  => time() + cfg('duree_session'),
            'path'     => '/',
            'httponly' => true,
            'secure'   => (bool) cfg('cookie_secure'),
            'samesite' => 'Lax',
        ]);
    }
    // $_COOKIE n'est pas repeuple dans la meme requete : sans cette ligne,
    // l'audit de la connexion serait attribue au « systeme » au lieu de la
    // personne qui vient d'entrer.
    acteur_audit($utilisateur_id);
    return $jeton;
}

function fermer_session(): void
{
    $jeton = $_COOKIE['rh_session'] ?? null;
    if ($jeton) {
        q('DELETE FROM sessions WHERE jeton = ?', [hash('sha256', $jeton)]);
        if (!headers_sent()) {
            setcookie('rh_session', '', ['expires' => time() - 3600, 'path' => '/']);
        }
    }
}

/* ------------------------------------------------------------------ */
/* Permissions                                                         */
/* ------------------------------------------------------------------ */

function role_de(?array $u): array
{
    if (!$u) return ['rang' => 0, 'fr' => 'Visiteur', 'en' => 'Visitor',
                     'perms' => [], 'cle' => 'visiteur'];
    $r = qun('SELECT * FROM roles WHERE id = ?', [(int) $u['role_id']]);
    $cle = $r['cle'] ?? 'employe';
    return (ROLES[$cle] ?? ROLES['employe']) + ['cle' => $cle];
}

/**
 * Les permissions d'un role, LUES EN BASE.
 *
 * La constante ROLES declare la configuration livree ; c'est elle que
 * l'installeur ecrit dans `role_permissions`. Mais c'est la TABLE qui fait
 * foi a l'execution — sinon la table n'est qu'un decor et la page
 * d'administration des permissions ne change rien.
 *
 * Deux garde-fous, tous deux orientes vers le defaut declare et jamais vers
 * l'ouverture :
 *
 * - un role declare « * » reste tout-puissant sans passer par la table. Une
 *   permission ajoutee dans le code apres la derniere installation
 *   manquerait sinon a l'administrateur, qui s'enfermerait dehors.
 * - un role SANS AUCUNE ligne en base retombe sur la declaration du code.
 *   Une table vide veut dire « pas encore installe », pas « plus aucun
 *   droit ».
 */
function permissions_de_role(string $cle)
{
    static $cache = [];
    if (array_key_exists($cle, $cache)) return $cache[$cle];

    $declare = ROLES[$cle]['perms'] ?? [];
    if ($declare === '*') return $cache[$cle] = '*';

    try {
        $lignes = qtous(
            'SELECT p.cle FROM role_permissions rp
             JOIN roles r ON r.id = rp.role_id
             JOIN permissions p ON p.id = rp.permission_id
             WHERE r.cle = ?', [$cle]);
    } catch (Throwable) {
        return $cache[$cle] = $declare;      // avant installation
    }
    $en_base = array_column($lignes, 'cle');
    return $cache[$cle] = $en_base ?: $declare;
}

function peut(string $permission, ?array $u = null): bool
{
    $u = $u ?? utilisateur();
    if (!$u) return false;
    if (($u['statut'] ?? '') === 'suspendu') return false;

    $perms = permissions_de_role(role_de($u)['cle']);
    if ($perms === '*') return true;
    return in_array($permission, $perms, true);
}

function exige(string $permission): void
{
    if (peut($permission)) return;
    if (!connecte()) {
        // Sur l'interface, un visiteur non connecte est ENVOYE vers la
        // connexion en gardant l'adresse demandee : sinon un signet vers
        // /paie renvoie une page d'erreur et perd la destination. L'API,
        // elle, doit recevoir un vrai 401 et pas une redirection HTML.
        if (!est_api()) {
            $retour = (string) ($_SERVER['REQUEST_URI'] ?? '/');
            redirige('/connexion?retour=' . rawurlencode($retour));
        }
        reponse_refus(401, t('refus_connexion'));
    }
    reponse_refus(403, t('refus_droit'));
}

/**
 * Refus unique pour l'interface ET pour l'API. Le seul moyen d'etre sur que
 * les deux cotes refusent pareil est qu'il n'y ait qu'un chemin de refus,
 * appele par les deux.
 */
function reponse_refus(int $code, string $message): never
{
    http_response_code($code);
    if (est_api()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['erreur' => $message, 'code' => $code], JSON_UNESCAPED_UNICODE);
    } else {
        rendre('erreur', ['code' => $code, 'message' => $message]);
    }
    exit;
}

function est_api(): bool
{
    return str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/');
}

/* ------------------------------------------------------------------ */
/* Reauthentification (section 4 : « authentification supplementaire ») */
/* ------------------------------------------------------------------ */

/**
 * Une session ouverte ce matin ne suffit pas pour changer un numero de
 * compte a 16 h. L'ecran est peut-etre reste deverrouille. On redemande
 * donc le mot de passe, et la validite de cette redemande est courte.
 *
 * Elle est stockee sur la LIGNE DE SESSION, pas dans un cookie ni dans
 * $_SESSION : un cookie se rejoue, une ligne de session s'invalide en la
 * supprimant, et c'est ce que fait la deconnexion.
 */
function reauth_valide(): bool
{
    $s = $GLOBALS['rh_session'] ?? null;
    if (!$s || empty($s['reauth_le'])) return false;
    return time() - strtotime($s['reauth_le'] . ' UTC') <= (int) cfg('duree_reauth');
}

function reauth_marque(): void
{
    $s = $GLOBALS['rh_session'] ?? null;
    if (!$s) return;
    maj('sessions', (int) $s['id'], ['reauth_le' => maintenant()]);
    $GLOBALS['rh_session']['reauth_le'] = maintenant();
}

function exige_reauth(string $retour): void
{
    if (reauth_valide()) return;
    redirige('/reauthentification?retour=' . rawurlencode($retour));
}

/* ------------------------------------------------------------------ */
/* Connexion                                                           */
/* ------------------------------------------------------------------ */

function verifier_identifiants(string $email, string $mdp): ?array
{
    $email = trim(mb_strtolower($email));
    $u = qun('SELECT * FROM utilisateurs WHERE email = ?', [$email]);

    // On hache meme quand le compte n'existe pas. Sans cela, un compte
    // inconnu repond plus vite qu'un mot de passe faux, et cette difference
    // suffit a savoir qui travaille dans l'entreprise.
    $hache = $u['mot_de_passe'] ?? '$2y$12$invalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidin';
    $ok = password_verify($mdp, $hache);

    if (!$u || !$ok) return null;
    if ((int) $u['actif'] !== 1 || ($u['statut'] ?? '') === 'parti') return null;
    return $u;
}

function hache_mdp(string $mdp): string
{
    return password_hash($mdp, PASSWORD_BCRYPT, ['cost' => 12]);
}

function mdp_acceptable(string $mdp): bool
{
    // Longueur d'abord. Une regle « une majuscule, un chiffre, un symbole »
    // produit surtout des mots de passe de 8 caracteres notes sur un
    // post-it ; une longueur minimale de 10 en produit de meilleurs.
    return mb_strlen($mdp) >= 10;
}

function mdp_temporaire(): string
{
    // Lisible a voix haute et sans caracteres ambigus (0/O, 1/l/I).
    $a = 'abcdefghjkmnpqrstuvwxyz';
    $c = '23456789';
    $out = '';
    for ($i = 0; $i < 4; $i++) $out .= $a[random_int(0, strlen($a) - 1)];
    $out .= '-';
    for ($i = 0; $i < 4; $i++) $out .= $c[random_int(0, strlen($c) - 1)];
    $out .= '-';
    for ($i = 0; $i < 4; $i++) $out .= $a[random_int(0, strlen($a) - 1)];
    return $out;
}

/* ------------------------------------------------------------------ */
/* Portee : qui a le droit de voir le dossier de qui                    */
/* ------------------------------------------------------------------ */

/** Les identifiants de l'equipe d'un manager, directs ET indirects. */
function equipe_de(int $manager_id, int $profondeur = 6): array
{
    $ids = [];
    $niveau = [$manager_id];
    while ($niveau && $profondeur-- > 0) {
        $in = implode(',', array_fill(0, count($niveau), '?'));
        $lignes = qtous("SELECT id FROM utilisateurs WHERE manager_id IN ($in)", $niveau);
        $niveau = array_map('intval', array_column($lignes, 'id'));
        $niveau = array_values(array_diff($niveau, $ids));
        $ids = array_merge($ids, $niveau);
    }
    return $ids;
}

/**
 * Peut-on consulter le dossier de $cible_id, et jusqu'ou ?
 *
 * Rend 'complet' (RH / admin), 'equipe' (manager sur un subordonne),
 * 'soi', ou null. Les vues se servent de cette valeur pour decider quoi
 * afficher — mais chaque route sensible revalide de son cote : une vue qui
 * cache un champ ne l'a pas protege.
 */
function portee_dossier(int $cible_id): ?string
{
    $u = utilisateur();
    if (!$u) return null;
    if ((int) $u['id'] === $cible_id) return 'soi';
    if (peut('profil.tous.voir')) return 'complet';
    if (peut('equipe.voir') && in_array($cible_id, equipe_de((int) $u['id']), true)) return 'equipe';
    return null;
}

function est_mon_subordonne(int $cible_id): bool
{
    $u = utilisateur();
    return $u ? in_array($cible_id, equipe_de((int) $u['id']), true) : false;
}

/* ------------------------------------------------------------------ */
/* Jeton anti-CSRF                                                     */
/* ------------------------------------------------------------------ */

function jeton_csrf(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function champ_csrf(): string
{
    return '<input type="hidden" name="csrf" value="' . h(jeton_csrf()) . '">';
}

function verifie_csrf(): void
{
    $recu = (string) ($_POST['csrf'] ?? '');
    if ($recu === '' || !hash_equals(jeton_csrf(), $recu)) {
        journal('alerte', 'jeton csrf invalide', ['uri' => $_SERVER['REQUEST_URI'] ?? '']);
        reponse_refus(403, t('refus_csrf'));
    }
}
