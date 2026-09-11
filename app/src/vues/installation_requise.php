<?php
/* Cette vue s'affiche AVANT que la base existe. Elle ne peut donc appeler
   ni t(), ni la base : tout est ecrit en dur, en francais et en anglais. */
?><!doctype html>
<html lang="fr"><head><meta charset="utf-8">
<title>Installation requise</title>
<style>body{font:16px/1.6 system-ui,sans-serif;max-width:44rem;margin:8vh auto;padding:0 1.5rem;color:#1d2733}
code{background:#eef2f6;padding:.15em .4em;border-radius:4px}h1{font-size:1.5rem}</style>
</head><body>
<h1>Le portail n'est pas encore installe</h1>
<p>La base de donnees ne repond pas ou les tables n'existent pas. Lance
l'installeur une fois, depuis le repertoire du projet :</p>
<p><code>php outils/installer.php</code></p>
<p>Il cree les tables, les roles, les permissions, le compte administrateur,
et il ecrit <code>src/config.local.php</code> avec un sel de session et une
cle de chiffrement tires au hasard. Le mot de passe administrateur est
affiche une seule fois.</p>
<hr>
<h2>Setup required</h2>
<p>The database is unreachable or empty. Run <code>php outils/installer.php</code>
once. It creates the tables, roles, permissions and the administrator
account, and prints the administrator password exactly once.</p>
</body></html>
