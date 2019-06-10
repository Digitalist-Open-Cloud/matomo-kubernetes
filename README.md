# Instructions

This readme will show you how to deploy this helm chart locally on Minikube.

## Requirements

    * Minikube
    * Kubectl
    * Helm

## Start local development environment.

1. Start minikube using this command.

    `minikube start --bootstrapper kubeadm --extra-config=apiserver.enable-admission-plugins="Initializers,NamespaceLifecycle,LimitRanger,ServiceAccount,DefaultStorageClass,DefaultTolerationSeconds,NodeRestriction,ResourceQuota,MutatingAdmissionWebhook,ValidatingAdmissionWebhook,PodSecurityPolicy" --memory=4096 --cpus=4`

2. Go to the minikube directory (.minikube) and run these commands to get Pod security policies running.
    
    `kubectl apply -f psp.yaml`

    `kubectl auth reconcile -f psp-cr.yaml`

    `kubectl auth reconcile -f psp-crb.yaml`

3. Install helm tiller to your cluster so we can use helm for deploying our applications in helm charts.

    `helm init`


## Deploy Matomo helm charts to K8S for the first time.

1. Create matomo namespace.

    `kubectl create namespace matomo`

2. Create secret about your registry to where the matomo image is and the authentication for that.

    `kubectl --namespace matomo create secret docker-registry matomo-registry-secret --docker-server=<your-registry-server> --docker-username=<your-name> --docker-password=<your-pword> --docker-email=<your-email>`

3. Create secret about your OpenStack authentication details.

```
kubectl -n matomo create secret generic matomo-openstack-secret \
--from-literal=ST_AUTH_VERSION=3 \
--from-literal=OS_USERNAME=<your-username> \
--from-literal=OS_USER_DOMAIN_NAME=<your-user-domain-name> \
--from-literal=OS_PASSWORD=<your-password> \
--from-literal=OS_PROJECT_NAME=<your-project-name> \
--from-literal=OS_PROJECT_DOMAIN_NAME=<your-project-domain-name> \
--from-literal=OS_AUTH_URL=<your-provider-auth-url> \
--from-literal=OS_USER_ID=<your-user-id> \
--from-literal=OS_PROJECT_ID=<your-project-id> \
--from-literal=OS_SWIFT_CONTAINER=<your-swift-container> \
```

4. Deploy the redis helm chart.

    Go to the redis directory and run:

    `helm dependencies update` - Downloads dependencies for the Helm chart

    `helm install -n matomo-redis . --namespace=matomo -f values.yaml`

5. Deploy the mysql helm chart.

    Go to the mysql directory and run:

    `helm dependencies update` - Downloads dependencies for the Helm chart

    `helm install -n matomo-db . --namespace=matomo -f values.yaml`

6. Deploy the matomo helm chart.

    Go to the matomo directory and run:

    `helm install -n matomo . --namespace=matomo -f values.yaml`

---

## To update the Matomo K8S setup with your changes:

If you have made any changes in a helm chart, use this command to update pods, services, configmaps etc. in your cluster. Go to the specific directory for the helm chart.

    Can either be 'matomo', 'matomo-db' or 'matomo-mysql'.

    `helm upgrade matomo . --namespace=matomo -f values.yaml`


---

## Description of `values.yaml`

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