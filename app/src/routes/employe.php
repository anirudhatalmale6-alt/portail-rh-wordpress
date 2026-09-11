<?php
/**
 * Routes de l'espace salarie.
 */

declare(strict_types=1);

function page_accueil(): void
{
    $u = utilisateur();
    // Un salarie decentralise arrive sur SA feuille de temps, pas sur un
    // solde de vacances : c'est sa premiere action de la journee.
    rendre('accueil', ['u' => employe_enrichi((int) $u['id'])] + tableau_employe($u));
}

/* ------------------------------------------------------------------ */
/* Profil                                                              */
/* ------------------------------------------------------------------ */

function page_profil(): void
{
    $u = utilisateur();
    rendre('profil', [
        'u' => employe_enrichi((int) $u['id']),
        'erreurs' => [],
        'manquants' => champs_manquants($u),
    ]);
}

function post_profil(): void
{
    $u = utilisateur();
    $id = (int) $u['id'];

    // LISTE BLANCHE. Le salarie modifie ses coordonnees PERSONNELLES et rien
    // d'autre. Son poste, son salaire, son manager et son role viennent de
    // la RH : accepter tout $_POST laisserait n'importe qui se nommer
    // directeur en ajoutant un champ dans le formulaire.
    $champs = ['adresse', 'telephone', 'email_perso', 'urgence_nom',
               'urgence_lien', 'urgence_tel', 'langue', 'fuseau',
               'annuaire_visible', 'annuaire_tel'];
    $donnees = [];
    foreach ($champs as $c) {
        if (!array_key_exists($c, $_POST)) continue;
        $v = $_POST[$c];
        $donnees[$c] = match ($c) {
            'annuaire_visible', 'annuaire_tel' => !empty($v) ? 1 : 0,
            'langue' => in_array($v, cfg('langues'), true) ? $v : $u['langue'],
            'fuseau' => in_array($v, timezone_identifiers_list(), true) ? $v : $u['fuseau'],
            default => mb_substr(trim((string) $v), 0, 255),
        };
    }
    // Les cases a cocher absentes du POST valent 0, pas « inchange » :
    // decocher une case n'envoie rien du tout.
    foreach (['annuaire_visible', 'annuaire_tel'] as $c) {
        $donnees[$c] = !empty($_POST[$c]) ? 1 : 0;
    }
    if (!empty($donnees['email_perso']) && !filter_var($donnees['email_perso'], FILTER_VALIDATE_EMAIL)) {
        rendre('profil', ['u' => employe_enrichi($id), 'manquants' => champs_manquants($u),
                          'erreurs' => ['email_perso' => t('rec_err_email')]]);
        return;
    }

    $donnees['maj_le'] = maintenant();
    maj('utilisateurs', $id, $donnees);
    audit('profil.modification', 'utilisateur', $id, changements($u, $donnees));
    flash('ok', t('profil_enregistre'));
    redirige('/profil');
}

function page_bancaire(): void
{
    // La consultation elle-meme passe par la reauthentification : afficher
    // « •••• 4321 » sur un ecran laisse ouvert n'est pas grave, mais le
    // formulaire de MODIFICATION est sur la meme page, et c'est lui qu'on
    // protege.
    exige_reauth('/profil/bancaire');
    $u = utilisateur();
    rendre('bancaire', [
        'u' => $u, 'erreur' => null,
        'coffre' => coffre_disponible(),
    ]);
}

function post_bancaire(): void
{
    exige_reauth('/profil/bancaire');
    $u = utilisateur();
    $r = enregistrer_banque((int) $u['id'],
        (string) ($_POST['institution'] ?? ''),
        (string) ($_POST['numero'] ?? ''),
        in_array($_POST['mode_paiement'] ?? '', ['virement', 'cheque', 'autre'], true)
            ? (string) $_POST['mode_paiement'] : 'virement');
    if (isset($r['erreur'])) {
        rendre('bancaire', ['u' => $u, 'erreur' => $r['erreur'], 'coffre' => coffre_disponible()]);
        return;
    }
    flash('ok', t('banque_enregistre'));
    redirige('/profil');
}

/* ------------------------------------------------------------------ */
/* Conges et absences                                                  */
/* ------------------------------------------------------------------ */

