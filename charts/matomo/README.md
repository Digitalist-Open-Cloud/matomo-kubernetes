# matomo

![Version: 11.0.55](https://img.shields.io/badge/Version-11.0.55-informational?style=flat-square) ![AppVersion: 5.3.2](https://img.shields.io/badge/AppVersion-5.3.2-informational?style=flat-square)

A Helm chart for Matomo

## Values

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| db.hostname | string | `"matomo-db-mysql"` |  |
| db.name | string | `"matomo"` |  |
| db.password.secretKeyRef.key | string | `"mysql-root-password"` |  |
| db.password.secretKeyRef.name | string | `"matomo-db-mysql"` |  |
| db.prefix | string | `"matomo_"` |  |
| db.username | string | `"root"` |  |
| extraConfigMaps.create | bool | `true` |  |
| extraConfigMaps.data | object | `{}` |  |
| extraSecrets.create | bool | `false` |  |
| extraSecrets.data | object | `{}` |  |
| extraServices | object | `{}` |  |
| extraVolumeMounts | list | `[]` |  |
| extraVolumes | list | `[]` |  |
| global.imagePullSecrets | list | `[]` |  |
| global.imageRegistry | string | `""` |  |
| matomo.cli.enabled | bool | `true` |  |
| matomo.cli.replicas | int | `1` |  |
| matomo.cronJobs.coreArchive.activeDeadlineSeconds | int | `43200` |  |
| matomo.cronJobs.coreArchive.command | string | `"./console core:archive --disable-scheduled-tasks"` |  |
| matomo.cronJobs.coreArchive.concurrencyPolicy | string | `"Allow"` |  |
| matomo.cronJobs.coreArchive.enabled | bool | `true` |  |
| matomo.cronJobs.coreArchive.labels.component | string | `"cronjob"` |  |
| matomo.cronJobs.coreArchive.labels.instance | string | `"matomo"` |  |
| matomo.cronJobs.coreArchive.labels.managedBy | string | `"helm"` |  |
| matomo.cronJobs.coreArchive.labels.name | string | `"matomo-jobs-corearchive"` |  |
| matomo.cronJobs.coreArchive.labels.partOf | string | `"matomo"` |  |
| matomo.cronJobs.coreArchive.schedule | string | `"*/60 * * * *"` |  |
| matomo.cronJobs.scheduledTasks.activeDeadlineSeconds | int | `43200` |  |
| matomo.cronJobs.scheduledTasks.command | string | `"./console scheduled-tasks:run"` |  |
| matomo.cronJobs.scheduledTasks.enabled | bool | `true` |  |
| matomo.cronJobs.scheduledTasks.labels.component | string | `"cronjob"` |  |
| matomo.cronJobs.scheduledTasks.labels.instance | string | `"matomo"` |  |
| matomo.cronJobs.scheduledTasks.labels.managedBy | string | `"helm"` |  |
| matomo.cronJobs.scheduledTasks.labels.name | string | `"matomo-jobs-scheduled-tasks"` |  |
| matomo.cronJobs.scheduledTasks.labels.partOf | string | `"matomo"` |  |
| matomo.cronJobs.scheduledTasks.php | string | `nil` |  |
| matomo.cronJobs.scheduledTasks.schedule | string | `"*/60 * * * *"` |  |
| matomo.dashboard.enabled | bool | `true` |  |
| matomo.dashboard.firstuser.email | string | `"foo@example.com"` |  |
| matomo.dashboard.firstuser.password | string | `"admin123"` |  |
| matomo.dashboard.firstuser.username | string | `"admin"` |  |
| matomo.dashboard.hostname | string | `"my.host"` |  |
| matomo.dashboard.ingress.annotations."digitalist.cloud/instance" | string | `"matomo"` |  |
| matomo.dashboard.ingressClassName | string | `""` |  |
| matomo.dashboard.loadbalancer | bool | `false` |  |
| matomo.dashboard.nginx.nginxWorkerProcesses | int | `5` |  |
| matomo.dashboard.replicas | int | `1` |  |
| matomo.dashboard.sidecars | list | `[]` |  |
| matomo.dashboard.tls | bool | `false` |  |
| matomo.extralabels | object | `{}` |  |
| matomo.image | string | `"digitalist/matomo:5.2.1"` |  |
| matomo.imagePullSecrets | list | `[]` |  |
| matomo.imageRegistry | string | `""` |  |
| matomo.ingress.annotations."digitalist.cloud/instance" | string | `"matomo"` |  |
| matomo.ingress.enabled | bool | `true` |  |
| matomo.ingress.extralabels | object | `{}` |  |
| matomo.installCommand | string | `"./console plugin:activate ExtraTools && ./console matomo:install --install-file=/tmp/matomo/install.json --force --do-not-drop-db"` |  |
| matomo.license | string | `nil` |  |
| matomo.postInstallCommand | string | `""` |  |
| matomo.postInstallSleepTime | int | `5` |  |
| matomo.preUpgradeCommand | string | `""` |  |
| matomo.preUpgradeSleepTime | int | `5` |  |
| matomo.queuedTrackingMonitor.enabled | bool | `true` |  |
| matomo.queuedTrackingProcess.replicas | int | `1` |  |
| matomo.runAsUser | int | `82` |  |
| matomo.tracker.enabled | bool | `true` |  |
| matomo.tracker.hostname | string | `"my.host"` |  |
| matomo.tracker.ingress.annotations."digitalist.cloud/instance" | string | `"matomo"` |  |
| matomo.tracker.ingressClassName | string | `""` |  |
| matomo.tracker.loadbalancer | bool | `false` |  |
| matomo.tracker.nginx.nginxWorkerProcesses | int | `5` |  |
| matomo.tracker.nginx.resources | string | `nil` |  |
| matomo.tracker.phpfpm.max_children | int | `75` |  |
| matomo.tracker.phpfpm.max_requests | int | `500` |  |
| matomo.tracker.phpfpm.process_idle_timeout | string | `"600s"` |  |
| matomo.tracker.phpfpm.type | string | `"ondemand"` |  |
| matomo.tracker.replicas | int | `1` |  |
| matomo.tracker.tls | bool | `false` |  |
| namespace | string | `"matomo"` |  |
| nginx.image | string | `"digitalist/nginx:1.21.6"` |  |
| nginx.imagePullSecrets | list | `[]` |  |
| nginx.runAsUser | int | `100` |  |
| xdebug.disabled | int | `1` |  |

----------------------------------------------
Autogenerated from chart metadata using [helm-docs v1.14.2](https://github.com/norwoodj/helm-docs/releases/v1.14.2)
