<?php meta(['titre' => t('rh_lien_offres')]); ?>
<h1><?= h(t('rh_lien_offres')) ?></h1>

<section class="carte">
  <h2><?= h(t('rec_nouvelle_offre')) ?></h2>
  <form method="post" action="<?= h(lien('/rh/offres')) ?>" class="formulaire">
    <?= champ_csrf() ?>
    <div class="grille-2">
      <div class="champ"><label for="ot"><?= h(t('rec_titre_offre')) ?></label><input id="ot" name="titre" required></div>
      <div class="champ"><label for="ol"><?= h(t('act_langue')) ?></label>
        <select id="ol" name="langue"><?php foreach (cfg('langues') as $l): ?>
          <option value="<?= h($l) ?>"<?= $l === langue() ? ' selected' : '' ?>><?= h(t('langue_' . $l)) ?></option><?php endforeach; ?></select></div>
      <div class="champ"><label for="od"><?= h(t('emp_departement')) ?></label>
        <select id="od" name="departement_id"><option value=""></option>
          <?php foreach ($departements as $d): ?><option value="<?= (int) $d['id'] ?>"><?= h(col_langue($d, 'nom')) ?></option><?php endforeach; ?></select></div>
      <div class="champ"><label for="oo"><?= h(t('emp_localisation')) ?></label>
        <select id="oo" name="localisation_id"><option value=""></option>
          <?php foreach ($localisations as $l): ?><option value="<?= (int) $l['id'] ?>"><?= h((string) $l['nom']) ?></option><?php endforeach; ?></select></div>
      <div class="champ"><label for="op"><?= h(t('emp_pays')) ?></label>
        <select id="op" name="pays"><option value=""></option>
          <?php foreach ($pays as $code => $p): ?><option value="<?= h($code) ?>"><?= h(col_langue($p, 'nom')) ?></option><?php endforeach; ?></select></div>
      <div class="champ"><label for="oc"><?= h(t('emp_type_contrat')) ?></label>
        <select id="oc" name="type_contrat">
          <?php foreach (['cdi', 'cdd', 'stage', 'prestation'] as $tc): ?><option value="<?= h($tc) ?>"><?= h(t('contrat_' . $tc)) ?></option><?php endforeach; ?></select></div>
      <div class="champ"><label for="ow"><?= h(t('rec_teletravail')) ?></label>
        <select id="ow" name="teletravail">
          <?php foreach (['sur_site', 'hybride', 'distanciel'] as $tw): ?><option value="<?= h($tw) ?>"><?= h(t('teletravail_' . $tw)) ?></option><?php endforeach; ?></select></div>
      <div class="champ"><label for="os"><?= h(t('rec_remuneration')) ?></label>
        <input id="os" name="salaire_texte" placeholder="<?= h(t('rec_remuneration_placeholder')) ?>">
        <span class="aide"><?= h(t('rec_remuneration_aide')) ?></span></div>
      <div class="champ"><label for="oe"><?= h(t('rec_examen')) ?></label>
        <select id="oe" name="examen_id"><option value=""><?= h(t('rec_sans_examen')) ?></option>
          <?php foreach ($examens as $e): ?><option value="<?= (int) $e['id'] ?>"><?= h((string) $e['titre']) ?></option><?php endforeach; ?></select>
        <label class="case"><input type="checkbox" name="examen_obligatoire" value="1"> <?= h(t('rec_examen_rendre_obligatoire')) ?></label></div>
      <div class="champ"><label for="ost"><?= h(t('statut')) ?></label>
        <select id="ost" name="statut">
          <option value="brouillon"><?= h(t('statut_brouillon')) ?></option>
          <option value="publie"><?= h(t('statut_publie')) ?></option>
          <option value="ferme"><?= h(t('statut_ferme')) ?></option>
        </select></div>
    </div>
    <div class="champ"><label for="ode"><?= h(t('rec_description')) ?></label><textarea id="ode" name="description" rows="8"></textarea></div>
    <div class="champ"><label for="opr"><?= h(t('rec_profil')) ?></label><textarea id="opr" name="profil" rows="6"></textarea></div>
    <button class="btn btn--plein" type="submit"><?= h(t('enregistrer')) ?></button>
  </form>
</section>

<section>
  <h2><?= h(t('rec_offres')) ?></h2>
  <div class="tableau-defile">
  <table class="tableau">
    <thead><tr><th><?= h(t('dem_numero')) ?></th><th><?= h(t('rec_titre_offre')) ?></th>
      <th><?= h(t('emp_departement')) ?></th><th><?= h(t('statut')) ?></th>
      <th><?= h(t('rec_examen')) ?></th><th><?= h(t('rec_candidatures')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($offres as $o): ?>
      <tr>
        <td class="mono"><?= h((string) $o['reference']) ?></td>
        <td><?php if ($o['statut'] === 'publie'): ?>
              <a href="<?= h(lien('/carrieres/' . $o['slug'])) ?>"><?= h((string) $o['titre']) ?></a>
            <?php else: ?><?= h((string) $o['titre']) ?><?php endif; ?></td>
        <td><?= h(col_langue(['nom_fr' => $o['dep_fr'] ?? '', 'nom_en' => $o['dep_en'] ?? ''], 'nom')) ?></td>
        <td><?= badge_statut((string) $o['statut'], 'offre') ?></td>
        <td><?= $o['examen_id'] ? h((int) $o['examen_obligatoire'] === 1 ? t('rec_obligatoire') : t('rec_facultatif')) : '—' ?></td>
        <td class="num"><a href="<?= h(lien('/rh/candidatures?offre_id=' . (int) $o['id'])) ?>"><?= h(nombre((int) $o['nb_cand'])) ?></a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</section>
