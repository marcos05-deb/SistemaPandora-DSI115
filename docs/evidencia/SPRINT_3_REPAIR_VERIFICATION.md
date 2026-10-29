# Sprint 3 repair verification

**Current status: local automated verification passes.** The three inherited failures are repaired, global Pint is clean, and both final complete suites pass with the same assertion count and no skips. External deployment/institutional acceptance is not certified by local tests.

## Follow-up repairs and final QA — 2026-10-29

This section supersedes the earlier failed snapshots below; those results remain as historical evidence rather than being rewritten as successes.

### Root causes and preserved contracts

| Work unit | Evidence and repair |
| --- | --- |
| Appointment creation on a closed record | `sprint_2_backlog.md`, US-11 technical tasks, assigns closed-record validation to `CitaStoreRequest` and area authorization to `CitaPolicy`. The request already rejected closed records, but `CitaPolicy::create` intercepted that validation and returned 403. Removed only the duplicated state check from the Policy; role/area authorization remains intact. The existing redirect/session-error assertions were preserved. A new JSON regression requires 422, an `expediente` validation error and zero inserted appointments. `PacienteController` still exposes assignment only for an active record. |
| Secure-search authenticated fixture | `LoginController` stores a Base64 session key; `EncryptionContextService` requires it for web decryption. The fixture lacked that session and inserted a sensitive date as plain SQL text. It now uses the normal test-session shape and real `Paciente` encryption casts. An extra assertion checks the stored date is not plaintext; original access/status assertions remain intact. Explicit fixture UUIDs are strings, matching values returned by database hydration. No authentication or encryption bypass was added. |
| Global PHP formatting | User-authorized Pint normalization fixed 299 style findings across 306 checked files. Changes outside the above repairs and prior Sprint 3 work are mechanical formatting/import cleanup. `.gitattributes` now specifies `*.php text eol=lf` so Windows checkout does not reintroduce formatting failures. Lockfiles, generated assets and `.atl/` were not edited. |

### Final-source results

| Check | Actual result |
| --- | --- |
| Previously failing files plus new validation regression | **25 passed / 99 assertions**, 74.61s, exit 0. |
| Global check-only Pint | **PASS: 306 files**, exit 0, after all source-mutating normalization. |
| Real PDF extraction and XLSX parsing | **1 passed / 6 assertions**, 5.65s, exit 0. |
| Complete suite #1, hardened QA | **362 passed / 1842 assertions**, 869.22s, exit 0; no failures, warnings or skips. |
| Complete suite #2, identical hardened QA | **362 passed / 1842 assertions**, 692.86s, exit 0; same tests/assertions as run #1, no failures, warnings or skips. |
| Frontend build | **PASS**, 14.07s, exit 0. |
| Final production Dockerfile image build | **PASS**, image `pandora-sprint3-qa-production:20261029-v2`, exit 0. |
| Final production-image renderer smoke | **PASS**, exit 0; retained inspection confirms read-only root filesystem, network `none`, and final image identity. |

```text
Tests:    25 passed (99 assertions)
Duration: 74.61s

PASS   ......................................................... 306 files

Tests:    1 passed (6 assertions)
Duration: 5.65s

Tests:    362 passed (1842 assertions)
Duration: 869.22s

Tests:    362 passed (1842 assertions)
Duration: 692.86s

✓ built in 14.07s
HU20-E13 synthetic renderer smoke: PASS
```

The first expanded focused attempt produced **161 passed / 2 failed / 994 assertions**. Both failures were diagnosed as in-memory `Str::uuid()` objects in the corrected encrypted fixture, rejected by the endpoint's unchanged string/UUID validation. Casting fixture identifiers to strings resolved them. That attempt is retained in `v2/focused.log`; it is not presented as a passing run. No production validation or existing assertion was relaxed.

### Hardened isolation and inspectable evidence

All final database tests use the same dedicated QA project and **only `testing`**, with the application DML role and separate migration role. Final one-off test containers apply `compose.hardened.yml`: read-only root filesystem, `/tmp` tmpfs and `CACHE_STORE=database`. This exercises the conditional Compliance scenarios in the full suites rather than skipping them. Runs are serial and use a temporary empty `.env` placeholder; no unrelated database, container, volume or remote deployment is touched.

The ignored directory `storage/app/qa/sprint3-20261029/v2/` retains each command's `.log` and numeric `.exit`, plus `.inspect.json` captured **before** removal and `.cleanup.log` confirming removal for each one-off container. Inspection records the container name, project label, filesystem mode, network and observed exit code. The final production smoke additionally records its image SHA-256 identity; its network is disabled and its application entrypoint is bypassed, so it performs no database queries or migrations.

