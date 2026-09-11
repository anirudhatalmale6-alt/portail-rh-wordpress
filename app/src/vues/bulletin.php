<?php meta(['titre' => t('paie_bulletin')]); ?>
<?= fil([[t('nav_paie'), '/paie'], [t('paie_bulletin')]]) ?>
<h1><?= h(t('paie_bulletin')) ?> — <?= h((string) ($b['periode_code'] ?? '')) ?></h1>

<?php if (!$sien): ?>
  <p class="note"><?= h(t('paie_bulletin_de', ['nom' => nom_complet($employe)])) ?></p>
<?php endif; ?>
<?php if (!$b['publie_le']): ?>
  <p class="encart encart--attention"><?= h(t('paie_non_publie')) ?></p>
<?php endif; ?>

<dl class="fiche">
  <dt><?= h(t('paie_periode')) ?></dt><dd><?= h((string) ($b['debut'] ?? '')) ?> → <?= h((string) ($b['fin'] ?? '')) ?></dd>
  <dt><?= h(t('paie_versement')) ?></dt><dd><?= valeur($b['paiement_le'] ?? '', 'paie_versement') ?></dd>
  <dt><?= h(t('paie_devise')) ?></dt><dd><?= h((string) $b['devise']) ?></dd>
</dl>

<div class="tableau-defile">
<table class="tableau">
  <thead><tr><th><?= h(t('paie_ligne')) ?></th><th><?= h(t('paie_nature')) ?></th><th class="num"><?= h(t('paie_montant')) ?></th></tr></thead>
  <tbody>
  <?php foreach ($b['lignes'] as $l): $neg = in_array($l['type'], ['deduction', 'retenue'], true); ?>
    <tr>
      <td><?= h((string) $l['libelle']) ?></td>
      <td><?= h(t('paie_type_' . $l['type'])) ?></td>
      <td class="num <?= $neg ? 'negatif' : '' ?>">
        <?= $neg ? '−' : '' ?><?= h(argent((float) $l['montant'], (string) $b['devise'])) ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
  <tfoot>
    <tr><th colspan="2"><?= h(t('paie_brut')) ?></th><td class="num"><?= h(argent((float) $b['brut'], (string) $b['devise'])) ?></td></tr>
    <tr><th colspan="2"><?= h(t('paie_net')) ?></th><td class="num"><strong><?= h(argent((float) $b['net'], (string) $b['devise'])) ?></strong></td></tr>
  </tfoot>
</table>
</div>

<?php if (!$coherence['coherent']): ?>
  <?php /* On SIGNALE l'ecart, on ne corrige pas : c'est peut-etre le net qui
           est juste et une ligne qui manque. La correction est une decision
           de la RH, pas une soustraction faite en silence. */ ?>
  <p class="erreur" role="alert">
    <?= h(t('paie_ecart', ['somme' => nombre($coherence['somme_lignes'], 2),
                           'net' => nombre($coherence['net_saisi'], 2),
                           'ecart' => nombre($coherence['ecart'], 2)])) ?>
  </p>
<?php endif; ?>

<?php if ($b['document_id']): ?>
  <p><a class="btn" href="<?= h(lien('/document/' . (int) $b['document_id'])) ?>"><?= h(t('paie_pdf')) ?></a></p>
<?php endif; ?>
<?php if ($b['note']): ?><p class="note"><?= h((string) $b['note']) ?></p><?php endif; ?>
