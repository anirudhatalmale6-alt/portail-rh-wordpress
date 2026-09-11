<?php meta(['titre' => (string) $examen['titre']]); ?>
<div class="carte">
  <h1><?= h((string) $examen['titre']) ?></h1>
  <p class="lead"><?= h(t('rec_examen_pour', ['offre' => (string) $candidature['offre_titre']])) ?></p>
  <?php if ($examen['consigne']): ?><p><?= h((string) $examen['consigne']) ?></p><?php endif; ?>
  <?php if ($examen['duree_min']): ?>
    <p class="note"><?= h(t('rec_examen_duree', ['min' => (int) $examen['duree_min']])) ?></p>
  <?php endif; ?>
  <?php if ($erreur): ?><p class="erreur" role="alert"><?= h($erreur) ?></p><?php endif; ?>

  <?php if (!$questions): ?>
    <p class="vide-liste"><?= h(t('rec_err_examen_vide')) ?></p>
  <?php else: ?>
  <form method="post" action="<?= h(lien('/examen/' . $jeton)) ?>" class="formulaire">
    <?= champ_csrf() ?>
    <?php foreach ($questions as $i => $q): ?>
      <fieldset class="question">
        <legend><?= h(t('rec_question_n', ['n' => $i + 1])) ?></legend>
        <p class="question__enonce"><?= h((string) $q['enonce']) ?></p>
        <?php if ($q['type'] === 'texte'): ?>
          <label class="hors-ecran" for="q<?= (int) $q['id'] ?>"><?= h(t('rec_votre_reponse')) ?></label>
          <textarea id="q<?= (int) $q['id'] ?>" name="q[<?= (int) $q['id'] ?>]" rows="5"></textarea>
          <span class="aide"><?= h(t('rec_reponse_libre_aide')) ?></span>
        <?php else: ?>
          <?php foreach ($q['choix'] as $ci => $choix): ?>
            <label class="case">
              <input type="<?= $q['type'] === 'multiple' ? 'checkbox' : 'radio' ?>"
                     name="q[<?= (int) $q['id'] ?>]<?= $q['type'] === 'multiple' ? '[]' : '' ?>"
                     value="<?= h((string) $ci) ?>">
              <?= h((string) $choix) ?>
            </label>
          <?php endforeach; ?>
        <?php endif; ?>
      </fieldset>
    <?php endforeach; ?>
    <button class="btn btn--plein" type="submit"><?= h(t('rec_examen_envoyer')) ?></button>
    <p class="note"><?= h(t('rec_examen_une_fois')) ?></p>
  </form>
  <?php endif; ?>
</div>
