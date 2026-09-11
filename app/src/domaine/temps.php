<?php
/**
 * Temps de travail — l'employe decentralise.
 *
 * ------------------------------------------------------------------------
 * « employe normal et employe decentralise qui gere son temps »
 *
 * Ce n'est pas un employe avec une case cochee. Il a un metier different :
 * pas d'horaire impose, il declare ses journees, il travaille depuis un
 * autre pays et un autre fuseau. D'ou :
 *
 *   - une feuille de temps a la journee, en BROUILLON tant qu'il ne l'a pas
 *     soumise. Un salarie doit pouvoir noter sa journee au fil de l'eau sans
 *     que son manager voie chaque correction ;
 *   - une SEMAINE qui se soumet d'un bloc, parce que c'est comme cela que
 *     ca se valide en vrai ;
 *   - une date qui est SA date locale.
 *
 * LE PIEGE DU FUSEAU, en clair.
 *
 * Si la date de la journee etait derivee d'un horodatage UTC, un salarie a
 * Auckland qui saisit sa journee du mardi matin la verrait tomber le lundi
 * en base, et son manager a Montreal lirait un lundi. On stocke donc la
 * DATE LOCALE telle qu'il la declare (colonne `date`, texte AAAA-MM-JJ) et
 * la DUREE en minutes. Les horodatages `cree_le` et `valide_le`, eux,
 * restent en UTC : ce sont des evenements du systeme, pas des journees de
 * travail.
 * ------------------------------------------------------------------------
 */

declare(strict_types=1);

const STATUTS_TEMPS = ['brouillon', 'soumis', 'valide', 'refuse'];

/** La date d'aujourd'hui DANS LE FUSEAU du salarie. */
function aujourdhui_local(array $u): string
{
    $f = $u['fuseau'] ?? '';
    if (!$f || !in_array($f, timezone_identifiers_list(), true)) $f = 'UTC';
    return (new DateTimeImmutable('now', new DateTimeZone($f)))->format('Y-m-d');
}

/** Le lundi de la semaine contenant $date. */
function debut_semaine(string $date): string
{
    $d = new DateTimeImmutable($date);
    return $d->modify('monday this week')->format('Y-m-d');
}

function semaine_temps(int $utilisateur_id, string $lundi): array
{
    $jours = [];
    $d = new DateTimeImmutable($lundi);
    for ($i = 0; $i < 7; $i++) {
        $jd = $d->modify("+$i days")->format('Y-m-d');
        $jours[$jd] = qun('SELECT * FROM feuilles_temps WHERE utilisateur_id = ? AND date = ?',
                          [$utilisateur_id, $jd]);
    }
    $total = 0; $soumis = 0; $valide = 0;
    foreach ($jours as $l) {
        if (!$l) continue;
        $total += (int) $l['minutes'];
        if ($l['statut'] === 'soumis') $soumis++;
        if ($l['statut'] === 'valide') $valide++;
    }
    return ['lundi' => $lundi, 'jours' => $jours, 'minutes' => $total,
            'nb_soumis' => $soumis, 'nb_valides' => $valide];
}

/**
 * Enregistre une journee. Le total en minutes est calcule ICI a partir de
 * debut/fin/pause, et pas envoye par le formulaire.
 *
 * Une journee qui franchit minuit (22 h → 02 h) est courante en travail
 * decentralise. `fin < debut` ajoute donc 24 h au lieu de rendre une duree
 * negative — qui serait enregistree sans broncher et fausserait le total de
 * la semaine.
 */
function enregistrer_journee(int $utilisateur_id, array $d): array
{
    $date = (string) ($d['date'] ?? '');
    if (!date_valide($date)) return ['erreurs' => ['date' => t('cg_err_date')]];

    $debut = (string) ($d['debut'] ?? '');
    $fin   = (string) ($d['fin'] ?? '');
    if (!heure_valide($debut) || !heure_valide($fin)) {
        return ['erreurs' => ['debut' => t('tps_err_heure')]];
    }
    $pause = max(0, min(600, (int) ($d['pause_min'] ?? 0)));

    $m1 = minutes_de($debut); $m2 = minutes_de($fin);
    $duree = $m2 - $m1;
    if ($duree < 0) $duree += 24 * 60;          // journee a cheval sur minuit
    $duree -= $pause;
    if ($duree <= 0) return ['erreurs' => ['fin' => t('tps_err_duree')]];
    if ($duree > 16 * 60) return ['erreurs' => ['fin' => t('tps_err_trop_long')]];

    $existante = qun('SELECT * FROM feuilles_temps WHERE utilisateur_id = ? AND date = ?',
                     [$utilisateur_id, $date]);
    // Une journee VALIDEE ne se reecrit pas depuis le formulaire. Elle se
    // rouvre, et c'est une action du manager qui laisse une trace.
    if ($existante && $existante['statut'] === 'valide') {
        return ['erreurs' => ['date' => t('tps_err_validee')]];
    }

    $donnees = [
        'debut' => $debut, 'fin' => $fin, 'pause_min' => $pause, 'minutes' => $duree,
        'projet' => mb_substr((string) ($d['projet'] ?? ''), 0, 190),
        'note' => mb_substr((string) ($d['note'] ?? ''), 0, 2000),
        'statut' => 'brouillon', 'maj_le' => maintenant(),
    ];
    if ($existante) {
        maj('feuilles_temps', (int) $existante['id'], $donnees);
        $id = (int) $existante['id'];
    } else {
        $id = insere('feuilles_temps',
            $donnees + ['utilisateur_id' => $utilisateur_id, 'date' => $date,
                        'cree_le' => maintenant()]);
    }
    return ['id' => $id, 'minutes' => $duree];
}

