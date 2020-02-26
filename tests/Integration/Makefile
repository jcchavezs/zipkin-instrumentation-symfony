SYMFONY_VERSION ?= dev-master
LIBRARY_BRANCH ?= 3.0

build: ## Builds the symfony app
	./build.sh $(SYMFONY_VERSION) $(LIBRARY_BRANCH) $(SAMPLER)

run-app: ## Runs symfony app
	php ./test-app/bin/console server:run --port=8002

run-zipkin: ## Runs zipkin server
	docker run -d -p 9411:9411 openzipkin/zipkin
