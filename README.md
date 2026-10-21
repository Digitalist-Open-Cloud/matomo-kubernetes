# Readme

Helm chart for running Matomo.

## Professional support

We try to provide general community support in the issue queue, but for professional support, please contact <matomo@digitalist.com>.

## Version 12.x

Experimental support for Gateway API added.
Also a lot fixes for best practices. And a lot of more, please see [Change log](CHANGELOG.md)


## Dependencies

You need MySQL or MariaDB running, in the cluster our outside, also we recommend to use Redis/Valkey for Queuedtracking and caching.

We publish [Matomo images on docker hub](https://hub.docker.com/r/digitalist/matomo/tags) - that could be used in this chart - you can also use your own docker images.

## Install

We recommend to use the OCI registry, [hosted at docker hub](https://hub.docker.com/r/digitalist/matomo/).

### OCI

```sh
oci://registry-1.docker.io/digitalist/matomo
```

Download values so you can override it with your own changes.

```sh
helm show values oci://registry-1.docker.io/digitalist/matomo > overrides.yaml

```

Add your overrides add deploy Matomo:

```sh
helm upgrade -i --namespace=mynamespace -f overrides.yaml -i matomo oci://registry-1.docker.io/digitalist/matomo
```

## Development

Generate helm schema:

```shell
helm-schema --chart-search-root charts/matomo --helm-docs-compatibility-mode --no-dependencies --skip-auto-generation required
```

Generate helm docs:

```shell
cd charts/matomo
helm-docs
```
