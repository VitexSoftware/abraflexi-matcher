FROM php:7.2.7-cli-buster
LABEL maintainer="Vítězslav Dvořák <info@vitexsoftware.cz>"

RUN apt update && apt-get install -my wget gnupg

RUN wget -O - http://v.s.cz/info@vitexsoftware.cz.gpg.key | apt-key add -
RUN echo deb http://v.s.cz/ stable main | tee /etc/apt/sources.list.d/vitexsoftware.list

ADD flexibee-matcher_*_all.deb /repo/flexibee-matcher.deb

RUN cd /repo ; dpkg-scanpackages . /dev/null | gzip -9c > Packages.gz 
RUN echo "deb [trusted=yes] file:///repo/ ./" > /etc/apt/sources.list.d/local.list

RUN apt update ; RUN DEBIAN_FRONTEND=noninteractive apt-get -y install flexibee-matcher



FROM debian:latest
LABEL maintainer="info@vitexsoftware.cz"
ENV TERM xterm
ENV DEBIAN_FRONTEND noninteractive

RUN apt update ; apt -y install lsb-release wget dialog apt-utils cron msmtp
RUN echo "deb http://repo.vitexsoftware.cz $(lsb_release -sc) main paid" | tee /etc/apt/sources.list.d/vitexsoftware.list
RUN wget -O /etc/apt/trusted.gpg.d/vitexsoftware.gpg http://repo.vitexsoftware.cz/keyring.gpg

COPY flexibee-dev.spoje.conf /etc/flexibee/client.conf

RUN apt update ; apt -y install flexibee-reminder-sms

COPY msmtprc    /etc/msmtprc
COPY mail.ini   /etc/php/7.3/mods-available
RUN phpenmod mail

#EXPOSE 80

CMD ["cron", "-f"]

