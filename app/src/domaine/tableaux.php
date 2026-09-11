<?php
/**
 * Tableaux de bord : employe (3), manager (20), RH (21). Et actualites,
 * formations, avantages (11, 12, 14).
 */

declare(strict_types=1);

/* ------------------------------------------------------------------ */
/* Tableau de bord employe (section 3)                                 */
/* ------------------------------------------------------------------ */

function tableau_employe(array $u): array
{
    $id = (int) $u['id'];
    $soldes = soldes_de($id);
    // Le solde mis en avant est celui du premier type ACTIF, pas « les
    // vacances » : selon le pays, le type principal ne porte pas le meme nom
    // et n'a pas la meme cle.
    $principal = $soldes[0] ?? null;

    $prochaine = prochaine_paie($u);
    $ferie = qun("SELECT * FROM jours_feries WHERE pays = ? AND date >= ? AND chome = 1
                  ORDER BY date LIMIT 1", [(string) ($u['pays_travail'] ?? ''), aujourdhui()]);
    $formation = qun("SELECT i.*, f.titre_fr, f.titre_en, f.obligatoire
                      FROM inscriptions_formation i JOIN formations f ON f.id = i.formation_id
                      WHERE i.utilisateur_id = ? AND i.statut IN ('a_venir','en_cours')
                      ORDER BY i.date_prevue LIMIT 1", [$id]);

    return [
        'soldes' => $soldes,
        'solde_principal' => $principal,
        'prochaine_paie' => $prochaine,
        'prochain_ferie' => $ferie,
        'prochaine_formation' => $formation,
        'demandes_attente' => (int) qval(
            "SELECT COUNT(*) FROM demandes_rh WHERE utilisateur_id = ?
             AND statut IN ('nouveau','en_traitement','en_attente')", [$id]),
        'conges_attente' => (int) qval(
            "SELECT COUNT(*) FROM demandes_conges WHERE utilisateur_id = ? AND statut = 'en_attente'",
            [$id]),
        'documents_recents' => documents_de($id),
        'notifications' => notifications_de($id, true, 5),
        'actualites' => actualites_publiees(3),
        'champs_manquants' => champs_manquants($u),
    ];
}

/* ------------------------------------------------------------------ */
/* Tableau de bord manager (section 20)                                */
/* ------------------------------------------------------------------ */

function tableau_manager(array $u): array
{
    $ids = equipe_de((int) $u['id']);
    if (!$ids) {
        return ['equipe' => [], 'effectif' => 0, 'absents_aujourdhui' => [],
                'conges_a_valider' => [], 'temps_a_valider' => [],
                'anniversaires' => [], 'embauches' => []];
    }
    $in = implode(',', array_fill(0, count($ids), '?'));
    $equipe = qtous("SELECT * FROM utilisateurs WHERE id IN ($in) ORDER BY nom, prenom", $ids);

    $auj = aujourdhui();
    $absents = calendrier_absences($ids, $auj, $auj);

    // Anniversaires et dates d'embauche du mois : on compare le MOIS-JOUR,
    // pas la date entiere. La comparaison se fait en PHP parce que
    // l'extraction de mois-jour s'ecrit differemment en SQLite et en MySQL,
    // et qu'une requete portable ici ne vaut pas la complication.
    $mois = gmdate('m');
    $anniv = []; $embauches = [];
    foreach ($equipe as $e) {
        if (!empty($e['date_naissance']) && substr((string) $e['date_naissance'], 5, 2) === $mois) {
            $anniv[] = $e;
        }
        if (!empty($e['date_embauche']) && substr((string) $e['date_embauche'], 5, 2) === $mois) {
            $embauches[] = $e;
        }
    }

    return [
        'equipe' => $equipe,
        'effectif' => count($equipe),
        'absents_aujourdhui' => $absents,
        'conges_a_valider' => conges_a_valider((int) $u['id']),
        'temps_a_valider' => temps_a_valider((int) $u['id']),
        'anniversaires' => $anniv,
        'embauches' => $embauches,
    ];
}

/* ------------------------------------------------------------------ */
/* Tableau de bord RH (section 21)                                     */
/* ------------------------------------------------------------------ */

/**
 * Les indicateurs de la section 21.
 *
 * LE TURNOVER N'EST PAS AFFICHE TANT QUE L'HISTORIQUE NE LE PERMET PAS.
 *
 * Un taux de rotation se calcule sur une periode : departs de l'annee
 * divises par l'effectif MOYEN de l'annee. Sur une base installee la
 * semaine derniere, le denominateur est l'effectif d'aujourd'hui et le
 * resultat est un nombre qui ressemble a un taux sans en etre un. On rend
 * donc null et l'interface ecrit « historique insuffisant » — c'est une
 * information, un 0 % ne l'est pas.
 */
function tableau_rh(): array
{
    $effectif = (int) qval("SELECT COUNT(*) FROM utilisateurs WHERE statut = 'actif'");
    $annee = (int) gmdate('Y');
    $debut_annee = "$annee-01-01";

    $entrees = (int) qval("SELECT COUNT(*) FROM utilisateurs WHERE date_embauche >= ?", [$debut_annee]);
    $departs = (int) qval("SELECT COUNT(*) FROM utilisateurs
                           WHERE statut = 'parti' AND date_depart >= ?", [$debut_annee]);

    // Le plus ancien enregistrement d'embauche encore en base : s'il est
    // dans l'annee en cours, on n'a pas d'annee pleine.
    $plus_ancienne = (string) (qval("SELECT MIN(date_embauche) FROM utilisateurs
                                     WHERE date_embauche IS NOT NULL AND date_embauche <> ''") ?? '');
    $historique_suffisant = $plus_ancienne !== '' && $plus_ancienne < $debut_annee;
    $turnover = null;
    if ($historique_suffisant && $effectif > 0) {
        $moyen = max(1, ($effectif + $effectif - $entrees + $departs) / 2);
        $turnover = round($departs / $moyen * 100, 1);
    }

    $par_departement = qtous(
        "SELECT COALESCE(d.nom_fr, '') AS nom_fr, COALESCE(d.nom_en, '') AS nom_en,
                COUNT(u.id) AS n
         FROM utilisateurs u LEFT JOIN departements d ON d.id = u.departement_id
         WHERE u.statut = 'actif' GROUP BY d.id, d.nom_fr, d.nom_en ORDER BY n DESC");
    $par_localisation = qtous(
        "SELECT COALESCE(l.nom, '') AS nom, COUNT(u.id) AS n
         FROM utilisateurs u LEFT JOIN localisations l ON l.id = u.localisation_id
         WHERE u.statut = 'actif' GROUP BY l.id, l.nom ORDER BY n DESC");
    $par_pays = qtous(
        "SELECT COALESCE(pays_travail, '') AS pays, COUNT(*) AS n
         FROM utilisateurs WHERE statut = 'actif' GROUP BY pays_travail ORDER BY n DESC");

    $auj = aujourdhui();
    return [
        'effectif' => $effectif,
        'decentralises' => (int) qval("SELECT COUNT(*) FROM utilisateurs
                                       WHERE statut = 'actif' AND decentralise = 1"),
        'entrees' => $entrees, 'departs' => $departs,
        'turnover' => $turnover,
        'turnover_indisponible' => !$historique_suffisant,
        'absents_aujourdhui' => (int) qval(
            "SELECT COUNT(DISTINCT utilisateur_id) FROM absences
             WHERE statut IN ('declaree','validee') AND debut <= ? AND fin >= ?", [$auj, $auj]),
        'en_conge_aujourdhui' => (int) qval(
            "SELECT COUNT(DISTINCT utilisateur_id) FROM demandes_conges
             WHERE statut = 'approuve' AND debut <= ? AND fin >= ?", [$auj, $auj]),
        'conges_attente' => (int) qval("SELECT COUNT(*) FROM demandes_conges WHERE statut = 'en_attente'"),
        'absences_attente' => (int) qval("SELECT COUNT(*) FROM absences WHERE statut = 'declaree'"),
        'demandes_ouvertes' => (int) qval(
            "SELECT COUNT(*) FROM demandes_rh WHERE statut IN ('nouveau','en_traitement','en_attente')"),
        'temps_attente' => (int) qval("SELECT COUNT(*) FROM feuilles_temps WHERE statut = 'soumis'"),
        'candidatures_nouvelles' => (int) qval("SELECT COUNT(*) FROM candidatures WHERE statut = 'recue'"),
        'offres_publiees' => (int) qval("SELECT COUNT(*) FROM offres WHERE statut = 'publie'"),
        'formations_obligatoires_manquantes' => formations_obligatoires_manquantes(),
        'par_departement' => $par_departement,
        'par_localisation' => $par_localisation,
        'par_pays' => $par_pays,
        'fiches_incompletes' => fiches_incompletes(),
    ];
}

/** Les salaries a qui il manque une formation obligatoire. */
function formations_obligatoires_manquantes(): int
{
    return (int) qval(
        "SELECT COUNT(*) FROM utilisateurs u
         JOIN formations f ON f.obligatoire = 1 AND f.actif = 1
         LEFT JOIN inscriptions_formation i
                ON i.utilisateur_id = u.id AND i.formation_id = f.id AND i.statut = 'complete'
         WHERE u.statut = 'actif' AND i.id IS NULL");
}

function fiches_incompletes(): int
{
    $n = 0;
    foreach (qtous("SELECT * FROM utilisateurs WHERE statut = 'actif'") as $u) {
        if (champs_manquants($u)) $n++;
    }
    return $n;
}

/* ------------------------------------------------------------------ */
/* Actualites RH (section 11)                                          */
/* ------------------------------------------------------------------ */

function actualites_publiees(int $limite = 20): array
{
    $limite = max(1, min(100, $limite));
    // La date de publication est comparee A CHAQUE LECTURE : une actualite
    // programmee pour vendredi n'apparait pas jeudi parce qu'elle a ete
    // ecrite jeudi.
    $lignes = qtous("SELECT a.*, u.prenom, u.nom FROM actualites a
                     LEFT JOIN utilisateurs u ON u.id = a.auteur_id
                     WHERE a.statut = 'publie' AND a.publie_le IS NOT NULL AND a.publie_le <= ?
                     ORDER BY a.epingle DESC, a.publie_le DESC LIMIT $limite",
                    [maintenant()]);
    return preferer_langue($lignes);
}

function actualite_par_slug(string $slug): ?array
{
    return qun("SELECT a.*, u.prenom, u.nom FROM actualites a
                LEFT JOIN utilisateurs u ON u.id = a.auteur_id
                WHERE a.slug = ? AND a.statut = 'publie'
                  AND a.publie_le IS NOT NULL AND a.publie_le <= ?",
               [$slug, maintenant()]);
}

/**
 * Remplace une actualite par sa version dans la langue du lecteur quand
 * elle existe. Quand elle n'existe pas, on garde l'originale et l'interface
 * signale la langue — on ne traduit pas et on ne cache pas.
 */
function preferer_langue(array $lignes): array
{
    $l = langue();
    $out = [];
    foreach ($lignes as $a) {
        if (($a['langue'] ?? '') === $l || empty($a['groupe'])) { $out[] = $a; continue; }
        $autre = qun("SELECT a.*, u.prenom, u.nom FROM actualites a
                      LEFT JOIN utilisateurs u ON u.id = a.auteur_id
                      WHERE a.groupe = ? AND a.langue = ? AND a.statut = 'publie'
                        AND a.publie_le IS NOT NULL AND a.publie_le <= ? LIMIT 1",
                     [$a['groupe'], $l, maintenant()]);
        $out[] = $autre ?: $a;
    }
    return $out;
}

function enregistrer_actualite(array $d, ?int $id = null): array
{
    if (!peut('actualites.publier')) return ['erreur' => t('refus_droit')];
    $titre = trim((string) ($d['titre'] ?? ''));
    if (mb_strlen($titre) < 3) return ['erreurs' => ['titre' => t('act_err_titre')]];
    $corps = mb_substr((string) ($d['corps'] ?? ''), 0, 40000);

    $statut = (string) ($d['statut'] ?? 'brouillon');
    if (!in_array($statut, ['brouillon', 'publie', 'retire'], true)) $statut = 'brouillon';

    $donnees = [
        'titre' => mb_substr($titre, 0, 255),
        'chapeau' => mb_substr((string) ($d['chapeau'] ?? ''), 0, 1000),
        'corps' => $corps, 'rendu' => rendre_texte($corps),
        'langue' => (string) ($d['langue'] ?? langue()),
        'groupe' => mb_substr((string) ($d['groupe'] ?? ''), 0, 40),
        'epingle' => !empty($d['epingle']) ? 1 : 0,
        'statut' => $statut,
    ];
    if ($statut === 'publie') {
        // Une date future est une PROGRAMMATION, relue a chaque affichage.
        $donnees['publie_le'] = !empty($d['publie_le'])
            ? (string) $d['publie_le'] : maintenant();
    }
    if ($id) {
        $avant = qun('SELECT * FROM actualites WHERE id = ?', [$id]);
        if (!$avant) return ['erreur' => t('refus_introuvable')];
        maj('actualites', $id, $donnees);
        audit('actualite.modification', 'actualite', $id, changements($avant, $donnees));
        return ['id' => $id];
    }
    $donnees['slug'] = slug_unique('actualites', slug($titre));
    $donnees['auteur_id'] = (int) utilisateur()['id'];
    $donnees['cree_le'] = maintenant();
    $nid = insere('actualites', $donnees);
    audit('actualite.creation', 'actualite', $nid);
    return ['id' => $nid];
}

/* ------------------------------------------------------------------ */
/* Formations (section 12) et avantages (section 14)                   */
/* ------------------------------------------------------------------ */

function formations_de(int $utilisateur_id): array
{
    return qtous("SELECT i.*, f.code, f.titre_fr, f.titre_en, f.obligatoire, f.duree_h
                  FROM inscriptions_formation i JOIN formations f ON f.id = i.formation_id
                  WHERE i.utilisateur_id = ?
                  ORDER BY CASE i.statut WHEN 'en_cours' THEN 0 WHEN 'a_venir' THEN 1 ELSE 2 END,
                           i.date_prevue", [$utilisateur_id]);
}

function catalogue_formations(): array
{
    return qtous('SELECT * FROM formations WHERE actif = 1 ORDER BY obligatoire DESC, titre_fr');
}

function avantages_pour(array $u): array
{
    $pays = (string) ($u['pays_travail'] ?? '');
    return qtous("SELECT * FROM avantages WHERE actif = 1 AND (pays = ? OR pays IS NULL OR pays = '')
                  ORDER BY titre_fr", [$pays]);
}

/* ------------------------------------------------------------------ */
/* Recherche globale (section 22)                                      */
/* ------------------------------------------------------------------ */

/**
 * Une recherche qui respecte les droits.
 *
 * Chaque famille de resultats est filtree par la permission qui la couvre,
 * AVANT d'etre ajoutee. Une recherche globale est le moyen le plus simple
 * de faire fuir une donnee : elle traverse toutes les tables a la fois, et
 * c'est exactement ce qu'on lui demande.
 */
function recherche_globale(string $q, int $limite = 8): array
{
    $q = trim($q);
    if (mb_strlen($q) < 2) return [];
    $like = '%' . $q . '%';
    $limite = max(1, min(20, $limite));
    $res = [];

    if (peut('annuaire.voir')) {
        foreach (qtous("SELECT id, prenom, nom, poste FROM utilisateurs
                        WHERE statut = 'actif' AND annuaire_visible = 1
                          AND (prenom LIKE ? OR nom LIKE ? OR poste LIKE ?)
                        ORDER BY nom LIMIT $limite", [$like, $like, $like]) as $r) {
            $res[] = ['type' => 'employe', 'titre' => nom_complet($r),
                      'sous_titre' => (string) $r['poste'], 'lien' => '/annuaire/' . (int) $r['id']];
        }
    }
    foreach (qtous("SELECT slug, titre FROM actualites
                    WHERE statut = 'publie' AND publie_le <= ? AND titre LIKE ?
                    ORDER BY publie_le DESC LIMIT $limite", [maintenant(), $like]) as $r) {
        $res[] = ['type' => 'actualite', 'titre' => (string) $r['titre'],
                  'sous_titre' => '', 'lien' => '/actualites/' . $r['slug']];
    }
    $u = utilisateur();
    if ($u) {
        foreach (qtous("SELECT id, numero, objet, statut FROM demandes_rh
                        WHERE utilisateur_id = ? AND (objet LIKE ? OR numero LIKE ?)
                        ORDER BY cree_le DESC LIMIT $limite",
                       [(int) $u['id'], $like, $like]) as $r) {
            $res[] = ['type' => 'demande', 'titre' => (string) $r['objet'],
                      'sous_titre' => (string) $r['numero'], 'lien' => '/demandes/' . (int) $r['id']];
        }
        foreach (documents_de((int) $u['id']) as $d) {
            if (stripos((string) $d['titre'], $q) === false) continue;
            $res[] = ['type' => 'document', 'titre' => (string) $d['titre'],
                      'sous_titre' => t('doc_cat_' . $d['categorie']),
                      'lien' => '/document/' . (int) $d['id']];
        }
    }
    foreach (catalogue_formations() as $f) {
        if (stripos(col_langue($f, 'titre'), $q) === false) continue;
        $res[] = ['type' => 'formation', 'titre' => col_langue($f, 'titre'),
                  'sous_titre' => '', 'lien' => '/formations'];
    }
    return $res;
}
