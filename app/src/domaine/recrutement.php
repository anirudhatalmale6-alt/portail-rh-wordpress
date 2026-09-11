<?php
/**
 * Portail de recrutement — la partie PUBLIQUE, plus l'examen facultatif.
 *
 * ------------------------------------------------------------------------
 * TROIS REGLES QUI TIENNENT TOUT CE FICHIER.
 *
 * 1. LE FORMULAIRE PUBLIC EST LA SEULE PORTE OUVERTE DU PORTAIL. Tout le
 *    reste exige une session. Donc : limitation de debit, jeton anti-CSRF,
 *    type de fichier verifie DANS le fichier, taille bornee, et pas une
 *    seule requete SQL construite par concatenation.
 *
 * 2. LE CANDIDAT N'EST PAS UN SALARIE. Une candidature ne cree pas de
 *    compte, ne donne acces a rien, et se supprime. La date de consentement
 *    est horodatee et une date de fin de conservation est ecrite des la
 *    creation — pas « un jour on fera le menage ».
 *
 * 3. LA BONNE REPONSE NE SORT JAMAIS DU SERVEUR. Les questions envoyees au
 *    candidat passent par questions_publiques(), qui retire `bonne`. Un
 *    examen dont les reponses sont dans le HTML n'est pas un examen ; et
 *    c'est le defaut par defaut de tous les QCM ecrits vite.
 * ------------------------------------------------------------------------
 */

declare(strict_types=1);

const STATUTS_CANDIDATURE = ['recue', 'en_examen', 'entretien', 'offre', 'retenue', 'refusee', 'retiree'];

/* ------------------------------------------------------------------ */
/* Offres                                                              */
/* ------------------------------------------------------------------ */

