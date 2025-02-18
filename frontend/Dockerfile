FROM node:fermium AS install

WORKDIR /install

ADD package.json package-lock.json .

RUN npm install


FROM node:fermium AS builder

WORKDIR /builder

ADD ./build                        ./build
ADD ./config                       ./config
ADD ./src                          ./src
ADD ./static                       ./static
ADD ./.babelrc                     ./.babelrc
ADD ./index.html                   ./index.html
ADD package.json package-lock.json .

COPY --from=install /install/node_modules ./node_modules

RUN npm run build


FROM ubuntu:22.04

ARG DEBIAN_FRONTEND=noninteractive

RUN apt-get update -y && apt-get install -y nginx nginx-extras

ADD ./deploy/docker-entrypoint.sh /docker-entrypoint.sh
ADD ./deploy/nginx.conf /nginx.conf

COPY --from=builder /builder/dist /dist

EXPOSE 80

CMD [ "/bin/bash", "-c", "/docker-entrypoint.sh" ]
