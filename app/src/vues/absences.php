<?php meta(['titre' => t('nav_absences')]); ?>
<h1><?= h(t('abs_titre')) ?></h1>
<p class="lead"><?= h(t('abs_intro')) ?></p>

<section class="carte">
  <h2><?= h(t('abs_declarer')) ?></h2>
  <form method="post" action="<?= h(lien('/absences/declarer')) ?>" class="formulaire grille-2" enctype="multipart/form-data">
    <?= champ_csrf() ?>
    <div class="champ">
      <label for="ty"><?= h(t('abs_type')) ?></label>
      <select id="ty" name="type" required>
        <?php foreach (TYPES_ABSENCE as $ta): ?>
          <option value="<?= h($ta) ?>"><?= h(t('abs_type_' . $ta)) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if (!empty($erreurs['type'])): ?><span class="erreur"><?= h($erreurs['type']) ?></span><?php endif; ?>
    </div>
    <div class="champ">
      <label for="db"><?= h(t('cg_debut')) ?></label>
      <input id="db" name="debut" type="date" value="<?= h((string) ($saisie['debut'] ?? aujourdhui())) ?>" required>
      <?php if (!empty($erreurs['debut'])): ?><span class="erreur"><?= h($erreurs['debut']) ?></span><?php endif; ?>
    </div>
    <div class="champ">
      <label for="fn"><?= h(t('cg_fin')) ?></label>
      <input id="fn" name="fin" type="date" value="<?= h((string) ($saisie['fin'] ?? aujourdhui())) ?>" required>
      <?php if (!empty($erreurs['fin'])): ?><span class="erreur"><?= h($erreurs['fin']) ?></span><?php endif; ?>
    </div>
    <div class="champ">
      <label for="ju"><?= h(t('abs_justificatif')) ?></label>
      <input id="ju" name="justificatif" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp">
      <span class="aide"><?= h(t('abs_justificatif_aide')) ?></span>
      <?php if (!empty($erreurs['justificatif'])): ?><span class="erreur"><?= h($erreurs['justificatif']) ?></span><?php endif; ?>
    </div>
    <div class="champ champ--large">
      <label for="no"><?= h(t('abs_note')) ?></label>
      <textarea id="no" name="note" class="ta-court"><?= h((string) ($saisie['note'] ?? '')) ?></textarea>
    </div>
    <div class="champ champ--large">
      <button class="btn btn--plein" type="submit"><?= h(t('abs_envoyer')) ?></button>
    </div>
  </form>
</section>

<section>
  <h2><?= h(t('abs_historique')) ?></h2>
  <?php if (!$absences): ?>
    <p class="vide-liste"><?= h(t('abs_aucune')) ?></p>
  <?php else: ?>
  <div class="tableau-defile">
  <table class="tableau">
    <thead><tr><th><?= h(t('abs_type')) ?></th><th><?= h(t('cg_periode')) ?></th>
      <th><?= h(t('cg_jours')) ?></th><th><?= h(t('abs_justificatif')) ?></th><th><?= h(t('statut')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($absences as $a): ?>
      <tr>
        <td><?= h(t('abs_type_' . $a['type'])) ?></td>
        <td><?= h((string) $a['debut']) ?> → <?= h((string) $a['fin']) ?></td>
        <td class="num"><?= h(nombre((float) $a['nb_jours'], 1)) ?></td>
        <td><?php if ($a['justificatif_id']): ?>
              <a href="<?= h(lien('/document/' . (int) $a['justificatif_id'])) ?>"><?= h(t('abs_voir_justificatif')) ?></a>
            <?php else: ?><?= sans_valeur(t('abs_sans_justificatif')) ?><?php endif; ?></td>
        <td><?= badge_statut((string) $a['statut'], 'absence') ?>
          <?php if ($a['commentaire_rh']): ?><p class="gris"><?= h((string) $a['commentaire_rh']) ?></p><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</section>
