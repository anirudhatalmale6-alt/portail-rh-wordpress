# Portail RH — version WordPress installable

The HR and recruitment portal, packaged so it can be installed from the
WordPress admin like any plugin. **The application is not rewritten**: the
plugin is a wrapper around the same code that runs standalone.

```
app/            the application (unchanged, still runs on its own)
plugin/         the WordPress wrapper, one file
build.py        assembles portail-rh-<version>.zip
```

`python3 build.py` produces the installable zip.

## Why a wrapper and not a rewrite

The portal is a complete PHP application with its own router, its own
authentication and its own schema. WordPress can only install a theme or a
plugin, so the plugin does three things and nothing else:

1. **On activation** it writes the app's local config, reusing WordPress's own
   MySQL credentials — one database to back up, no password to type — and
   generates the session salt and the vault key **once**.
2. **On every request under `/rh`** it hands over to the application at
   `plugins_loaded` priority 0, before WordPress has emitted a single byte.
   The app sends its own `Content-Security-Policy` and opens its own session;
   both need headers to be unsent, so any later hook would break it.
3. **It adds one admin page** (Tools → Portail RH): status, the URL, and a
   button that runs the installer.

The install logic is **not duplicated**. `outils/installer.php` is the only
copy; its CLI guard now also accepts an explicit constant that the plugin
defines after checking capability and nonce.

WordPress tables are untouched. Deactivating removes nothing.

## Two bugs this packaging exposed

Both would have hit a real host, and both are fixed in the app rather than
worked around in the plugin:

- **`DB_HOST` can carry a socket path.** WordPress writes it three ways:
  `localhost`, `localhost:3307`, and `localhost:/var/lib/mysql/mysql.sock`.
  The third is common on shared hosting. Read as a port, PHP connects to the
  *default* socket — a different server — and answers `Access denied`, which
  sends you hunting for a password problem that does not exist. The app now
  accepts a `socket` setting and builds a `unix_socket` DSN.
- **Every internal link was an absolute path.** Mounted under `/rh`, a link to
  `/connexion` leaves the application and lands on WordPress. The app now has
  a `base_uri` setting that prefixes every link, redirect and asset; it is
  empty when the app is alone on its domain, so the standalone deployment is
  unchanged.

## Verified

Installed from the zip through WordPress itself, then exercised over HTTP:
plugin accepted and activated, tables created in the WordPress database,
`/rh/` redirects to `/rh/connexion`, the stylesheet is served and actually
applied (background and font read from the rendered page, not from the file),
a real login reaches the dashboard, and the WordPress site still answers 200.
