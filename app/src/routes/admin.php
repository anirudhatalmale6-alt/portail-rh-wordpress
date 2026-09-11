<?php
/**
 * Administration systeme (section 2.4) et API (section 29).
 */

declare(strict_types=1);

function page_admin_roles(): void
{
    $roles = qtous('SELECT * FROM roles ORDER BY rang');
    rendre('admin_roles', [
        'roles' => $roles,
        'permissions' => qtous('SELECT * FROM permissions ORDER BY cle'),
        // On affiche ce que le SERVEUR APPLIQUE, c'est-a-dire le contenu de
        // role_permissions — pas la declaration du code. Un ecran qui rend
        // la declaration ne peut pas contredire le code : il devient un
        // deuxieme mensonge d'accord avec le premier.
        'appliquees' => array_combine(
            array_column($roles, 'cle'),
            array_map(fn($r) => permissions_de_role((string) $r['cle']), $roles)),
    ]);
}

function page_admin_parametres(): void
{
    rendre('admin_parametres', [
        'reglages' => qtous('SELECT * FROM reglages ORDER BY cle'),
        'manquants' => reglages_manquants(),
        'coffre' => coffre_disponible(),
        'email' => email_actif(),
        'types_conges' => qtous('SELECT * FROM types_conges ORDER BY rang, id'),
        'departements' => qtous('SELECT * FROM departements ORDER BY rang, nom_fr'),
        'localisations' => qtous('SELECT * FROM localisations ORDER BY nom'),
        'feries' => qtous('SELECT * FROM jours_feries ORDER BY pays, date LIMIT 100'),
    ]);
}

function post_admin_parametres(): void
{
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'reglage') {
        $cle = mb_substr(trim((string) ($_POST['cle'] ?? '')), 0, 120);
        $valeur = mb_substr((string) ($_POST['valeur'] ?? ''), 0, 4000);
        if ($cle !== '') {
            $id = qval('SELECT id FROM reglages WHERE cle = ?', [$cle]);
            $avant = $id !== null ? (string) qval('SELECT valeur FROM reglages WHERE id = ?', [(int) $id]) : null;
            if ($id === null) insere('reglages', ['cle' => $cle, 'valeur' => $valeur]);
            else maj('reglages', (int) $id, ['valeur' => $valeur]);
            audit('reglage.modification', 'reglage', $id ? (int) $id : null,
                  [$cle => [$avant, $valeur]]);
        }
    } elseif ($action === 'ferie') {
        $pays = mb_substr((string) ($_POST['pays'] ?? ''), 0, 8);
        $date = (string) ($_POST['date'] ?? '');
        if ($pays !== '' && date_valide($date)
            && qval('SELECT id FROM jours_feries WHERE pays = ? AND date = ?', [$pays, $date]) === null) {
            insere('jours_feries', [
                'pays' => $pays, 'date' => $date,
                'nom_fr' => mb_substr((string) ($_POST['nom_fr'] ?? ''), 0, 150),
                'nom_en' => mb_substr((string) ($_POST['nom_en'] ?? ''), 0, 150),
                'chome' => !empty($_POST['chome']) ? 1 : 0,
            ]);
            audit('ferie.ajout', 'ferie', null, [], ['pays' => $pays, 'date' => $date]);
        }
    } elseif ($action === 'type_conge') {
        $cle = slug((string) ($_POST['cle'] ?? ''));
        if ($cle !== '' && qval('SELECT id FROM types_conges WHERE cle = ?', [$cle]) === null) {
            insere('types_conges', [
                'cle' => $cle,
                'nom_fr' => mb_substr((string) ($_POST['nom_fr'] ?? ''), 0, 150),
                'nom_en' => mb_substr((string) ($_POST['nom_en'] ?? ''), 0, 150),
                'paye' => !empty($_POST['paye']) ? 1 : 0,
                'justificatif' => !empty($_POST['justificatif']) ? 1 : 0,
                'solde_defaut' => (float) ($_POST['solde_defaut'] ?? 0),
                'pays' => mb_substr((string) ($_POST['pays'] ?? ''), 0, 8),
                'actif' => 1,
                'rang' => (int) qval('SELECT COALESCE(MAX(rang),0)+1 FROM types_conges'),
            ]);
            audit('type_conge.ajout', 'type_conge', null, [], ['cle' => $cle]);
        }
    }
    flash('ok', t('adm_enregistre'));
    redirige('/admin/parametres');
}

