<?php
/**
 * Paie (section 8).
 *
 * ------------------------------------------------------------------------
 * LE PORTAIL AFFICHE LA PAIE, IL NE LA CALCULE PAS.
 *
 * Il n'y a dans ce fichier aucune formule de retenue sociale ou fiscale, et
 * il n'y en aura pas. Les regles different entre l'Algerie et le Canada,
 * elles different entre provinces canadiennes, et elles changent chaque
 * annee. Une formule ecrite ici serait fausse un jour sans prevenir, elle
 * serait fausse sur le bulletin d'un salarie, et c'est l'employeur qui
 * repondrait de l'ecart.
 *
 * Ce que le fichier fait :
 *   - il enregistre des bulletins SAISIS par la RH ou IMPORTES ;
 *   - il additionne les lignes de CE bulletin pour verifier que le net
 *     saisi correspond aux lignes saisies, et il SIGNALE l'ecart au lieu de
 *     corriger l'un des deux ;
 *   - il calcule la prochaine date de paie a partir du calendrier, ce qui
 *     est de l'arithmetique de dates et pas du droit fiscal.
 *
 * Le jour ou un vrai logiciel de paie est branche, 'paie_source' passe a
 * 'api' et l'import remplace la saisie. Le reste ne bouge pas.
 * ------------------------------------------------------------------------
 */

declare(strict_types=1);

const TYPES_LIGNE_PAIE = ['salaire', 'prime', 'deduction', 'retenue'];

function bulletins_de(int $utilisateur_id, int $limite = 24): array
{
    $limite = max(1, min(120, $limite));
    return qtous(
        "SELECT b.*, p.code AS periode_code, p.debut, p.fin, p.paiement_le
         FROM bulletins b LEFT JOIN periodes_paie p ON p.id = b.periode_id
         WHERE b.utilisateur_id = ? AND b.publie_le IS NOT NULL
         ORDER BY p.paiement_le DESC, b.id DESC LIMIT $limite",
        [$utilisateur_id]);
}

