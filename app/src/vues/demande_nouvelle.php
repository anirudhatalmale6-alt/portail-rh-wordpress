<?php meta(['titre' => t('dem_nouvelle')]); ?>
<?= fil([[t('nav_demandes'), '/demandes'], [t('dem_nouvelle')]]) ?>
<div class="carte">
  <h1><?= h(t('dem_nouvelle')) ?></h1>
  <form method="post" action="<?= h(lien('/demandes')) ?>" class="formulaire" enctype="multipart/form-data">
    <?= champ_csrf() ?>
    <div class="champ">
      <label for="ca"><?= h(t('dem_categorie')) ?></label>
      <select id="ca" name="categorie" required>
        <?php foreach (CATEGORIES_DEMANDE as $c): ?>
          <option value="<?= h($c) ?>"<?= ($saisie['categorie'] ?? '') === $c ? ' selected' : '' ?>><?= h(t('dem_cat_' . $c)) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if (!empty($erreurs['categorie'])): ?><span class="erreur"><?= h($erreurs['categorie']) ?></span><?php endif; ?>
    </div>
    <div class="champ">
      <label for="ob"><?= h(t('dem_objet')) ?></label>
      <input id="ob" name="objet" type="text" maxlength="255" value="<?= h((string) ($saisie['objet'] ?? '')) ?>" required>
      <?php if (!empty($erreurs['objet'])): ?><span class="erreur"><?= h($erreurs['objet']) ?></span><?php endif; ?>
    </div>
    <div class="champ">
      <label for="co"><?= h(t('dem_message')) ?></label>
      <textarea id="co" name="corps" rows="8" required><?= h((string) ($saisie['corps'] ?? '')) ?></textarea>
      <span class="aide"><?= h(t('dem_syntaxe')) ?></span>
      <?php if (!empty($erreurs['corps'])): ?><span class="erreur"><?= h($erreurs['corps']) ?></span><?php endif; ?>
    </div>
    <div class="champ">
      <label for="pi"><?= h(t('dem_piece')) ?></label>
      <input id="pi" name="piece" type="file">
    </div>
    <button class="btn btn--plein" type="submit"><?= h(t('dem_envoyer')) ?></button>
  </form>
</div>
