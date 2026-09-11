<?php meta(['titre' => t('connexion')]); ?>
<div class="carte carte--etroite">
  <h1><?= h(t('cnx_titre')) ?></h1>
  <p class="lead"><?= h(t('cnx_sous_titre', ['org' => cfg('nom_org')])) ?></p>

  <?php if ($erreur): ?><p class="erreur" role="alert"><?= h($erreur) ?></p><?php endif; ?>

  <form method="post" action="<?= h(lien('/connexion')) ?>" class="formulaire">
    <?= champ_csrf() ?>
    <input type="hidden" name="retour" value="<?= h($retour) ?>">
    <div class="champ">
      <label for="email"><?= h(t('cnx_email')) ?></label>
      <input id="email" name="email" type="email" autocomplete="username" required autofocus>
    </div>
    <div class="champ">
      <label for="mdp"><?= h(t('cnx_mdp')) ?></label>
      <input id="mdp" name="mot_de_passe" type="password" autocomplete="current-password" required>
    </div>
    <button class="btn btn--plein" type="submit"><?= h(t('connexion')) ?></button>
  </form>

  <p class="note"><?= h(t('cnx_oubli')) ?></p>
  <p><a href="<?= h(lien('/carrieres')) ?>"><?= h(t('cnx_vers_carrieres')) ?></a></p>
</div>
