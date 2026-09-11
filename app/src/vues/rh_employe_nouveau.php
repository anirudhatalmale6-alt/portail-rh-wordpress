<?php meta(['titre' => t('emp_nouveau')]); ?>
<?= fil([[t('rh_lien_employes'), '/rh/employes'], [t('emp_nouveau')]]) ?>
<div class="carte">
  <h1><?= h(t('emp_nouveau')) ?></h1>
  <p class="lead"><?= h(t('emp_nouveau_intro')) ?></p>
  <form method="post" action="<?= h(lien('/rh/employes')) ?>" class="formulaire grille-2">
    <?= champ_csrf() ?>
    <div class="champ"><label for="pr"><?= h(t('emp_prenom')) ?></label>
      <input id="pr" name="prenom" required value="<?= h((string) ($saisie['prenom'] ?? '')) ?>">
      <?php if (!empty($erreurs['prenom'])): ?><span class="erreur"><?= h($erreurs['prenom']) ?></span><?php endif; ?></div>
    <div class="champ"><label for="no"><?= h(t('emp_nom')) ?></label>
      <input id="no" name="nom" required value="<?= h((string) ($saisie['nom'] ?? '')) ?>">
      <?php if (!empty($erreurs['nom'])): ?><span class="erreur"><?= h($erreurs['nom']) ?></span><?php endif; ?></div>
    <div class="champ"><label for="em"><?= h(t('emp_email_pro')) ?></label>
      <input id="em" name="email" type="email" required value="<?= h((string) ($saisie['email'] ?? '')) ?>">
      <?php if (!empty($erreurs['email'])): ?><span class="erreur"><?= h($erreurs['email']) ?></span><?php endif; ?></div>
    <div class="champ"><label for="ma"><?= h(t('emp_matricule')) ?></label>
      <input id="ma" name="matricule" value="<?= h((string) ($saisie['matricule'] ?? '')) ?>" placeholder="<?= h(t('emp_matricule_auto')) ?>">
      <?php if (!empty($erreurs['matricule'])): ?><span class="erreur"><?= h($erreurs['matricule']) ?></span><?php endif; ?></div>
    <div class="champ"><label for="po"><?= h(t('emp_poste')) ?></label>
      <input id="po" name="poste" value="<?= h((string) ($saisie['poste'] ?? '')) ?>"></div>
    <div class="champ"><label for="de"><?= h(t('emp_departement')) ?></label>
      <select id="de" name="departement_id"><option value=""></option>
        <?php foreach ($departements as $d): ?><option value="<?= (int) $d['id'] ?>"><?= h(col_langue($d, 'nom')) ?></option><?php endforeach; ?>
      </select></div>
    <div class="champ"><label for="mg"><?= h(t('emp_manager')) ?></label>
      <select id="mg" name="manager_id"><option value=""></option>
        <?php foreach ($managers as $m): ?><option value="<?= (int) $m['id'] ?>"><?= h(nom_complet($m)) ?></option><?php endforeach; ?>
      </select></div>
    <div class="champ"><label for="lo"><?= h(t('emp_localisation')) ?></label>
      <select id="lo" name="localisation_id"><option value=""></option>
        <?php foreach ($localisations as $l): ?><option value="<?= (int) $l['id'] ?>"><?= h((string) $l['nom']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="champ"><label for="ro"><?= h(t('emp_role')) ?></label>
      <select id="ro" name="role_id">
        <?php foreach ($roles as $r): ?><option value="<?= (int) $r['id'] ?>"<?= $r['cle'] === 'employe' ? ' selected' : '' ?>><?= h(col_langue($r, 'nom')) ?></option><?php endforeach; ?>
      </select></div>
    <div class="champ"><label for="pa"><?= h(t('emp_pays')) ?></label>
      <select id="pa" name="pays_travail">
        <?php foreach (cfg('pays') as $code => $p): ?>
          <option value="<?= h($code) ?>"<?= $code === cfg('pays_defaut') ? ' selected' : '' ?>>
            <?= h(col_langue($p, 'nom')) ?> — <?= h($p['devise']) ?>, <?= h(t('paie_frequence_' . $p['frequence_paie'])) ?></option>
        <?php endforeach; ?>
      </select>
      <span class="aide"><?= h(t('emp_pays_aide')) ?></span></div>
    <div class="champ"><label for="dh"><?= h(t('emp_date_embauche')) ?></label>
      <input id="dh" name="date_embauche" type="date" value="<?= h((string) ($saisie['date_embauche'] ?? '')) ?>"></div>
    <div class="champ"><label for="tc"><?= h(t('emp_type_contrat')) ?></label>
      <select id="tc" name="type_contrat">
        <?php foreach (['cdi', 'cdd', 'stage', 'prestation'] as $tc): ?>
          <option value="<?= h($tc) ?>"><?= h(t('contrat_' . $tc)) ?></option><?php endforeach; ?>
      </select></div>
    <div class="champ"><label for="tt"><?= h(t('emp_temps_travail')) ?></label>
      <select id="tt" name="temps_travail">
        <option value="plein"><?= h(t('tt_plein')) ?></option>
        <option value="partiel"><?= h(t('tt_partiel')) ?></option>
      </select></div>
    <div class="champ"><label for="fz"><?= h(t('emp_fuseau')) ?></label>
      <input id="fz" name="fuseau" list="fuseaux" placeholder="<?= h(t('emp_fuseau_defaut')) ?>">
      <datalist id="fuseaux"><?php foreach (timezone_identifiers_list() as $f): ?><option value="<?= h($f) ?>"><?php endforeach; ?></datalist></div>
    <div class="champ champ--large">
      <label class="case"><input type="checkbox" name="decentralise" value="1"> <?= h(t('emp_decentralise')) ?></label>
      <span class="aide"><?= h(t('emp_decentralise_aide')) ?></span>
    </div>
    <div class="champ champ--large">
      <button class="btn btn--plein" type="submit"><?= h(t('emp_creer')) ?></button>
      <span class="aide"><?= h(t('emp_mdp_note')) ?></span>
    </div>
  </form>
</div>
