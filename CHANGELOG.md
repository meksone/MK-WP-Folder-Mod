# Changelog – MK WP Folder Mod

## [0.4.9] – 2026-06-18
### Added
- `wpmf_count_orphaned_media()`: counts attachments with no `wpmf-category` term and no `post_parent`
- `wpmf_assign_orphaned_media()`: processes orphaned media in 200-item batches —
  1. Loads all `_thumbnail_id` post-meta in one query to build a featured-image map
  2. If attachment is used as featured image: sets `post_parent`, resolves/creates the post's folder, assigns the term
  3. Otherwise: assigns to a date-based folder using the attachment's upload date
- Folder Manager: "Orphaned" stat card + "Tenta di assegnare ai post" button
- All new strings added to `it_IT`, `en_US`, `es_ES` `.po` files

## [0.4.8] – 2026-06-18
### Changed
- `wpmf_delete_all_folders_except_protected`: replaced per-term `wp_delete_term()` loop with single bulk SQL delete (3 queries total regardless of folder count); cache flushed once at the end
- `wpmf_delete_empty_folders`: replaced per-term `wpmf_folder_is_empty()` calls with a single SQL query per pass to find all leaf-empty folders at once; uses same bulk delete path
- `wpmf_collect_protected_ids`: replaced recursive function (N DB queries) with iterative BFS — one query per depth level; extracted shared `wpmf_get_protected_ids()` helper
- New `wpmf_bulk_delete_terms()`: deletes `term_relationships`, `term_taxonomy`, `terms` rows in bulk, cleans `folder_color` option entries, flushes caches once

## [0.4.7] – 2026-06-18
### Added
- Full WordPress plugin header (`Plugin Name`, `Plugin URI`, `Description`, `Author`, `License`)
- GitHub updater: `pre_set_site_transient_update_plugins` checks `meksone/mk-wp-folder-mod` latest release; shows update notice in WP plugins screen
- `plugins_api` filter: populates plugin info modal with release notes from GitHub
- GitHub Actions workflow `.github/workflows/release.yml` — builds and attaches `mk-wp-folder-mod.zip` on every published release

## [0.4.6] – 2026-06-18
### Added
- Full i18n support: `Text Domain: mk-wp-folder-mod`, `Domain Path: /languages`, textdomain loaded on `init`
- All UI strings wrapped with `__()` / `_e()` / `esc_html__()` / `esc_html_e()` — admin menu titles, page headings, labels, descriptions, button text, confirm dialogs, bulk action labels, admin notices
- Month names in `wpmf_month_map()` now translatable (English source strings)
- Special folder names (`Unassigned`, `DeletedPosts`) now translatable

## [0.4.5] – 2026-06-18
### Added
- Settings toggle "Cartella per nome post": when enabled, creates a named post subfolder inside Year/Month; when disabled (default), media go directly into the month folder
- `post_name_folder` key added to `wpmf_auto_settings`; defaults to `false` for no behavior change on existing installs
- `untrash_post` hook respects the setting — skips folder rename when post-name folders are disabled

## [0.4.4] – 2026-05-10
### Fixed
- S3 offload / block-editor upload-first flows: folders were not created because `save_post` runs before attachments are linked to the parent post
- Added `wpmf_sync_attachment_to_parent_folder()` hooked on `add_attachment` and `attachment_updated`; resolves the parent post's folder (creating it if needed) and assigns the term — skips if attachment already in the correct folder

## [0.4.3] – 2026-05-10
### Added
- Unassigned media collector: `wpmf_assign_unassigned_media()` batch-moves all attachments without a `wpmf-category` term into a `Non assegnati` root folder (500-item batches)
- Folder Manager stat card for unassigned count; "Raccogli non assegnati" button (disabled when count is 0)

## [0.4.2] – 2026-05-10
### Added
- Duplicate folder assignment fixer: `wpmf_fix_root_duplicates()` keeps oldest term, removes extras; exposed via "Correggi duplicati" button in Folder Manager

## [0.4.1] – 2026-05-10
### Added
- Folder Manager page (`wpmf-folder-manager`) under Media menu with live stats (protected / empty / deletable / duplicate / unassigned) and four bulk operation buttons
- `wpmf_collect_protected_ids()` — recursive protected-subtree collector
- `wpmf_folder_is_empty()` — checks attachment count and child terms
- `wpmf_delete_empty_folders()` — iterative pass until no empty unprotected folders remain
- `wpmf_delete_all_folders_except_protected()` — bulk delete all unprotected folders

## [0.4.0] – initial release
### Added
- Core logging via `wpmf_custom_logger()` with rolling 100-entry WP option store
- Settings helpers: `wpmf_auto_get_settings()`, `wpmf_auto_get_folder_length()`, `wpmf_auto_cpt_is_enabled()`
- Admin menu: Log page and Settings page under Media
- Settings page: folder name max-length (5–100) and CPT opt-in checkboxes
- `wpmf_sanitize_folder_name()` — strips HTML, entities, non-alphanumeric chars; truncates at word boundary
- `wpmf_month_map()` — Italian month names with per-month colours
- `wpmf_pick_color()` — deterministic colour from CRC32 hash
- `wpmf_set_folder_color()` — writes to `folder_color` WPMF option (idempotent, skips default grey)
- `wpmf_find_folder_in_db()` / `wpmf_get_or_create_folder()` — create-or-get by name + parent
- `wpmf_build_date_hierarchy()` — Year / Month / Title three-level structure for posts
- `wpmf_build_cpt_folder()` — single root folder named after CPT label for enabled CPTs
- `save_post` hook: auto-creates folder on first save, renames on title change, syncs all attached media
- `wp_trash_post` hook: moves folder under `DeletedPosts` root
- `untrash_post` hook: restores folder to correct year/month with current title
- Bulk action _Elimina Meta WPMF_ on `edit-post` and `upload` screens
- Bulk action _Scollega cartella WPMF_ on enabled CPT list screens (registered dynamically on `init`)
- Admin notices for both bulk actions
