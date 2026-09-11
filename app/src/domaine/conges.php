<?php
/**
 * Conges et absences (sections 5 et 6).
 */

declare(strict_types=1);

const STATUTS_CONGE = ['en_attente', 'approuve', 'refuse', 'modification', 'annule'];

/**
 * Le solde d'un type de conge pour une annee.
 *
 * « pris » n'est PAS une colonne : c'est la somme des demandes approuvees.
 * Une colonne mise a jour a chaque approbation derive des qu'une demande est
 * annulee, corrigee ou supprimee a la main en base — et elle derive en
 * silence, parce qu'un solde faux a l'air d'un solde.
 */
function solde_conge(int $utilisateur_id, int $type_id, ?int $annee = null): array
{
    $annee = $annee ?: (int) gmdate('Y');
    $s = qun('SELECT * FROM soldes_conges WHERE utilisateur_id = ? AND type_id = ? AND annee = ?',
             [$utilisateur_id, $type_id, $annee]);
    $acquis = (float) ($s['acquis'] ?? 0);
    $ajuste = (float) ($s['ajuste'] ?? 0);

    $pris = (float) (qval(
        "SELECT COALESCE(SUM(nb_jours), 0) FROM demandes_conges
         WHERE utilisateur_id = ? AND type_id = ? AND statut = 'approuve'
           AND debut >= ? AND debut <= ?",
        [$utilisateur_id, $type_id, "$annee-01-01", "$annee-12-31"]) ?? 0);

    $reserve = (float) (qval(
        "SELECT COALESCE(SUM(nb_jours), 0) FROM demandes_conges
         WHERE utilisateur_id = ? AND type_id = ? AND statut = 'en_attente'
           AND debut >= ? AND debut <= ?",
        [$utilisateur_id, $type_id, "$annee-01-01", "$annee-12-31"]) ?? 0);

    return [
        'annee' => $annee,
        'acquis' => $acquis, 'ajuste' => $ajuste,
        'pris' => $pris, 'reserve' => $reserve,
        'restant' => $acquis + $ajuste - $pris,
        // Le solde « disponible » retire aussi ce qui est en attente. C'est
        // celui qu'on montre au salarie : sinon il pose deux demandes pour
        // les memes jours et decouvre le probleme apres l'approbation.
        'disponible' => $acquis + $ajuste - $pris - $reserve,
        'configure' => $s !== null,
    ];
}

function soldes_de(int $utilisateur_id, ?int $annee = null): array
{
    $out = [];
    foreach (qtous('SELECT * FROM types_conges WHERE actif = 1 ORDER BY rang, id') as $ty) {
        $out[] = ['type' => $ty] + solde_conge($utilisateur_id, (int) $ty['id'], $annee);
    }
    return $out;
}

/**
 * Cree une demande de conge. Rend ['id' => n] ou ['erreurs' => [...]].
 *
 * Le nombre de jours est calcule ICI, cote serveur, a partir des dates. Le
 * formulaire l'affiche pour information ; s'il etait envoye par le client,
 * n'importe qui poserait trois semaines comptees pour un jour.
 */
