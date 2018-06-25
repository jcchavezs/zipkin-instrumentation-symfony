build: ## Builds the symfony app
	./build.sh $(SYMFONY_VERSION)

run-app: ## Runs symfony app
	php ./test-app/bin/console server:run

run-zipkin: ## Runs zipkin server
	docker run -d -p 9411:9411 openzipkin/zipkin