function soumettre_semaine(int $utilisateur_id, string $lundi): array
{
    $sem = semaine_temps($utilisateur_id, $lundi);
    $n = 0;
    foreach ($sem['jours'] as $l) {
        if ($l && $l['statut'] === 'brouillon') {
            maj('feuilles_temps', (int) $l['id'], ['statut' => 'soumis', 'maj_le' => maintenant()]);
            $n++;
        }
    }
    if ($n === 0) return ['erreur' => t('tps_err_rien_a_soumettre')];
    audit('temps.soumission', 'temps', null, [], ['semaine' => $lundi, 'jours' => $n]);

    $u = employe($utilisateur_id);
    $mid = (int) ($u['manager_id'] ?? 0);
    if ($mid) {
        notifier($mid, 'temps.a_valider', t('notif_temps_titre', ['nom' => nom_complet($u)]),
                 t('notif_temps_corps', ['semaine' => $lundi, 'jours' => $n]), '/equipe/temps');
    } else {
        notifier_role('rh', 'temps.a_valider', t('notif_temps_titre', ['nom' => nom_complet($u)]),
                      t('notif_temps_corps', ['semaine' => $lundi, 'jours' => $n]), '/rh/temps');
    }
    return ['soumis' => $n];
}

function decider_temps(int $id, string $decision, string $commentaire = ''): array
{
    if (!in_array($decision, ['valide', 'refuse'], true)) return ['erreur' => t('cg_err_decision')];
    $l = qun('SELECT * FROM feuilles_temps WHERE id = ?', [$id]);
    if (!$l) return ['erreur' => t('refus_introuvable')];
    $cible = (int) $l['utilisateur_id'];
    $autorise = peut('temps.administrer')
             || (peut('temps.equipe.valider') && est_mon_subordonne($cible));
    if (!$autorise) return ['erreur' => t('refus_droit')];
    if ($l['statut'] !== 'soumis') return ['erreur' => t('tps_err_pas_soumis')];

    maj('feuilles_temps', $id, [
        'statut' => $decision, 'valide_par' => (int) utilisateur()['id'],
        'valide_le' => maintenant(), 'commentaire' => mb_substr($commentaire, 0, 1000),
        'maj_le' => maintenant(),
    ]);
    audit('temps.' . $decision, 'temps', $id, ['statut' => ['soumis', $decision]]);
    notifier($cible, 'temps.' . $decision, t('notif_temps_decision_' . $decision),
             (string) $l['date'], '/temps');
    return ['ok' => true];
}

/** Les journees soumises que $manager peut trancher. */
function temps_a_valider(int $manager_id, bool $tous = false): array
{
    if ($tous) {
        return qtous("SELECT f.*, u.prenom, u.nom, u.fuseau
                      FROM feuilles_temps f JOIN utilisateurs u ON u.id = f.utilisateur_id
                      WHERE f.statut = 'soumis' ORDER BY u.nom, f.date");
    }
    $equipe = equipe_de($manager_id);
    if (!$equipe) return [];
    $in = implode(',', array_fill(0, count($equipe), '?'));
    return qtous("SELECT f.*, u.prenom, u.nom, u.fuseau
                  FROM feuilles_temps f JOIN utilisateurs u ON u.id = f.utilisateur_id
                  WHERE f.statut = 'soumis' AND f.utilisateur_id IN ($in)
                  ORDER BY u.nom, f.date", $equipe);
}

function total_mois(int $utilisateur_id, string $mois): array
{
    $r = qun("SELECT COALESCE(SUM(minutes),0) AS m, COUNT(*) AS n
              FROM feuilles_temps
              WHERE utilisateur_id = ? AND date LIKE ? AND statut IN ('soumis','valide')",
             [$utilisateur_id, $mois . '-%']);
    return ['minutes' => (int) $r['m'], 'jours' => (int) $r['n']];
}

function heure_valide(string $h): bool
{
    return (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $h);
}

function minutes_de(string $h): int
{
    [$hh, $mm] = array_map('intval', explode(':', $h));
    return $hh * 60 + $mm;
}

function duree_lisible(int $minutes): string
{
    $h = intdiv($minutes, 60); $m = $minutes % 60;
    return $m === 0 ? $h . ' h' : $h . ' h ' . str_pad((string) $m, 2, '0', STR_PAD_LEFT);
}
