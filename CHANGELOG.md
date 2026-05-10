# Changelog – MK WP Folder Mod

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
