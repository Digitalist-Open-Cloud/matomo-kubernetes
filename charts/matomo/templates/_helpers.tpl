{{/* Generate basic labels */}}
{{- define "matomo.labels" }}
  labels:
    app.kubernetes.io/name: {{ .name }}
    app.kubernetes.io/instance: {{ .instance }}
    app.kubernetes.io/component: {{ .component }}
    app.kubernetes.io/part-of: {{ .partOf }}
    app.kubernetes.io/managed-by: {{ .managedBy }}
{{- end }}

{{/*
Combined checksum of every ConfigMap this chart renders.

Kubernetes only rolls a Deployment/CronJob's pods when the pod template
itself changes; updating a ConfigMap's content does not by itself trigger
anything, so without a checksum baked into the pod template, `helm upgrade`
(or `kubectl apply`) updates the ConfigMap object but already-running pods
keep using the stale mounted file until they restart for some unrelated
reason. This is deliberately chart-wide (every ConfigMap template, not just
the ones a given workload happens to mount today) rather than computed
per-workload: simpler to keep correct than tracking which of the ~12
ConfigMap templates each workload mounts - that's exactly how this
annotation previously only covered configmap-matomo.yaml and silently
missed every ConfigMap added since (e.g. the php-fpm pool config).
*/}}
{{- define "matomo.configChecksum" -}}
{{- $ctx := . -}}
{{- $templates := list
  "configmap-matomo.yaml"
  "configmap-matomo-phpfpm.yaml"
  "configmap-matomo-phpfpm-tracker.yaml"
  "configmap-matomo-dashboard-php.yaml"
  "configmap-matomo-cronjob-php.yaml"
  "configmap-matomo-scheduledtasks-php.yaml"
  "configmap-nginx-matomo-dashboard.yaml"
  "configmap-nginx-matomo-tracker.yaml"
  "configmap-supervisor-tracker.yaml"
  "configmap-supervisor-queuedtrackingmonitor.yaml"
  "configmap-supervisor-queuedtrackingprocess.yaml"
  "configmap-pre-upgrade-extras.yaml"
  "configmaps-extras.yaml"
-}}
{{- $rendered := "" -}}
{{- range $templates -}}
{{- $rendered = print $rendered (include (print $ctx.Template.BasePath "/" .) $ctx) -}}
{{- end -}}
{{- $rendered | sha256sum -}}
{{- end -}}

{{/*
Standard pod-template annotations: the combined config checksum above, plus
the chart version and app version that rendered this pod. Bumping the chart
version alone (even with no rendered content change) also forces a restart,
as a second line of defense alongside the checksum. Include under
`annotations:` at the caller's indent, e.g.:
  annotations:
    (include "matomo.podAnnotations" . | indent 8, wrapped in {{ }})
*/}}
{{- define "matomo.podAnnotations" -}}
checksum/config: {{ include "matomo.configChecksum" . }}
helm.sh/chart-version: {{ .Chart.Version | quote }}
app.kubernetes.io/version: {{ .Chart.AppVersion | quote }}
{{- end -}}

{{- define "matomo.images.pullSecrets" -}}
  {{- $pullSecrets := list }}

  {{- if .global }}
    {{- range .global.imagePullSecrets -}}
      {{- $pullSecrets = append $pullSecrets . -}}
    {{- end -}}
  {{- end -}}

  {{- range .images -}}
    {{- range .imagePullSecrets -}}
      {{- $pullSecrets = append $pullSecrets . -}}
    {{- end -}}
  {{- end -}}

  {{- if (not (empty $pullSecrets)) }}
