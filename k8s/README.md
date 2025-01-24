
# k8s workflow

## Requirements:
- [kubectl](https://kubernetes.io/docs/tasks/tools/install-kubectl/)
- [helm](https://helm.sh/docs/intro/install/)
- [helmwave](https://helmwave.readthedocs.io/en/latest/install/)
- [kubeseal](https://sealed-secrets.netlify.app/) (optional)

## Setup

#### 1. Rename or copy `.env-dist` to `.env`. Then fill fields in `.env` with desired values.

#### 2. Encrypt your secrets with sealed secrets. [Install it first](https://github.com/bitnami-labs/sealed-secrets/releases)
```
$ kubeseal --fetch-cert --controller-name=sealed-secrets-controller --controller-namespace=kube-system > pub-sealed-secrets.pem
$ kubectl create -n nullchan secret generic nullchan-secrets --from-env-file=.env --dry-run=client -o yaml > secrets.yaml
$ kubeseal --format=yaml --cert=pub-sealed-secrets.pem < secrets.yaml > encrypted_secrets.yaml
$ rm -f secrets.yaml
$ kubectl apply -f encrypted_secrets.yaml
```

or

#### 2. Create opaque secrets
```
$ kubectl create -n nullchan secret generic nullchan-secrets --from-env-file=.env
```

####  3.  Create storage class for your cloud ISP (or use default one)
Examples is located in `examples/` directory
```
kubectl apply -f <provider-name>-sc.yaml
```

#### 4. Deploy
```
$ helmwave up --build
```

#### 5. Setup db and admin account
```
$ kubectl exec -n nullchan -t deployments/backend -- /src/config/docker-entrypoint.sh createdb createadmin
```
you can simply remove `createadmin` from this line. If you don't need admin account, of course.

#### 6. Enjoy your ochko
