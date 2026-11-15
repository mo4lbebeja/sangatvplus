# SangaTV Plus — server (Laravel 13 + Filament 5, PostgreSQL)

Project spec: ../doc/CLAUDEsangatv.md — read it first.

- DB: PostgreSQL `db_sangatv_plus` (tests: `db_sangatv_plus_test`, configured in phpunit.xml).
- CMS: Filament at `/admin` (Herd: http://sangatv-cms.test). Roles: admin, front_office, it (`App\Filament\Concerns\RestrictedByRole`).
- TV API: `routes/api.php` (`/api/v1`), Sanctum tokens on `Device` only (ability `tv`). CMS users cannot mint tokens.
- Provider credentials live only in `channel_providers.credentials` (encrypted); TVs get short-lived signed URLs (`StreamUrlSigner`).
- Every model change is audited (`Auditable` trait → `audit_logs`).
- Run `php artisan test --compact` and `vendor/bin/pint` before finishing.
- Production: see DEPLOYMENT.md (`./deploy.sh`). Never run `db:seed` in production; first admin via `php artisan sangatv:install`.
- PMS/BMS inbound API: `routes/api.php` (`/api/pms/v1`, `AuthenticatePms` HMAC/bearer + IP + idempotency, every call logged in `pms_events`). Stay logic in `PmsStayService` (reservation id = `guests.external_ref`). Vendor doc source: `resources/views/pms/vendor-doc.blade.php` (shown in CMS → Integrasi PMS and downloadable).
- Channel features: EPG (`EpgImporter`, XMLTV sources in `hotel_profiles.epg_urls`, `/api/v1/epg`), room-type packages + adult PIN (`Channel::scopeVisibleTo`), announcements (`Announcement::payloadFor`, delivered in bootstrap + heartbeat), viewing stats (`/api/v1/views` → `channel_views`) and uptime (`channel_checks`) in `ChannelStats`.
- Multi-hotel: `config/tenancy.php`, `App\Tenancy\HotelManager` (DB per hotel), central models in `App\Models\Central`, middleware `InitializeHotel` (CMS, session `hotel_id`) / `InitializeHotelForApi` (header `X-SangaTV-Hotel`, code/PMS index, legacy). Vendor panel `/sijalucom` (`App\Vendor`, guard `vendor`). Isolation tests: `tests/Tenancy` (real PG DBs `sangatv_test_*`).
