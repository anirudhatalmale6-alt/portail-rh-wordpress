<?php
meta(['titre' => t('nav_organigramme')]);

/* L'arbre se dessine par recursion. La profondeur est bornee : une boucle
   dans les rattachements est deja neutralisee par organigramme(), mais une
   borne ici coute une ligne et evite un ecran blanc si le modele change. */
function branche(array $par_id, array $ids, int $profondeur = 0): void
{
    if ($profondeur > 8 || !$ids) return;
    echo '<ul class="org">';
    foreach ($ids as $id) {
        $n = $par_id[$id];
        echo '<li><div class="org__noeud">';
        echo '<a href="' . h(lien('/annuaire/' . (int) $n['id'])) . '">' . h(nom_complet($n)) . '</a>';
        echo '<span class="gris">' . ($n['poste'] ? h((string) $n['poste']) : sans_valeur()) . '</span>';
        if ((int) ($n['decentralise'] ?? 0) === 1) {
            echo '<span class="etiquette etiquette--dec">' . h(t('acc_decentralise')) . '</span>';
        }
        echo '</div>';
        branche($par_id, $n['enfants'], $profondeur + 1);
        echo '</li>';
    }
    echo '</ul>';
}
?>
<h1><?= h(t('org_titre')) ?></h1>
<p class="lead"><?= h(t('org_intro')) ?></p>

<?php if ($boucles): ?>
  <p class="erreur" role="alert"><?= h(tn('org_boucles', count($boucles))) ?></p>
<?php endif; ?>

<?php if (!$racines): ?>
  <p class="vide-liste"><?= h(t('org_vide')) ?></p>
<?php else: ?>
  <div class="org-cadre"><?php branche($par_id, $racines); ?></div>
<?php endif; ?>
