SYMFONY_VERSION ?= 4.4
LIBRARY_BRANCH ?= master

build: ## Builds the symfony app
	./build.sh $(SYMFONY_VERSION) $(LIBRARY_BRANCH) $(SAMPLER)

run-app: ## Runs symfony app
	php ./test-app/bin/console server:run 8002

run-zipkin: ## Runs zipkin server
	docker run -d -p 9411:9411 openzipkin/zipkin

clean:
	rm -rf test-app
