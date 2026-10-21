# Change log

## [11.0.65] - 2026-10-21

### Security

- Mitigations for CVE-2026-109618 and CVE-2026-109619: FPM pools enforce `memory_limit=-1`, low `post_max_size`/`upload_max_filesize` and `session.upload_progress.enabled=0` via `php_admin_*`. nginx `client_max_body_size` lowered to 32M (dashboard) and 8M (tracker).

## [11.0.60] - 2026-02-16

### Added

- Support for adding env variables to Matomo pods, clear text or secrets

## [11.0.59] - 2026-01-31

### Added

- Possibility to override nginx config for dashboard.

## [11.0.58] - 2026-01-31

### Added

- Possibility to override dashboard nginx image.
- Missing entries from Changelog.

### Changed

- extraConfigMaps set to false by default.

### Changed

- App version to 5.7.0

## [11.0.57] - 2025-01-07

### Changed

- OCI release workflow.

## [11.0.56] - 2025-12-21

### Changed

- Seconds (s) removed from annotation `nginx.ingress.kubernetes.io/proxy-read-timeout`.

## [11.0.55] - 2025-09-30

### Added

- Default annotations for tracker and ingress

## [11.0.54] - 2025-09-29

### Added

- Support for annotations in tracker and dashboard ingress

## [11.0.53] - 2025-06-23

### Changed

- Release workflow.
- App version updated to 5.3.2.

## [11.0.52] - 2025-06-23

### Changed

- Release workflow.

## [11.0.51] - 2025-06-09

### Added

- Support for UserFeedback plugin (Digitalist Open Cloud premium plugin) in ingress.

## [11.0.50] - 2025-04-03

### Changed

- pathType changed from Prefix to ImplementationSpecific for tracker ingress.

## [11.0.49] - 2025-04-03

### Changed

- pathType changed from Prefix to ImplementationSpecific for tracker ingress.
- App version updated to 5.3.1.

## [11.0.48] - 2025-01-25

### Added

- cspell CI checker.

### Changed

- Image to deploy

### Removed

- Blackfire nginx config.

## [11.0.39] - 2024-10-01

### Added

- Possibility to set ingressClassName
- Set app to 5.1.2 of Matomo

## [11.0.38] - 2024-09-18

### Added

- Info about OCI registry.
- Change log 😁.

### Changed

- Classic helm registry moved to <https://digitalist-open-cloud.github.io/matomo-kubernetes>.
