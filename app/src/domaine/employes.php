<?php
/**
 * Le dossier salarie (sections 4, 15, 16).
 */

declare(strict_types=1);

function employe(int $id): ?array
{
    return qun('SELECT * FROM utilisateurs WHERE id = ?', [$id]);
}

function employe_enrichi(int $id): ?array
{
    $u = employe($id);
    if (!$u) return null;
    $u['departement'] = $u['departement_id']
        ? qun('SELECT * FROM departements WHERE id = ?', [(int) $u['departement_id']]) : null;
    $u['localisation'] = $u['localisation_id']
        ? qun('SELECT * FROM localisations WHERE id = ?', [(int) $u['localisation_id']]) : null;
    $u['manager'] = $u['manager_id'] ? employe((int) $u['manager_id']) : null;
    $u['role'] = role_de($u);
    return $u;
}

/**
 * L'anciennete, en annees et mois pleins.
 *
 * Elle est CALCULEE a chaque affichage a partir de la date d'embauche.
 * Une colonne « anciennete » serait fausse le lendemain de son ecriture, et
 * personne ne s'en apercoit parce qu'elle a l'air juste.
 */
function anciennete(?string $date_embauche): ?array
{
    if (!$date_embauche) return null;
    try {
        $d = new DateTimeImmutable($date_embauche);
    } catch (Throwable) { return null; }
    $n = new DateTimeImmutable(aujourdhui());
    if ($d > $n) return ['annees' => 0, 'mois' => 0, 'futur' => true];
    $i = $d->diff($n);
    return ['annees' => (int) $i->y, 'mois' => (int) $i->m, 'futur' => false];
}

function anciennete_texte(?string $date_embauche): string
{
    $a = anciennete($date_embauche);
    if ($a === null) return '';
    if ($a['futur']) return t('anc_a_venir');
    if ($a['annees'] === 0 && $a['mois'] === 0) return t('anc_moins_un_mois');
    $bouts = [];
    if ($a['annees'] > 0) $bouts[] = tn('anc_annee', $a['annees']);
    if ($a['mois'] > 0)   $bouts[] = tn('anc_mois', $a['mois']);
    return implode(' ', $bouts);
}

/* ------------------------------------------------------------------ */
/* Annuaire (section 15)                                               */
/* ------------------------------------------------------------------ */

/**
 * L'annuaire ne montre que ce que la fiche autorise.
 *
 * `annuaire_visible` retire quelqu'un de l'annuaire ; `annuaire_tel` decide
 * si son telephone y figure. Par defaut le telephone PERSONNEL n'y est pas :
 * il a ete donne a la RH pour la joindre en cas d'urgence, pas pour etre
 * publie a trois cents collegues.
 */
