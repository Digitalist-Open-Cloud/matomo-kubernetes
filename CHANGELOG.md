# Change log

<<<<<<< HEAD
## [12.0.7] - 2026-07-23

### Fixed

- The dashboard and tracker fpm-metrics (php-fpm_exporter) sidecars had no way to be scraped: the container declared no port and the `matomo-dashboard`/`matomo-tracker` Services only exposed the nginx port (8080). Added a named `metrics` containerPort (9253) to both sidecars, exposed it as a second `metrics` port on both Services (existing port renamed to `http` since Services with more than one port require all ports to be named), and added `prometheus.io/scrape`, `prometheus.io/port`, `prometheus.io/path` annotations to both Services for classic annotation-based Prometheus discovery. Ingress/HTTPRoute backends reference port 8080 by number so this doesn't affect routing.

- The dashboard and tracker nginx readiness probes (`GET /index.php` and `GET /matomo.js` on port 8080) had no explicit `timeoutSeconds`, so they relied on Kubernetes' implicit default of 1 second. `/index.php` in particular is Matomo's full application bootstrap proxied through php-fpm (autoloader, config, session, DB check), not a lightweight ping, and routinely takes longer than 1s to respond. `kube-probe` was disconnecting before php-fpm replied, which nginx logs as HTTP 499 (nginx's own `fastcgi_read_timeout` is 600s, so nginx itself wasn't the one giving up) - with the default single dashboard replica, a probe that fails this consistently means the pod stays `NotReady` and the Service can end up with zero endpoints. Added `timeoutSeconds: 5` to both probes.

### Changed

- Retuned the dashboard's php-fpm container and pool for a 4Gi memory limit (up from 768Mi):
  - `matomo.resources` limits raised to 2000m/4Gi (requests 500m/512Mi), sized for `matomo.phpfpm`'s new `pm.max_children` default.
  - `matomo.phpfpm` pool defaults: `pm.max_children` 100 -> 32 and `pm.max_spare_servers` 75 -> 20 (realistic worker count for the new memory budget; the old `max_children`/container-memory pairing allowed far more workers than the container could ever actually hold), `php_admin_value[memory_limit]` 2048M -> 1024M (still generous per-request, but a single runaway request can no longer claim a quarter of the container's budget). This pool config is also mounted into the core:archive CronJob container, but its `pm.*`/`php_admin_value` directives are FPM-pool-only and have no effect there since core:archive runs via the php CLI SAPI, not php-fpm; its actual memory ceiling comes from `matomo.php.memory_limit` (default `2G`) instead, unaffected by this change.
  - `pm` default switched from `ondemand` to `dynamic`. Under `ondemand`, `pm.start_servers`/`pm.min_spare_servers`/`pm.max_spare_servers` were parsed but never used (only `pm.max_children` and `pm.process_idle_timeout` apply to `ondemand`). `dynamic` makes active use of the already-configured spare-server settings: it keeps `pm.min_spare_servers` (5) workers warm at all times and grows up to `pm.max_children` (32) under load, avoiding fork latency on the next request after an idle period. Trade-off: unlike `ondemand`, `dynamic` never scales down to zero workers, so there's a small constant baseline (~5 idle workers) even with no traffic; `pm.process_idle_timeout` no longer applies (it's `ondemand`-only) and is now the inert one. Verified the existing defaults (`max_children=32`, `start_servers=5`, `min_spare_servers=5`, `max_spare_servers=20`) satisfy php-fpm's `dynamic`-mode startup validation (`min_spare_servers <= start_servers <= max_spare_servers <= max_children`), so no values needed to change to make the switch. The tracker's separate `matomo.tracker.phpfpm` pool is unchanged (still `ondemand`, 600s idle timeout).

||||||| 2d576a7
=======
## [12.0.7] - 2026-07-23

### Fixed

- The dashboard and tracker fpm-metrics (php-fpm_exporter) sidecars had no way to be scraped: the container declared no port and the `matomo-dashboard`/`matomo-tracker` Services only exposed the nginx port (8080). Added a named `metrics` containerPort (9253) to both sidecars, exposed it as a second `metrics` port on both Services (existing port renamed to `http` since Services with more than one port require all ports to be named), and added `prometheus.io/scrape`, `prometheus.io/port`, `prometheus.io/path` annotations to both Services for classic annotation-based Prometheus discovery. Ingress/HTTPRoute backends reference port 8080 by number so this doesn't affect routing.

### Changed

