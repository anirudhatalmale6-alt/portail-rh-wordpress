<?php meta(['titre' => t('prof_bancaire')]); ?>
<div class="carte carte--etroite">
  <h1><?= h(t('prof_bancaire')) ?></h1>

  <?php if (!$coffre): ?>
    <p class="erreur" role="alert"><?= h(t('banque_coffre_absent')) ?></p>
    <p class="note"><?= h(t('banque_coffre_absent_note')) ?></p>
  <?php else: ?>
    <p class="lead"><?= h(t('banque_explication')) ?></p>
    <?php if ($erreur): ?><p class="erreur" role="alert"><?= h($erreur) ?></p><?php endif; ?>

    <?php if ($u['banque_masque']): ?>
      <p><?= h(t('banque_actuel')) ?> <strong class="mono"><?= h((string) $u['banque_masque']) ?></strong></p>
    <?php endif; ?>

    <form method="post" action="<?= h(lien('/profil/bancaire')) ?>" class="formulaire" autocomplete="off">
      <?= champ_csrf() ?>
      <div class="champ">
        <label for="inst"><?= h(t('banque_institution')) ?></label>
        <input id="inst" name="institution" type="text" value="<?= h((string) $u['banque_institution']) ?>" required>
      </div>
      <div class="champ">
        <label for="num"><?= h(t('banque_numero')) ?></label>
        <input id="num" name="numero" type="text" inputmode="numeric" autocomplete="off" required>
        <span class="aide"><?= h(t('banque_numero_aide')) ?></span>
      </div>
      <div class="champ">
        <label for="mp"><?= h(t('banque_mode')) ?></label>
        <select id="mp" name="mode_paiement">
          <?php foreach (['virement', 'cheque', 'autre'] as $m): ?>
            <option value="<?= h($m) ?>"<?= ($u['mode_paiement'] ?? '') === $m ? ' selected' : '' ?>><?= h(t('mode_' . $m)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="btn btn--plein" type="submit"><?= h(t('enregistrer')) ?></button>
    </form>
  <?php endif; ?>

  <p class="note"><?= h(t('banque_audit_note')) ?></p>
  <p><a href="<?= h(lien('/profil')) ?>"><?= h(t('retour_profil')) ?></a></p>
</div>
