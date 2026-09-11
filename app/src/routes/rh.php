<?php
/**
 * Routes de l'administration RH (sections 2.3, 8, 9, 21) et du recrutement.
 */

declare(strict_types=1);

function page_rh(): void
{
    rendre('rh_tableau', tableau_rh());
}

/* ------------------------------------------------------------------ */
/* Dossiers                                                            */
/* ------------------------------------------------------------------ */

function page_rh_employes(): void
{
    $q = trim((string) ($_GET['q'] ?? ''));
    $statut = (string) ($_GET['statut'] ?? 'actif');
    $where = ['1=1']; $p = [];
    if ($statut !== '' && $statut !== 'tous') { $where[] = 'u.statut = ?'; $p[] = $statut; }
    if ($q !== '') {
        $where[] = '(u.nom LIKE ? OR u.prenom LIKE ? OR u.email LIKE ? OR u.matricule LIKE ?)';
        $like = '%' . $q . '%'; array_push($p, $like, $like, $like, $like);
    }
    $w = implode(' AND ', $where);
    $lignes = qtous("SELECT u.*, r.cle AS role_cle, d.nom_fr AS dep_fr, d.nom_en AS dep_en
                     FROM utilisateurs u
                     LEFT JOIN roles r ON r.id = u.role_id
                     LEFT JOIN departements d ON d.id = u.departement_id
                     WHERE $w ORDER BY u.nom, u.prenom LIMIT 300", $p);
    rendre('rh_employes', ['lignes' => $lignes, 'q' => $q, 'statut' => $statut]);
}

function page_rh_employe(string $id): void
{
    $c = employe_enrichi((int) $id);
    if (!$c) { http_response_code(404); rendre('erreur', ['code' => 404, 'message' => t('refus_introuvable')]); return; }
    rendre('rh_employe', [
        'c' => $c,
        'soldes' => soldes_de((int) $id),
        'conges' => conges_de((int) $id, 20),
        'absences' => absences_de((int) $id, 20),
        'documents' => documents_de((int) $id),
        'bulletins' => peut('paie.gerer')
            ? qtous('SELECT b.*, p.code AS periode_code, p.paiement_le
                     FROM bulletins b LEFT JOIN periodes_paie p ON p.id = b.periode_id
                     WHERE b.utilisateur_id = ? ORDER BY p.paiement_le DESC LIMIT 24', [(int) $id])
            : [],
        'primes' => peut('paie.gerer') ? primes_de((int) $id) : [],
        'manquants' => champs_manquants($c),
        'departements' => qtous('SELECT * FROM departements ORDER BY rang, nom_fr'),
        'localisations' => qtous('SELECT * FROM localisations ORDER BY nom'),
        'roles' => qtous('SELECT * FROM roles ORDER BY rang'),
        'managers' => qtous("SELECT id, prenom, nom FROM utilisateurs
                             WHERE statut = 'actif' ORDER BY nom, prenom"),
        'periodes' => qtous('SELECT * FROM periodes_paie ORDER BY paiement_le DESC LIMIT 40'),
        'erreurs' => [],
    ]);
}

function page_rh_employe_nouveau(): void
{
    rendre('rh_employe_nouveau', [
        'departements' => qtous('SELECT * FROM departements ORDER BY rang, nom_fr'),
        'localisations' => qtous('SELECT * FROM localisations ORDER BY nom'),
        'roles' => qtous('SELECT * FROM roles ORDER BY rang'),
        'managers' => qtous("SELECT id, prenom, nom FROM utilisateurs
                             WHERE statut = 'actif' ORDER BY nom, prenom"),
        'erreurs' => [], 'saisie' => [], 'mdp' => null,
    ]);
}

function post_rh_employe(): void
{
    $err = [];
    $email = trim(mb_strtolower((string) ($_POST['email'] ?? '')));
    $prenom = trim((string) ($_POST['prenom'] ?? ''));
    $nom = trim((string) ($_POST['nom'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err['email'] = t('rec_err_email');
    if (mb_strlen($prenom) < 2) $err['prenom'] = t('rec_err_prenom');
    if (mb_strlen($nom) < 2) $err['nom'] = t('rec_err_nom');
    if (!$err && qval('SELECT id FROM utilisateurs WHERE email = ?', [$email]) !== null) {
        $err['email'] = t('emp_err_email_pris');
    }
    $matricule = trim((string) ($_POST['matricule'] ?? ''));
    if ($matricule === '') $matricule = matricule_suivant();
    if (!$err && qval('SELECT id FROM utilisateurs WHERE matricule = ?', [$matricule]) !== null) {
        $err['matricule'] = t('emp_err_matricule_pris');
    }

    if ($err) {
        rendre('rh_employe_nouveau', [
            'departements' => qtous('SELECT * FROM departements ORDER BY rang, nom_fr'),
            'localisations' => qtous('SELECT * FROM localisations ORDER BY nom'),
            'roles' => qtous('SELECT * FROM roles ORDER BY rang'),
            'managers' => qtous("SELECT id, prenom, nom FROM utilisateurs WHERE statut = 'actif' ORDER BY nom"),
            'erreurs' => $err, 'saisie' => $_POST, 'mdp' => null,
        ]);
        return;
    }

    $pays = in_array($_POST['pays_travail'] ?? '', array_keys(cfg('pays')), true)
        ? (string) $_POST['pays_travail'] : (string) cfg('pays_defaut');
    $defauts = cfg('pays')[$pays];

    // Le mot de passe est TIRE AU HASARD et montre UNE FOIS a la RH, qui le
    // transmet par le canal de son choix. Il n'est pas envoye par courriel
    // (aucun expediteur configure) et il n'est jamais reaffichable : la
    // seule sortie possible ensuite est de le regenerer.
    $mdp = mdp_temporaire();

    $id = insere('utilisateurs', [
        'matricule' => $matricule, 'email' => $email,
        'mot_de_passe' => hache_mdp($mdp), 'doit_changer' => 1, 'actif' => 1,
        'role_id' => (int) ($_POST['role_id'] ?? 0)
            ?: (int) qval("SELECT id FROM roles WHERE cle = 'employe'"),
        'prenom' => mb_substr($prenom, 0, 120), 'nom' => mb_substr($nom, 0, 120),
        'poste' => mb_substr(trim((string) ($_POST['poste'] ?? '')), 0, 190),
        'departement_id' => (int) ($_POST['departement_id'] ?? 0) ?: null,
        'manager_id' => (int) ($_POST['manager_id'] ?? 0) ?: null,
        'localisation_id' => (int) ($_POST['localisation_id'] ?? 0) ?: null,
        'date_embauche' => date_valide((string) ($_POST['date_embauche'] ?? ''))
            ? (string) $_POST['date_embauche'] : null,
        'type_contrat' => mb_substr((string) ($_POST['type_contrat'] ?? ''), 0, 40),
        'statut' => 'actif',
        'temps_travail' => in_array($_POST['temps_travail'] ?? '', ['plein', 'partiel'], true)
            ? (string) $_POST['temps_travail'] : 'plein',
        'decentralise' => !empty($_POST['decentralise']) ? 1 : 0,
        'pays_travail' => $pays,
        'fuseau' => in_array($_POST['fuseau'] ?? '', timezone_identifiers_list(), true)
            ? (string) $_POST['fuseau'] : $defauts['fuseau'],
        'langue' => in_array($_POST['langue'] ?? '', cfg('langues'), true)
            ? (string) $_POST['langue'] : (string) cfg('langue_defaut'),
        'frequence_paie' => $defauts['frequence_paie'],
        'devise' => $defauts['devise'],
        'annuaire_visible' => 1, 'annuaire_tel' => 0,
        'cree_le' => maintenant(), 'maj_le' => maintenant(),
    ]);
    audit('employe.creation', 'utilisateur', $id, [], ['email' => $email]);

    rendre('rh_employe_cree', ['id' => $id, 'email' => $email, 'mdp' => $mdp,
                               'nom' => $prenom . ' ' . $nom]);
}

function matricule_suivant(): string
{
    $annee = gmdate('Y');
    $dernier = (string) (qval("SELECT matricule FROM utilisateurs WHERE matricule LIKE ?
                               ORDER BY matricule DESC LIMIT 1", ["E$annee-%"]) ?? '');
    $n = $dernier === '' ? 0 : (int) substr($dernier, strrpos($dernier, '-') + 1);
    return sprintf('E%s-%04d', $annee, $n + 1);
}

function post_rh_employe_maj(string $id): void
{
    $id = (int) $id;
    $avant = employe($id);
    if (!$avant) { http_response_code(404); rendre('erreur', ['code' => 404, 'message' => t('refus_introuvable')]); return; }

    // Liste blanche, cote RH cette fois : elle touche au professionnel, pas
    // au mot de passe ni au compte bancaire, qui ont chacun leur chemin.
    $donnees = [];
    foreach (['poste' => 'str', 'type_contrat' => 'str', 'date_embauche' => 'date',
              'date_naissance' => 'date', 'date_depart' => 'date',
              'departement_id' => 'int', 'manager_id' => 'int', 'localisation_id' => 'int',
              'role_id' => 'int', 'taux_partiel' => 'int',
              'temps_travail' => 'str', 'statut' => 'str', 'pays_travail' => 'str',
              'frequence_paie' => 'str', 'devise' => 'str', 'fuseau' => 'str',
              'prenom' => 'str', 'nom' => 'str', 'telephone' => 'str',
              'adresse' => 'str', 'urgence_nom' => 'str', 'urgence_tel' => 'str',
              'urgence_lien' => 'str'] as $c => $type) {
        if (!array_key_exists($c, $_POST)) continue;
        $v = $_POST[$c];
        $donnees[$c] = match ($type) {
            'int'  => ((int) $v) ?: null,
            'date' => date_valide((string) $v) ? (string) $v : null,
            default => mb_substr(trim((string) $v), 0, 255),
        };
    }
    $donnees['decentralise'] = !empty($_POST['decentralise']) ? 1 : 0;

    // Un manager qui se designerait lui-meme cree une boucle dans
    // l'organigramme. On refuse le cas evident tout de suite ; les boucles
    // plus longues sont detectees et rendues visibles par organigramme().
    if (($donnees['manager_id'] ?? null) === $id) $donnees['manager_id'] = null;

    if (in_array($donnees['statut'] ?? '', ['parti', 'suspendu'], true)) {
        // Un depart ferme les sessions ouvertes. Sans cela, l'onglet reste
        // valable jusqu'a l'expiration — huit heures pendant lesquelles un
        // ancien salarie lit encore ses collegues.
        q('DELETE FROM sessions WHERE utilisateur_id = ?', [$id]);
        if (($donnees['statut'] ?? '') === 'parti') $donnees['actif'] = 0;
    }

    $donnees['maj_le'] = maintenant();
    maj('utilisateurs', $id, $donnees);
    audit('employe.modification', 'utilisateur', $id, changements($avant, $donnees));
    flash('ok', t('emp_enregistre'));
    redirige('/rh/employes/' . $id);
}

/* ------------------------------------------------------------------ */
/* Conges, absences, demandes, temps                                   */
/* ------------------------------------------------------------------ */

function page_rh_conges(): void
{
    $u = utilisateur();
    rendre('rh_conges', [
        'demandes' => conges_a_valider((int) $u['id'], true),
        'types' => qtous('SELECT * FROM types_conges ORDER BY rang, id'),
        'recentes' => qtous("SELECT c.*, u.prenom, u.nom, t.nom_fr AS type_fr, t.nom_en AS type_en
                             FROM demandes_conges c
                             JOIN utilisateurs u ON u.id = c.utilisateur_id
                             JOIN types_conges t ON t.id = c.type_id
                             WHERE c.statut <> 'en_attente'
                             ORDER BY c.maj_le DESC LIMIT 50"),
    ]);
}

function page_rh_absences(): void
{
    rendre('rh_absences', [
        'absences' => qtous("SELECT a.*, u.prenom, u.nom, u.matricule
                             FROM absences a JOIN utilisateurs u ON u.id = a.utilisateur_id
                             ORDER BY CASE a.statut WHEN 'declaree' THEN 0 ELSE 1 END,
                                      a.debut DESC LIMIT 200"),
    ]);
}

function post_decision_absence(string $id): void
{
    $r = valider_absence((int) $id, (string) ($_POST['decision'] ?? ''),
                         (string) ($_POST['commentaire'] ?? ''));
    flash(isset($r['erreur']) ? 'erreur' : 'ok', $r['erreur'] ?? t('abs_decision_prise'));
    redirige('/rh/absences');
}

function page_rh_demandes(): void
{
    $filtres = [
        'statut' => (string) ($_GET['statut'] ?? ''),
        'categorie' => (string) ($_GET['categorie'] ?? ''),
        'q' => trim((string) ($_GET['q'] ?? '')),
    ];
    $page = max(1, (int) ($_GET['page'] ?? 1));
    rendre('rh_demandes', ['resultat' => demandes_rh($filtres, $page), 'filtres' => $filtres]);
}

function page_rh_temps(): void
{
    $u = utilisateur();
    rendre('rh_temps', [
        'lignes' => temps_a_valider((int) $u['id'], true),
        'decentralises' => qtous("SELECT * FROM utilisateurs
                                  WHERE decentralise = 1 AND statut = 'actif' ORDER BY nom"),
        'tous' => true,
    ]);
}

/* ------------------------------------------------------------------ */
/* Documents                                                           */
/* ------------------------------------------------------------------ */

function page_rh_documents(): void
{
    rendre('rh_documents', [
        'recents' => qtous('SELECT d.*, f.nom_origine, f.taille, u.prenom, u.nom
                            FROM documents d
                            LEFT JOIN fichiers f ON f.id = d.fichier_id
                            LEFT JOIN utilisateurs u ON u.id = d.utilisateur_id
                            ORDER BY d.cree_le DESC LIMIT 100'),
        'employes' => qtous("SELECT id, prenom, nom, matricule FROM utilisateurs
                             WHERE statut <> 'parti' ORDER BY nom, prenom"),
        'erreur' => null,
    ]);
}

function post_rh_document(): void
{
    $u = utilisateur();
    $r = deposer_fichier($_FILES['fichier'] ?? [], (int) $u['id']);
    if (isset($r['erreur'])) {
        flash('erreur', $r['erreur']);
        redirige('/rh/documents');
    }
    $cible = (int) ($_POST['utilisateur_id'] ?? 0);
    creer_document([
        'utilisateur_id' => $cible ?: null,
        'fichier_id' => (int) $r['id'],
        'categorie' => mb_substr((string) ($_POST['categorie'] ?? 'administratif'), 0, 60),
        'titre' => mb_substr(trim((string) ($_POST['titre'] ?? '')) ?: (string) $_FILES['fichier']['name'], 0, 255),
        'description' => mb_substr((string) ($_POST['description'] ?? ''), 0, 2000),
        'periode' => mb_substr((string) ($_POST['periode'] ?? ''), 0, 20),
        'ajoute_par' => (int) $u['id'],
    ]);
    if ($cible) {
        notifier($cible, 'document.ajoute', t('notif_doc_titre'),
                 (string) ($_POST['titre'] ?? ''), '/documents');
    }
    flash('ok', t('doc_depose'));
    redirige('/rh/documents');
}

/* ------------------------------------------------------------------ */
/* Paie                                                                */
/* ------------------------------------------------------------------ */

function page_rh_paie(): void
{
    $periodes = qtous('SELECT p.*,
                        (SELECT COUNT(*) FROM bulletins b WHERE b.periode_id = p.id) AS nb,
                        (SELECT COUNT(*) FROM bulletins b WHERE b.periode_id = p.id
                          AND b.publie_le IS NOT NULL) AS nb_publies
                       FROM periodes_paie p ORDER BY p.paiement_le DESC LIMIT 40');
    $en_attente = qtous('SELECT b.*, u.prenom, u.nom, p.code AS periode_code
                         FROM bulletins b
                         JOIN utilisateurs u ON u.id = b.utilisateur_id
                         LEFT JOIN periodes_paie p ON p.id = b.periode_id
                         WHERE b.publie_le IS NULL ORDER BY b.cree_le DESC LIMIT 100');
    rendre('rh_paie', [
        'periodes' => $periodes,
        'en_attente' => $en_attente,
        'employes' => qtous("SELECT id, prenom, nom, matricule, devise, frequence_paie
                             FROM utilisateurs WHERE statut = 'actif' ORDER BY nom, prenom"),
        'pays' => cfg('pays'),
        'erreurs' => [],
    ]);
}

function post_rh_periodes(): void
{
    $r = generer_periodes(
        (string) ($_POST['pays'] ?? ''),
        (string) ($_POST['frequence'] ?? ''),
        (string) ($_POST['debut'] ?? ''),
        (int) ($_POST['combien'] ?? 12),
        (int) ($_POST['decalage'] ?? 5));
    flash(isset($r['erreur']) ? 'erreur' : 'ok',
          $r['erreur'] ?? t('paie_periodes_creees', ['n' => (int) $r['crees']]));
    redirige('/rh/paie');
}

function post_rh_bulletin(): void
{
    $lignes = [];
    $types = $_POST['ligne_type'] ?? [];
    foreach ($types as $i => $type) {
        $lignes[] = [
            'type' => (string) $type,
            'libelle' => (string) ($_POST['ligne_libelle'][$i] ?? ''),
            'montant' => (float) str_replace([' ', ','], ['', '.'],
                            (string) ($_POST['ligne_montant'][$i] ?? '0')),
        ];
    }
    $r = enregistrer_bulletin($_POST, $lignes);
    if (isset($r['erreur']) || isset($r['erreurs'])) {
        flash('erreur', $r['erreur'] ?? implode(' · ', $r['erreurs']));
        redirige('/rh/paie');
    }
    flash('ok', t('paie_bulletin_saisi'));
    redirige('/rh/paie');
}

function post_rh_publier(string $id): void
{
    $r = publier_bulletin((int) $id);
    flash(isset($r['erreur']) ? 'erreur' : 'ok', $r['erreur'] ?? t('paie_bulletin_publie'));
    redirige('/rh/paie');
}

function post_rh_prime(): void
{
    $r = accorder_prime((int) ($_POST['utilisateur_id'] ?? 0), $_POST);
    if (isset($r['erreur']) || isset($r['erreurs'])) {
        flash('erreur', $r['erreur'] ?? implode(' · ', $r['erreurs']));
    } else {
        flash('ok', t('paie_prime_accordee'));
    }
    redirige((string) ($_POST['retour'] ?? '/rh/paie'));
}

/* ------------------------------------------------------------------ */
/* Actualites                                                          */
/* ------------------------------------------------------------------ */

function page_rh_actualites(): void
{
    rendre('rh_actualites', [
        'lignes' => qtous('SELECT a.*, u.prenom, u.nom FROM actualites a
                           LEFT JOIN utilisateurs u ON u.id = a.auteur_id
                           ORDER BY a.cree_le DESC LIMIT 100'),
        'erreurs' => [], 'saisie' => [],
    ]);
}

function post_rh_actualite(): void
{
    $r = enregistrer_actualite($_POST, (int) ($_POST['id'] ?? 0) ?: null);
    if (isset($r['erreur']) || isset($r['erreurs'])) {
        flash('erreur', $r['erreur'] ?? implode(' · ', $r['erreurs']));
    } else {
        flash('ok', t('act_enregistree'));
    }
    redirige('/rh/actualites');
}

/* ------------------------------------------------------------------ */
/* Recrutement                                                         */
/* ------------------------------------------------------------------ */

function page_rh_offres(): void
{
    rendre('rh_offres', [
        'offres' => qtous('SELECT o.*, d.nom_fr AS dep_fr, d.nom_en AS dep_en,
                            (SELECT COUNT(*) FROM candidatures c WHERE c.offre_id = o.id) AS nb_cand
                           FROM offres o LEFT JOIN departements d ON d.id = o.departement_id
                           ORDER BY o.cree_le DESC'),
        'departements' => qtous('SELECT * FROM departements ORDER BY rang, nom_fr'),
        'localisations' => qtous('SELECT * FROM localisations ORDER BY nom'),
        'examens' => qtous('SELECT * FROM examens WHERE actif = 1 ORDER BY titre'),
        'pays' => cfg('pays'),
        'erreurs' => [], 'saisie' => [],
    ]);
}

function post_rh_offre(): void
{
    $r = enregistrer_offre($_POST, (int) ($_POST['id'] ?? 0) ?: null);
    if (isset($r['erreur']) || isset($r['erreurs'])) {
        flash('erreur', $r['erreur'] ?? implode(' · ', $r['erreurs']));
    } else {
        flash('ok', t('rec_offre_enregistree'));
    }
    redirige('/rh/offres');
}

function page_rh_candidatures(): void
{
    $filtres = [
        'statut' => (string) ($_GET['statut'] ?? ''),
        'offre_id' => (int) ($_GET['offre_id'] ?? 0),
        'q' => trim((string) ($_GET['q'] ?? '')),
    ];
    rendre('rh_candidatures', [
        'resultat' => candidatures($filtres, max(1, (int) ($_GET['page'] ?? 1))),
        'filtres' => $filtres,
        'offres' => qtous('SELECT id, titre, reference FROM offres ORDER BY cree_le DESC'),
    ]);
}

function page_rh_candidature(string $id): void
{
    $c = qun('SELECT c.*, o.titre AS offre_titre, o.reference AS offre_ref, o.examen_id
              FROM candidatures c LEFT JOIN offres o ON o.id = c.offre_id WHERE c.id = ?', [(int) $id]);
    if (!$c) { http_response_code(404); rendre('erreur', ['code' => 404, 'message' => t('refus_introuvable')]); return; }

    $reponses = [];
    if ($c['examen_statut'] === 'termine') {
        $reponses = qtous('SELECT r.*, q.enonce, q.type, q.choix, q.bonne, q.points AS bareme
                           FROM reponses_examen r JOIN questions_examen q ON q.id = r.question_id
                           WHERE r.candidature_id = ? ORDER BY q.rang, q.id', [(int) $id]);
    }
    rendre('rh_candidature', ['c' => $c, 'reponses' => $reponses]);
}

function post_rh_candidature(string $id): void
{
    $r = changer_statut_candidature((int) $id, (string) ($_POST['statut'] ?? ''),
                                    (string) ($_POST['note_rh'] ?? ''));
    flash(isset($r['erreur']) ? 'erreur' : 'ok', $r['erreur'] ?? t('rec_statut_change'));
    redirige('/rh/candidatures/' . (int) $id);
}
