/* Portail RH — le seul script du projet.
 *
 * Le portail fonctionne ENTIEREMENT sans JavaScript : chaque formulaire
 * poste, chaque lien navigue. Ce fichier n'ajoute que du confort, et un
 * test de la suite parcourt le parcours complet avec le script desactive.
 */
(function () {
  'use strict';

  /* Duree d'une journee calculee a l'ecran pendant la saisie. Le serveur
     recalcule de toute facon : ce qui est affiche ici est indicatif, ce qui
     est enregistre vient du serveur. */
  document.querySelectorAll('.tableau--temps tr').forEach(function (tr) {
    var debut = tr.querySelector('input[name="debut"]');
    var fin = tr.querySelector('input[name="fin"]');
    var pause = tr.querySelector('input[name="pause_min"]');
    var cell = tr.querySelector('td.num');
    if (!debut || !fin || !cell) return;

    function minutes(v) {
      var m = /^(\d{2}):(\d{2})$/.exec(v || '');
      return m ? parseInt(m[1], 10) * 60 + parseInt(m[2], 10) : null;
    }
    function calcule() {
      var a = minutes(debut.value), b = minutes(fin.value);
      if (a === null || b === null) return;
      var d = b - a;
      if (d < 0) d += 1440;              // journee a cheval sur minuit
      d -= parseInt(pause && pause.value ? pause.value : '0', 10) || 0;
      if (d <= 0) { cell.textContent = '—'; return; }
      cell.textContent = Math.floor(d / 60) + ' h' + (d % 60 ? ' ' + ('0' + (d % 60)).slice(-2) : '');
    }
    [debut, fin, pause].forEach(function (el) {
      if (el) el.addEventListener('input', calcule);
    });
  });

  /* Le bouton « tout marquer comme lu » et les decisions de validation
     sont des actions irreversibles cote interface. On ne les confirme pas
     par une boite de dialogue : elles sont toutes reversibles cote donnees,
     et une confirmation de plus finit par etre cliquee sans etre lue. */

  /* Total en direct des lignes de bulletin, cote RH. Le serveur refuse la
     publication si les lignes et le net different : autant que la personne
     qui saisit le voie tout de suite. */
  var formPaie = document.querySelector('form[action$="/rh/paie/bulletin"]');
  if (formPaie) {
    var net = formPaie.querySelector('input[name="net"]');
    var sortie = document.createElement('p');
    sortie.className = 'note';
    formPaie.appendChild(sortie);

    formPaie.addEventListener('input', function () {
      var somme = 0;
      var types = formPaie.querySelectorAll('select[name="ligne_type[]"]');
      var montants = formPaie.querySelectorAll('input[name="ligne_montant[]"]');
      for (var i = 0; i < types.length; i++) {
        var v = parseFloat((montants[i].value || '0').replace(',', '.').replace(/\s/g, ''));
        if (isNaN(v)) continue;
        var t = types[i].value;
        somme += (t === 'deduction' || t === 'retenue') ? -v : v;
      }
      var n = parseFloat((net && net.value ? net.value : '0').replace(',', '.').replace(/\s/g, ''));
      if (isNaN(n)) n = 0;
      var ecart = Math.round((somme - n) * 100) / 100;
      sortie.textContent = Math.abs(ecart) < 0.01
        ? '✓ ' + somme.toFixed(2)
        : somme.toFixed(2) + ' − ' + n.toFixed(2) + ' = ' + ecart.toFixed(2);
      sortie.className = Math.abs(ecart) < 0.01 ? 'note ok-texte' : 'note erreur';
    });
  }
})();
