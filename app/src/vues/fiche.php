<?php meta(['titre' => nom_complet($c)]); ?>
<?= fil([[t('nav_annuaire'), '/annuaire'], [nom_complet($c)]]) ?>
<section class="identite">
  <?= avatar($c, 64) ?>
  <div>
    <h1><?= h(nom_complet($c)) ?></h1>
    <p class="identite__poste"><?= valeur($c['poste'], 'emp_poste') ?></p>
    <p class="identite__meta">
      <?php if ($c['departement']): ?><?= h(col_langue($c['departement'], 'nom')) ?><?php endif; ?>
      <?php if ($c['localisation']): ?> · <?= h((string) $c['localisation']['nom']) ?><?php endif; ?>
      <?php if ((int) $c['decentralise'] === 1): ?>
        · <span class="etiquette etiquette--dec"><?= h(t('acc_decentralise')) ?> <?= h((string) $c['fuseau']) ?></span>
      <?php endif; ?>
    </p>
  </div>
</section>

<dl class="fiche">
  <dt><?= h(t('cnx_email')) ?></dt><dd class="mono"><?= h((string) $c['email']) ?></dd>
  <?php if ((int) $c['annuaire_tel'] === 1 || $portee !== null): ?>
    <dt><?= h(t('emp_telephone')) ?></dt><dd class="mono"><?= valeur($c['telephone'], 'emp_telephone') ?></dd>
  <?php endif; ?>
  <dt><?= h(t('emp_manager')) ?></dt>
  <dd><?php if ($c['manager']): ?><a href="<?= h(lien('/annuaire/' . (int) $c['manager']['id'])) ?>"><?= h(nom_complet($c['manager'])) ?></a><?php else: ?><?= sans_valeur() ?><?php endif; ?></dd>
</dl>

<?php if ($portee !== null && $portee !== 'soi'): ?>
  <?php /* Ce qui suit ne s'affiche que pour la RH ou pour le manager de la
           personne. Et meme la, le manager ne voit ni la paie, ni le compte
           bancaire, ni le motif d'une absence — le modele de permissions le
           refuse en amont, cette page ne fait que ne pas les demander. */ ?>
  <section class="carte">
    <h2><?= h(t('fiche_dossier')) ?></h2>
    <dl class="fiche">
      <dt><?= h(t('emp_matricule')) ?></dt><dd><?= h((string) $c['matricule']) ?></dd>
      <dt><?= h(t('emp_date_embauche')) ?></dt><dd><?= valeur($c['date_embauche'], 'emp_date_embauche') ?></dd>
      <dt><?= h(t('emp_anciennete')) ?></dt><dd><?= $c['date_embauche'] ? h(anciennete_texte((string) $c['date_embauche'])) : sans_valeur() ?></dd>
      <dt><?= h(t('emp_type_contrat')) ?></dt><dd><?= valeur($c['type_contrat'], 'emp_type_contrat') ?></dd>
      <dt><?= h(t('emp_statut')) ?></dt><dd><?= h(t('statut_' . $c['statut'])) ?></dd>
    </dl>
    <?php if (peut('profil.tous.voir')): ?>
      <p><a class="btn" href="<?= h(lien('/rh/employes/' . (int) $c['id'])) ?>"><?= h(t('fiche_ouvrir_rh')) ?></a></p>
    <?php endif; ?>
  </section>
<?php endif; ?>