function offres_publiees(array $filtres = []): array
{
    $where = ["o.statut = 'publie'", 'o.publie_le IS NOT NULL', 'o.publie_le <= ?'];
    $p = [maintenant()];
    if (!empty($filtres['pays']))   { $where[] = 'o.pays = ?'; $p[] = $filtres['pays']; }
    if (!empty($filtres['departement_id'])) {
        $where[] = 'o.departement_id = ?'; $p[] = (int) $filtres['departement_id'];
    }
    if (!empty($filtres['q'])) {
        $where[] = '(o.titre LIKE ? OR o.description LIKE ?)';
        $like = '%' . $filtres['q'] . '%'; array_push($p, $like, $like);
    }
    $w = implode(' AND ', $where);
    return qtous("SELECT o.*, d.nom_fr AS dep_fr, d.nom_en AS dep_en, l.nom AS loc_nom
                  FROM offres o
                  LEFT JOIN departements d ON d.id = o.departement_id
                  LEFT JOIN localisations l ON l.id = o.localisation_id
                  WHERE $w ORDER BY o.publie_le DESC", $p);
}

/**
 * Une offre par son slug, VISIBLE.
 *
 * La condition de visibilite est reverifiee ici, independamment de la
 * liste. Ce n'est pas une redondance inutile : c'est ce qui empeche
 * d'atteindre un brouillon en tapant son adresse directement. Un test de la
 * suite retire la condition d'un cote et verifie que l'autre tient encore.
 */
function offre_par_slug(string $slug): ?array
{
    return qun("SELECT o.*, d.nom_fr AS dep_fr, d.nom_en AS dep_en, l.nom AS loc_nom
                FROM offres o
                LEFT JOIN departements d ON d.id = o.departement_id
                LEFT JOIN localisations l ON l.id = o.localisation_id
                WHERE o.slug = ? AND o.statut = 'publie'
                  AND o.publie_le IS NOT NULL AND o.publie_le <= ?",
               [$slug, maintenant()]);
}

function enregistrer_offre(array $d, ?int $id = null): array
{
    if (!peut('recrutement.gerer')) return ['erreur' => t('refus_droit')];
    $titre = trim((string) ($d['titre'] ?? ''));
    if (mb_strlen($titre) < 3) return ['erreurs' => ['titre' => t('rec_err_titre')]];

    $statut = (string) ($d['statut'] ?? 'brouillon');
    if (!in_array($statut, ['brouillon', 'publie', 'ferme'], true)) $statut = 'brouillon';

    $desc = mb_substr((string) ($d['description'] ?? ''), 0, 20000);
    $profil = mb_substr((string) ($d['profil'] ?? ''), 0, 20000);

    $donnees = [
        'titre' => mb_substr($titre, 0, 255),
        'langue' => (string) ($d['langue'] ?? langue()),
        'departement_id' => (int) ($d['departement_id'] ?? 0) ?: null,
        'localisation_id' => (int) ($d['localisation_id'] ?? 0) ?: null,
        'pays' => mb_substr((string) ($d['pays'] ?? ''), 0, 8),
        'type_contrat' => mb_substr((string) ($d['type_contrat'] ?? ''), 0, 40),
        'teletravail' => mb_substr((string) ($d['teletravail'] ?? ''), 0, 30),
        'description' => $desc, 'rendu_desc' => rendre_texte($desc),
        'profil' => $profil, 'rendu_profil' => rendre_texte($profil),
        // Aucune fourchette n'est ecrite par le portail. Si le champ est
        // vide, la page dit que la remuneration n'est pas communiquee.
        'salaire_texte' => mb_substr((string) ($d['salaire_texte'] ?? ''), 0, 190),
        'examen_id' => (int) ($d['examen_id'] ?? 0) ?: null,
        'examen_obligatoire' => !empty($d['examen_obligatoire']) ? 1 : 0,
        'statut' => $statut,
    ];
    if ($statut === 'publie') {
        $donnees['publie_le'] = $d['publie_le'] ?? maintenant();
    }

    if ($id) {
        $avant = qun('SELECT * FROM offres WHERE id = ?', [$id]);
        if (!$avant) return ['erreur' => t('refus_introuvable')];
        maj('offres', $id, $donnees);
        audit('offre.modification', 'offre', $id, changements($avant, $donnees));
        return ['id' => $id];
    }
    $donnees['reference'] = numero('OFF', 'offres');
    $donnees['slug'] = slug_unique('offres', slug($titre));
    $donnees['cree_par'] = (int) utilisateur()['id'];
    $donnees['cree_le'] = maintenant();
    $nid = insere('offres', $donnees);
    audit('offre.creation', 'offre', $nid, [], ['titre' => $titre]);
    return ['id' => $nid];
}

/* ------------------------------------------------------------------ */
/* Candidatures                                                        */
/* ------------------------------------------------------------------ */

/** Duree de conservation par defaut, en mois, modifiable en parametres. */
function duree_conservation_mois(): int
{
    return max(1, (int) (reglage('conservation_candidature_mois') ?: 12));
}

/**
 * Depose une candidature publique.
 *
 * Rend ['id' => n, 'numero' => …, 'jeton_examen' => …|null] ou ['erreurs'].
 */
function deposer_candidature(int $offre_id, array $d, array $fichiers): array
{
    $offre = qun("SELECT * FROM offres WHERE id = ? AND statut = 'publie'
                  AND publie_le IS NOT NULL AND publie_le <= ?",
                 [$offre_id, maintenant()]);
    if (!$offre) return ['erreurs' => ['offre' => t('rec_err_offre_fermee')]];

    if (!limite_ok('candidature', ip_client())) {
        return ['erreurs' => ['email' => t('rec_err_trop')]];
    }

    $err = [];
    $prenom = trim((string) ($d['prenom'] ?? ''));
    $nom    = trim((string) ($d['nom'] ?? ''));
    $email  = trim(mb_strtolower((string) ($d['email'] ?? '')));
    if (mb_strlen($prenom) < 2) $err['prenom'] = t('rec_err_prenom');
    if (mb_strlen($nom) < 2)    $err['nom'] = t('rec_err_nom');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err['email'] = t('rec_err_email');
    if (empty($d['consentement'])) $err['consentement'] = t('rec_err_consentement');
    if ($err) return ['erreurs' => $err];

    // Doublon sur la MEME offre. On refuse en le disant, plutot que
    // d'enregistrer deux fois la meme personne et de laisser la RH trier.
    $deja = qval('SELECT id FROM candidatures WHERE offre_id = ? AND email = ?',
                 [$offre_id, $email]);
    if ($deja !== null) return ['erreurs' => ['email' => t('rec_err_deja_postule')]];

    $cv_id = null;
    if (!empty($fichiers['cv']) && ($fichiers['cv']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        // Le CV est depose par quelqu'un qui n'a pas de compte : `depose_par`
        // est NULL, et c'est correct — la colonne dit qui, pas « un id
        // quelconque pour remplir ».
        $r = deposer_fichier($fichiers['cv'], 0);
        if (isset($r['erreur'])) return ['erreurs' => ['cv' => $r['erreur']]];
        $cv_id = (int) $r['id'];
    } elseif ((int) (reglage('cv_obligatoire') ?: 1) === 1) {
        return ['erreurs' => ['cv' => t('rec_err_cv_requis')]];
    }

    $lettre_id = null;
    if (!empty($fichiers['lettre']) && ($fichiers['lettre']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $r = deposer_fichier($fichiers['lettre'], 0);
        if (isset($r['id'])) $lettre_id = (int) $r['id'];
    }

    $examen_id = (int) ($offre['examen_id'] ?? 0);
    $jeton = $examen_id ? bin2hex(random_bytes(24)) : null;

    $id = insere('candidatures', [
        'numero' => numero('CAND', 'candidatures'),
        'offre_id' => $offre_id,
        'prenom' => mb_substr($prenom, 0, 120), 'nom' => mb_substr($nom, 0, 120),
        'email' => mb_substr($email, 0, 190),
        'telephone' => mb_substr((string) ($d['telephone'] ?? ''), 0, 60),
        'pays' => mb_substr((string) ($d['pays'] ?? ''), 0, 8),
        'ville' => mb_substr((string) ($d['ville'] ?? ''), 0, 120),
        'lien_pro' => mb_substr((string) ($d['lien_pro'] ?? ''), 0, 255),
        'message' => mb_substr((string) ($d['message'] ?? ''), 0, 5000),
        'cv_id' => $cv_id, 'lettre_id' => $lettre_id,
        'statut' => 'recue',
        'examen_statut' => $examen_id ? 'a_faire' : 'non_requis',
        'jeton_examen' => $jeton,
        'consentement_le' => maintenant(),
        'conservation_jusqu' => (new DateTimeImmutable(aujourdhui()))
            ->modify('+' . duree_conservation_mois() . ' months')->format('Y-m-d'),
        'cree_le' => maintenant(), 'maj_le' => maintenant(),
    ]);
    // L'audit d'une candidature ne porte PAS le nom du candidat : le
    // journal d'audit est lu par l'administrateur systeme, qui n'a aucune
    // raison de connaitre la liste des gens qui postulent.
    audit('candidature.depot', 'candidature', $id, [], ['offre' => (int) $offre['id']]);
    notifier_role('rh', 'candidature.nouvelle', t('notif_cand_titre'),
                  t('notif_cand_corps', ['offre' => (string) $offre['titre']]),
                  '/rh/candidatures');

    return ['id' => $id, 'numero' => qval('SELECT numero FROM candidatures WHERE id = ?', [$id]),
            'jeton_examen' => $jeton,
            'examen_obligatoire' => (int) ($offre['examen_obligatoire'] ?? 0) === 1];
}

function candidature_par_jeton(string $jeton): ?array
{
    if (strlen($jeton) < 20) return null;
    return qun('SELECT c.*, o.titre AS offre_titre, o.examen_id, o.examen_obligatoire
                FROM candidatures c JOIN offres o ON o.id = c.offre_id
                WHERE c.jeton_examen = ?', [$jeton]);
}

/* ------------------------------------------------------------------ */
/* Examen facultatif                                                   */
/* ------------------------------------------------------------------ */

function examen(int $id): ?array
{
    return qun('SELECT * FROM examens WHERE id = ? AND actif = 1', [$id]);
}

/**
 * Les questions telles qu'on les envoie au candidat.
 *
 * `bonne` est retiree ICI et pas dans la vue. Une vue qui oublie un unset
 * publie le corrige dans le HTML, et personne ne s'en apercoit tant qu'un
 * candidat n'a pas ouvert l'inspecteur — c'est-a-dire jamais, jusqu'au jour
 * ou tous les candidats ont 100 %.
 */
function questions_publiques(int $examen_id): array
{
    $out = [];
    foreach (qtous('SELECT * FROM questions_examen WHERE examen_id = ? ORDER BY rang, id',
                   [$examen_id]) as $q) {
        $out[] = [
            'id' => (int) $q['id'], 'rang' => (int) $q['rang'], 'type' => $q['type'],
            'enonce' => $q['enonce'],
            'choix' => $q['choix'] ? (json_decode((string) $q['choix'], true) ?: []) : [],
            'points' => (float) $q['points'],
        ];
    }
    return $out;
}

/**
 * Corrige et enregistre. Rend le score, ou une erreur.
 *
 * Une question de type « texte » n'est PAS notee automatiquement : elle vaut
 * 0 point au bareme automatique et elle est marquee pour relecture humaine.
 * Attribuer des points a une reponse libre en comparant des chaines produit
 * un score qui a l'air d'un score et n'en est pas un.
 */
function corriger_examen(array $cand, array $reponses): array
{
    $examen_id = (int) ($cand['examen_id'] ?? 0);
    if (!$examen_id) return ['erreur' => t('rec_err_pas_examen')];
    if (($cand['examen_statut'] ?? '') === 'termine') return ['erreur' => t('rec_err_examen_fait')];

    $questions = qtous('SELECT * FROM questions_examen WHERE examen_id = ? ORDER BY rang, id',
                       [$examen_id]);
    if (!$questions) return ['erreur' => t('rec_err_examen_vide')];

    $obtenu = 0.0; $total_auto = 0.0; $a_relire = 0;
    foreach ($questions as $q) {
        $qid = (int) $q['id'];
        $rep = $reponses[$qid] ?? null;
        $pts = 0.0;

        if ($q['type'] === 'texte') {
            $a_relire++;
            $valeur = mb_substr((string) ($rep ?? ''), 0, 5000);
        } else {
            $total_auto += (float) $q['points'];
            $attendu = array_filter(array_map('trim', explode(',', (string) $q['bonne'])), 'strlen');
            sort($attendu);
            $donne = is_array($rep) ? $rep : ($rep === null ? [] : [$rep]);
            $donne = array_values(array_filter(array_map('strval', $donne), 'strlen'));
            sort($donne);
            if ($attendu && $donne === $attendu) $pts = (float) $q['points'];
            $obtenu += $pts;
            $valeur = implode(',', $donne);
        }

        $existe = qval('SELECT id FROM reponses_examen WHERE candidature_id = ? AND question_id = ?',
                       [(int) $cand['id'], $qid]);
        if ($existe === null) {
            insere('reponses_examen', [
                'candidature_id' => (int) $cand['id'], 'question_id' => $qid,
                'reponse' => $valeur, 'points' => $pts, 'cree_le' => maintenant(),
            ]);
        }
    }

    maj('candidatures', (int) $cand['id'], [
        'examen_statut' => 'termine',
        'examen_score' => $obtenu, 'examen_sur' => $total_auto,
        'examen_fini_le' => maintenant(),
        // Le jeton est brule. Sans cela, le lien recu par courriel rejoue
        // l'examen autant de fois qu'on veut.
        'jeton_examen' => null,
        'maj_le' => maintenant(),
    ]);
    audit('examen.termine', 'candidature', (int) $cand['id'], [],
          ['score' => $obtenu, 'sur' => $total_auto]);

    return ['score' => $obtenu, 'sur' => $total_auto, 'a_relire' => $a_relire];
}

/* ------------------------------------------------------------------ */
/* Cote RH                                                             */
/* ------------------------------------------------------------------ */

function candidatures(array $filtres = [], int $page = 1, int $par_page = 25): array
{
    $where = ['1=1']; $p = [];
    if (!empty($filtres['offre_id'])) { $where[] = 'c.offre_id = ?'; $p[] = (int) $filtres['offre_id']; }
    if (!empty($filtres['statut']))   { $where[] = 'c.statut = ?';   $p[] = $filtres['statut']; }
    if (!empty($filtres['q'])) {
        $where[] = '(c.nom LIKE ? OR c.prenom LIKE ? OR c.email LIKE ? OR c.numero LIKE ?)';
        $like = '%' . $filtres['q'] . '%'; array_push($p, $like, $like, $like, $like);
    }
    $w = implode(' AND ', $where);
    $total = (int) qval("SELECT COUNT(*) FROM candidatures c WHERE $w", $p);
    $par_page = max(1, min(100, $par_page));
    $depart = max(0, ($page - 1) * $par_page);
    $lignes = qtous("SELECT c.*, o.titre AS offre_titre, o.reference AS offre_ref
                     FROM candidatures c LEFT JOIN offres o ON o.id = c.offre_id
                     WHERE $w ORDER BY c.cree_le DESC LIMIT $par_page OFFSET $depart", $p);
    return ['total' => $total, 'lignes' => $lignes, 'page' => $page, 'par_page' => $par_page];
}

function changer_statut_candidature(int $id, string $statut, string $note = ''): array
{
    if (!peut('recrutement.gerer')) return ['erreur' => t('refus_droit')];
    if (!in_array($statut, STATUTS_CANDIDATURE, true)) return ['erreur' => t('cg_err_decision')];
    $c = qun('SELECT * FROM candidatures WHERE id = ?', [$id]);
    if (!$c) return ['erreur' => t('refus_introuvable')];
    $donnees = ['statut' => $statut, 'maj_le' => maintenant()];
    if ($note !== '') $donnees['note_rh'] = mb_substr($note, 0, 5000);
    maj('candidatures', $id, $donnees);
    audit('candidature.statut', 'candidature', $id, ['statut' => [$c['statut'], $statut]]);
    return ['ok' => true];
}

/**
 * Purge des candidatures dont la conservation est echue.
 *
 * Rend le nombre supprime. Appelee a la main ou par une tache planifiee ;
 * elle n'est PAS automatique au chargement d'une page — une suppression
 * declenchee par une visite est une suppression dont personne ne connait le
 * moment.
 */
function purger_candidatures_echues(bool $simulation = true): array
{
    $lignes = qtous('SELECT id, cv_id, lettre_id FROM candidatures
                     WHERE conservation_jusqu IS NOT NULL AND conservation_jusqu < ?',
                    [aujourdhui()]);
    if ($simulation) return ['a_supprimer' => count($lignes), 'simulation' => true];
    $n = 0;
    foreach ($lignes as $l) {
        q('DELETE FROM reponses_examen WHERE candidature_id = ?', [(int) $l['id']]);
        foreach ([(int) $l['cv_id'], (int) $l['lettre_id']] as $fid) {
            if (!$fid) continue;
            $f = qun('SELECT * FROM fichiers WHERE id = ?', [$fid]);
            if ($f) {
                @unlink(repertoire_documents() . '/' . $f['chemin']);
                q('DELETE FROM fichiers WHERE id = ?', [$fid]);
            }
        }
        q('DELETE FROM candidatures WHERE id = ?', [(int) $l['id']]);
        $n++;
    }
    audit('candidature.purge', 'candidature', null, [], ['supprimees' => $n]);
    return ['supprimees' => $n, 'simulation' => false];
}
