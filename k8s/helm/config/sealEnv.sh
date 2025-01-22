#!/bin/bash
kubeseal --fetch-cert --controller-name=sealed-secrets-controller --controller-namespace=kube-system > charts/tools/pub-sealed-secrets.pem
kubectl create -n nullchan secret generic nullchan-secrets --from-env-file=.env --dry-run=client -o yaml > charts/tools/secrets.yaml
kubeseal --format=yaml --cert=charts/tools/pub-sealed-secrets.pem < charts/tools/secrets.yaml > charts/tools/templates/encrypted_secrets.yaml
rm -f charts/tools/secrets.yaml
