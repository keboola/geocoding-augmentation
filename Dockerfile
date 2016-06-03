FROM keboola/base-php70
MAINTAINER Jakub Matejka <jakub@keboola.com>

WORKDIR /tmp

ADD . /code
WORKDIR /code

RUN composer install --no-interaction

ENTRYPOINT php ./src/run.php --data=/data