function page_conges(): void
{
    $u = utilisateur();
    rendre('conges', [
        'u' => $u,
        'soldes' => soldes_de((int) $u['id']),
        'demandes' => conges_de((int) $u['id']),
        'types' => qtous('SELECT * FROM types_conges WHERE actif = 1 ORDER BY rang, id'),
        'erreurs' => [], 'saisie' => [],
    ]);
}

function post_conge(): void
{
    $u = utilisateur();
    $r = demander_conge((int) $u['id'], $_POST);
    if (isset($r['erreurs'])) {
        rendre('conges', [
            'u' => $u, 'soldes' => soldes_de((int) $u['id']),
            'demandes' => conges_de((int) $u['id']),
            'types' => qtous('SELECT * FROM types_conges WHERE actif = 1 ORDER BY rang, id'),
            'erreurs' => $r['erreurs'], 'saisie' => $_POST,
        ]);
        return;
    }
    flash('ok', t('cg_envoyee'));
    redirige('/conges');
}

function post_annuler_conge(string $id): void
{
    $r = annuler_conge((int) $id);
    flash(isset($r['erreur']) ? 'erreur' : 'ok', $r['erreur'] ?? t('cg_annulee'));
    redirige('/conges');
}

function page_absences(): void
{
    $u = utilisateur();
    rendre('absences', [
        'u' => $u, 'absences' => absences_de((int) $u['id']),
        'erreurs' => [], 'saisie' => [],
    ]);
}

function post_absence(): void
{
    $u = utilisateur();
    $r = declarer_absence((int) $u['id'], $_POST, $_FILES['justificatif'] ?? null);
    if (isset($r['erreurs'])) {
        rendre('absences', ['u' => $u, 'absences' => absences_de((int) $u['id']),
                            'erreurs' => $r['erreurs'], 'saisie' => $_POST]);
        return;
    }
    flash('ok', t('abs_enregistree'));
    redirige('/absences');
}

function page_calendrier(): void
{
    $u = utilisateur();
    $mois = (string) ($_GET['mois'] ?? gmdate('Y-m'));
    if (!preg_match('/^\d{4}-\d{2}$/', $mois)) $mois = gmdate('Y-m');
    $debut = $mois . '-01';
    $fin = (new DateTimeImmutable($debut))->modify('last day of this month')->format('Y-m-d');

    // Le calendrier montre SON equipe s'il en a une, sinon lui seul. Il ne
    // montre jamais toute l'entreprise a un salarie : « qui est absent » est
    // une information sur les gens, pas une information de service.
    $ids = peut('equipe.voir') ? equipe_de((int) $u['id']) : [];
    $ids[] = (int) $u['id'];
    $ids = array_values(array_unique($ids));

    rendre('calendrier', [
        'mois' => $mois, 'debut' => $debut, 'fin' => $fin,
        'evenements' => calendrier_absences($ids, $debut, $fin),
        'personnes' => $ids,
        'feries' => qtous('SELECT * FROM jours_feries WHERE date >= ? AND date <= ? ORDER BY date',
                          [$debut, $fin]),
        'noms' => noms_par_id($ids),
    ]);
}

function noms_par_id(array $ids): array
{
    if (!$ids) return [];
    $in = implode(',', array_fill(0, count($ids), '?'));
    $out = [];
    foreach (qtous("SELECT id, prenom, nom FROM utilisateurs WHERE id IN ($in)", $ids) as $r) {
        $out[(int) $r['id']] = nom_complet($r);
    }
    return $out;
}

/* ------------------------------------------------------------------ */
/* Documents et paie                                                   */
/* ------------------------------------------------------------------ */

function page_documents(): void
{
    $u = utilisateur();
    rendre('documents', [
        'documents' => documents_de((int) $u['id'], (string) ($_GET['categorie'] ?? '') ?: null),
        'entreprise' => documents_entreprise(),
        'categorie' => (string) ($_GET['categorie'] ?? ''),
    ]);
}

function page_document(string $id): void
{
    servir_document((int) $id);
}