imagePullSecrets:
    {{- range $pullSecrets }}
  - name: {{ . }}
    {{- end }}
  {{- end }}
{{- end -}}
{{- define "matomo.license" }}
{{- if .Values.matomo.license }}
- name: MATOMO_LICENSE
  valueFrom:
    secretKeyRef:
      name: {{ .Values.matomo.license.secretKeyRef.name }}
      key: {{ .Values.matomo.license.secretKeyRef.key }}
{{- end -}}
{{- end -}}
{{- define "matomo.initContainer" -}}
- name: matomo-init
  image: {{.Values.matomo.image}}
  securityContext:
    runAsUser: {{.Values.matomo.runAsUser}}
    privileged: false
    allowPrivilegeEscalation: false
    runAsNonRoot: true
    capabilities:
      drop:
        - ALL
  imagePullPolicy: Always
  env:
  - name: MATOMO_FIRST_USER_NAME
    value: {{.Values.matomo.dashboard.firstuser.username}}
  - name: MATOMO_FIRST_USER_EMAIL
    value: {{.Values.matomo.dashboard.firstuser.email}}
  - name: MATOMO_FIRST_USER_PASSWORD
    value: {{.Values.matomo.dashboard.firstuser.password}}
  - name: MATOMO_DB_HOST
    value: {{.Values.db.hostname}}
  - name: MATOMO_DB_NAME
    value: {{.Values.db.name}}
{{- if .Values.db.prefix }}
  - name: MATOMO_DB_PREFIX
    value: {{.Values.db.prefix}}
{{- end }}
  - name: MATOMO_DB_USERNAME
    value: {{.Values.db.username}}
  - name: MATOMO_DB_PASSWORD
    valueFrom:
      secretKeyRef:
        name: {{ .Values.db.password.secretKeyRef.name }}
        key: {{ .Values.db.password.secretKeyRef.key }}
{{- include "matomo.license" . | nindent 2 }}
  command: [ 'sh' , '-c' , 'rsync -crlOt --no-owner --no-group --no-perms /usr/src/matomo/ /var/www/html/ && {{.Values.matomo.installCommand}}' ]
  {{- if .Values.matomo.initResources }}
  resources:
{{ toYaml .Values.matomo.initResources | indent 4 }}
  {{- end }}
  volumeMounts:
    - name: static-data
      mountPath: /var/www/html
    - name: matomo-configuration
      mountPath: /tmp/matomo/
      readOnly: true
{{- end -}}

{{- define "matomo.init" -}}
initContainers:
  {{- include "matomo.initContainer" . | nindent 2 }}
{{- end -}}

{{/*
Runs once after matomo-init (rsync + matomo:install), before php-fpm/nginx
start. `matomo:install --force` reconciles the DB schema/admin user/
install.json but does not itself fire the plugin-installed/update events
Matomo's own plugins (e.g. TagManager) hook to regenerate their generated
assets - those are normally left to fire lazily on the first web request(s)
after a (re)start. Since `static-data` is an emptyDir, that plugin-installed
bookkeeping is reset on every fresh pod, so without this step, concurrent
probes/traffic on every pod (re)start each independently pay for and race
that expensive regeneration (observed as repeated multi-second stalls in the
php-fpm slowlog, all bottoming out in TagManager::regenerateReleasedContainers()).
Running `core:update`/`tagmanager:regenerate-released-containers` here instead
does that work once, synchronously, gated behind pod readiness.
*/}}
{{- define "matomo.warmupContainer" -}}
- name: matomo-warmup
  image: {{.Values.matomo.image}}
  securityContext:
    runAsUser: {{.Values.matomo.runAsUser}}
    privileged: false
    allowPrivilegeEscalation: false
    runAsNonRoot: true
    capabilities:
      drop:
        - ALL
  imagePullPolicy: Always
  env:
  - name: MATOMO_DB_HOST
    value: {{.Values.db.hostname}}
  - name: MATOMO_DB_NAME
    value: {{.Values.db.name}}
{{- if .Values.db.prefix }}
  - name: MATOMO_DB_PREFIX
    value: {{.Values.db.prefix}}
{{- end }}
  - name: MATOMO_DB_USERNAME
    value: {{.Values.db.username}}
  - name: MATOMO_DB_PASSWORD
    valueFrom:
      secretKeyRef:
        name: {{ .Values.db.password.secretKeyRef.name }}
        key: {{ .Values.db.password.secretKeyRef.key }}
{{- include "matomo.license" . | nindent 2 }}
  command: [ 'sh' , '-c' , '{{.Values.matomo.warmupCommand}}' ]
  {{- if .Values.matomo.warmupResources }}
  resources:
{{ toYaml .Values.matomo.warmupResources | indent 4 }}
  {{- end }}
  volumeMounts:
    - name: static-data
      mountPath: /var/www/html
{{- end -}}

{{/* initContainers for the dashboard and tracker: matomo-init, then matomo-warmup. */}}
{{- define "matomo.initWithWarmup" -}}
initContainers:
  {{- include "matomo.initContainer" . | nindent 2 }}
  {{- include "matomo.warmupContainer" . | nindent 2 }}
{{- end -}}