Source hashes were recorded in `v2/source-manifest.json` after final normalization and before acceptance/full-suite verification. The post-verification comparison checked **361 files with zero mismatches**, recorded in `v2/source-manifest-verification.json`. Local commands are retained as `run-v2-focused-final.sh`, `run-v2-full.sh` and `run-v2-build.sh` in the QA directory; all `run` operations use the named dedicated project and no published ports.

After both successful suites, all three dedicated QA services were stopped. `v2/containers-after-stop.log` records only the three expected service containers in state `exited`; `v2/running-after-stop.log` is empty, and `v2/stop.exit` is 0. One-off inspection and removal records are retained per phase, including both full suites. The temporary empty `.env` was removed after checking its exact known QA content (`v2/env-cleanup.json`); the identified WSL keepalive was terminated and confirmed absent (`v2/keepalive-cleanup.json`). Images, dedicated volumes and logs remain available for reproducibility. No unrelated container/volume, non-test database or remote deployment was changed; no commit or push was made.

### Current closure checklist

- [x] Original status/access assertions preserved; closed-record requests rejected with zero inserts.
- [x] Real encryption/session fixture used; sensitive test date stored encrypted.
- [x] Global Pint passes all 306 files, with durable PHP LF checkout policy.
- [x] Two complete suites pass: 362 tests / 1842 assertions each, no failures or skips.
- [x] Frontend build, real PDF/XLSX acceptance and final production-image read-only renderer smoke pass.
- [x] Final source hashes and per-container inspection/removal/cleanup evidence retained.
- [ ] Authorized destination database privileges/triggers and required destination EXPLAIN checks.
- [ ] Institutional rubric validation, Product Owner acceptance, Jira updates and required interactive sign-off/captures.

These remaining approvals are not granted by a passing local test suite. The earlier failed checks below are retained solely as historical snapshots, not current blockers.

## Earlier repair snapshot — retained historical results

## Authorized WSL Docker QA — 2026-10-29

| Check | Observed result |
| --- | --- |
| Locked dependencies | Composer: 138 installs, 0 updates, 0 removals. npm: 108 packages installed with `npm ci`; lockfiles unchanged. |
| Focused HU-20/HU-21/HU-23/dashboard regression | **42 passed, 280 assertions**, 68.10s; no warnings after creating the temporary empty QA `.env` placeholder. |
| Actual PDF extraction + XLSX parsing | **1 passed, 6 assertions**, 3.56s; Poppler extracts total 13 and monthly rows 6 and 7 from the final PDF. |
| Repaired PHP formatting | **7 files PASS** in check-only Pint after scoped normalization. |
| Frontend | `npm run build` **PASS**, 13.61s on the final normalized source. |
| Complete suite #1 | **3 failed, 2 skipped, 356 passed; 1826 assertions**, 589.26s, exit 1. |
| Complete suite #2 | **3 failed, 2 skipped, 356 passed; 1826 assertions**, 498.56s, exit 1. Same failing tests and assertion count as run #1. |
| Additional coverage for skipped scenarios | **2 passed, 8 assertions**, 4.12s, exit 0, serial after both suites: persistent-cache rate limiting and filesystem hardening in an extra read-only QA container with `CACHE_STORE=database`. |
| Global Pint | **FAIL: 306 files, 299 style issues**, exit 1. The seven repaired PHP files pass; no repository-wide formatting was applied. |
| Real production Dockerfile build | **PASS**, image `pandora-sprint3-qa-production:20261029`. |
| Read-only production-image renderer smoke | **PASS**, exit 0. Inspected container: `ReadonlyRootfs=true`, `NetworkMode=none`. No database access or mutating application entrypoint. |
| Export evidence | Actual synthetic PDF/XLSX and CSV/checksum generated. PDF rendered and visually inspected: readable headers, total 13, monthly rows 6/7, no clipping or overlapping content. |

Raw result excerpts:

```text
Tests:    42 passed (280 assertions)
Duration: 68.10s

Tests:    1 passed (6 assertions)
Duration: 3.56s

Tests:    3 failed, 2 skipped, 356 passed (1826 assertions)
Duration: 589.26s

Tests:    3 failed, 2 skipped, 356 passed (1826 assertions)
Duration: 498.56s

Tests:    2 passed (8 assertions)
Duration: 4.12s

PASS   ........................................................... 7 files
FAIL   ....................................... 306 files, 299 style issues
✓ built in 13.61s

HU20-E13 synthetic renderer smoke: PASS
PDF SHA256: 17dcee855d417f68076ca1172b10d06bacf4a4d39e2c012bace287eef7450e4e
XLSX SHA256: 4b966e7287b493a903926e9284b5de25cfba9c5f53c70a5cf58d2c35f24cfc12
```