- Retuned the dashboard's php-fpm container and pool for a 4Gi memory limit (up from 768Mi):
  - `matomo.resources` limits raised to 2000m/4Gi (requests 500m/512Mi), sized for `matomo.phpfpm`'s new `pm.max_children` default.
  - `matomo.phpfpm` pool defaults: `pm.max_children` 100 -> 32 and `pm.max_spare_servers` 75 -> 20 (realistic worker count for the new memory budget; the old `max_children`/container-memory pairing allowed far more workers than the container could ever actually hold), `php_admin_value[memory_limit]` 2048M -> 1024M (still generous per-request, but a single runaway request can no longer claim a quarter of the container's budget). This pool config is also mounted into the core:archive CronJob container, but its `pm.*`/`php_admin_value` directives are FPM-pool-only and have no effect there since core:archive runs via the php CLI SAPI, not php-fpm; its actual memory ceiling comes from `matomo.php.memory_limit` (default `2G`) instead, unaffected by this change.
  - `pm` default switched from `ondemand` to `dynamic`. Under `ondemand`, `pm.start_servers`/`pm.min_spare_servers`/`pm.max_spare_servers` were parsed but never used (only `pm.max_children` and `pm.process_idle_timeout` apply to `ondemand`). `dynamic` makes active use of the already-configured spare-server settings: it keeps `pm.min_spare_servers` (5) workers warm at all times and grows up to `pm.max_children` (32) under load, avoiding fork latency on the next request after an idle period. Trade-off: unlike `ondemand`, `dynamic` never scales down to zero workers, so there's a small constant baseline (~5 idle workers) even with no traffic; `pm.process_idle_timeout` no longer applies (it's `ondemand`-only) and is now the inert one. Verified the existing defaults (`max_children=32`, `start_servers=5`, `min_spare_servers=5`, `max_spare_servers=20`) satisfy php-fpm's `dynamic`-mode startup validation (`min_spare_servers <= start_servers <= max_spare_servers <= max_children`), so no values needed to change to make the switch. The tracker's separate `matomo.tracker.phpfpm` pool is unchanged (still `ondemand`, 600s idle timeout).

>>>>>>> 1dcf5f39b6b29f6b581137c9d7a087fd6654be45
## [12.0.6] - 2026-07-22

### Added

- Overridable resources for containers whose limits/requests were previously hardcoded in templates: the shared `matomo-init` init container (`matomo.initResources`), the `wait-for-db` init container used by the pre-upgrade/post-install Jobs (`matomo.waitForDbResources`), the pre-upgrade and post-install Job containers (`matomo.preUpgradeResources`, `matomo.postInstallResources`), the queuedtracking-monitor and queuedtracking-process containers (`matomo.queuedTrackingMonitor.resources`, `matomo.queuedTrackingProcess.resources`), and the dashboard/tracker fpm-metrics exporter sidecars (`matomo.dashboard.exporter.resources`, `matomo.tracker.exporter.resources`). Defaults are unchanged.

## [12.0.5] - 2026-07-21

### Added