{{/*
matomo-init fails fast when the database is unreachable, so a Job would burn
its backoff retries while a fresh database is still provisioning. Bounded so
an unreachable database fails the Job instead of hanging it forever.
*/}}
{{- define "matomo.waitForDb" -}}
- name: wait-for-db
  image: {{.Values.matomo.image}}
  securityContext:
    runAsUser: {{.Values.matomo.runAsUser}}
    privileged: false
    allowPrivilegeEscalation: false
    runAsNonRoot: true
    capabilities:
      drop:
        - ALL
  imagePullPolicy: Always
  command:
    - bash
    - -c
    - |
      host={{.Values.db.hostname}}
      port={{.Values.db.port | default 3306}}
      for i in $(seq 1 180); do
        if (echo > /dev/tcp/$host/$port) 2>/dev/null; then
          echo "database $host:$port is reachable"
          exit 0
        fi
        echo "waiting for database $host:$port ($i/180)"
        sleep 5
      done
      echo "database $host:$port not reachable after 15 minutes, giving up"
      exit 1
  {{- if .Values.matomo.waitForDbResources }}
  resources:
{{ toYaml .Values.matomo.waitForDbResources | indent 4 }}
  {{- end }}
{{- end -}}

{{/*
matomo.probe renders a container probe with REPLACE (not merge) semantics.

Helm always deep-merges a user's values onto the chart's values.yaml, so a
partial override of a probe would be merged with the chart default and could
end up specifying two handler types (e.g. tcpSocket + exec), which Kubernetes
rejects. To make an override fully REPLACE the default, probe defaults are kept
out of values.yaml (each probe value defaults to {}) and supplied here instead:

  - a non-empty override is rendered verbatim and fully replaces the default
  - an empty override ({} / unset) falls back to the built-in default

Params (dict): "override" = the value from .Values, "default" = default probe
YAML rendered from one of the matomo.probe.default.* templates below.
*/}}
{{- define "matomo.probe" -}}
{{- if .override -}}
{{- toYaml .override -}}
{{- else -}}
{{- .default -}}
{{- end -}}
{{- end -}}

{{/* Default php-fpm container probes (dashboard + tracker matomo container). */}}
{{- define "matomo.probe.default.phpfpm.liveness" -}}
tcpSocket:
  port: 9000
initialDelaySeconds: 15
periodSeconds: 20
{{- end -}}
{{- define "matomo.probe.default.phpfpm.readiness" -}}
tcpSocket:
  port: 9000
initialDelaySeconds: 10
periodSeconds: 10
{{- end -}}

{{/* Default supervisord probes (cli, queuedtracking-monitor, queuedtracking-process). */}}
{{- define "matomo.probe.default.supervisord.liveness" -}}
exec:
  command:
  - /bin/sh
  - -c
  - "ps -A | grep supervisord"
initialDelaySeconds: 15
periodSeconds: 20
{{- end -}}
{{- define "matomo.probe.default.supervisord.readiness" -}}
exec:
  command:
  - /bin/sh
  - -c
  - "ps -A | grep supervisord"
initialDelaySeconds: 10
periodSeconds: 10
timeoutSeconds: 5
{{- end -}}

{{/* Default nginx probes. Liveness is shared; readiness differs per service. */}}
{{- define "matomo.probe.default.nginx.liveness" -}}
exec:
  command:
  - /bin/sh
  - -c
  - "[ -f /tmp/nginx.pid ] && ps -A | grep nginx"
initialDelaySeconds: 10
periodSeconds: 10
timeoutSeconds: 5
{{- end -}}
{{- define "matomo.probe.default.nginx.dashboard.readiness" -}}
httpGet:
  scheme: HTTP
  path: /index.php
  port: 8080
initialDelaySeconds: 10
periodSeconds: 10
timeoutSeconds: 5
{{- end -}}
{{- define "matomo.probe.default.nginx.tracker.readiness" -}}
httpGet:
  scheme: HTTP
  path: /matomo.js
  port: 8080
initialDelaySeconds: 10
periodSeconds: 10
timeoutSeconds: 5
{{- end -}}

{{/* Default php-fpm_exporter (fpm-metrics) sidecar probes (dashboard + tracker). */}}
{{- define "matomo.probe.default.exporter.liveness" -}}
tcpSocket:
  port: 9253
initialDelaySeconds: 15
periodSeconds: 20
{{- end -}}
{{- define "matomo.probe.default.exporter.readiness" -}}
tcpSocket:
  port: 9253
initialDelaySeconds: 10
periodSeconds: 10
{{- end -}}
