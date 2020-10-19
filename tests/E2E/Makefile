SYMFONY_VERSION ?= 4.4
LIBRARY_BRANCH ?= master

build: ## Builds the symfony app
	@./build.sh $(SYMFONY_VERSION) $(LIBRARY_BRANCH) $(SAMPLER)

test: ## Runs e2e tests
	@./test.sh

run-app: ## Runs symfony app
	@echo "\nRun curl -i http://127.0.0.1:8002/_health to check the status of the app.\n"
	php ./test-app/bin/console server:run 8002

run-zipkin: ## Runs zipkin server
	docker run -d -p 9411:9411 openzipkin/zipkin

clean: ## Cleans build
	rm -rf test-app
