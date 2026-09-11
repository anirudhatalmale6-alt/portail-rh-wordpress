<?php meta(['titre' => t('rh_lien_actualites')]); ?>
<h1><?= h(t('rh_lien_actualites')) ?></h1>

<section class="carte">
  <h2><?= h(t('act_nouvelle')) ?></h2>
  <form method="post" action="<?= h(lien('/rh/actualites')) ?>" class="formulaire">
    <?= champ_csrf() ?>
    <div class="grille-2">
      <div class="champ"><label for="at"><?= h(t('act_titre_champ')) ?></label><input id="at" name="titre" required></div>
      <div class="champ"><label for="al"><?= h(t('act_langue')) ?></label>
        <select id="al" name="langue"><?php foreach (cfg('langues') as $l): ?>
          <option value="<?= h($l) ?>"<?= $l === langue() ? ' selected' : '' ?>><?= h(t('langue_' . $l)) ?></option><?php endforeach; ?></select>
        <span class="aide"><?= h(t('act_langue_aide')) ?></span></div>
      <div class="champ"><label for="ag"><?= h(t('act_groupe')) ?></label><input id="ag" name="groupe">
        <span class="aide"><?= h(t('act_groupe_aide')) ?></span></div>
      <div class="champ"><label for="ad"><?= h(t('act_date_publication')) ?></label>
        <input id="ad" name="publie_le" type="datetime-local">
        <span class="aide"><?= h(t('act_date_aide')) ?></span></div>
    </div>
    <div class="champ"><label for="ac"><?= h(t('act_chapeau')) ?></label><textarea id="ac" name="chapeau" class="ta-court"></textarea></div>
    <div class="champ"><label for="ab"><?= h(t('act_corps')) ?></label><textarea id="ab" name="corps" rows="10"></textarea>
      <span class="aide"><?= h(t('dem_syntaxe')) ?></span></div>
    <div class="champ">
      <label class="case"><input type="checkbox" name="epingle" value="1"> <?= h(t('act_epingler')) ?></label>
    </div>
    <div class="champ"><label for="as"><?= h(t('statut')) ?></label>
      <select id="as" name="statut">
        <option value="brouillon"><?= h(t('statut_brouillon')) ?></option>
        <option value="publie"><?= h(t('statut_publie')) ?></option>
      </select></div>
    <button class="btn btn--plein" type="submit"><?= h(t('enregistrer')) ?></button>
  </form>
</section>

<section>
  <h2><?= h(t('act_titre')) ?></h2>
  <div class="tableau-defile">
  <table class="tableau">
    <thead><tr><th><?= h(t('act_titre_champ')) ?></th><th><?= h(t('act_langue')) ?></th>
      <th><?= h(t('act_groupe')) ?></th><th><?= h(t('statut')) ?></th><th><?= h(t('act_date_publication')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($lignes as $a): ?>
      <tr>
        <td><?php if ($a['statut'] === 'publie' && $a['publie_le'] <= maintenant()): ?>
              <a href="<?= h(lien('/actualites/' . $a['slug'])) ?>"><?= h((string) $a['titre']) ?></a>
            <?php else: ?><?= h((string) $a['titre']) ?><?php endif; ?></td>
        <td><?= h(strtoupper((string) $a['langue'])) ?></td>
        <td class="mono"><?= $a['groupe'] ? h((string) $a['groupe']) : sans_valeur() ?></td>
        <td><?= badge_statut((string) $a['statut'], 'article') ?>
          <?php if ($a['statut'] === 'publie' && $a['publie_le'] > maintenant()): ?>
            <span class="etiquette etiquette--attente"><?= h(t('act_programmee')) ?></span>
          <?php endif; ?></td>
        <td><?= $a['publie_le'] ? h(date_locale((string) $a['publie_le'])) : sans_valeur() ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</section>
