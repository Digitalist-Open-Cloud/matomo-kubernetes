# Readme
* Matomo - Helm chart

Helm chart for running the matomo containers (php) and nginx containers + cronjobs and configmaps needed.

## Dependencies

You need Mysql or Mariadb running, in the cluster our outside, also we recommend to use redis for Queuedtracking and caching.

We publish [Matomo images on docker hub](https://hub.docker.com/repository/docker/digitalist/matomo) - that could be used in this chart - you can also use your own docker images.

## Install

```
helm repo add matomo-kubernetes https://digitalist-se.github.io/matomo-kubernetes
```

Download values so you can override it with your own changes.

```
helm show values matomo-kubernetes/matomo > overrides.yaml
```

Add your overrides add deploy Matomo:

```
helm upgrade --namespace=mynamespace -f overrides.yaml -i my-matomo matomo-kubernetes/matomo
```


## Matomo - File structure (`matomo` directory)

| File | Description |
| ---- | ----------- |
| `Chart.yaml` | Describes the chart name, version etc. |
| `templates/configmap-matomo.yaml` | Contains the configuration for Matomo in a json-format, what plugins that should be activated etc. |
| `templates/configmap-matomo-cronjob-php.yaml` | Contains the php conf file for the Matomo cronjobs. |
| `templates/configmap-matomo-dashboard-php.yaml` | Contains the php conf file for the Matomo dashboard. |
| `templates/configmap-nginx-matomo-dashboard.yaml` | Contains the nginx conf file for the Matomo dashboard. |
| `templates/configmap-nginx-matomo-tracker.yaml` | Contains the nginx conf file for the Matomo tracker script. |
| `templates/configmap-supervisor-queuedtrackingprocess.yaml` | Contains the supervisor.d config for the queeudtrackingprocess |
| `templates/cronjob-matomo-backup-db.yaml` | Cronjob - For backing up database to Openstack Object storage. |
| `templates/cronjob-matomo-corearchive.yaml` | Cronjob - Creates visitor reports in Matomo. |
| `templates/cronjob-matomo-scheduled-tasks.yaml` | Cronjob - Runs scheduled tasks in Matomo, like sending e-mail reports on scheduled time. |
| `templates/deployment-matomo-dashboard.yaml` | Deployment specification for the Matomo dashboard pods. |
| `templates/deployment-matomo-queuedtrackingprocess.yaml` | Deployment for the queuedtrackingprocess for Matomo. |
| `templates/deployment-matomo-tracker.yaml` | Deployment for the matomo tracker js/php files. |
| `templates/ingress-matomo-dashboard.yaml` | Ingress for the Matomo dashboard |
| `templates/ingress-matomo-tracker.yaml` | Ingress for the matomo tracker. |
| `Values.yaml` | Here  you can change most common things we need to change in the templates like "Change cause" and image for Matomo, instead of digging through all YAML files in the templates-directory. |

## Matomo configuration (`matomo/values.yaml` file)

Check file for default values.

| Key | Description |
| --- | ----------- |
| `changeCause` | Changes the changecause, which is added in all deployment files, will trigger a re-deployment of all pods. |
| `namespace` | Describes what namespace Matomo should be deployed to. |
| `nginxWorkerProcesses` | How many cpu cores should nginx use, should be same as number of cpu cores on server. |
| `queuedTrackingProcess.numProcs` | How many processes should supervisor.d use for running `./console queuedtracking:process`. |
| `matomo.image` | What image should all matomo pods use, default or a custom one (if you need premium plugins for example). |
| `matomo.runAsUser` | Inside the matomo containers we want to run as a non root user. |
| `matomo.installCommand` | The command that should run in the init container for all Matomo pods to install / run db migrations for Matomo |
| `matomo.license.secretKeyRef.name` | Refers to a Kubernetes secret name for a Matomo premium plugin license key. |
| `matomo.license.secretKeyRef.key` | Refers to the key inside above Kubernetes secret where the license key value is. |
| `matomo.cronJobs.coreArchive.schedule` | Cronjob - Corearchive - How often should we run the `./console core:archive` cronjob. |
| `matomo.cronJobs.coreArchive.activeDeadlineSeconds` | Cronjob - Corearchive - How long can the cronjob take before it dies. |
| `matomo.cronJobs.coreArchive.command` | Cronjob - Corearchive - What command should we run? |
| `matomo.cronJobs.scheduledTasks.schedule` | Cronjob - Scheduled tasks - How often should we run the `./console scheduled-tasks:run` cronjob. |
| `matomo.cronJobs.scheduledTasks.activeDeadlineSeconds` | Cronjob - Scheduled tasks - How long can the cronjob take before it dies. |
| `matomo.cronJobs.scheduledTasks.command` | Cronjob - Scheduled tasks - What command should we run? |
| `matomo.cronJobs.swiftDbBackup.image` | Cronjob - swiftDbBackup - Define the image we should use for making database backups to Swift. |
| `matomo.cronJobs.swiftDbBackup.schedule` | Cronjob - swiftDbBackup - How often should we run the database backup cronjob. |
| `matomo.cronJobs.swiftDbBackup.namePrefix` | Cronjob - swiftDbBackup - What should the prefix of the filename on database backups? |
| `matomo.cronJobs.swiftDbBackup.deleteBackupAfter` | Cronjob - swiftDbBackup - How many days should we keep the backups? |
| `matomo.cronJobs.swiftDbBackup.openStackSecret` | Cronjob - swiftDbBackup - Name of the Kubernetes secret where the Openstack Swift authenticaion is. |
| `matomo.dashboard.replicas` | Number of replicas we should run of the Matomo dashboard. |
| `matomo.dashboard.hostname` | Hostname for the Matomo dashboard. |
| `matomo.dashboard.tls` | Should we runt tls for the dashboard or not. |
| `matomo.dashboard.firstuser.username` | What should the Username be for the admin user in Matomo. |
| `matomo.dashboard.firstuser.password` | What should the Password be for the admin user in Matomo. |
| `matomo.dashboard.firstuser.email` | What should the Email be for the admin user in Matomo. |
| `matomo.queuedTrackingProcess.replicas` | How many replicas should we run for the queuedTrackingProcess pod. |
| `matomo.tracker.replicas` | How many replicas should we run for the tracker pod. |
| `matomo.tracker.hostname` | What should be the hostname be for the tracker pod. |
| `matomo.tracker.tls` | Should we run tls for the tracker pod or not. |
| `nginx.image` | What nginx image do we want to run in the nginx container we run in the Matomo dashboard / tracker pods. |
| `nginx.runAsUser` | Inside the nginx containers we want to run as a non root user. |
| `db.hostname` | Hostname for the database server |
| `db.hostnameSlave` | Hostname for the slave database (which we run backups from). |
| `db.password.secretKeyRef.name` | Name of the Kubernetes secret where we have our database password. |
| `db.password.secretKeyRef.key` | Name of the key in the above Kubernetes secret where the database password value is. |
| `db.name` | Database name for Matomo |
| `db.username` | Username to use to access the Matomo database. |
| `db.prefix` | Prefix for the Matomo database tables. |
