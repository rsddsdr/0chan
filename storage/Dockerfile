FROM node:jod

ARG DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y supervisor nginx nginx-extras
RUN npm -g i pm2 nodemon

RUN mkdir /app
WORKDIR /app
ADD ./package.json ./
RUN npm install

ADD ./config  ./config
ADD ./src     ./src
ADD ./init.sh ./init.sh

EXPOSE 80
EXPOSE 228
EXPOSE 53

CMD bash init.sh
