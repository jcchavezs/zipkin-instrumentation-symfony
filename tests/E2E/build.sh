#!/usr/bin/env bash

APP_FOLDER=test-app
SYMFONY_VERSION=${1:-dev-master}
SAMPLER=${2:-default}
DEFAULT_COMPOSER_RUNNER="$(which php) -d memory_limit=-1 $(which composer)"
COMPOSER_RUNNER=${COMPOSER_RUNNER:-${DEFAULT_COMPOSER_RUNNER}}

# Deletes old executions of the build
rm -rf ${APP_FOLDER}

${COMPOSER_RUNNER} create-project --prefer-dist --no-interaction symfony/website-skeleton:^${SYMFONY_VERSION} ${APP_FOLDER}
cd ${APP_FOLDER}

pwd

mv composer.json composer.json.dist
cat composer.json.dist \
| jq '. + {"minimum-stability": "dev"}' \
| jq '. + {"prefer-stable": true}' \
| jq '.require["jcchavezs/zipkin-instrumentation-symfony"] = "*"' \
| jq '.repositories = [{"type": "path","url": "../../../../zipkin-instrumentation-symfony"}]' > composer.json
cat composer.json
${COMPOSER_RUNNER} update --prefer-dist --no-interaction --no-ansi

# includes configuration files to run the kernel listener in the app
cp -r ./application/* ${APP_FOLDER}
cp ../tracing.${SAMPLER}.yaml ./config/tracing.yaml
mv ./config/services.yaml ./config/services.yaml.dist
echo "imports: [{ resource: tracing.yaml }]" > ./config/services.yaml
cat ./config/services.yaml.dist >> ./config/services.yaml
php bin/console cache:warmup
