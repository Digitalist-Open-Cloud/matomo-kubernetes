# Change log

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
