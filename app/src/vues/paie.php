<?php meta(['titre' => t('nav_paie')]); ?>
<h1><?= h(t('paie_titre')) ?></h1>

<p class="encart encart--info"><?= h(t('paie_affiche_pas_calcule')) ?></p>

<section class="widgets widgets--3">
  <article class="widget">
    <h2><?= h(t('acc_prochaine_paie')) ?></h2>
    <?php if ($prochaine): ?>
      <p class="widget__valeur"><?= h((string) $prochaine['paiement_le']) ?></p>
      <p class="widget__note"><?= h(t('paie_frequence_' . ($u['frequence_paie'] ?: 'mensuelle'))) ?></p>
    <?php else: ?>
      <p class="widget__valeur"><?= sans_valeur(t('paie_calendrier_absent')) ?></p>
      <p class="widget__note"><?= h(t('paie_calendrier_absent_note')) ?></p>
    <?php endif; ?>
  </article>
  <article class="widget">
    <h2><?= h(t('paie_cumul', ['annee' => (string) $cumul['annee']])) ?></h2>
    <p class="widget__valeur"><?= $cumul['bulletins'] ? h(argent($cumul['net'], (string) $u['devise'])) : sans_valeur() ?></p>
    <p class="widget__note"><?= h(tn('paie_nb_bulletins', (int) $cumul['bulletins'])) ?></p>
  </article>
  <article class="widget">
    <h2><?= h(t('paie_primes_annee')) ?></h2>
    <p class="widget__valeur"><?= $cumul['primes'] > 0 ? h(argent($cumul['primes'], (string) $u['devise'])) : sans_valeur(t('paie_aucune_prime')) ?></p>
  </article>
</section>

<section>
  <h2><?= h(t('paie_bulletins')) ?></h2>
  <?php if (!$bulletins): ?>
    <p class="vide-liste"><?= h(t('paie_aucun_bulletin')) ?></p>
  <?php else: ?>
  <div class="tableau-defile">
  <table class="tableau">
    <thead><tr><th><?= h(t('paie_periode')) ?></th><th><?= h(t('paie_versement')) ?></th>
      <th class="num"><?= h(t('paie_brut')) ?></th><th class="num"><?= h(t('paie_net')) ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($bulletins as $b): ?>
      <tr>
        <td><?= h((string) ($b['debut'] ?? '')) ?> → <?= h((string) ($b['fin'] ?? '')) ?></td>
        <td><?= h((string) ($b['paiement_le'] ?? '')) ?></td>
        <td class="num"><?= h(argent((float) $b['brut'], (string) $b['devise'])) ?></td>
        <td class="num"><strong><?= h(argent((float) $b['net'], (string) $b['devise'])) ?></strong></td>
        <td><a href="<?= h(lien('/paie/' . (int) $b['id'])) ?>"><?= h(t('paie_detail')) ?></a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</section>

<section>
  <h2><?= h(t('paie_primes')) ?></h2>
  <?php if (!$primes): ?>
    <p class="vide-liste"><?= h(t('paie_aucune_prime')) ?></p>
  <?php else: ?>
    <ul class="liste-docs">
      <?php foreach ($primes as $p): ?>
        <li><strong><?= h((string) $p['libelle']) ?></strong>
          <span class="mono"><?= h(argent((float) $p['montant'], (string) $p['devise'])) ?></span>
          <span class="gris"><?= h((string) $p['accordee_le']) ?></span>
          <?php if ($p['versee_bulletin_id']): ?>
            <span class="etiquette"><?= h(t('paie_versee')) ?></span>
          <?php else: ?>
            <span class="etiquette etiquette--attente"><?= h(t('paie_a_verser')) ?></span>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