function bulletin(int $id): ?array
{
    $b = qun('SELECT b.*, p.code AS periode_code, p.debut, p.fin, p.paiement_le
              FROM bulletins b LEFT JOIN periodes_paie p ON p.id = b.periode_id
              WHERE b.id = ?', [$id]);
    if (!$b) return null;
    $b['lignes'] = qtous('SELECT * FROM lignes_bulletin WHERE bulletin_id = ? ORDER BY rang, id', [$id]);
    return $b;
}

/**
 * Verifie la coherence d'un bulletin SANS rien recalculer.
 *
 * salaire + primes - deductions - retenues doit valoir le net saisi. Si ce
 * n'est pas le cas, on rend l'ecart et l'interface l'affiche en rouge sur
 * l'ecran de la RH. On ne « corrige » pas le net : c'est peut-etre le net
 * qui est juste et une ligne qui manque.
 */
function coherence_bulletin(array $b): array
{
    $somme = 0.0;
    foreach ($b['lignes'] ?? [] as $l) {
        $m = (float) $l['montant'];
        $somme += in_array($l['type'], ['deduction', 'retenue'], true) ? -$m : $m;
    }
    $net = (float) ($b['net'] ?? 0);
    $ecart = round($somme - $net, 2);
    return ['somme_lignes' => round($somme, 2), 'net_saisi' => round($net, 2),
            'ecart' => $ecart, 'coherent' => abs($ecart) < 0.01];
}

/**
 * La prochaine date de paie d'un salarie.
 *
 * Elle vient du CALENDRIER de periodes saisi par la RH. Si aucune periode
 * future n'existe, on rend null et l'interface dit « calendrier de paie non
 * renseigne » — elle n'extrapole pas « dans 14 jours » a partir de la
 * derniere, parce qu'un jour ferie ou une fermeture decale la date reelle.
 */
function prochaine_paie(array $u): ?array
{
    $freq = (string) ($u['frequence_paie'] ?? '');
    $pays = (string) ($u['pays_travail'] ?? '');
    $p = qun("SELECT * FROM periodes_paie
              WHERE paiement_le >= ? AND (pays = ? OR pays IS NULL OR pays = '')
                AND (frequence = ? OR ? = '')
              ORDER BY paiement_le LIMIT 1",
             [aujourdhui(), $pays, $freq, $freq]);
    return $p ?: null;
}

/** Cumul de l'annee : ce que le salarie a REELLEMENT recu, pas une projection. */
function cumul_annee(int $utilisateur_id, ?int $annee = null): array
{
    $annee = $annee ?: (int) gmdate('Y');
    $r = qun("SELECT COALESCE(SUM(b.brut),0) AS brut, COALESCE(SUM(b.net),0) AS net,
                     COUNT(*) AS n
              FROM bulletins b JOIN periodes_paie p ON p.id = b.periode_id
              WHERE b.utilisateur_id = ? AND b.publie_le IS NOT NULL
                AND p.paiement_le >= ? AND p.paiement_le <= ?",
             [$utilisateur_id, "$annee-01-01", "$annee-12-31"]);
    $primes = (float) (qval(
        "SELECT COALESCE(SUM(l.montant),0)
         FROM lignes_bulletin l JOIN bulletins b ON b.id = l.bulletin_id
         JOIN periodes_paie p ON p.id = b.periode_id
         WHERE b.utilisateur_id = ? AND l.type = 'prime' AND b.publie_le IS NOT NULL
           AND p.paiement_le >= ? AND p.paiement_le <= ?",
        [$utilisateur_id, "$annee-01-01", "$annee-12-31"]) ?? 0);
    return ['annee' => $annee, 'brut' => (float) $r['brut'], 'net' => (float) $r['net'],
            'primes' => $primes, 'bulletins' => (int) $r['n']];
}

/* ------------------------------------------------------------------ */
/* Primes (« et ses primes »)                                          */
/* ------------------------------------------------------------------ */

function primes_de(int $utilisateur_id): array
{
    return qtous('SELECT * FROM primes WHERE utilisateur_id = ? ORDER BY accordee_le DESC',
                 [$utilisateur_id]);
}

function accorder_prime(int $utilisateur_id, array $d): array
{
    if (!peut('paie.gerer')) return ['erreur' => t('refus_droit')];
    $montant = (float) str_replace([' ', ','], ['', '.'], (string) ($d['montant'] ?? ''));
    if ($montant <= 0) return ['erreurs' => ['montant' => t('paie_err_montant')]];
    $libelle = trim((string) ($d['libelle'] ?? ''));
    if ($libelle === '') return ['erreurs' => ['libelle' => t('paie_err_libelle')]];

    $u = employe($utilisateur_id);
    $id = insere('primes', [
        'utilisateur_id' => $utilisateur_id,
        'libelle' => mb_substr($libelle, 0, 190),
        'montant' => $montant,
        'devise' => (string) ($d['devise'] ?? $u['devise'] ?? ''),
        'motif' => mb_substr((string) ($d['motif'] ?? ''), 0, 2000),
        'accordee_le' => (string) ($d['accordee_le'] ?? aujourdhui()),
        'accordee_par' => (int) utilisateur()['id'],
        'cree_le' => maintenant(),
    ]);
    audit('prime.accordee', 'prime', $id, [], ['montant' => $montant]);
    notifier($utilisateur_id, 'prime.accordee', t('notif_prime_titre'),
             t('notif_prime_corps', ['libelle' => $libelle]), '/paie');
    return ['id' => $id];
}

/* ------------------------------------------------------------------ */
/* Saisie d'un bulletin par la RH                                      */
/* ------------------------------------------------------------------ */

function enregistrer_bulletin(array $d, array $lignes): array
{
    if (!peut('paie.gerer')) return ['erreur' => t('refus_droit')];
    $uid = (int) ($d['utilisateur_id'] ?? 0);
    $pid = (int) ($d['periode_id'] ?? 0);
    if (!employe($uid)) return ['erreurs' => ['utilisateur_id' => t('refus_introuvable')]];
    if (!qval('SELECT id FROM periodes_paie WHERE id = ?', [$pid])) {
        return ['erreurs' => ['periode_id' => t('paie_err_periode')]];
    }
    $existe = qval('SELECT id FROM bulletins WHERE utilisateur_id = ? AND periode_id = ?', [$uid, $pid]);
    if ($existe !== null) return ['erreurs' => ['periode_id' => t('paie_err_deja')]];

    $u = employe($uid);
    $id = insere('bulletins', [
        'utilisateur_id' => $uid, 'periode_id' => $pid,
        'devise' => (string) ($d['devise'] ?? $u['devise'] ?? ''),
        'brut' => (float) ($d['brut'] ?? 0), 'net' => (float) ($d['net'] ?? 0),
        'note' => mb_substr((string) ($d['note'] ?? ''), 0, 2000),
        'cree_le' => maintenant(),
    ]);
    $rang = 0;
    foreach ($lignes as $l) {
        if (!in_array($l['type'] ?? '', TYPES_LIGNE_PAIE, true)) continue;
        if (trim((string) ($l['libelle'] ?? '')) === '') continue;
        insere('lignes_bulletin', [
            'bulletin_id' => $id, 'rang' => ++$rang,
            'type' => $l['type'], 'libelle' => mb_substr((string) $l['libelle'], 0, 190),
            'montant' => (float) $l['montant'],
        ]);
    }
    audit('bulletin.saisie', 'bulletin', $id, [], ['utilisateur' => $uid, 'periode' => $pid]);
    return ['id' => $id];
}

/**
 * Publication : c'est ce geste, et lui seul, qui rend le bulletin visible
 * du salarie. Tant que `publie_le` est NULL, la RH peut corriger sans que
 * personne ait vu un chiffre faux.
 */
function publier_bulletin(int $id): array
{
    if (!peut('paie.gerer')) return ['erreur' => t('refus_droit')];
    $b = bulletin($id);
    if (!$b) return ['erreur' => t('refus_introuvable')];
    if ($b['publie_le']) return ['erreur' => t('paie_err_deja_publie')];

    $c = coherence_bulletin($b);
    if (!$c['coherent']) return ['erreur' => t('paie_err_incoherent',
        ['ecart' => nombre($c['ecart'], 2), 'devise' => (string) $b['devise']])];

    maj('bulletins', $id, ['publie_le' => maintenant()]);
    audit('bulletin.publication', 'bulletin', $id);
    notifier((int) $b['utilisateur_id'], 'bulletin.publie', t('notif_bulletin_titre'),
             t('notif_bulletin_corps', ['periode' => (string) $b['periode_code']]), '/paie');
    return ['ok' => true];
}

/**
 * Genere un calendrier de periodes.
 *
 * C'est de l'arithmetique de dates, pas du droit : « toutes les deux
 * semaines a partir du 2 janvier », ou « le dernier jour de chaque mois ».
 * La RH relit et corrige les dates de versement — un versement qui tombe un
 * jour ferie se decale, et c'est une decision de l'employeur.
 */
function generer_periodes(string $pays, string $frequence, string $premier_debut,
                          int $combien, int $decalage_paiement = 5): array
{
    if (!in_array($frequence, ['bimensuelle', 'mensuelle'], true)) {
        return ['erreur' => t('paie_err_frequence')];
    }
    if (!date_valide($premier_debut)) return ['erreur' => t('cg_err_date')];
    $combien = max(1, min(60, $combien));

    $d = new DateTimeImmutable($premier_debut);
    $crees = 0;
    for ($i = 0; $i < $combien; $i++) {
        if ($frequence === 'bimensuelle') {
            $fin = $d->modify('+13 days');
            $suivant = $d->modify('+14 days');
        } else {
            $fin = $d->modify('last day of this month');
            $suivant = $d->modify('first day of next month');
        }
        $paie = $fin->modify('+' . $decalage_paiement . ' days');
        $code = strtoupper($pays) . '-' . $d->format('Y-m-d');
        if (qval('SELECT id FROM periodes_paie WHERE code = ?', [$code]) === null) {
            insere('periodes_paie', [
                'code' => $code, 'pays' => $pays, 'frequence' => $frequence,
                'debut' => $d->format('Y-m-d'), 'fin' => $fin->format('Y-m-d'),
                'paiement_le' => $paie->format('Y-m-d'), 'statut' => 'prevue',
            ]);
            $crees++;
        }
        $d = $suivant;
    }
    audit('paie.calendrier', 'periode', null, [], ['pays' => $pays, 'crees' => $crees]);
    return ['crees' => $crees];
}
