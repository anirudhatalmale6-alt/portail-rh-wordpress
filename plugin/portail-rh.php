<?php
/**
 * Plugin Name:       Portail RH
 * Description:       Le portail RH et recrutement, installable depuis WordPress. L'application vit a cote de WordPress et n'y touche pas ; WordPress sert d'installeur, d'hote et de porte d'entree.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Anirudha Talmale
 * License:           GPLv2 or later
 * Text Domain:       portail-rh
 *
 * ---------------------------------------------------------------------------
 * POURQUOI UNE EXTENSION, ET CE QU'ELLE FAIT VRAIMENT
 *
 * Le portail RH n'est pas un morceau de WordPress : c'est une application PHP
 * complete, avec son propre routeur, sa propre authentification et son propre
 * schema de base. Elle a ete ecrite comme ca parce qu'elle doit pouvoir vivre
 * sans WordPress.
 *
 * WordPress ne sait installer que deux choses : un theme ou une extension.
 * D'ou cette enveloppe. Elle ne reecrit pas l'application et n'en duplique
 * aucune ligne — elle la MONTE sur une adresse du site :
 *
 *   1. a l'activation, elle ecrit la configuration locale de l'application en
 *      reprenant les identifiants MySQL de WordPress, puis cree les tables ;
 *   2. a chaque requete commencant par /rh, elle passe la main a
 *      l'application AVANT que WordPress n'ait produit la moindre sortie ;
 *   3. elle ajoute une page d'administration : etat, lien, reinstallation.
 *
 * Les tables de l'application sont PREFIXEES rh_ et ne touchent a aucune
 * table de WordPress. Desactiver l'extension ne supprime rien.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'RH_WP_VERSION', '1.0.0' );
define( 'RH_WP_DIR', plugin_dir_path( __FILE__ ) );
define( 'RH_WP_APP', RH_WP_DIR . 'app/' );

/**
 * L'adresse a laquelle le portail repond. Modifiable depuis wp-config.php :
 *   define( 'RH_BASE', '/personnel' );
 */
if ( ! defined( 'RH_BASE' ) ) { define( 'RH_BASE', '/rh' ); }


/* =========================================================================
 * 1. MONTAGE DE L'APPLICATION
 * ========================================================================= */

/**
 * Priorite 0 sur plugins_loaded : le plus tot possible.
 *
 * L'application envoie ses propres en-tetes (Content-Security-Policy) et
 * ouvre sa propre session. Les deux exigent qu'AUCUNE sortie n'ait encore ete
 * produite. A `init` ou `template_redirect`, un autre plugin a deja pu ecrire
 * un octet et tout casserait avec un « headers already sent » impossible a
 * diagnostiquer pour qui ne connait pas WordPress.
 */
add_action( 'plugins_loaded', 'rh_wp_monte', 0 );

function rh_wp_monte() {
	$base = rtrim( RH_BASE, '/' );
	$chemin = (string) parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH );
	$chemin = rtrim( $chemin, '/' );
	if ( '' === $chemin ) { $chemin = '/'; }

	if ( $chemin !== $base && 0 !== strpos( $chemin . '/', $base . '/' ) ) {
		return;
	}

	$interne = substr( $chemin, strlen( $base ) );
	if ( '' === $interne ) { $interne = '/'; }

	/*
	 * LES FICHIERS STATIQUES.
	 * Quand l'application est seule sur son domaine, le serveur web sert
	 * public/assets/... directement. Ici ces chemins n'existent pas sur le
	 * disque a l'adresse demandee : ils arrivent dans WordPress. Sans ce
	 * bloc, le portail s'afficherait SANS AUCUN STYLE — une page qui a l'air
	 * cassee alors que tout fonctionne.
	 */
	if ( 0 === strpos( $interne, '/assets/' ) ) {
		rh_wp_sert_fichier( $interne );
	}

	$requete = parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_QUERY );
	$_SERVER['REQUEST_URI'] = $interne . ( $requete ? '?' . $requete : '' );
	$_SERVER['SCRIPT_NAME'] = $base . '/index.php';

	require RH_WP_APP . 'public/index.php';
	exit;
}

/**
 * Sert un fichier d'assets, en refusant tout ce qui sort du dossier.
 */
