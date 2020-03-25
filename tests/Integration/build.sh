#!/usr/bin/env bash

APP_FOLDER=test-app
SYMFONY_VERSION=${1:-dev-master}
LIBRARY_BRANCH=dev-${2:-master}
SAMPLER=${3:-default}
DEFAULT_COMPOSER_RUNNER="$(which php) -d memory_limit=-1 $(which composer)"
COMPOSER_RUNNER=${COMPOSER_RUNNER:-${DEFAULT_COMPOSER_RUNNER}}

# Deletes old executions of the build
rm -rf ${APP_FOLDER}

${COMPOSER_RUNNER} create-project symfony/website-skeleton:^${SYMFONY_VERSION} ${APP_FOLDER} || exit 1
cd ${APP_FOLDER}

# Includes zipkin-instrumentation-symfony to the composer.json of the app
mv composer.json composer.json.dist
cat composer.json.dist \
| jq '. + {"minimum-stability": "dev"}' \
| jq '. + {"prefer-stable": true}' \
| jq '.scripts["sync"] = ["rsync -arv --exclude=.git --exclude=tests/Integration --exclude=composer.lock --exclude=vendor ../../../. ./.zipkin-instrumentation-symfony"]' \
| jq '.scripts["pre-install-cmd"] = ["@sync"]' \
| jq '.scripts["pre-update-cmd"] = ["@sync"]' \
| jq '.require["jcchavezs/zipkin-instrumentation-symfony"] = "*"' \
| jq '.repositories = [{"type": "path","url": "./.zipkin-instrumentation-symfony","options": {"symlink": true}}]' > composer.json

echo "cat composer.json"
cat composer.json

rm composer.lock
composer sync

echo "Installing web-server-bundle"
# web-server-bundle:4.4 supports ^3.4, ^4.0 and ^5.0 (see https://github.com/symfony/web-server-bundle/blob/4.4/composer.json#L23)
${COMPOSER_RUNNER} require symfony/web-server-bundle:^4.4 --dev

# includes configuration files to run the middleware in the app
cp ../tracing.${SAMPLER}.yaml ./config/tracing.yaml
cp ../HealthController.php ./src/Controller
mv ./config/services.yaml ./config/services.yaml.dist
echo "imports: [{ resource: tracing.yaml }]" > ./config/services.yaml
cat ./config/services.yaml.dist >> ./config/services.yaml
