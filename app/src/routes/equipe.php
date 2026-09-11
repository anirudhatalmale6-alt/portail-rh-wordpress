<?php
/**
 * Routes du manager (section 20).
 *
 * Rappel du modele : le manager valide et suit. Il ne consulte NI la paie,
 * NI le dossier bancaire, NI le justificatif medical de son equipe. Les
 * permissions le disent, et ces routes n'exposent rien d'autre.
 */

declare(strict_types=1);

function page_equipe(): void
{
    $u = utilisateur();
    rendre('equipe', ['u' => $u] + tableau_manager($u));
}

function page_equipe_conges(): void
{
    $u = utilisateur();
    rendre('equipe_conges', [
        'demandes' => conges_a_valider((int) $u['id'], peut('conges.administrer')),
        'tous' => peut('conges.administrer'),
    ]);
}

function post_decision_conge(string $id): void
{
    $r = decider_conge((int) $id, (string) ($_POST['decision'] ?? ''),
                       (string) ($_POST['commentaire'] ?? ''));
    flash(isset($r['erreur']) ? 'erreur' : 'ok', $r['erreur'] ?? t('cg_decision_prise'));
    redirige((string) ($_POST['retour'] ?? '/equipe/conges'));
}

function page_equipe_absences(): void
{
    $u = utilisateur();
    $ids = peut('absences.administrer')
        ? array_map('intval', array_column(qtous("SELECT id FROM utilisateurs WHERE statut = 'actif'"), 'id'))
        : equipe_de((int) $u['id']);

    $lignes = [];
    if ($ids) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $lignes = qtous("SELECT a.*, u.prenom, u.nom FROM absences a
                         JOIN utilisateurs u ON u.id = a.utilisateur_id
                         WHERE a.utilisateur_id IN ($in)
                         ORDER BY a.debut DESC LIMIT 200", $ids);
    }
    rendre('equipe_absences', [
        'absences' => $lignes,
        // Le manager voit les dates et le fait qu'un justificatif existe.
        // Il ne voit ni le motif detaille ni le document.
        'voit_motif' => peut('absences.justificatif'),
        'administre' => peut('absences.administrer'),
    ]);
}

function page_equipe_temps(): void
{
    $u = utilisateur();
    rendre('equipe_temps', [
        'lignes' => temps_a_valider((int) $u['id'], peut('temps.administrer')),
        'tous' => peut('temps.administrer'),
    ]);
}

function post_decision_temps(string $id): void
{
    $r = decider_temps((int) $id, (string) ($_POST['decision'] ?? ''),
                       (string) ($_POST['commentaire'] ?? ''));
    flash(isset($r['erreur']) ? 'erreur' : 'ok', $r['erreur'] ?? t('tps_decision_prise'));
    redirige((string) ($_POST['retour'] ?? '/equipe/temps'));
}
