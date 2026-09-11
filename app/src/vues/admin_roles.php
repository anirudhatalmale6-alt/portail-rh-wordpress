<?php meta(['titre' => t('adm_roles')]); ?>
<h1><?= h(t('adm_roles')) ?></h1>
<?php /* Les permissions affichees sont celles que le serveur APPLIQUE,
         c'est-a-dire celles de la table role_permissions — pas la
         declaration du code. Un ecran qui rend la declaration ne peut pas
         contredire le code : il devient un deuxieme mensonge d'accord avec
         le premier. */ ?>
<p class="lead"><?= h(t('adm_roles_intro')) ?></p>
<p class="note"><?= h(t('adm_roles_source')) ?></p>

<div class="tableau-defile">
<table class="tableau tableau--matrice">
  <thead><tr><th><?= h(t('adm_permission')) ?></th>
    <?php foreach ($roles as $r): ?><th><?= h(col_langue($r, 'nom')) ?><br><span class="gris"><?= h((string) $r['cle']) ?></span></th><?php endforeach; ?>
  </tr></thead>
  <tbody>
  <?php foreach ($permissions as $p): ?>
    <tr>
      <th><code><?= h((string) $p['cle']) ?></code><br><span class="gris"><?= h((string) $p['description']) ?></span></th>
      <?php foreach ($roles as $r): $perms = $appliquees[$r['cle']] ?? [];
            $a = $perms === '*' || in_array($p['cle'], (array) $perms, true); ?>
        <td class="<?= $a ? 'oui' : 'non' ?>"><?= $a ? '✓' : '—' ?></td>
      <?php endforeach; ?>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<p class="note"><?= h(t('adm_roles_admin_note')) ?></p>