function rh_wp_sert_fichier( $interne ) {
	$racine = realpath( RH_WP_APP . 'public/assets' );
	$vise   = realpath( RH_WP_APP . 'public' . $interne );

	// realpath resout « .. » : un chemin qui ne commence plus par le dossier
	// des assets est une tentative de remonter dans l'arborescence.
	if ( false === $vise || false === $racine || 0 !== strpos( $vise, $racine ) ) {
		status_header( 404 );
		exit;
	}

	$types = array(
		'css' => 'text/css', 'js' => 'application/javascript',
		'svg' => 'image/svg+xml', 'png' => 'image/png',
		'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif',
		'woff2' => 'font/woff2', 'woff' => 'font/woff', 'ico' => 'image/x-icon',
	);
	$ext = strtolower( pathinfo( $vise, PATHINFO_EXTENSION ) );
	if ( ! isset( $types[ $ext ] ) ) { status_header( 404 ); exit; }

	header( 'Content-Type: ' . $types[ $ext ] );
	header( 'Content-Length: ' . filesize( $vise ) );
	header( 'Cache-Control: public, max-age=86400' );
	readfile( $vise );
	exit;
}


/* =========================================================================
 * 2. CONFIGURATION ET INSTALLATION
 * ========================================================================= */

/**
 * Ecrit src/config.local.php s'il n'existe pas.
 *
 * IL N'EST JAMAIS REECRIT, ET C'EST VITAL : ce fichier porte la cle qui
 * chiffre les coordonnees bancaires des salaries. La regenerer rendrait
 * illisibles toutes celles deja en base. Le fichier est donc cree une fois,
 * puis conserve tel quel — y compris si l'extension est desactivee puis
 * reactivee.
 *
 * Les identifiants de base sont ceux de WordPress : une seule base a
 * sauvegarder, et aucun mot de passe a saisir.
 */
function rh_wp_ecrit_config() {
	$fichier = RH_WP_APP . 'src/config.local.php';
	if ( is_file( $fichier ) ) {
		return 'existant';
	}

	/*
	 * DB_HOST de WordPress prend TROIS formes, et les confondre coute cher :
	 *   « localhost »                         -> TCP, port par defaut
	 *   « localhost:3307 »                    -> TCP, port explicite
	 *   « localhost:/var/lib/mysql/mysql.sock » -> SOCKET UNIX
	 * La troisieme est frequente en mutualise. Traitee comme un port, elle
	 * fait connecter PHP a la socket par defaut — un autre serveur — et rend
	 * un « Access denied » qui envoie chercher un probleme de mot de passe
	 * inexistant. Vu en vrai en installant cette extension.
	 */
	$hote   = DB_HOST;
	$port   = 3306;
	$socket = '';
	if ( false !== strpos( $hote, ':' ) ) {
		list( $hote, $suffixe ) = explode( ':', $hote, 2 );
		if ( ctype_digit( $suffixe ) ) {
			$port = (int) $suffixe;
		} else {
			$socket = $suffixe;
		}
	}

	$contenu = "<?php\n"
		. "/* Ecrit par l'extension WordPress « Portail RH » a l'activation.\n"
		. " *\n"
		. " * 'cle_coffre' dechiffre les coordonnees bancaires de tous les\n"
		. " * salaries. La perdre les rend illisibles ; la REGENERER aussi.\n"
		. " * Sauvegarde ce fichier SEPAREMENT de la base : les garder\n"
		. " * ensemble annule tout l'interet du chiffrement.\n"
		. " */\n"
		. "return [\n"
		. "    'sel_session'   => " . var_export( bin2hex( random_bytes( 32 ) ), true ) . ",\n"
		. "    'cle_coffre'    => " . var_export( base64_encode( random_bytes( 32 ) ), true ) . ",\n"
		. "    'cookie_secure' => " . ( is_ssl() ? 'true' : 'false' ) . ",\n"
		. "    'base_uri'      => " . var_export( rtrim( RH_BASE, '/' ), true ) . ",\n"
		. "    'domaine'       => " . var_export( untrailingslashit( home_url() ), true ) . ",\n"
		. "    'bd' => [\n"
		. "        'pilote' => 'mysql',\n"
		. "        'hote'   => " . var_export( $hote, true ) . ",\n"
		. "        'port'   => " . $port . ",\n"
		. "        'base'   => " . var_export( DB_NAME, true ) . ",\n"
		. "        'user'   => " . var_export( DB_USER, true ) . ",\n"
		. "        'passe'  => " . var_export( DB_PASSWORD, true ) . ",\n"
		. ( '' !== $socket
			? "        'socket' => " . var_export( $socket, true ) . ",\n"
			: '' )
		. "    ],\n"
		. "];\n";

	if ( false === file_put_contents( $fichier, $contenu ) ) {
		return 'echec';
	}
	@chmod( $fichier, 0600 );
	return 'cree';
}

register_activation_hook( __FILE__, 'rh_wp_activation' );

function rh_wp_activation() {
	rh_wp_ecrit_config();
	// On ne cree PAS les tables ici. L'activation d'une extension se fait
	// dans une requete deja contrainte, et une erreur y est affichee de
	// facon illisible. L'installation se lance depuis la page dediee, ou
	// l'on peut lire ce qui s'est passe.
	update_option( 'rh_wp_a_installer', 1 );
}

