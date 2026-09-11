<?php meta(['titre' => t('rh_lien_documents')]);
$cats = ['contrat','avenant','bulletin','fiscal','attestation','certificat','politique','assurance','administratif']; ?>
<h1><?= h(t('rh_lien_documents')) ?></h1>

<section class="carte">
  <h2><?= h(t('doc_deposer')) ?></h2>
  <form method="post" action="<?= h(lien('/rh/documents')) ?>" class="formulaire grille-2" enctype="multipart/form-data">
    <?= champ_csrf() ?>
    <div class="champ"><label for="ui"><?= h(t('doc_destinataire')) ?></label>
      <select id="ui" name="utilisateur_id">
        <option value=""><?= h(t('doc_tous_salaries')) ?></option>
        <?php foreach ($employes as $e): ?><option value="<?= (int) $e['id'] ?>"><?= h(nom_complet($e)) ?> (<?= h((string) $e['matricule']) ?>)</option><?php endforeach; ?>
      </select>
      <span class="aide"><?= h(t('doc_destinataire_aide')) ?></span></div>
    <div class="champ"><label for="ca"><?= h(t('dem_categorie')) ?></label>
      <select id="ca" name="categorie">
        <?php foreach ($cats as $c): ?><option value="<?= h($c) ?>"><?= h(t('doc_cat_' . $c)) ?></option><?php endforeach; ?>
      </select>
      <span class="aide"><?= h(t('doc_confidentiel_aide')) ?></span></div>
    <div class="champ"><label for="ti"><?= h(t('doc_titre_champ')) ?></label><input id="ti" name="titre"></div>
    <div class="champ"><label for="pe"><?= h(t('doc_periode')) ?></label><input id="pe" name="periode" placeholder="2026-08"></div>
    <div class="champ champ--large"><label for="fi"><?= h(t('doc_fichier')) ?></label>
      <input id="fi" name="fichier" type="file" required>
      <span class="aide"><?= h(t('doc_types_admis')) ?></span></div>
    <div class="champ champ--large"><button class="btn btn--plein" type="submit"><?= h(t('doc_deposer')) ?></button></div>
  </form>
</section>

<section>
  <h2><?= h(t('doc_recents')) ?></h2>
  <div class="tableau-defile">
  <table class="tableau">
    <thead><tr><th><?= h(t('doc_titre_champ')) ?></th><th><?= h(t('dem_categorie')) ?></th>
      <th><?= h(t('doc_destinataire')) ?></th><th><?= h(t('doc_taille')) ?></th><th><?= h(t('dem_date')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($recents as $d): ?>
      <tr>
        <td><a href="<?= h(lien('/document/' . (int) $d['id'])) ?>"><?= h((string) $d['titre']) ?></a>
          <?php if ((int) $d['confidentiel'] === 1): ?><span class="etiquette etiquette--conf"><?= h(t('doc_confidentiel')) ?></span><?php endif; ?></td>
        <td><?= h(t('doc_cat_' . $d['categorie'])) ?></td>
        <td><?= $d['utilisateur_id'] ? h(nom_complet($d)) : h(t('doc_tous_salaries')) ?></td>
        <td class="num"><?= h(taille_lisible((int) $d['taille'])) ?></td>
        <td><?= h(date_locale((string) $d['cree_le'], false)) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</section>
