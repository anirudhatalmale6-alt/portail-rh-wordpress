<?php meta(['titre' => t('nav_notifications')]); ?>
<h1><?= h(t('nav_notifications')) ?></h1>

<?php if (!$email_actif): ?>
  <p class="encart encart--info"><?= h(t('notif_pas_email')) ?></p>
<?php endif; ?>

<form method="post" action="<?= h(lien('/notifications/lues')) ?>" class="en-ligne">
  <?= champ_csrf() ?>
  <button class="btn" type="submit"><?= h(t('notif_tout_lu')) ?></button>
</form>

<?php if (!$notifications): ?>
  <p class="vide-liste"><?= h(t('notif_aucune')) ?></p>
<?php else: ?>
<ul class="liste-notifs">
  <?php foreach ($notifications as $n): ?>
    <li class="<?= $n['lu_le'] ? '' : 'non-lue' ?>">
      <div class="notif__t">
        <?php if ($n['lien']): ?>
          <a href="<?= h(lien((string) $n['lien'])) ?>"><?= h($n['titre']) ?></a>
        <?php else: ?><?= h($n['titre']) ?><?php endif; ?>
      </div>
      <?php if ($n['corps']): ?><p class="notif__c"><?= h($n['corps']) ?></p><?php endif; ?>
      <time datetime="<?= h((string) $n['cree_le']) ?>"><?= h(date_locale((string) $n['cree_le'])) ?></time>
    </li>
  <?php endforeach; ?>
</ul>
<?php endif; ?>
