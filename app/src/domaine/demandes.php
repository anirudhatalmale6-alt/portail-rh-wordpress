<?php
/**
 * Demandes RH (section 9) et notifications (section 10).
 */

declare(strict_types=1);

const STATUTS_DEMANDE = ['nouveau', 'en_traitement', 'en_attente', 'resolu', 'ferme'];

/** Les categories de la section 9. Elles sont en base pour etre editables. */
const CATEGORIES_DEMANDE = [
    'attestation_emploi', 'lettre_reference', 'modification_information',
    'question_paie', 'question_assurance', 'question_conges',
    'demande_document', 'demande_administrative', 'autre',
];

function creer_demande(int $utilisateur_id, array $d, ?array $fichier = null): array
{
    $cat = (string) ($d['categorie'] ?? '');
    if (!in_array($cat, CATEGORIES_DEMANDE, true)) {
        return ['erreurs' => ['categorie' => t('dem_err_categorie')]];
    }
    $objet = trim((string) ($d['objet'] ?? ''));
    if (mb_strlen($objet) < 3) return ['erreurs' => ['objet' => t('dem_err_objet')]];
    $corps = trim((string) ($d['corps'] ?? ''));
    if (mb_strlen($corps) < 5) return ['erreurs' => ['corps' => t('dem_err_corps')]];

    if (!limite_ok('demande', 'u' . $utilisateur_id)) {
        return ['erreurs' => ['corps' => t('err_trop_de_demandes')]];
    }

    $id = insere('demandes_rh', [
        'numero' => numero('DEM', 'demandes_rh'),
        'utilisateur_id' => $utilisateur_id,
        'categorie' => $cat,
        'objet' => mb_substr($objet, 0, 255),
        'corps' => mb_substr($corps, 0, 8000),
        'statut' => 'nouveau',
        'cree_le' => maintenant(), 'maj_le' => maintenant(),
    ]);

    $fid = null;
    if ($fichier && ($fichier['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $r = deposer_fichier($fichier, $utilisateur_id);
        if (isset($r['id'])) $fid = (int) $r['id'];
    }
    insere('messages_demande', [
        'demande_id' => $id, 'auteur_id' => $utilisateur_id,
        'corps' => mb_substr($corps, 0, 8000), 'rendu' => rendre_texte(mb_substr($corps, 0, 8000)),
        'fichier_id' => $fid, 'interne' => 0, 'cree_le' => maintenant(),
    ]);
    audit('demande.creation', 'demande', $id, [], ['categorie' => $cat]);
    notifier_role('rh', 'demande.nouvelle', t('notif_demande_titre'),
                  t('notif_demande_corps', ['objet' => $objet]), '/rh/demandes');
    return ['id' => $id];
}

function demande(int $id): ?array
{
    $d = qun('SELECT d.*, u.prenom, u.nom, u.email, u.matricule
             FROM demandes_rh d JOIN utilisateurs u ON u.id = d.utilisateur_id
             WHERE d.id = ?', [$id]);
    if (!$d) return null;
    // Les notes INTERNES ne sortent pas d'ici pour un salarie. Le filtre est
    // dans la requete, pas dans la vue : une vue qui oublie un if affiche la
    // note, et la note dit souvent ce que la RH pense du dossier.
    $interne_visible = peut('demandes.traiter') ? '' : ' AND m.interne = 0';
    $d['messages'] = qtous(
        "SELECT m.*, u.prenom, u.nom, f.nom_origine, f.mime
         FROM messages_demande m
         LEFT JOIN utilisateurs u ON u.id = m.auteur_id
         LEFT JOIN fichiers f ON f.id = m.fichier_id
         WHERE m.demande_id = ?$interne_visible ORDER BY m.cree_le, m.id", [$id]);
    return $d;
}

function peut_voir_demande(array $d): bool
{
    $u = utilisateur();
    if (!$u) return false;
    if ((int) $d['utilisateur_id'] === (int) $u['id']) return true;
    return peut('demandes.traiter');
}

function repondre_demande(int $demande_id, string $corps, bool $interne = false,
                          ?array $fichier = null): array
{
    $d = qun('SELECT * FROM demandes_rh WHERE id = ?', [$demande_id]);
    if (!$d) return ['erreur' => t('refus_introuvable')];
    if (!peut_voir_demande($d)) return ['erreur' => t('refus_droit')];
    if ($interne && !peut('demandes.traiter')) $interne = false;
    $corps = trim($corps);
    if ($corps === '') return ['erreur' => t('dem_err_corps')];

    $u = utilisateur();
    $fid = null;
    if ($fichier && ($fichier['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $r = deposer_fichier($fichier, (int) $u['id']);
        if (isset($r['id'])) $fid = (int) $r['id'];
    }
    insere('messages_demande', [
        'demande_id' => $demande_id, 'auteur_id' => (int) $u['id'],
        'corps' => mb_substr($corps, 0, 8000), 'rendu' => rendre_texte(mb_substr($corps, 0, 8000)),
        'fichier_id' => $fid, 'interne' => $interne ? 1 : 0, 'cree_le' => maintenant(),
    ]);
    maj('demandes_rh', $demande_id, ['maj_le' => maintenant()]);
    audit('demande.reponse', 'demande', $demande_id, [], ['interne' => $interne]);

    if (!$interne) {
        $destinataire = (int) $d['utilisateur_id'] === (int) $u['id']
            ? (int) ($d['responsable_id'] ?? 0) : (int) $d['utilisateur_id'];
        if ($destinataire) {
            notifier($destinataire, 'demande.reponse', t('notif_demande_reponse_titre'),
                     $d['objet'], '/demandes/' . $demande_id);
        }
    }
    return ['ok' => true];
}

function changer_statut_demande(int $id, string $statut, ?int $responsable_id = null): array
{
    if (!peut('demandes.traiter')) return ['erreur' => t('refus_droit')];
    if (!in_array($statut, STATUTS_DEMANDE, true)) return ['erreur' => t('cg_err_decision')];
    $d = qun('SELECT * FROM demandes_rh WHERE id = ?', [$id]);
    if (!$d) return ['erreur' => t('refus_introuvable')];

    $donnees = ['statut' => $statut, 'maj_le' => maintenant()];
    if ($responsable_id !== null) $donnees['responsable_id'] = $responsable_id ?: null;
    if (in_array($statut, ['resolu', 'ferme'], true) && !$d['ferme_le']) {
        $donnees['ferme_le'] = maintenant();
    }
    maj('demandes_rh', $id, $donnees);
    audit('demande.statut', 'demande', $id, ['statut' => [$d['statut'], $statut]]);
    notifier((int) $d['utilisateur_id'], 'demande.statut',
             t('notif_demande_statut_titre'), t('statut_' . $statut), '/demandes/' . $id);
    return ['ok' => true];
}

function demandes_de(int $utilisateur_id): array
{
    return qtous('SELECT * FROM demandes_rh WHERE utilisateur_id = ? ORDER BY cree_le DESC',
                 [$utilisateur_id]);
}

function demandes_rh(array $filtres = [], int $page = 1, int $par_page = 25): array
{
    $where = ['1=1']; $p = [];
    if (!empty($filtres['statut'])) { $where[] = 'd.statut = ?'; $p[] = $filtres['statut']; }
    if (!empty($filtres['categorie'])) { $where[] = 'd.categorie = ?'; $p[] = $filtres['categorie']; }
    if (!empty($filtres['q'])) {
        $where[] = '(d.objet LIKE ? OR d.numero LIKE ? OR u.nom LIKE ? OR u.prenom LIKE ?)';
        $like = '%' . $filtres['q'] . '%';
        array_push($p, $like, $like, $like, $like);
    }
    $w = implode(' AND ', $where);
    $total = (int) qval("SELECT COUNT(*) FROM demandes_rh d
                         JOIN utilisateurs u ON u.id = d.utilisateur_id WHERE $w", $p);
    $par_page = max(1, min(100, $par_page));
    $depart = max(0, ($page - 1) * $par_page);
    $lignes = qtous("SELECT d.*, u.prenom, u.nom, u.matricule
                     FROM demandes_rh d JOIN utilisateurs u ON u.id = d.utilisateur_id
                     WHERE $w ORDER BY
                       CASE d.statut WHEN 'nouveau' THEN 0 WHEN 'en_traitement' THEN 1
                                     WHEN 'en_attente' THEN 2 ELSE 3 END,
                       d.cree_le DESC LIMIT $par_page OFFSET $depart", $p);
    return ['total' => $total, 'lignes' => $lignes, 'page' => $page, 'par_page' => $par_page];
}

/* ------------------------------------------------------------------ */
/* Notifications (section 10)                                          */
/* ------------------------------------------------------------------ */

/**
 * Une notification est ecrite dans le portail. Le canal e-mail existe dans
 * les preferences mais il n'ENVOIE RIEN tant que `mail_expediteur` est vide,
 * et l'interface le dit. Faire croire a la RH qu'un salarie a ete prevenu
 * par courriel est pire que ne pas proposer le canal du tout.
 */
function notifier(int $utilisateur_id, string $type, string $titre,
                  string $corps = '', string $lien = ''): void
{
    if ($utilisateur_id <= 0) return;
    insere('notifications', [
        'utilisateur_id' => $utilisateur_id, 'type' => $type,
        'titre' => mb_substr($titre, 0, 255), 'corps' => mb_substr($corps, 0, 2000),
        'lien' => mb_substr($lien, 0, 255), 'cree_le' => maintenant(),
    ]);
}

function notifier_role(string $role_cle, string $type, string $titre,
                       string $corps = '', string $lien = ''): void
{
    $ids = qtous("SELECT u.id FROM utilisateurs u JOIN roles r ON r.id = u.role_id
                  WHERE r.cle = ? AND u.actif = 1 AND u.statut = 'actif'", [$role_cle]);
    foreach ($ids as $r) notifier((int) $r['id'], $type, $titre, $corps, $lien);
}

function notifications_de(int $utilisateur_id, bool $non_lues = false, int $limite = 50): array
{
    $limite = max(1, min(200, $limite));
    $cond = $non_lues ? ' AND lu_le IS NULL' : '';
    return qtous("SELECT * FROM notifications WHERE utilisateur_id = ?$cond
                  ORDER BY cree_le DESC LIMIT $limite", [$utilisateur_id]);
}

function compte_non_lues(int $utilisateur_id): int
{
    return (int) qval('SELECT COUNT(*) FROM notifications WHERE utilisateur_id = ? AND lu_le IS NULL',
                      [$utilisateur_id]);
}

function marquer_lues(int $utilisateur_id, ?int $id = null): void
{
    if ($id) {
        q('UPDATE notifications SET lu_le = ? WHERE id = ? AND utilisateur_id = ? AND lu_le IS NULL',
          [maintenant(), $id, $utilisateur_id]);
    } else {
        q('UPDATE notifications SET lu_le = ? WHERE utilisateur_id = ? AND lu_le IS NULL',
          [maintenant(), $utilisateur_id]);
    }
}

function email_actif(): bool
{
    return trim((string) cfg('mail_expediteur')) !== '';
}
