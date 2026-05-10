# MK WP Folder Mod

WordPress plugin — extends **WP Media Folder** with automatic post-to-folder sync, bulk folder management, and a structured date hierarchy for media organisation.

## Features

- **Post → folder sync**: on `save_post`, creates a `Year / Month / Post Title` folder hierarchy and assigns attached media automatically
- **CPT support**: enabled post types get a root folder named after the CPT label; all attached media moved into it
- **Folder rename**: when a post title changes, the linked WPMF folder is renamed to match
- **Trash / restore**: trashed posts move their folder under `DeletedPosts`; restoring moves it back to the correct year/month
- **Folder Manager** (`Media › WPMF Gestione Cartelle`): live stats (protected, empty, deletable, duplicates, unassigned), one-click bulk operations
- **Bulk actions**: _Elimina Meta WPMF_ on posts and media; _Scollega cartella WPMF_ on enabled CPT screens
- **Unassigned collector**: moves all media without a folder into a `Non assegnati` root folder
- **Duplicate fixer**: attachments assigned to more than one WPMF folder are corrected to keep only the first term
- **Protection system**: folders prefixed with `!` and all their descendants are never deleted by bulk operations
- **Automation log** (`Media › WPMF Log`): rolling 100-entry log of all plugin actions
- **Settings** (`Media › WPMF Impostazioni`): configurable max folder name length (5–100 chars) and CPT opt-in list

## Requirements

- WordPress 6.0+
- PHP 8.0+
- [WP Media Folder](https://www.joomunited.com/wordpress-products/wp-media-folder) (plugin by JoomUnited) — must be active

## Installation

1. Copy `mk-wp-folder-mod.php` into `wp-content/mu-plugins/` (must-use plugin, no activation needed).
2. Alternatively drop it into `wp-content/plugins/` and activate via the Plugins screen.
3. Configure enabled CPTs under **Media › WPMF Impostazioni**.

## Usage

### Automatic sync
Save or update any `post` (or enabled CPT) — the plugin creates the folder hierarchy and assigns media. No manual steps required.

### Folder Manager
Open **Media › WPMF Gestione Cartelle** to:
- See counts for protected / empty / deletable / duplicate / unassigned items
- Collect unassigned media into `Non assegnati`
- Fix duplicate folder assignments
- Delete empty folders (iterative passes until none remain)
- Delete all unprotected folders (irreversible — confirm dialog required)

### Protection
Prefix any folder name with `!` in WP Media Folder to protect it and all child folders from bulk deletion.

## Versioning

`MAJOR.MINOR.PATCH` — patch bumps on every code change; minor/major on explicit instruction.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).