function demander_conge(int $utilisateur_id, array $d): array
{
    $err = [];
    $type = qun('SELECT * FROM types_conges WHERE id = ? AND actif = 1', [(int) ($d['type_id'] ?? 0)]);
    if (!$type) $err['type_id'] = t('cg_err_type');

    $debut = (string) ($d['debut'] ?? '');
    $fin   = (string) ($d['fin'] ?? '');
    if (!date_valide($debut)) $err['debut'] = t('cg_err_date');
    if (!date_valide($fin))   $err['fin'] = t('cg_err_date');
    if (!$err && $fin < $debut) $err['fin'] = t('cg_err_ordre');

    if ($err) return ['erreurs' => $err];

    $u = employe($utilisateur_id);
    $pays = (string) ($u['pays_travail'] ?? cfg('pays_defaut'));
    $jours = jours_ouvres($debut, $fin, $pays);
    if (!empty($d['demi_debut'])) $jours -= 0.5;
    if (!empty($d['demi_fin']) && $fin !== $debut) $jours -= 0.5;
    if ($jours <= 0) return ['erreurs' => ['debut' => t('cg_err_zero_jour')]];

    // Chevauchement avec une demande deja posee. Un conge pose deux fois est
    // approuve deux fois, et le solde se retrouve entame deux fois.
    $chevauche = qval(
        "SELECT id FROM demandes_conges
         WHERE utilisateur_id = ? AND statut IN ('en_attente','approuve')
           AND debut <= ? AND fin >= ?", [$utilisateur_id, $fin, $debut]);
    if ($chevauche !== null) return ['erreurs' => ['debut' => t('cg_err_chevauche')]];

    $id = insere('demandes_conges', [
        'utilisateur_id' => $utilisateur_id,
        'type_id' => (int) $type['id'],
        'debut' => $debut, 'fin' => $fin,
        'demi_debut' => !empty($d['demi_debut']) ? 1 : 0,
        'demi_fin' => !empty($d['demi_fin']) ? 1 : 0,
        'nb_jours' => $jours,
        'motif' => mb_substr((string) ($d['motif'] ?? ''), 0, 2000),
        'statut' => 'en_attente',
        'cree_le' => maintenant(), 'maj_le' => maintenant(),
    ]);
    audit('conge.demande', 'conge', $id, [], ['jours' => $jours, 'type' => $type['cle']]);

    // Le manager est prevenu. Si le salarie n'a pas de manager, c'est la RH
    // qui recoit — et la fiche est signalee comme incomplete ailleurs.
    $dest = (int) ($u['manager_id'] ?? 0);
    if ($dest) {
        notifier($dest, 'conge.a_valider',
                 t('notif_conge_titre', ['nom' => nom_complet($u)]),
                 t('notif_conge_corps', ['debut' => $debut, 'fin' => $fin,
                                         'jours' => nombre($jours, 1)]),
                 '/equipe/conges');
    } else {
        notifier_role('rh', 'conge.a_valider',
                      t('notif_conge_titre', ['nom' => nom_complet($u)]),
                      t('notif_conge_sans_manager'), '/rh/conges');
    }
    return ['id' => $id];
}

/**
 * Decision du manager ou de la RH.
 *
 * La verification du droit est ICI, pas seulement dans la route : c'est la
 * fonction qui ecrit, donc c'est elle qui doit refuser. Un manager ne decide
 * que pour son equipe ; la RH decide pour tout le monde.
 */
function decider_conge(int $demande_id, string $decision, string $commentaire = ''): array
{
    if (!in_array($decision, ['approuve', 'refuse', 'modification'], true)) {
        return ['erreur' => t('cg_err_decision')];
    }
    $dem = qun('SELECT * FROM demandes_conges WHERE id = ?', [$demande_id]);
    if (!$dem) return ['erreur' => t('refus_introuvable')];
    if ($dem['statut'] !== 'en_attente') return ['erreur' => t('cg_err_deja_traitee')];

    $cible = (int) $dem['utilisateur_id'];
    $u = utilisateur();
    $autorise = peut('conges.administrer')
             || (peut('conges.equipe.valider') && est_mon_subordonne($cible));
    // On ne valide pas sa propre demande, meme en etant manager. C'est la
    // regle qui empeche le seul cas ou tout le circuit d'approbation ne veut
    // plus rien dire.
    if ($u && (int) $u['id'] === $cible && !peut('conges.administrer')) $autorise = false;
    if (!$autorise) return ['erreur' => t('refus_droit')];

    maj('demandes_conges', $demande_id, [
        'statut' => $decision,
        'decideur_id' => (int) $u['id'],
        'decide_le' => maintenant(),
        'commentaire' => mb_substr($commentaire, 0, 2000),
        'maj_le' => maintenant(),
    ]);
    audit('conge.' . $decision, 'conge', $demande_id, ['statut' => ['en_attente', $decision]]);

    notifier($cible, 'conge.' . $decision,
             t('notif_conge_decision_titre_' . $decision),
             t('notif_conge_decision_corps', ['debut' => $dem['debut'], 'fin' => $dem['fin']]),
             '/conges');
    return ['ok' => true];
}

function annuler_conge(int $demande_id): array
{
    $dem = qun('SELECT * FROM demandes_conges WHERE id = ?', [$demande_id]);
    if (!$dem) return ['erreur' => t('refus_introuvable')];
    $u = utilisateur();
    $sien = $u && (int) $u['id'] === (int) $dem['utilisateur_id'];
    if (!$sien && !peut('conges.administrer')) return ['erreur' => t('refus_droit')];
    if (!in_array($dem['statut'], ['en_attente', 'approuve'], true)) {
        return ['erreur' => t('cg_err_deja_traitee')];
    }
    maj('demandes_conges', $demande_id, ['statut' => 'annule', 'maj_le' => maintenant()]);
    audit('conge.annule', 'conge', $demande_id, ['statut' => [$dem['statut'], 'annule']]);
    return ['ok' => true];
}

function conges_de(int $utilisateur_id, int $limite = 50): array
{
    $limite = max(1, min(200, $limite));
    return qtous(
        "SELECT c.*, t.cle AS type_cle, t.nom_fr AS type_fr, t.nom_en AS type_en
         FROM demandes_conges c JOIN types_conges t ON t.id = c.type_id
         WHERE c.utilisateur_id = ? ORDER BY c.debut DESC LIMIT $limite",
        [$utilisateur_id]);
}

/** Les demandes en attente que $manager a le droit de trancher. */
function conges_a_valider(int $manager_id, bool $tous = false): array
{
    if ($tous) {
        return qtous(
            "SELECT c.*, t.nom_fr AS type_fr, t.nom_en AS type_en,
                    u.prenom, u.nom, u.email
             FROM demandes_conges c
             JOIN types_conges t ON t.id = c.type_id
             JOIN utilisateurs u ON u.id = c.utilisateur_id
             WHERE c.statut = 'en_attente' ORDER BY c.debut");
    }
    $equipe = equipe_de($manager_id);
    if (!$equipe) return [];
    $in = implode(',', array_fill(0, count($equipe), '?'));
    return qtous(
        "SELECT c.*, t.nom_fr AS type_fr, t.nom_en AS type_en,
                u.prenom, u.nom, u.email
         FROM demandes_conges c
         JOIN types_conges t ON t.id = c.type_id
         JOIN utilisateurs u ON u.id = c.utilisateur_id
         WHERE c.statut = 'en_attente' AND c.utilisateur_id IN ($in)
         ORDER BY c.debut", $equipe);
}

/** Le calendrier d'equipe : qui est absent entre deux dates. */
function calendrier_absences(array $ids, string $debut, string $fin): array
{
    if (!$ids) return [];
    $in = implode(',', array_fill(0, count($ids), '?'));
    $p = [...$ids, $fin, $debut];
    $conges = qtous(
        "SELECT c.utilisateur_id, c.debut, c.fin, 'conge' AS nature,
                t.nom_fr AS lib_fr, t.nom_en AS lib_en
         FROM demandes_conges c JOIN types_conges t ON t.id = c.type_id
         WHERE c.utilisateur_id IN ($in) AND c.statut = 'approuve'
           AND c.debut <= ? AND c.fin >= ?", $p);
    // Le motif d'absence n'apparait PAS dans le calendrier d'equipe. Les
    // collegues voient « absent », pas « maladie ».
    $abs = qtous(
        "SELECT a.utilisateur_id, a.debut, a.fin, 'absence' AS nature,
                NULL AS lib_fr, NULL AS lib_en
         FROM absences a
         WHERE a.utilisateur_id IN ($in) AND a.statut IN ('declaree','validee')
           AND a.debut <= ? AND a.fin >= ?", $p);
    return array_merge($conges, $abs);
}

function date_valide(string $d): bool
{
    return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false;
}

/* ------------------------------------------------------------------ */
/* Absences (section 6)                                                */
/* ------------------------------------------------------------------ */

const TYPES_ABSENCE = ['maladie', 'personnelle', 'parentale', 'sans_solde', 'accident', 'autre'];

function declarer_absence(int $utilisateur_id, array $d, ?array $fichier = null): array
{
    $type = (string) ($d['type'] ?? '');
    if (!in_array($type, TYPES_ABSENCE, true)) return ['erreurs' => ['type' => t('abs_err_type')]];
    $debut = (string) ($d['debut'] ?? ''); $fin = (string) ($d['fin'] ?? '');
    if (!date_valide($debut) || !date_valide($fin)) return ['erreurs' => ['debut' => t('cg_err_date')]];
    if ($fin < $debut) return ['erreurs' => ['fin' => t('cg_err_ordre')]];

    $u = employe($utilisateur_id);
    $jours = jours_ouvres($debut, $fin, (string) ($u['pays_travail'] ?? ''));

    $justif_id = null;
    if ($fichier && ($fichier['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $r = deposer_fichier($fichier, $utilisateur_id);
        if (isset($r['erreur'])) return ['erreurs' => ['justificatif' => $r['erreur']]];
        // Le justificatif est un document CONFIDENTIEL rattache au dossier.
        // Categorie « justificatif » : le manager ne l'ouvre pas.
        $justif_id = creer_document([
            'utilisateur_id' => $utilisateur_id, 'fichier_id' => (int) $r['id'],
            'categorie' => 'justificatif', 'confidentiel' => 1,
            'titre' => t('abs_justificatif_titre', ['debut' => $debut]),
            'ajoute_par' => $utilisateur_id,
        ]);
    }

    $id = insere('absences', [
        'utilisateur_id' => $utilisateur_id, 'type' => $type,
        'debut' => $debut, 'fin' => $fin, 'nb_jours' => $jours,
        'note' => mb_substr((string) ($d['note'] ?? ''), 0, 2000),
        'justificatif_id' => $justif_id,
        'statut' => 'declaree', 'cree_le' => maintenant(),
    ]);
    // L'audit enregistre le fait, pas le motif medical.
    audit('absence.declaration', 'absence', $id, [], ['jours' => $jours]);

    $mid = (int) ($u['manager_id'] ?? 0);
    if ($mid) {
        notifier($mid, 'absence.declaree', t('notif_absence_titre', ['nom' => nom_complet($u)]),
                 t('notif_absence_corps', ['debut' => $debut, 'fin' => $fin]), '/equipe/absences');
    }
    notifier_role('rh', 'absence.declaree', t('notif_absence_titre', ['nom' => nom_complet($u)]),
                  t('notif_absence_corps', ['debut' => $debut, 'fin' => $fin]), '/rh/absences');
    return ['id' => $id];
}

function absences_de(int $utilisateur_id, int $limite = 50): array
{
    $limite = max(1, min(200, $limite));
    return qtous("SELECT * FROM absences WHERE utilisateur_id = ?
                  ORDER BY debut DESC LIMIT $limite", [$utilisateur_id]);
}

function valider_absence(int $id, string $decision, string $commentaire = ''): array
{
    if (!peut('absences.administrer')) return ['erreur' => t('refus_droit')];
    if (!in_array($decision, ['validee', 'refusee'], true)) return ['erreur' => t('cg_err_decision')];
    $a = qun('SELECT * FROM absences WHERE id = ?', [$id]);
    if (!$a) return ['erreur' => t('refus_introuvable')];
    maj('absences', $id, [
        'statut' => $decision, 'valide_par' => (int) utilisateur()['id'],
        'valide_le' => maintenant(),
        'commentaire_rh' => mb_substr($commentaire, 0, 2000),
    ]);
    audit('absence.' . $decision, 'absence', $id, ['statut' => [$a['statut'], $decision]]);
    notifier((int) $a['utilisateur_id'], 'absence.' . $decision,
             t('notif_absence_decision_' . $decision), '', '/absences');
    return ['ok' => true];
}
