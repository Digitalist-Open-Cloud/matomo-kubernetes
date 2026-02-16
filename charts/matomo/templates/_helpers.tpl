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