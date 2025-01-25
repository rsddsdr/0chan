```
 ██████╗  ██████╗██╗  ██╗██╗  ██╗ ██████╗ 
██╔═████╗██╔════╝██║  ██║██║ ██╔╝██╔═══██╗
██║██╔██║██║     ███████║█████╔╝ ██║   ██║
████╔╝██║██║     ██╔══██║██╔═██╗ ██║   ██║
╚██████╔╝╚██████╗██║  ██║██║  ██╗╚██████╔╝
 ╚═════╝  ╚═════╝╚═╝  ╚═╝╚═╝  ╚═╝ ╚═════╝  
```
![CI](https://img.shields.io/github/actions/workflow/status/rsddsdr/0chan/build.yml?label=CI&logo=github&style=for-the-badge)

## Installation

### Docker compose way

#### 1. Copy or rename `.env-dist` to `.env`. Then fill fields in  `.env`  with desired values

#### 2. Deploy
```
$ docker compose up -d
```

#### 3. Setup db and admin account
```
$ docker exec -t backend /src/config/docker-entrypoint.sh createdb createadmin
```
you can simply remove  `createadmin`  from this line, if you don't need admin account.

frontend will appear on `http://localhost:80`

### K8S way

#### See in https://github.com/rsddsdr/0chan/tree/main/k8s
