<?php
meta(['titre' => t('nav_tableau')]);
$decentralise = (int) ($u['decentralise'] ?? 0) === 1;
?>
<h1><?= h(t('acc_bonjour', ['prenom' => (string) $u['prenom']])) ?></h1>

<section class="identite">
  <?= avatar($u, 64) ?>
  <div>
    <p class="identite__poste"><?= valeur($u['poste'], 'emp_poste') ?></p>
    <p class="identite__meta">
      <?php if ($u['departement']): ?><?= h(col_langue($u['departement'], 'nom')) ?> · <?php endif; ?>
      <?php if ($u['manager']): ?><?= h(t('acc_manager')) ?> <a href="<?= h(lien('/annuaire/' . (int) $u['manager']['id'])) ?>"><?= h(nom_complet($u['manager'])) ?></a> · <?php endif; ?>
      <?= h(t('acc_matricule')) ?> <?= h((string) $u['matricule']) ?>
    </p>
    <p class="identite__meta">
      <?php if ($u['date_embauche']): ?>
        <?= h(t('acc_embauche')) ?> <?= h((string) $u['date_embauche']) ?>
        · <?= h(anciennete_texte((string) $u['date_embauche'])) ?>
      <?php else: ?><?= valeur('', 'emp_date_embauche') ?><?php endif; ?>
      <?php if ($decentralise): ?>
        · <span class="etiquette etiquette--dec"><?= h(t('acc_decentralise')) ?> <?= h((string) $u['fuseau']) ?></span>
      <?php endif; ?>
    </p>
  </div>
</section>

<section class="widgets">
  <?php if ($solde_principal): ?>
  <article class="widget">
    <h2><?= h(col_langue($solde_principal['type'], 'nom')) ?></h2>
    <?php if (!$solde_principal['configure']): ?>
      <p class="widget__valeur"><?= sans_valeur(t('cg_solde_non_configure')) ?></p>
      <p class="widget__note"><?= h(t('cg_solde_non_configure_note')) ?></p>
    <?php else: ?>
      <p class="widget__valeur"><?= h(nombre($solde_principal['disponible'], 1)) ?></p>
      <p class="widget__note"><?= h(t('cg_jours_disponibles')) ?>
        <?php if ($solde_principal['reserve'] > 0): ?>
          · <?= h(t('cg_en_attente_n', ['n' => nombre($solde_principal['reserve'], 1)])) ?>
        <?php endif; ?>
      </p>
    <?php endif; ?>
    <a href="<?= h(lien('/conges')) ?>"><?= h(t('acc_demander_conge')) ?></a>
  </article>
  <?php endif; ?>

  <article class="widget">
    <h2><?= h(t('acc_prochaine_paie')) ?></h2>
    <?php if ($prochaine_paie): ?>
      <p class="widget__valeur"><?= h((string) $prochaine_paie['paiement_le']) ?></p>
      <p class="widget__note"><?= h(t('acc_periode')) ?> <?= h((string) $prochaine_paie['debut']) ?> → <?= h((string) $prochaine_paie['fin']) ?></p>
    <?php else: ?>
      <p class="widget__valeur"><?= sans_valeur(t('paie_calendrier_absent')) ?></p>
      <p class="widget__note"><?= h(t('paie_calendrier_absent_note')) ?></p>
    <?php endif; ?>
    <a href="<?= h(lien('/paie')) ?>"><?= h(t('acc_voir_paie')) ?></a>
  </article>

  <article class="widget">
    <h2><?= h(t('acc_prochain_ferie')) ?></h2>
    <?php if ($prochain_ferie): ?>
      <p class="widget__valeur"><?= h((string) $prochain_ferie['date']) ?></p>
      <p class="widget__note"><?= h(col_langue($prochain_ferie, 'nom')) ?></p>
    <?php else: ?>
      <p class="widget__valeur"><?= sans_valeur(t('acc_feries_absents')) ?></p>
      <p class="widget__note"><?= h(t('acc_feries_absents_note')) ?></p>
    <?php endif; ?>
  </article>

  <article class="widget">
    <h2><?= h(t('acc_prochaine_formation')) ?></h2>
    <?php if ($prochaine_formation): ?>
      <p class="widget__valeur"><?= h(col_langue($prochaine_formation, 'titre')) ?></p>
      <p class="widget__note">
        <?= h(t('statut_' . $prochaine_formation['statut'])) ?>
        <?php if ($prochaine_formation['date_prevue']): ?> · <?= h((string) $prochaine_formation['date_prevue']) ?><?php endif; ?>
        <?php if ((int) $prochaine_formation['obligatoire'] === 1): ?>
          <span class="etiquette etiquette--obl"><?= h(t('form_obligatoire')) ?></span>
        <?php endif; ?>
      </p>
    <?php else: ?>
      <p class="widget__valeur"><?= sans_valeur(t('acc_aucune_formation')) ?></p>
    <?php endif; ?>
    <a href="<?= h(lien('/formations')) ?>"><?= h(t('nav_formations')) ?></a>
  </article>

  <article class="widget">
    <h2><?= h(t('acc_demandes_attente')) ?></h2>
    <p class="widget__valeur"><?= h(nombre($demandes_attente + $conges_attente)) ?></p>
    <p class="widget__note"><?= h(t('acc_dont', ['a' => nombre($demandes_attente), 'b' => nombre($conges_attente)])) ?></p>
    <a href="<?= h(lien('/demandes')) ?>"><?= h(t('nav_demandes')) ?></a>
  </article>

  <article class="widget">
    <h2><?= h(t('acc_documents_recents')) ?></h2>
    <p class="widget__valeur"><?= h(nombre(count($documents_recents))) ?></p>
    <p class="widget__note"><?= h(t('acc_documents_note')) ?></p>
    <a href="<?= h(lien('/documents')) ?>"><?= h(t('nav_documents')) ?></a>
  </article>
