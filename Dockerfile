FROM php:7.2.7-cli-stretch
MAINTAINER Vítězslav Dvořák <info@vitexsoftware.cz>

RUN apt update && apt-get install -my wget gnupg

RUN wget -O - http://v.s.cz/info@vitexsoftware.cz.gpg.key | apt-key add -
RUN echo deb http://v.s.cz/ stable main | tee /etc/apt/sources.list.d/vitexsoftware.list

ADD flexibee-matcher_*_all.deb /repo/flexibee-matcher.deb

RUN cd /repo ; dpkg-scanpackages . /dev/null | gzip -9c > Packages.gz 
RUN echo "deb [trusted=yes] file:///repo/ ./" > /etc/apt/sources.list.d/local.list

RUN apt update ; RUN DEBIAN_FRONTEND=noninteractive apt-get -y install flexibee-matcher

