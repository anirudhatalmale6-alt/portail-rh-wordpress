<?php meta(['titre' => t('nav_conges')]); ?>
<h1><?= h(t('cg_titre')) ?></h1>

<section class="soldes">
  <?php foreach ($soldes as $s): ?>
    <article class="widget widget--solde">
      <h2><?= h(col_langue($s['type'], 'nom')) ?></h2>
      <?php if (!$s['configure']): ?>
        <p class="widget__valeur"><?= sans_valeur(t('cg_solde_non_configure')) ?></p>
        <p class="widget__note"><?= h(t('cg_solde_non_configure_note')) ?></p>
      <?php else: ?>
        <p class="widget__valeur"><?= h(nombre($s['disponible'], 1)) ?></p>
        <p class="widget__note">
          <?= h(t('cg_acquis')) ?> <?= h(nombre($s['acquis'] + $s['ajuste'], 1)) ?> ·
          <?= h(t('cg_pris')) ?> <?= h(nombre($s['pris'], 1)) ?>
          <?php if ($s['reserve'] > 0): ?> · <?= h(t('cg_reserve')) ?> <?= h(nombre($s['reserve'], 1)) ?><?php endif; ?>
        </p>
      <?php endif; ?>
    </article>
  <?php endforeach; ?>
</section>

<section class="carte">
  <h2><?= h(t('cg_nouvelle')) ?></h2>
  <form method="post" action="<?= h(lien('/conges/demander')) ?>" class="formulaire grille-2">
    <?= champ_csrf() ?>
    <div class="champ">
      <label for="ty"><?= h(t('cg_type')) ?></label>
      <select id="ty" name="type_id" required>
        <?php foreach ($types as $ty): ?>
          <option value="<?= (int) $ty['id'] ?>"<?= (int) ($saisie['type_id'] ?? 0) === (int) $ty['id'] ? ' selected' : '' ?>>
            <?= h(col_langue($ty, 'nom')) ?><?= (int) $ty['paye'] === 0 ? ' — ' . h(t('cg_non_paye')) : '' ?>
          </option>
        <?php endforeach; ?>
      </select>
      <?php if (!empty($erreurs['type_id'])): ?><span class="erreur"><?= h($erreurs['type_id']) ?></span><?php endif; ?>
    </div>
    <div class="champ">
      <label for="db"><?= h(t('cg_debut')) ?></label>
      <input id="db" name="debut" type="date" value="<?= h((string) ($saisie['debut'] ?? '')) ?>" required>
      <label class="case"><input type="checkbox" name="demi_debut" value="1"> <?= h(t('cg_demi_debut')) ?></label>
      <?php if (!empty($erreurs['debut'])): ?><span class="erreur"><?= h($erreurs['debut']) ?></span><?php endif; ?>
    </div>
    <div class="champ">
      <label for="fn"><?= h(t('cg_fin')) ?></label>
      <input id="fn" name="fin" type="date" value="<?= h((string) ($saisie['fin'] ?? '')) ?>" required>
      <label class="case"><input type="checkbox" name="demi_fin" value="1"> <?= h(t('cg_demi_fin')) ?></label>
      <?php if (!empty($erreurs['fin'])): ?><span class="erreur"><?= h($erreurs['fin']) ?></span><?php endif; ?>
    </div>
    <div class="champ champ--large">
      <label for="mo"><?= h(t('cg_motif')) ?></label>
      <textarea id="mo" name="motif" class="ta-court"><?= h((string) ($saisie['motif'] ?? '')) ?></textarea>
      <span class="aide"><?= h(t('cg_motif_aide')) ?></span>
    </div>
    <div class="champ champ--large">
      <button class="btn btn--plein" type="submit"><?= h(t('cg_envoyer')) ?></button>
      <span class="aide"><?= h(t('cg_calcul_note')) ?></span>
    </div>
  </form>
</section>

<section>
  <h2><?= h(t('cg_historique')) ?></h2>
  <?php if (!$demandes): ?>
    <p class="vide-liste"><?= h(t('cg_aucune')) ?></p>
  <?php else: ?>
  <div class="tableau-defile">
  <table class="tableau">
    <thead><tr>
      <th><?= h(t('cg_type')) ?></th><th><?= h(t('cg_periode')) ?></th>
      <th><?= h(t('cg_jours')) ?></th><th><?= h(t('statut')) ?></th><th></th>
    </tr></thead>
    <tbody>
    <?php foreach ($demandes as $d): ?>
      <tr>
        <td><?= h(col_langue(['nom_fr' => $d['type_fr'], 'nom_en' => $d['type_en']], 'nom')) ?></td>
        <td><?= h((string) $d['debut']) ?> → <?= h((string) $d['fin']) ?></td>
        <td class="num"><?= h(nombre((float) $d['nb_jours'], 1)) ?></td>
        <td><?= badge_statut((string) $d['statut'], 'conge') ?>
          <?php if ($d['commentaire']): ?><p class="gris"><?= h((string) $d['commentaire']) ?></p><?php endif; ?>
        </td>
        <td>
          <?php if (in_array($d['statut'], ['en_attente', 'approuve'], true)): ?>
            <form method="post" action="<?= h(lien('/conges/' . (int) $d['id'] . '/annuler')) ?>" class="en-ligne">
              <?= champ_csrf() ?>
              <button class="btn btn--plat" type="submit"><?= h(t('annuler')) ?></button>
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