function page_paie(): void
{
    $u = utilisateur();
    rendre('paie', [
        'u' => $u,
        'bulletins' => bulletins_de((int) $u['id']),
        'primes' => primes_de((int) $u['id']),
        'prochaine' => prochaine_paie($u),
        'cumul' => cumul_annee((int) $u['id']),
    ]);
}

function page_bulletin(string $id): void
{
    $b = bulletin((int) $id);
    $u = utilisateur();
    if (!$b) { http_response_code(404); rendre('erreur', ['code' => 404, 'message' => t('refus_introuvable')]); return; }
    // Son bulletin, ou celui de quelqu'un d'autre avec paie.gerer. Le
    // manager n'est pas dans la liste : il ne voit pas la paie de son
    // equipe.
    $sien = (int) $b['utilisateur_id'] === (int) $u['id'];
    if (!$sien && !peut('paie.gerer')) reponse_refus(403, t('refus_droit'));
    if ($sien && !$b['publie_le']) reponse_refus(404, t('refus_introuvable'));

    rendre('bulletin', [
        'b' => $b, 'coherence' => coherence_bulletin($b),
        'employe' => employe((int) $b['utilisateur_id']),
        'sien' => $sien,
    ]);
}

/* ------------------------------------------------------------------ */
/* Demandes RH                                                         */
/* ------------------------------------------------------------------ */

function page_demandes(): void
{
    $u = utilisateur();
    rendre('demandes', ['demandes' => demandes_de((int) $u['id'])]);
}

function page_demande_nouvelle(): void
{
    rendre('demande_nouvelle', ['erreurs' => [], 'saisie' => []]);
}

function post_demande(): void
{
    $u = utilisateur();
    $r = creer_demande((int) $u['id'], $_POST, $_FILES['piece'] ?? null);
    if (isset($r['erreurs'])) {
        rendre('demande_nouvelle', ['erreurs' => $r['erreurs'], 'saisie' => $_POST]);
        return;
    }
    flash('ok', t('dem_envoyee'));
    redirige('/demandes/' . $r['id']);
}

function page_demande(string $id): void
{
    $d = demande((int) $id);
    if (!$d) { http_response_code(404); rendre('erreur', ['code' => 404, 'message' => t('refus_introuvable')]); return; }
    if (!peut_voir_demande($d)) reponse_refus(403, t('refus_droit'));
    rendre('demande', ['d' => $d, 'traite' => peut('demandes.traiter')]);
}

function post_message_demande(string $id): void
{
    $r = repondre_demande((int) $id, (string) ($_POST['corps'] ?? ''),
                          !empty($_POST['interne']), $_FILES['piece'] ?? null);
    flash(isset($r['erreur']) ? 'erreur' : 'ok', $r['erreur'] ?? t('dem_message_ajoute'));
    redirige('/demandes/' . (int) $id);
}

function post_statut_demande(string $id): void
{
    $r = changer_statut_demande((int) $id, (string) ($_POST['statut'] ?? ''),
        isset($_POST['responsable_id']) ? (int) $_POST['responsable_id'] : null);
    flash(isset($r['erreur']) ? 'erreur' : 'ok', $r['erreur'] ?? t('dem_statut_change'));
    redirige('/demandes/' . (int) $id);
}

/* ------------------------------------------------------------------ */
/* Notifications, annuaire, contenus                                   */
/* ------------------------------------------------------------------ */

function page_notifications(): void
{
    $u = utilisateur();
    rendre('notifications', [
        'notifications' => notifications_de((int) $u['id']),
        'email_actif' => email_actif(),
    ]);
}

function post_notifications_lues(): void
{
    $u = utilisateur();
    marquer_lues((int) $u['id'], isset($_POST['id']) ? (int) $_POST['id'] : null);
    redirige('/notifications');
}

function page_annuaire(): void
{
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $filtres = [
        'q' => trim((string) ($_GET['q'] ?? '')),
        'departement_id' => (int) ($_GET['departement_id'] ?? 0),
        'localisation_id' => (int) ($_GET['localisation_id'] ?? 0),
    ];
    $r = annuaire($filtres, $page);
    rendre('annuaire', [
        'resultat' => $r,
        'cartes' => array_map('carte_annuaire', $r['lignes']),
        'filtres' => $filtres,
        'departements' => qtous('SELECT * FROM departements ORDER BY rang, nom_fr'),
        'localisations' => qtous('SELECT * FROM localisations ORDER BY nom'),
    ]);
}

