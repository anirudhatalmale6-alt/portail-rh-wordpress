<?php
meta(['titre' => t('nav_calendrier')]);
$prec = (new DateTimeImmutable($debut))->modify('-1 month')->format('Y-m');
$suiv = (new DateTimeImmutable($debut))->modify('+1 month')->format('Y-m');
$par_jour = [];
foreach ($evenements as $e) {
    $d = new DateTimeImmutable(max($e['debut'], $debut));
    $f = new DateTimeImmutable(min($e['fin'], $fin));
    for ($c = $d; $c <= $f; $c = $c->modify('+1 day')) {
        $par_jour[$c->format('Y-m-d')][] = $e;
    }
}
$feries_par_jour = [];
foreach ($feries as $f) $feries_par_jour[$f['date']] = $f;
$premier = new DateTimeImmutable($debut);
$decalage = ((int) $premier->format('N')) - 1;
$nb_jours = (int) $premier->format('t');
?>
<h1><?= h(t('cal_titre')) ?></h1>
<nav class="nav-semaine">
  <a class="btn btn--plat" href="<?= h(lien('/calendrier?mois=' . $prec)) ?>">← <?= h($prec) ?></a>
  <strong><?= h($mois) ?></strong>
  <a class="btn btn--plat" href="<?= h(lien('/calendrier?mois=' . $suiv)) ?>"><?= h($suiv) ?> →</a>
</nav>
<p class="note"><?= h(t('cal_note_motif')) ?></p>

<div class="calendrier">
  <?php foreach (['lun','mar','mer','jeu','ven','sam','dim'] as $jn): ?>
    <div class="cal__entete"><?= h(t('jour_' . $jn)) ?></div>
  <?php endforeach; ?>
  <?php for ($i = 0; $i < $decalage; $i++): ?><div class="cal__vide"></div><?php endfor; ?>
  <?php for ($j = 1; $j <= $nb_jours; $j++):
    $date = sprintf('%s-%02d', $mois, $j);
    $ferie = $feries_par_jour[$date] ?? null; ?>
    <div class="cal__jour<?= $ferie ? ' cal__jour--ferie' : '' ?><?= $date === aujourdhui() ? ' cal__jour--auj' : '' ?>">
      <span class="cal__num"><?= $j ?></span>
      <?php if ($ferie): ?><span class="cal__ferie"><?= h(col_langue($ferie, 'nom')) ?></span><?php endif; ?>
      <?php foreach ($par_jour[$date] ?? [] as $e): ?>
        <span class="cal__ev cal__ev--<?= h($e['nature']) ?>">
          <?= h($noms[(int) $e['utilisateur_id']] ?? '') ?>
        </span>
      <?php endforeach; ?>
    </div>
  <?php endfor; ?>
</div>