function annuaire(array $filtres = [], int $page = 1, int $par_page = 24): array
{
    $where = ["u.statut = 'actif'", 'u.annuaire_visible = 1'];
    $p = [];
    if (!empty($filtres['q'])) {
        $where[] = '(u.prenom LIKE ? OR u.nom LIKE ? OR u.poste LIKE ? OR u.email LIKE ?)';
        $like = '%' . $filtres['q'] . '%';
        array_push($p, $like, $like, $like, $like);
    }
    if (!empty($filtres['departement_id'])) {
        $where[] = 'u.departement_id = ?'; $p[] = (int) $filtres['departement_id'];
    }
    if (!empty($filtres['localisation_id'])) {
        $where[] = 'u.localisation_id = ?'; $p[] = (int) $filtres['localisation_id'];
    }
    $w = implode(' AND ', $where);
    $total = (int) qval("SELECT COUNT(*) FROM utilisateurs u WHERE $w", $p);

    // LIMIT et OFFSET ne sont pas des parametres lies : PDO en preparation
    // NATIVE les passe en chaine et MySQL refuse « LIMIT '24' ». Les deux
    // sont donc castes en entier juste ici.
    $par_page = max(1, min(100, $par_page));
    $depart = max(0, ($page - 1) * $par_page);
    $lignes = qtous(
        "SELECT u.*, d.nom_fr AS dep_fr, d.nom_en AS dep_en, l.nom AS loc_nom
         FROM utilisateurs u
         LEFT JOIN departements d ON d.id = u.departement_id
         LEFT JOIN localisations l ON l.id = u.localisation_id
         WHERE $w ORDER BY u.nom, u.prenom LIMIT $par_page OFFSET $depart", $p);

    return ['total' => $total, 'lignes' => $lignes, 'page' => $page, 'par_page' => $par_page];
}

/** La carte d'annuaire : ce qui sort d'ici est ce qui s'affiche. */
function carte_annuaire(array $u): array
{
    return [
        'id' => (int) $u['id'],
        'nom' => nom_complet($u),
        'poste' => $u['poste'] ?? '',
        'departement' => col_langue(['nom_fr' => $u['dep_fr'] ?? '', 'nom_en' => $u['dep_en'] ?? ''], 'nom'),
        'email' => $u['email'] ?? '',
        'telephone' => ((int) ($u['annuaire_tel'] ?? 0) === 1) ? ($u['telephone'] ?? '') : '',
        'localisation' => $u['loc_nom'] ?? '',
        'decentralise' => (int) ($u['decentralise'] ?? 0) === 1,
        'fuseau' => $u['fuseau'] ?? '',
    ];
}

/* ------------------------------------------------------------------ */
/* Organigramme (section 16)                                           */
/* ------------------------------------------------------------------ */

/**
 * Construit l'arbre a partir de manager_id.
 *
 * Une boucle dans les rattachements (A dirige B qui dirige A) fait tourner
 * un parcours naif jusqu'a l'epuisement de la memoire. Elle arrive pour de
 * vrai : deux changements de manager saisis dans le desordre suffisent. On
 * marque donc les noeuds deja vus et on rend la boucle VISIBLE dans le
 * resultat au lieu de planter en silence.
 */
function organigramme(): array
{
    $tous = qtous("SELECT id, prenom, nom, poste, manager_id, departement_id, decentralise
                   FROM utilisateurs WHERE statut = 'actif' ORDER BY nom, prenom");
    $par_id = [];
    foreach ($tous as $u) { $u['enfants'] = []; $par_id[(int) $u['id']] = $u; }

    $racines = []; $boucles = [];
    foreach ($par_id as $id => $u) {
        $mid = (int) ($u['manager_id'] ?? 0);
        if (!$mid || !isset($par_id[$mid])) { $racines[] = $id; continue; }
        // Detection de cycle : on remonte la chaine avant de rattacher.
        $vus = [$id => true]; $c = $mid; $cycle = false;
        while ($c && isset($par_id[$c])) {
            if (isset($vus[$c])) { $cycle = true; break; }
            $vus[$c] = true;
            $c = (int) ($par_id[$c]['manager_id'] ?? 0);
        }
        if ($cycle) { $boucles[] = $id; $racines[] = $id; continue; }
        $par_id[$mid]['enfants'][] = $id;
    }

    return ['par_id' => $par_id, 'racines' => $racines, 'boucles' => $boucles];
}

/* ------------------------------------------------------------------ */
/* Coordonnees bancaires (section 4.3)                                 */
/* ------------------------------------------------------------------ */

/**
 * Ecrit les coordonnees bancaires. Le numero complet ne repasse jamais par
 * une colonne en clair et ne va jamais dans l'audit.
 */
function enregistrer_banque(int $utilisateur_id, string $institution, string $numero,
                            string $mode_paiement): array
{
    if (!coffre_disponible()) {
        return ['erreur' => t('banque_coffre_absent')];
    }
    if (!banque_plausible($numero)) {
        return ['erreur' => t('banque_numero_invalide')];
    }
    $chiffre = coffre_chiffre($numero);
    if ($chiffre === null) return ['erreur' => t('banque_coffre_absent')];

    maj('utilisateurs', $utilisateur_id, [
        'banque_institution' => mb_substr(trim($institution), 0, 190),
        'banque_chiffre' => $chiffre,
        'banque_masque' => coffre_masque($numero),
        'banque_maj_le' => maintenant(),
        'mode_paiement' => $mode_paiement,
        'maj_le' => maintenant(),
    ]);
    // L'audit dit QUE le compte a change, QUI l'a change et QUAND. Il ne dit
    // pas quel etait le numero, ni quel il est devenu. Voir CHAMPS_OPAQUES.
    audit('banque.modification', 'utilisateur', $utilisateur_id,
          ['banque_chiffre' => ['', '']]);
    return ['ok' => true];
}

/**
 * Rend le numero EN CLAIR. Un seul appelant legitime : l'export de paie
 * fait par la RH. Chaque appel est audite nominativement.
 */
function banque_en_clair(int $utilisateur_id, string $motif): ?string
{
    if (!peut('profil.bancaire.tous')) return null;
    if (!reauth_valide()) return null;
    $c = qval('SELECT banque_chiffre FROM utilisateurs WHERE id = ?', [$utilisateur_id]);
    if (!$c) return null;
    audit('banque.lecture_claire', 'utilisateur', $utilisateur_id, [], ['motif' => $motif]);
    return coffre_dechiffre((string) $c);
}

/* ------------------------------------------------------------------ */
/* Champs a renseigner                                                 */
/* ------------------------------------------------------------------ */

/**
 * Ce qui manque dans une fiche. Sert la page /admin/a-renseigner et la
 * pastille ambre de l'interface.
 *
 * La liste ne contient QUE des champs dont l'absence a une consequence
 * concrete — pas « tout ce qui est vide ». Un contact d'urgence manquant se
 * decouvre le jour de l'accident.
 */
function champs_manquants(array $u): array
{
    $manque = [];
    foreach (['poste' => 'emp_poste', 'date_embauche' => 'emp_date_embauche',
              'type_contrat' => 'emp_type_contrat', 'telephone' => 'emp_telephone',
              'urgence_nom' => 'emp_urgence', 'urgence_tel' => 'emp_urgence_tel'] as $c => $cle) {
        if (empty($u[$c])) $manque[] = $cle;
    }
    if (empty($u['banque_masque'])) $manque[] = 'emp_banque';
    if (empty($u['manager_id']) && ($u['statut'] ?? '') === 'actif') $manque[] = 'emp_manager';
    return $manque;
}

/** Les reglages d'organisation encore vides (config.php). */
function reglages_manquants(): array
{
    $manque = [];
    foreach (['entite_juridique', 'numero_entreprise', 'adresse_postale',
              'contact_rh', 'responsable_donnees', 'domaine'] as $c) {
        if (trim((string) cfg($c)) === '') $manque[] = $c;
    }
    if (trim((string) cfg('mail_expediteur')) === '') $manque[] = 'mail_expediteur';
    if (!coffre_disponible()) $manque[] = 'cle_coffre';
    return $manque;
}
