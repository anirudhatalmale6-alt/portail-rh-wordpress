<?php meta(['titre' => t('emp_cree')]); ?>
<div class="carte carte--etroite">
  <h1><?= h(t('emp_cree')) ?></h1>
  <p class="lead"><?= h(t('emp_cree_texte', ['nom' => $nom])) ?></p>

  <?php /* Le mot de passe est montre UNE FOIS. Il n'est ecrit nulle part
           ailleurs et il n'est pas reaffichable : la seule sortie ensuite est
           de le regenerer. */ ?>
  <div class="encart encart--attention">
    <p><?= h(t('emp_identifiant')) ?> <strong class="mono"><?= h($email) ?></strong></p>
    <p><?= h(t('emp_mdp_provisoire')) ?> <strong class="mono grand"><?= h($mdp) ?></strong></p>
    <p class="note"><?= h(t('emp_mdp_une_fois')) ?></p>
  </div>

  <p><a class="btn btn--plein" href="<?= h(lien('/rh/employes/' . (int) $id)) ?>"><?= h(t('emp_ouvrir_dossier')) ?></a>
     <a class="btn" href="<?= h(lien('/rh/employes/nouveau')) ?>"><?= h(t('emp_nouveau')) ?></a></p>
</div>
