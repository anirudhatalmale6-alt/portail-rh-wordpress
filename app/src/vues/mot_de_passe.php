<?php meta(['titre' => t('mdp_titre')]);
$doit = (int) (utilisateur()['doit_changer'] ?? 0) === 1; ?>
<div class="carte carte--etroite">
  <h1><?= h(t('mdp_titre')) ?></h1>
  <?php if ($doit): ?>
    <p class="lead"><?= h(t('mdp_obligatoire')) ?></p>
  <?php endif; ?>
  <form method="post" action="<?= h(lien('/mot-de-passe')) ?>" class="formulaire">
    <?= champ_csrf() ?>
    <div class="champ">
      <label for="a"><?= h(t('mdp_actuel')) ?></label>
      <input id="a" name="actuel" type="password" autocomplete="current-password" required>
      <?php if (!empty($erreurs['actuel'])): ?><span class="erreur"><?= h($erreurs['actuel']) ?></span><?php endif; ?>
    </div>
    <div class="champ">
      <label for="n"><?= h(t('mdp_nouveau')) ?></label>
      <input id="n" name="nouveau" type="password" autocomplete="new-password" minlength="10" required>
      <span class="aide"><?= h(t('mdp_regle')) ?></span>
      <?php if (!empty($erreurs['nouveau'])): ?><span class="erreur"><?= h($erreurs['nouveau']) ?></span><?php endif; ?>
    </div>
    <div class="champ">
      <label for="c"><?= h(t('mdp_confirme')) ?></label>
      <input id="c" name="confirme" type="password" autocomplete="new-password" required>
      <?php if (!empty($erreurs['confirme'])): ?><span class="erreur"><?= h($erreurs['confirme']) ?></span><?php endif; ?>
    </div>
    <button class="btn btn--plein" type="submit"><?= h(t('enregistrer')) ?></button>
  </form>
  <p class="note"><?= h(t('mdp_autres_sessions')) ?></p>
</div>