- Make post-install and pre-upgrade jobs work without pre-existing matomo-startup-config ConfigMap (#72)

## [12.0.4] - 2026-07-20

### Added

- Example for queued tracking process liveness probe.

## [12.0.3] - 2026-07-17

### Added

- The php-fpm_exporter (fpm-metrics) sidecar liveness and readiness probes are now overridable via new values `matomo.dashboard.exporter.livenessProbe`/`readinessProbe` and `matomo.tracker.exporter.livenessProbe`/`readinessProbe`.

### Changed

- All container probes now use **replace** semantics instead of merge. Setting any `livenessProbe`/`readinessProbe` value (matomo php-fpm, cli, nginx, queuedtracking, exporter) fully replaces the built-in default rather than deep-merging with it. This lets you switch handler type (e.g. `tcpSocket` → `exec`) without hitting `may not specify more than 1 handler type`. The defaults themselves are unchanged; each probe value now defaults to `{}` in `values.yaml` and the effective default is applied by the template when the value is empty.

## [12.0.2] - 2026-07-17

### Changed

- Changelog and chart README, missed in 12.0.1

## [12.0.1] - 2026-07-17

### Changed

- Matomo to 5.12.0

## [12.0.0] - 2026-07-17

### Added

- Checkov security scanning in CI (GitHub Actions): blocking on failed checks, SARIF results uploaded to the GitHub Security tab.
- Security hardening (Checkov remediation phase 1):
  - `automountServiceAccountToken: false` on all pods, overridable via new value `matomo.automountServiceAccountToken`.
  - Pod-level `seccompProfile: RuntimeDefault` on all workloads.
  - `runAsNonRoot: true` and `capabilities: drop: [ALL]` on all containers, including the init container.
  - securityContext (runAsUser 82, non-root, no capabilities) for the pre-upgrade and post-install job containers, which previously had none.
- Default resources (Checkov remediation phase 2), all overridable in values:
  - Matomo php-fpm containers (dashboard `matomo.resources`, tracker `matomo.tracker.resources`): 300m/256Mi requests, 1000m/768Mi limits.
  - nginx containers (`nginx.resources`, `matomo.tracker.nginx.resources`): 50m/64Mi requests, 500m/256Mi limits.
  - cli (`matomo.cli.resources`): 100m/128Mi requests, 500m/512Mi limits.
  - CronJobs (`matomo.cronJobs.*.resources`): 100m/128Mi requests, 1000m/1Gi limits. NOTE: core:archive was previously unlimited; raise these on large installations.
  - Pre-upgrade and post-install job containers: 100m/128Mi requests, 500m/512Mi limits.
  - CPU limit for the dashboard fpm-metrics sidecar (previously memory only).
- Default probes (Checkov remediation phase 2), all overridable in values:
  - Matomo php-fpm containers: tcpSocket on port 9000 (`matomo.livenessProbe`/`matomo.readinessProbe`, previously empty).
  - cli, queuedTrackingMonitor and queuedTrackingProcess: exec probe checking supervisord (new values under `matomo.cli`, `matomo.queuedTrackingMonitor`, `matomo.queuedTrackingProcess`).
  - fpm-metrics sidecars (dashboard, tracker): tcpSocket on exporter port 9253.
- Explicit `imagePullPolicy: Always` on the nginx containers (dashboard, tracker) and the pre-upgrade/post-install job containers.
- NetworkPolicy per component (Checkov remediation phase 3), enabled by default via `networkPolicy.enabled`. The default rule allows all ingress, so behavior is unchanged; tighten via `networkPolicy.ingress`.
- `.checkov.yaml` with documented skips for the checks that require image changes to fix (CKV_K8S_35 secrets as env vars, CKV_K8S_40 high UID, CKV_K8S_43 image digests) and CKV_K8S_22 (read-only root filesystem, pending writable-path mapping). Wired into the CI workflow via `config_file`.
- E2E test in CI (GitHub Actions): installs the chart on a kind cluster together with MariaDB and Valkey (`tests/kind/`), waits for all workloads, smoke tests the dashboard and tracker over HTTP, sends a tracking hit, verifies the QueuedTracking plugin talks to Valkey, and triggers both CronJobs (core:archive, scheduled-tasks) requiring them to complete.
- Render matrix in CI (GitHub Actions): `helm lint` + `helm template` + kubeconform schema validation over values combinations in `tests/render/` (defaults, all components disabled, loadbalancers/TLS ingress, extra secrets/configmaps/services/volumes with hook jobs, env/license/sidecars, scaled workers with restrictive NetworkPolicy) plus the kind e2e values.
- Gateway API support: HTTPRoutes for the dashboard and tracker via `matomo.gatewayApi.enabled` and `matomo.gatewayApi.parentRefs`, mirroring the tracker Ingress path list and reusing the `hostname` values. TLS is terminated on the Gateway listener. Can be used instead of, or alongside, Ingress.
- E2E routing coverage: the kind workflow installs ingress-nginx and uses `cloud-provider-kind` (which provides the Gateway API CRDs and GatewayClass) to program the chart's Gateway/HTTPRoutes, then verifies the dashboard, tracker and a tracking hit through both an Ingress and an HTTPRoute.
- `gateway-api` combination in the render matrix; kubeconform now also validates CRD-backed resources (HTTPRoute) via the CRDs-catalog schemas.
- Documented previously-undocumented but template-referenced override points: `matomo.config` (full install.json replacement), `matomo.php`/`matomo.phpfpm` (shared php.ini/php-fpm pool tuning for dashboard + core:archive), `matomo.dashboard.whitelist`, `matomo.queuedTrackingMonitor.replicas`, `matomo.queuedTrackingProcess.numProcs`, and the remaining `matomo.tracker.phpfpm` pool settings.
- `# @schema` annotations on open-ended fields (`matomo.env`, `matomo.license`, `matomo.php`, `matomo.phpfpm`, `matomo.cronJobs.scheduledTasks.php`, `matomo.config`, `matomo.dashboard.nginx.conf`, `extraSecrets.data`, `extraConfigMaps.data`, `extraServices`, `extraVolumes`, `extraVolumeMounts`, `networkPolicy.ingress`) so a generated `values.schema.json` can be strict (`additionalProperties: false`) everywhere else without rejecting legitimate overrides. Also extended to every label map (`matomo.extralabels`, `matomo.ingress.extralabels`, `matomo.gatewayApi.extralabels`), every ingress annotations map (`matomo.ingress.annotations`, `matomo.dashboard.ingress.annotations`, `matomo.tracker.ingress.annotations`), `nodeSelector`, and every liveness/readiness probe (dashboard/tracker php-fpm, cli, nginx, queuedtracking monitor/process) since probes are polymorphic (`exec`/`httpGet`/`tcpSocket`) and label/annotation maps are inherently arbitrary-key.
- CI workflow (`schema.yaml`) that regenerates `values.schema.json` with `helm-schema` (`--helm-docs-compatibility-mode --no-dependencies --skip-auto-generation required`) and fails if the committed file is missing or out of date. `helm lint`/`helm template` (already run in the render matrix) validate against it automatically once generated.

### Changed

- Checkov workflow pinned to current checkov-action master (checkov 3.3.8) instead of the stale v12.1347.0 release.

### Fixed

- `matomo-tracker` Service, both loadbalancer Services and extra services were missing `namespace` and were installed into the current context namespace instead of `.Values.namespace`.
- `matomo.queuedTrackingProcess.enabled` was required by the template but undefined in values; now documented explicitly (default `false`). Its NetworkPolicy is only created when the deployment is enabled.

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
