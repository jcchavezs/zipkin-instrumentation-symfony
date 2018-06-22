#!/usr/bin/env bash

APP_FOLDER=test-app
SYMFONY_VERSION=3.3

rm -rf ${APP_FOLDER}

composer create-project symfony/website-skeleton:${SYMFONY_VERSION} ${APP_FOLDER}
cd ${APP_FOLDER}
mv composer.json composer.json.dist
cat composer.json.dist \
| jq '.scripts["sync"] = ["rsync -arv --exclude=.git --exclude=example --exclude=composer.lock --exclude=vendor ../../../ .zipkin-instrumentation-symfony"]' \
| jq '.scripts["pre-install-cmd"] = ["@sync"]' \
| jq '.scripts["pre-update-cmd"] = ["@sync"]' \
| jq '.require["jcchavezs/zipkin-instrumentation-symfony"] = "dev-master"' \
| jq '.repositories = [{"type": "path","url": "./.zipkin-instrumentation-symfony/","options": {"symlink": true}}]' > composer.json

rm composer.lock

composer require symfony/web-server-bundle --dev

cp ../tracing.yaml ./config/tracing.yaml

mv ./config/services.yaml ./config/services.yaml.dist
echo "imports: [{ resource: tracing.yaml }]" > ./config/services.yaml
cat ./config/services.yaml.dist >> ./config/services.yaml

php bin/console server:run
