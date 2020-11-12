FROM php:7.4-cli-buster
LABEL maintainer="Vítězslav Dvořák <info@vitexsoftware.cz>"
ENV DEBIAN_FRONTEND noninteractive 

RUN apt update && apt-get install -my wget gnupg lsb-release gdebi-core

RUN echo "deb http://repo.vitexsoftware.cz $(lsb_release -sc) main" | tee /etc/apt/sources.list.d/vitexsoftware.list
RUN wget -O /etc/apt/trusted.gpg.d/vitexsoftware.gpg http://repo.vitexsoftware.cz/keyring.gpg
RUN apt update

ADD abraflexi-matcher_*_all.deb /tmp/abraflexi-matcher.deb

RUN gdebi -n /tmp/abraflexi-matcher.deb

CMD ["cron", "-f"]