### Isolation and commands

QA uses dedicated Compose project `pandora-sprint3-qa-20261029`, no published ports and separate `qa-pg`, `qa-vendor`, `qa-node` volumes. The database was explicitly checked as `testing`; application credentials have DML-only privileges, migrations use the separate admin connection. No unrelated containers or volumes were modified. Runtime: PHP 8.4.27, PostgreSQL 16, Node 22.19, Poppler 22.12.0. A temporary WSL session keeps Docker alive during execution.

The local-only QA definition and complete logs are retained under ignored `storage/app/qa/sprint3-20261029/`. Commands were run through this prefix:

```sh
docker compose -p pandora-sprint3-qa-20261029 -f storage/app/qa/sprint3-20261029/compose.yml
```

Operations: `build app`; `up -d postgres node app`; `exec -T app composer install --no-interaction --prefer-dist --no-progress`; `exec -T node npm ci --no-audit --no-fund`; scoped Pint normalization; focused tests; explicit PDF acceptance with `PANDORA_WRITE_QA_EVIDENCE=1`; full suites serially; check-only Pint; frontend build. The production image was built from the repository's actual `Dockerfile`, then run with `--read-only --tmpfs /tmp --network none --entrypoint php` and the isolated vendor volume plus the same writable storage/bootstrap-cache exceptions as production. The one-off smoke container was removed after inspection.

### Remaining complete-suite findings

| Test | Actual failure | Evidence of existing contract mismatch |
| --- | --- | --- |
| `tests/Feature/Cita/AsignarProximaCitaTest.php:170` | `Session is missing expected key [errors].` | Closed records are rejected by `CitaPolicy::create` before the expected session validation error. |
| `tests/Feature/CitaTest.php:159` | Expected redirect, received 403. | Same closed-record Policy behavior. |
| `tests/Feature/HU06/SecureSearchTest.php:201` | Expected 200, received 302; `Clave de sesión no disponible.` | The own-area patient-detail test does not establish the session encryption context required for decryption. |

These same three test locations are recorded in historical `HU-19.md`; their tests and appointment/search production code were not changed in this repair. They require a separate scoped fix or explicit contract resolution, not automatic weakening of assertions. The two skipped Compliance scenarios concern persistent-cache rate limiting and filesystem hardening. Both passed in a separate serial run using a dedicated read-only QA container with database cache; the full-suite results above are preserved without relabeling their skips as passes.

The additional command used the local QA Compose definition plus `compose.hardened.yml` (`read_only: true`, `/tmp` tmpfs, `CACHE_STORE=database`), then `run --rm --no-deps app php vendor/bin/pest tests/Feature/Compliance/ConfigurationTest.php tests/Feature/Compliance/InfrastructureIsolationTest.php --filter='rate_limiting_estricto|restriccion_de_sistema_de_archivos' --colors=never`.

### Cleanup and retained reproduction state

All three containers belonging to `pandora-sprint3-qa-20261029` were stopped after verification. Both one-off verification containers were removed. The temporary empty `.env` was removed only after verifying its exact QA-only content; the repository again has no `.env`, as before this task. The temporary WSL keepalive process was terminated. Images, dedicated volumes and ignored QA logs/configuration are retained for reproducibility; no unrelated container, volume or deployment was changed, and no commit or push was made.

To repeat locally, restart only this dedicated QA project and supply the container configuration plus an empty temporary `.env` placeholder to avoid phpdotenv's missing-file test warning. Never substitute a non-test database. The actual complete-suite outcomes remain failures even though the scoped repairs and additional acceptance checks pass.

## Changes and regression coverage

| Work unit | Root cause and correction | Verification |
| --- | --- | --- |
| HU-20 export parity | PDF printed only grouped rows, not the report totals. It now prints the same protected totals as the XLSX summary. | Monthly 6 + 7 = 13 regression; row limit above 5,000; role, guest, audit and header tests. |
| Dashboard privacy and HU-21 reads | An alternate sensitive read path bypassed access auditing and small-cohort protection. The shared service now records aggregate/list reads and suppresses related breakdowns/totals together. Sysadmin receives no individual urgent-alert records; authorized clinical access remains available. | Existing clinical access test retained; protected admin payload, both academic hierarchies, audit metadata and excluded-user regressions. |
| HU-23 bounded queries | The export hydrated every audit row and mapped every user at once. It now queries/maps pages of 500 in the same stable `(created_at DESC, id DESC)` order as HU-22. | More than one page, tied timestamps, exact row IDs/order, bounded query checks. The final download bytes remain buffered by the existing `ArchivoExportado` contract and 50,000-event cap. |

