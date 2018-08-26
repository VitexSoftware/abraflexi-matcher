FROM php:7.2.7-cli-stretch
MAINTAINER Vítězslav Dvořák <info@vitexsoftware.cz>

RUN apt update
RUN apt-get update && apt-get install -my wget gnupg

RUN wget -O - http://v.s.cz/info@vitexsoftware.cz.gpg.key | apt-key add -
RUN echo deb http://v.s.cz/ stable main | tee /etc/apt/sources.list.d/vitexsoftware.list
RUN apt update


RUN apt-get update
RUN apt-get -y upgrade

RUN DEBIAN_FRONTEND=noninteractive apt-get -y install flexibee-matcher

