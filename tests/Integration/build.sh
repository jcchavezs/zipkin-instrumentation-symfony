#!/usr/bin/env bash

APP_FOLDER=test-app
SYMFONY_VERSION=$1

rm -rf ${APP_FOLDER}

composer create-project symfony/website-skeleton:${SYMFONY_VERSION} ${APP_FOLDER} || exit 1
cd ${APP_FOLDER}
mv composer.json composer.json.dist
cat composer.json.dist \
| jq '.scripts["sync"] = ["rsync -arv --exclude=.git --exclude=tests/Integration --exclude=composer.lock --exclude=vendor ../../../ .zipkin-instrumentation-symfony"]' \
| jq '.scripts["pre-install-cmd"] = ["@sync"]' \
| jq '.scripts["pre-update-cmd"] = ["@sync"]' \
| jq '.require["jcchavezs/zipkin-instrumentation-symfony"] = "dev-symfony-4-compatibility"' \
| jq '.repositories = [{"type": "vcs","url": "https://github.com/dimonchuk/zipkin-instrumentation-symfony.git"}]' > composer.json

rm composer.lock

composer require symfony/web-server-bundle --dev

cp ../tracing.yaml ./config/tracing.yaml
cp ../HealthController.php ./src/Controller

mv ./config/services.yaml ./config/services.yaml.dist
echo "imports: [{ resource: tracing.yaml }]" > ./config/services.yaml
cat ./config/services.yaml.dist >> ./config/services.yaml

