#!/usr/bin/env bash

APP_FOLDER=test-app
SYMFONY_VERSION=${1:-dev-master}
LIBRARY_BRANCH=dev-${2:-master}
SAMPLER=${3:-default}
COMPOSER="$(which php) -d memory_limit=-1 /vendor/bin/composer"

# Deletes old executions of the build
rm -rf ${APP_FOLDER}

${COMPOSER} create-project symfony/website-skeleton:${SYMFONY_VERSION} ${APP_FOLDER} || exit 1
cd ${APP_FOLDER}

# Includes zipkin-instrumentation-symfony to the composer.json of the app
mv composer.json composer.json.dist
cat composer.json.dist \
| jq '.scripts["sync"] = ["rsync -arv --exclude=.git --exclude=tests/Integration --exclude=composer.lock --exclude=vendor ../../../. .zipkin-instrumentation-symfony"]' \
| jq '.scripts["pre-install-cmd"] = ["@sync"]' \
| jq '.scripts["pre-update-cmd"] = ["@sync"]' \
| jq '.require["jcchavezs/zipkin-instrumentation-symfony"] = "*"' \
| jq '.repositories = [{"type": "path","url": "./.zipkin-instrumentation-symfony/","options": {"symlink": true}}]' > composer.json

rm composer.lock

${COMPOSER} require symfony/web-server-bundle --dev

# includes configuration files to run the middleware in the app
cp ../tracing.${SAMPLER}.yaml ./config/tracing.yaml
cp ../HealthController.php ./src/Controller
mv ./config/services.yaml ./config/services.yaml.dist
echo "imports: [{ resource: tracing.yaml }]" > ./config/services.yaml
cat ./config/services.yaml.dist >> ./config/services.yaml