Dashboard protection is intentionally conservative: when a related breakdown contains a positive count below `indicadores.min_celda`, its whole breakdown and related totals are hidden. This sacrifices some chart detail to prevent reconstruction across faculty/career totals. Zero-only datasets stay zero. Clinical urgent-alert access is not converted into anonymous institutional access.

## Initial Windows-host checks — superseded by Docker QA above

| Command/check | Actual result |
| --- | --- |
| `php vendor/bin/pest tests/Feature/HU20 tests/Feature/HU23 tests/Feature/Dashboard/DashboardAlertOverviewTest.php` | Blocked: `php` is not recognized. `vendor/` is also absent. No tests ran. |
| `php vendor/bin/pint --test` | Blocked: `php` is not recognized. No formatter ran. |
| `npm run build` | Failed: `'vite' is not recognized`; `node_modules/` is absent. |
| `git diff --check` | Passed; Git reported only an LF/CRLF normalization warning. |
| Complete suite, twice | Not run: same missing PHP/dependency prerequisite; do not retry without fixing the environment. |
| Real PDF extraction / read-only renderer | Not run: PHP/dependencies and Poppler are unavailable locally. |

Those initial checks preceded explicit authorization for WSL Docker QA. The later run installed locked dependencies and modified only the isolated disposable QA database; no remote deployment was accessed.

## Run in an isolated QA environment

Use the project's PHP 8.4/dependency environment and **only a disposable `testing` PostgreSQL database**. `Tests\TestCase` refreshes the database, so never point these tests at demo or production data. Run all database tests serially.

```sh
php vendor/bin/pest tests/Feature/HU20 tests/Feature/HU23 tests/Feature/Dashboard/DashboardAlertOverviewTest.php
php vendor/bin/pest
php vendor/bin/pest
php vendor/bin/pint --test
npm run build
git diff --check
```

Record both complete-suite outputs and any failures. Do not describe focused tests as the complete suite. If formatting changes files, rerun verification on the final bytes.

### Real PDF acceptance and synthetic sample files

`tests/Acceptance/ReporteExportPdfTest.php` is an explicit additional acceptance command, outside the default `phpunit.xml` suites. It requires **Poppler `pdftotext` on PATH**, PHP dependencies and the same disposable test database. Provision Poppler in the QA image with maintainer approval; it is not a new production Composer dependency. Missing Poppler causes an explicit failure, not a skipped success.

```sh
PANDORA_WRITE_QA_EVIDENCE=1 php vendor/bin/pest tests/Acceptance/ReporteExportPdfTest.php
PANDORA_WRITE_QA_EVIDENCE=1 php vendor/bin/pest tests/Feature/HU23 --filter=HU23-E09
```

The first command extracts text from actual PDF bytes and parses actual XLSX bytes for monthly rows 6 and 7 and total 13. It uses a synthetic report DTO; the HU-20 feature test separately compares the endpoint's screen DTO and grouped XLSX rows. Together they cover renderer parity, not visual acceptance by a human.

After successful assertions, these commands wrote synthetic examples to `docs/evidencia/HU-20/` (PDF/XLSX) and `docs/evidencia/HU-23/` (CSV/checksum). See their README files for provenance. The PDF sample was visually checked; limited-user behavior is covered by focused feature tests. Broader human/PO acceptance remains separate.

### HU20-E13 read-only production-image smoke

An operator may run the following only against an explicitly authorized, already-running production-shaped QA container built with the current source:

```sh
docker compose -f docker-compose.prod.yml exec -T app php tests/Acceptance/read-only-export-smoke.php
```

Expected: `HU20-E13 synthetic renderer smoke: PASS` and two SHA-256 hashes. The script renders the real PDF template and uses the real XLSX writer with synthetic data in `/tmp`; it performs no database queries or migrations. Confirm the app container is configured `read_only: true` with writable `/tmp`, storage and bootstrap cache. This is a renderer/filesystem smoke, not a substitute for endpoint permission/audit tests or visual export review.

## Remaining closure evidence

- [x] Focused tests and explicit real-PDF acceptance command pass.
- [ ] Complete suite passes twice with consistent results; Pint and frontend build pass.
- [x] HU20-E13 read-only smoke and PDF visual check recorded.
- [x] Actual synthetic export samples generated; PDF inspected and XLSX/CSV parsed by tests.
- [ ] Deployed database privileges/triggers and any required EXPLAIN checks verified by an authorized operator.
- [ ] Jira, Product Owner CSV acceptance and institutional rubric validation completed by their owners.

Rollback boundaries: revert the PDF template plus HU-20 tests/acceptance script as one unit; the dashboard service/component/tests as a second; and the audit export service/HU-23 tests as a third. No migrations or data backfill are introduced.
