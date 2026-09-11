<?php meta(['titre' => t('nav_documents')]);
$cats = ['contrat','avenant','bulletin','fiscal','attestation','certificat','assurance','administratif','justificatif']; ?>
<h1><?= h(t('doc_titre')) ?></h1>

<nav class="filtres">
  <a class="<?= $categorie === '' ? 'actif' : '' ?>" href="<?= h(lien('/documents')) ?>"><?= h(t('doc_toutes')) ?></a>
  <?php foreach ($cats as $c): ?>
    <a class="<?= $categorie === $c ? 'actif' : '' ?>" href="<?= h(lien('/documents?categorie=' . $c)) ?>"><?= h(t('doc_cat_' . $c)) ?></a>
  <?php endforeach; ?>
</nav>

<section>
  <h2><?= h(t('doc_mes')) ?></h2>
  <?php if (!$documents): ?>
    <p class="vide-liste"><?= h(t('doc_aucun')) ?></p>
  <?php else: ?>
    <ul class="liste-docs">
      <?php foreach ($documents as $d): ?>
        <li>
          <span class="etiquette"><?= h(t('doc_cat_' . $d['categorie'])) ?></span>
          <a href="<?= h(lien('/document/' . (int) $d['id'])) ?>"><?= h((string) $d['titre']) ?></a>
          <span class="gris"><?= h(taille_lisible((int) $d['taille'])) ?> · <?= h(date_locale((string) $d['cree_le'], false)) ?></span>
          <?php if ((int) $d['confidentiel'] === 1): ?>
            <span class="etiquette etiquette--conf"><?= h(t('doc_confidentiel')) ?></span>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

<section>
  <h2><?= h(t('doc_entreprise')) ?></h2>
  <?php if (!$entreprise): ?>
    <p class="vide-liste"><?= h(t('doc_aucun_entreprise')) ?></p>
  <?php else: ?>
    <ul class="liste-docs">
      <?php foreach ($entreprise as $d): ?>
        <li><span class="etiquette"><?= h(t('doc_cat_' . $d['categorie'])) ?></span>
          <a href="<?= h(lien('/document/' . (int) $d['id'])) ?>"><?= h((string) $d['titre']) ?></a>
          <span class="gris"><?= h(taille_lisible((int) $d['taille'])) ?></span></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

<p class="note"><?= h(t('doc_hors_racine')) ?></p>
