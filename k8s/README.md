
# k8s workflow

## Requirements:
- [kubectl](https://kubernetes.io/docs/tasks/tools/install-kubectl/)
- [helm](https://helm.sh/docs/intro/install/)
- [helmwave](https://helmwave.readthedocs.io/en/latest/install/)
- [kubeseal](https://sealed-secrets.netlify.app/) (optional)

## Setup

#### 1. Prepare .env 
```
$ cp .env-dist .env
```
Then fill fields in `.env` by your text editor with needed values

#### 2. Create namespace
Example is located in `examples/` directory
```
$ kubectl apply -f examples/ns.yaml
```

#### 3. Create secrets

**(Optional)** Encrypt your secrets with sealed secrets. [Install it first](https://github.com/bitnami-labs/sealed-secrets/releases)
```
$ kubeseal --fetch-cert --controller-name=sealed-secrets-controller --controller-namespace=kube-system > pub-sealed-secrets.pem
$ kubectl create -n nullchan secret generic nullchan-secrets --from-env-file=.env --dry-run=client -o yaml > secrets.yaml
$ kubeseal --format=yaml --cert=pub-sealed-secrets.pem < secrets.yaml > encrypted_secrets.yaml
$ rm -f secrets.yaml
$ kubectl apply -f encrypted_secrets.yaml
```

Or you can just create opaque secrets:
```
$ kubectl create -n nullchan secret generic nullchan-secrets --from-env-file=.env
```

#### 4. Create storage class (or use default one)
Examples is located in `examples/sc` directory
```
$ kubectl apply -f examples/sc/<provisioner-name>-sc.yaml
```

#### 5. Deploy
```
$ helmwave up --build
```

#### 6. Set up db and admin account
```
$ kubectl exec -n nullchan -t deployments/backend -- /src/config/docker-entrypoint.sh createdb createadmin
```
You can simply remove `createadmin` from this line, if you don't need admin account

#### 7. (Optional) Expose to clearnet
Example is located in `examples/` directory
```
$ kubectl apply -f examples/lb.yaml
```