/**
 * Lance l'installeur de l'application. UN SEUL code d'installation existe :
 * celui de outils/installer.php. On ne le recopie pas ici, on l'execute.
 */
function rh_wp_installe() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return "Droits insuffisants.";
	}
	rh_wp_ecrit_config();

	if ( ! defined( 'RH_INSTALL_AUTORISE' ) ) {
		define( 'RH_INSTALL_AUTORISE', true );
	}
	ob_start();
	try {
		require RH_WP_APP . 'outils/installer.php';
	} catch ( Throwable $e ) {
		echo "\nEXCEPTION : " . $e->getMessage() . "\n";
	}
	$sortie = ob_get_clean();
	update_option( 'rh_wp_a_installer', 0 );
	return $sortie;
}

/**
 * Les tables existent-elles ? Lu dans la base, pas suppose.
 */
function rh_wp_installe_deja() {
	global $wpdb;
	$tables = $wpdb->get_col( "SHOW TABLES LIKE 'reglages'" );
	return ! empty( $tables );
}


/* =========================================================================
 * 3. LA PAGE D'ADMINISTRATION
 * ========================================================================= */

add_action( 'admin_menu', 'rh_wp_menu' );

function rh_wp_menu() {
	add_management_page( 'Portail RH', 'Portail RH', 'manage_options',
		'portail-rh', 'rh_wp_page' );
}

function rh_wp_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }

	$sortie = '';
	if ( isset( $_POST['rh_installer'] ) && check_admin_referer( 'rh_installer' ) ) {
		$sortie = rh_wp_installe();
	}

	$url  = home_url( RH_BASE . '/' );
	$pret = rh_wp_installe_deja();

	echo '<div class="wrap"><h1>Portail RH</h1>';

	echo '<h2>Adresse</h2><p>Le portail repond ici : <a href="' . esc_url( $url )
		. '" target="_blank"><code>' . esc_html( $url ) . '</code></a></p>';
	echo '<p class="description">Pour la changer, ajoutez dans <code>wp-config.php</code> :'
		. ' <code>define( \'RH_BASE\', \'/personnel\' );</code></p>';

	echo '<h2>Base de donnees</h2>';
	if ( $pret ) {
		echo '<p><span style="color:#1a7f37">&#10003;</span> Les tables sont en place'
			. ' dans la base de WordPress.</p>';
	} else {
		echo '<p><span style="color:#b32d2e">&#10007;</span> Les tables ne sont pas'
			. ' encore creees. Lancez l\'installation ci-dessous.</p>';
	}

	echo '<form method="post">';
	wp_nonce_field( 'rh_installer' );
	echo '<p><button class="button button-primary" name="rh_installer" value="1">'
		. ( $pret ? 'Relancer l\'installation' : 'Installer maintenant' )
		. '</button></p></form>';
	echo '<p class="description">L\'installation est rejouable sans risque :'
		. ' rien n\'est efface, les tables absentes sont creees.'
		. ' <strong>Le mot de passe administrateur du portail n\'est affiche'
		. ' qu\'une seule fois</strong>, dans le resultat ci-dessous.</p>';

	if ( '' !== $sortie ) {
		echo '<h2>Resultat</h2><pre style="background:#fff;border:1px solid #c3c4c7;'
			. 'padding:12px;max-height:420px;overflow:auto;white-space:pre-wrap">'
			. esc_html( $sortie ) . '</pre>';
	}

	echo '<h2>Ce que cette extension ne fait pas</h2><ul style="list-style:disc;'
		. 'margin-left:20px">';
	echo '<li>Elle ne modifie aucune table de WordPress. Les tables du portail'
		. ' portent leurs propres noms et vivent a cote.</li>';
	echo '<li>Elle n\'utilise pas les comptes WordPress. Le portail a sa propre'
		. ' connexion, ses propres roles et ses propres permissions.</li>';
	echo '<li>La desactiver ne supprime rien : ni tables, ni fichiers, ni'
		. ' configuration.</li>';
	echo '</ul>';

	echo '</div>';
}


/* =========================================================================
 * 4. UN AVERTISSEMENT TANT QUE RIEN N'EST INSTALLE
 * ========================================================================= */

add_action( 'admin_notices', 'rh_wp_avis' );

function rh_wp_avis() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	if ( ! get_option( 'rh_wp_a_installer' ) ) { return; }
	if ( rh_wp_installe_deja() ) { update_option( 'rh_wp_a_installer', 0 ); return; }

	echo '<div class="notice notice-warning"><p><strong>Portail RH</strong> est'
		. ' active mais ses tables ne sont pas encore creees. '
		. '<a href="' . esc_url( admin_url( 'tools.php?page=portail-rh' ) ) . '">'
		. 'Terminer l\'installation</a>.</p></div>';
}
