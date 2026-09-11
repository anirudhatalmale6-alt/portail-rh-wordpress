<?php meta(['titre' => t('rh_lien_paie')]); ?>
<h1><?= h(t('rh_lien_paie')) ?></h1>

<p class="encart encart--info"><?= h(t('paie_affiche_pas_calcule_long')) ?></p>

<section class="carte">
  <h2><?= h(t('paie_calendrier')) ?></h2>
  <form method="post" action="<?= h(lien('/rh/paie/periodes')) ?>" class="formulaire formulaire--ligne">
    <?= champ_csrf() ?>
    <div class="champ"><label for="pp"><?= h(t('emp_pays')) ?></label>
      <select id="pp" name="pays"><?php foreach ($pays as $code => $p): ?>
        <option value="<?= h($code) ?>"><?= h(col_langue($p, 'nom')) ?></option><?php endforeach; ?></select></div>
    <div class="champ"><label for="pf"><?= h(t('emp_frequence_paie')) ?></label>
      <select id="pf" name="frequence">
        <option value="bimensuelle"><?= h(t('paie_frequence_bimensuelle')) ?></option>
        <option value="mensuelle"><?= h(t('paie_frequence_mensuelle')) ?></option>
      </select></div>
    <div class="champ"><label for="pd"><?= h(t('paie_premier_debut')) ?></label><input id="pd" name="debut" type="date" required></div>
    <div class="champ"><label for="pc"><?= h(t('paie_combien')) ?></label><input id="pc" name="combien" type="number" min="1" max="60" value="12" class="etroit"></div>
    <div class="champ"><label for="px"><?= h(t('paie_decalage')) ?></label><input id="px" name="decalage" type="number" min="0" max="30" value="5" class="etroit"></div>
    <button class="btn btn--plein" type="submit"><?= h(t('paie_generer')) ?></button>
  </form>
  <p class="aide"><?= h(t('paie_calendrier_aide')) ?></p>

  <div class="tableau-defile">
  <table class="tableau tableau--compact">
    <thead><tr><th><?= h(t('paie_periode')) ?></th><th><?= h(t('paie_versement')) ?></th>
      <th><?= h(t('emp_pays')) ?></th><th><?= h(t('emp_frequence_paie')) ?></th><th><?= h(t('paie_bulletins')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($periodes as $p): ?>
      <tr><td><?= h((string) $p['debut']) ?> → <?= h((string) $p['fin']) ?></td>
          <td><?= h((string) $p['paiement_le']) ?></td>
          <td><?= h(strtoupper((string) $p['pays'])) ?></td>
          <td><?= h(t('paie_frequence_' . $p['frequence'])) ?></td>
          <td class="num"><?= h(nombre((int) $p['nb_publies'])) ?> / <?= h(nombre((int) $p['nb'])) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</section>

<section class="carte">
  <h2><?= h(t('paie_saisir')) ?></h2>
  <form method="post" action="<?= h(lien('/rh/paie/bulletin')) ?>" class="formulaire">
    <?= champ_csrf() ?>
    <div class="grille-2">
      <div class="champ"><label for="bu"><?= h(t('eq_salarie')) ?></label>
        <select id="bu" name="utilisateur_id" required>
          <?php foreach ($employes as $e): ?><option value="<?= (int) $e['id'] ?>"><?= h(nom_complet($e)) ?> — <?= h((string) $e['devise']) ?></option><?php endforeach; ?>
        </select></div>
      <div class="champ"><label for="bp"><?= h(t('paie_periode')) ?></label>
        <select id="bp" name="periode_id" required>
          <?php foreach ($periodes as $p): ?><option value="<?= (int) $p['id'] ?>"><?= h((string) $p['code']) ?> (<?= h((string) $p['paiement_le']) ?>)</option><?php endforeach; ?>
        </select></div>
      <div class="champ"><label for="bb"><?= h(t('paie_brut')) ?></label><input id="bb" name="brut" inputmode="decimal" required></div>
      <div class="champ"><label for="bn"><?= h(t('paie_net')) ?></label><input id="bn" name="net" inputmode="decimal" required></div>
      <div class="champ"><label for="bd"><?= h(t('emp_devise')) ?></label><input id="bd" name="devise" size="5"></div>
    </div>

    <h3><?= h(t('paie_lignes')) ?></h3>
    <p class="aide"><?= h(t('paie_lignes_aide')) ?></p>
    <?php for ($i = 0; $i < 6; $i++): ?>
      <div class="ligne-paie">
        <select name="ligne_type[]">
          <?php foreach (TYPES_LIGNE_PAIE as $tp): ?><option value="<?= h($tp) ?>"><?= h(t('paie_type_' . $tp)) ?></option><?php endforeach; ?>
        </select>
        <input name="ligne_libelle[]" placeholder="<?= h(t('paie_ligne')) ?>">
        <input name="ligne_montant[]" inputmode="decimal" placeholder="0.00" class="etroit">
      </div>
    <?php endfor; ?>
    <button class="btn btn--plein" type="submit"><?= h(t('paie_enregistrer_brouillon')) ?></button>
  </form>
</section>

<section>
  <h2><?= h(t('paie_a_publier')) ?></h2>
  <p class="note"><?= h(t('paie_a_publier_note')) ?></p>
  <?php if (!$en_attente): ?><p class="vide-liste"><?= h(t('paie_rien_a_publier')) ?></p><?php else: ?>
  <div class="tableau-defile">
  <table class="tableau">
    <thead><tr><th><?= h(t('eq_salarie')) ?></th><th><?= h(t('paie_periode')) ?></th>
      <th class="num"><?= h(t('paie_net')) ?></th><th><?= h(t('paie_coherence')) ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($en_attente as $b): $bb = bulletin((int) $b['id']); $co = coherence_bulletin($bb); ?>
      <tr>
        <td><?= h(nom_complet($b)) ?></td>
        <td><?= h((string) $b['periode_code']) ?></td>
        <td class="num"><?= h(argent((float) $b['net'], (string) $b['devise'])) ?></td>
        <td><?php if ($co['coherent']): ?><span class="ok-texte">✓</span>
            <?php else: ?><span class="erreur"><?= h(t('paie_ecart_court', ['ecart' => nombre($co['ecart'], 2)])) ?></span><?php endif; ?></td>
        <td>
          <a href="<?= h(lien('/paie/' . (int) $b['id'])) ?>"><?= h(t('paie_detail')) ?></a>
          <?php if ($co['coherent']): ?>
            <form method="post" action="<?= h(lien('/rh/paie/bulletin/' . (int) $b['id'] . '/publier')) ?>" class="en-ligne">
              <?= champ_csrf() ?><button class="btn btn--oui" type="submit"><?= h(t('paie_publier')) ?></button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</section>
