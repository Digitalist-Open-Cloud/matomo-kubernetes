{{/* Generate basic labels */}}
{{- define "matomo.labels" }}
  labels:
    app.kubernetes.io/name: {{ .name }}
    app.kubernetes.io/instance: {{ .instance }}
    app.kubernetes.io/component: {{ .component }}
    app.kubernetes.io/part-of: {{ .partOf }}
    app.kubernetes.io/managed-by: {{ .managedBy }}
{{- end }}

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
{{- define "matomo.init" -}}
initContainers:
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
{{ if .Values.db.prefix }}
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
{{- include "matomo.license" . | nindent 4 }}
    command: [ 'sh' , '-c' , 'rsync -crlOt --no-owner --no-group --no-perms /usr/src/matomo/ /var/www/html/ && {{.Values.matomo.installCommand}}' ]
    resources:
      limits:
        cpu: 200m
        memory: 512Mi
      requests:
        cpu: 100m
        memory: 128Mi
    volumeMounts:
      - name: static-data
        mountPath: /var/www/html
      - name: matomo-configuration
        mountPath: /tmp/matomo/
        readOnly: true

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
{{- end -}}

{{/* Default nginx probes. Liveness is shared; readiness differs per service. */}}
{{- define "matomo.probe.default.nginx.liveness" -}}
exec:
  command:
  - /bin/sh
  - -c
  - "[ -f /tmp/nginx.pid ] && ps -A | grep nginx"
initialDelaySeconds: 10
periodSeconds: 5
{{- end -}}
{{- define "matomo.probe.default.nginx.dashboard.readiness" -}}
httpGet:
  scheme: HTTP
  path: /index.php
  port: 8080
initialDelaySeconds: 10
periodSeconds: 5
{{- end -}}
{{- define "matomo.probe.default.nginx.tracker.readiness" -}}
httpGet:
  scheme: HTTP
  path: /matomo.js
  port: 8080
initialDelaySeconds: 10
periodSeconds: 5
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
