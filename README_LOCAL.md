# Instructions

This readme will show you how to deploy Matomo locally on Minikube.

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

3. Create a secret for `Matomo license`.

    `kubectl -n local create secret generic matomo-license --from-literal=matomo-license=<YOUR-LICENSE-KEY>`

4. Create secret about your OpenStack authentication details (if you plan to use the Swift backup).

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

5. Deploy the redis helm chart.

    Go to the redis directory and run:

    `helm dependencies update` - Downloads dependencies for the Helm chart

    `helm install -n matomo-redis . --namespace=matomo -f values.yaml`

6. Deploy the mysql helm chart.

    Go to the mysql directory and run:

    `helm dependencies update` - Downloads dependencies for the Helm chart

    `helm install -n matomo-db . --namespace=matomo -f values.yaml`

7. Deploy the matomo helm chart.

    Go to the matomo directory and run:

    `helm install -n matomo . --namespace=matomo -f values.yaml`

---

## To update the Matomo K8S setup with your changes:

If you have made any changes in a helm chart, use this command to update pods, services, configmaps etc. in your cluster. Go to the specific directory for the helm chart.

Can either be 'matomo', 'matomo-db' or 'matomo-mysql'.

`helm upgrade matomo . --namespace=matomo -f values.yaml`