function page_admin_journal(): void
{
    $filtres = [
        'action' => (string) ($_GET['action'] ?? ''),
        'objet' => (string) ($_GET['objet'] ?? ''),
        'utilisateur_id' => (int) ($_GET['utilisateur_id'] ?? 0),
    ];
    $where = ['1=1']; $p = [];
    if ($filtres['action'] !== '') { $where[] = 'j.action LIKE ?'; $p[] = $filtres['action'] . '%'; }
    if ($filtres['objet'] !== '')  { $where[] = 'j.objet = ?';     $p[] = $filtres['objet']; }
    if ($filtres['utilisateur_id']) { $where[] = 'j.utilisateur_id = ?'; $p[] = $filtres['utilisateur_id']; }
    $w = implode(' AND ', $where);
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $par_page = 50;
    $depart = ($page - 1) * $par_page;
    $total = (int) qval("SELECT COUNT(*) FROM journal_audit j WHERE $w", $p);
    $lignes = qtous("SELECT j.*, u.prenom, u.nom FROM journal_audit j
                     LEFT JOIN utilisateurs u ON u.id = j.utilisateur_id
                     WHERE $w ORDER BY j.cree_le DESC, j.id DESC
                     LIMIT $par_page OFFSET $depart", $p);
    rendre('admin_journal', [
        'lignes' => $lignes, 'total' => $total, 'page' => $page,
        'par_page' => $par_page, 'filtres' => $filtres,
        'actions' => qtous('SELECT DISTINCT action FROM journal_audit ORDER BY action'),
    ]);
}

/* ------------------------------------------------------------------ */
/* API (section 29)                                                    */
/* ------------------------------------------------------------------ */

function json_sortie($donnees, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, no-store');
    echo json_encode($donnees, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function api_moi(): void
{
    $u = utilisateur();
    // L'API rend le MASQUE bancaire, jamais le numero. Ce n'est pas une
    // omission : c'est la meme regle que l'interface, appliquee au meme
    // endroit, pour que les deux ne puissent pas diverger.
    json_sortie([
        'id' => (int) $u['id'], 'matricule' => $u['matricule'],
        'nom' => nom_complet($u), 'email' => $u['email'],
        'poste' => $u['poste'], 'role' => role_de($u)['cle'],
        'decentralise' => (int) $u['decentralise'] === 1,
        'fuseau' => $u['fuseau'], 'langue' => $u['langue'],
        'banque_masque' => $u['banque_masque'] ?: null,
        'soldes' => array_map(fn($s) => [
            'type' => $s['type']['cle'], 'restant' => $s['restant'],
            'disponible' => $s['disponible'],
        ], soldes_de((int) $u['id'])),
        'notifications_non_lues' => compte_non_lues((int) $u['id']),
    ]);
}

function api_annuaire(): void
{
    $r = annuaire(['q' => trim((string) ($_GET['q'] ?? ''))],
                  max(1, (int) ($_GET['page'] ?? 1)));
    json_sortie([
        'total' => $r['total'], 'page' => $r['page'],
        'resultats' => array_map('carte_annuaire', $r['lignes']),
    ]);
}

function api_notifications(): void
{
    $u = utilisateur();
    json_sortie(['non_lues' => compte_non_lues((int) $u['id']),
                 'recentes' => notifications_de((int) $u['id'], false, 20)]);
}

function api_offres(): void
{
    $out = [];
    foreach (offres_publiees() as $o) {
        $out[] = [
            'reference' => $o['reference'], 'titre' => $o['titre'],
            'lien' => '/carrieres/' . $o['slug'],
            'pays' => $o['pays'], 'type_contrat' => $o['type_contrat'],
            'teletravail' => $o['teletravail'],
            'localisation' => $o['loc_nom'],
            'publie_le' => $o['publie_le'],
            // Pas de fourchette de salaire inventee : le champ est rendu
            // tel quel, vide s'il est vide.
            'remuneration' => $o['salaire_texte'] ?: null,
        ];
    }
    json_sortie(['total' => count($out), 'offres' => $out]);
}