</section>

<section class="deux-cols">
  <div>
    <h2><?= h(t('acc_actions_rapides')) ?></h2>
    <ul class="actions-rapides">
      <li><a href="<?= h(lien('/conges')) ?>"><?= h(t('acc_ar_conge')) ?></a></li>
      <li><a href="<?= h(lien('/absences')) ?>"><?= h(t('acc_ar_absence')) ?></a></li>
      <li><a href="<?= h(lien('/paie')) ?>"><?= h(t('acc_ar_bulletin')) ?></a></li>
      <li><a href="<?= h(lien('/demandes/nouvelle')) ?>"><?= h(t('acc_ar_demande')) ?></a></li>
      <li><a href="<?= h(lien('/profil')) ?>"><?= h(t('acc_ar_profil')) ?></a></li>
      <li><a href="<?= h(lien('/documents')) ?>"><?= h(t('acc_ar_documents')) ?></a></li>
      <?php if ($decentralise): ?>
        <li><a href="<?= h(lien('/temps')) ?>"><?= h(t('acc_ar_temps')) ?></a></li>
      <?php endif; ?>
    </ul>

    <?php if ($notifications): ?>
      <h2><?= h(t('acc_notifications')) ?></h2>
      <ul class="liste-simple">
        <?php foreach ($notifications as $n): ?>
          <li><a href="<?= h(lien((string) ($n['lien'] ?: '/notifications'))) ?>"><?= h($n['titre']) ?></a>
              <span class="gris"><?= h(date_locale((string) $n['cree_le'])) ?></span></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>

  <div>
    <h2><?= h(t('acc_actualites')) ?></h2>
    <?php if (!$actualites): ?>
      <p class="vide-liste"><?= h(t('act_aucune')) ?></p>
    <?php else: ?>
      <ul class="liste-actus">
        <?php foreach ($actualites as $a): ?>
          <li>
            <a href="<?= h(lien('/actualites/' . $a['slug'])) ?>"><?= h($a['titre']) ?></a>
            <?php if ($a['langue'] !== langue()): ?>
              <span class="etiquette"><?= h(strtoupper((string) $a['langue'])) ?></span>
            <?php endif; ?>
            <time datetime="<?= h((string) $a['publie_le']) ?>"><?= h(date_locale((string) $a['publie_le'], false)) ?></time>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</section>
