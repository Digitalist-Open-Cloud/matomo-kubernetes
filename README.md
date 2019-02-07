# Instructions

## Deploy Matomo helm chart to K8S for the first time.

1. Create matomo namespace.
`kubectl create namespace matomo`

2. Add secret about your registry to where the matomo image is and the authentication for that.

`kubectl --namespace matomo create secret docker-registry matomo-registry-secret --docker-server=<your-registry-server> --docker-username=<your-name> --docker-password=<your-pword> --docker-email=<your-email>`

3. Deploy the matomo helm chart.

`helm install -n matomo . --namespace=matomo -f values.yaml`

---

## To update the Matomo K8S setup with your changes:
If you have made any changes in the helm chart, use this command to update pods, services, configmaps etc. in your cluster.

`helm upgrade matomo . --namespace=matomo -f values.yaml`