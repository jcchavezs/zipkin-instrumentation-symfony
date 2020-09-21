SYMFONY_VERSION ?= 4.4
LIBRARY_BRANCH ?= master

build: ## Builds the symfony app
	./build.sh $(SYMFONY_VERSION) $(LIBRARY_BRANCH) $(SAMPLER)

run-app: ## Runs symfony app
	@echo "\nRun curl -i http://127.0.0.1:8002/_health to check the status of the app.\n"
	php ./test-app/bin/console server:run 8002

run-zipkin: ## Runs zipkin server
	docker run -d -p 9411:9411 openzipkin/zipkin

run-rabbitmq: ## Runs rabbitmq server
	docker run -d -p 5672:5672 -p 15672:15672 rabbitmq:3.8-management-alpine

run-consumer: ## Runs messenger consumer
	php ./test-app/bin/console messenger:consume

clean:
	rm -rf test-app
