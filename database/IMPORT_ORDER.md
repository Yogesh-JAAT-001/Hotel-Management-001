# Heartland Abode DB Import Order (Safe)

Use this order in **phpMyAdmin** after selecting database `heartland_abode`:

1. `database/heartland_abode.sql`
2. `database/step4_ui_rooms_dining_seed.sql`
3. `database/step5_media_asset_mapping_seed.sql`
4. `database/step7_advanced_analytics_seed.sql`
5. `database/step6_analytics_indexes.sql`
6. `database/step8_audit_tables.sql`
7. `database/step8_dining_images_mapping.sql`

If admin login fails, import:

8. `database/fix_admin_login.sql`

## Notes

- The `step6_analytics_indexes.sql` in this repo is **routine-free** and does not use stored procedures.
- If phpMyAdmin shows `#1046 No database selected`, first run:
  - `CREATE DATABASE IF NOT EXISTS heartland_abode;`
  - `USE heartland_abode;`
- If your MariaDB reports old system-table mismatch (`mysql.proc`), avoid old procedure-based scripts and use the current `step6_analytics_indexes.sql` from this repo.
