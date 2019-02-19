# Instructions

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

2. Add secret about your registry to where the matomo image is and the authentication for that.

    `kubectl --namespace matomo create secret docker-registry matomo-registry-secret --docker-server=<your-registry-server> --docker-username=<your-name> --docker-password=<your-pword> --docker-email=<your-email>`

3. Deploy the redis helm chart.

    Go to the redis directory and run:

    `helm dependencies update` - Downloads dependencies for the Helm chart

    `helm install -n matomo-redis . --namespace=matomo -f values.yaml`

4. Deploy the mysql helm chart.

    Go to the mysql directory and run:

    `helm dependencies update` - Downloads dependencies for the Helm chart

    `helm install -n matomo-db . --namespace=matomo -f values.yaml`

5. Deploy the matomo helm chart.

    Go to the matomo directory and run:

    `helm install -n matomo . --namespace=matomo -f values.yaml`

---

## To update the Matomo K8S setup with your changes:

If you have made any changes in a helm chart, use this command to update pods, services, configmaps etc. in your cluster. Go to the specific directory for the helm chart.

    Can either be 'matomo', 'matomo-db' or 'matomo-mysql'.

    `helm upgrade matomo . --namespace=matomo -f values.yaml`