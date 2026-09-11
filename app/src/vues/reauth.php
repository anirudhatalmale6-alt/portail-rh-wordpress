<?php meta(['titre' => t('reauth_titre')]); ?>
<div class="carte carte--etroite">
  <h1><?= h(t('reauth_titre')) ?></h1>
  <p class="lead"><?= h(t('reauth_pourquoi')) ?></p>
  <?php if ($erreur): ?><p class="erreur" role="alert"><?= h($erreur) ?></p><?php endif; ?>
  <form method="post" action="<?= h(lien('/reauthentification')) ?>" class="formulaire">
    <?= champ_csrf() ?>
    <input type="hidden" name="retour" value="<?= h($retour) ?>">
    <div class="champ">
      <label for="mdp"><?= h(t('cnx_mdp')) ?></label>
      <input id="mdp" name="mot_de_passe" type="password" autocomplete="current-password" required autofocus>
    </div>
    <button class="btn btn--plein" type="submit"><?= h(t('reauth_valider')) ?></button>
  </form>
  <p class="note"><?= h(t('reauth_duree', ['min' => (int) (cfg('duree_reauth') / 60)])) ?></p>
</div>