function page_fiche(string $id): void
{
    $cible = employe_enrichi((int) $id);
    if (!$cible) { http_response_code(404); rendre('erreur', ['code' => 404, 'message' => t('refus_introuvable')]); return; }
    $portee = portee_dossier((int) $id);
    // Sans portee, on ne rend que la carte d'annuaire — et seulement si la
    // personne y figure. Un salarie retire de l'annuaire n'a pas de page.
    if ($portee === null && ((int) $cible['annuaire_visible'] !== 1 || $cible['statut'] !== 'actif')) {
        reponse_refus(403, t('refus_droit'));
    }
    rendre('fiche', ['c' => $cible, 'portee' => $portee]);
}

function page_organigramme(): void
{
    rendre('organigramme', organigramme());
}

function page_actualites(): void
{
    rendre('actualites', ['actualites' => actualites_publiees(50)]);
}

function page_actualite(string $slug): void
{
    $a = actualite_par_slug($slug);
    if (!$a) { http_response_code(404); rendre('erreur', ['code' => 404, 'message' => t('refus_introuvable')]); return; }
    rendre('actualite', ['a' => $a]);
}

function page_formations(): void
{
    $u = utilisateur();
    rendre('formations', [
        'miennes' => formations_de((int) $u['id']),
        'catalogue' => catalogue_formations(),
    ]);
}

function page_avantages(): void
{
    $u = utilisateur();
    rendre('avantages', ['avantages' => avantages_pour($u)]);
}

function page_recherche(): void
{
    $q = trim((string) ($_GET['q'] ?? ''));
    rendre('recherche', ['q' => $q, 'resultats' => recherche_globale($q)]);
}

function page_a_renseigner(): void
{
    $u = utilisateur();
    rendre('a_renseigner', [
        'mes_champs' => champs_manquants($u),
        'reglages' => peut('admin.parametres') ? reglages_manquants() : [],
        'fiches' => peut('profil.tous.voir') ? fiches_incompletes() : null,
    ]);
}

/* ------------------------------------------------------------------ */
/* Temps de travail                                                    */
/* ------------------------------------------------------------------ */

function page_temps(): void
{
    $u = utilisateur();
    $ref = (string) ($_GET['semaine'] ?? aujourdhui_local($u));
    if (!date_valide($ref)) $ref = aujourdhui_local($u);
    $lundi = debut_semaine($ref);
    rendre('temps', [
        'u' => $u,
        'semaine' => semaine_temps((int) $u['id'], $lundi),
        'mois' => total_mois((int) $u['id'], substr($lundi, 0, 7)),
        'aujourdhui' => aujourdhui_local($u),
        'erreurs' => [],
    ]);
}

function post_journee(): void
{
    $u = utilisateur();
    $r = enregistrer_journee((int) $u['id'], $_POST);
    if (isset($r['erreurs'])) {
        $lundi = debut_semaine((string) ($_POST['date'] ?? aujourdhui_local($u)));
        rendre('temps', [
            'u' => $u, 'semaine' => semaine_temps((int) $u['id'], $lundi),
            'mois' => total_mois((int) $u['id'], substr($lundi, 0, 7)),
            'aujourdhui' => aujourdhui_local($u), 'erreurs' => $r['erreurs'],
        ]);
        return;
    }
    flash('ok', t('tps_journee_enregistree', ['duree' => duree_lisible((int) $r['minutes'])]));
    redirige('/temps?semaine=' . rawurlencode(debut_semaine((string) $_POST['date'])));
}

function post_soumettre_semaine(): void
{
    $u = utilisateur();
    $lundi = (string) ($_POST['lundi'] ?? '');
    if (!date_valide($lundi)) redirige('/temps');
    $r = soumettre_semaine((int) $u['id'], $lundi);
    flash(isset($r['erreur']) ? 'erreur' : 'ok',
          $r['erreur'] ?? t('tps_semaine_soumise', ['n' => (int) $r['soumis']]));
    redirige('/temps?semaine=' . rawurlencode($lundi));
}
