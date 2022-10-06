SYMFONY_VERSION ?= 4.4

build: ## Builds the symfony app
	@./build.sh $(SYMFONY_VERSION) $(SAMPLER)

test: ## Runs e2e tests
	@./test.sh

run-app: ## Runs symfony app
	@echo "\nRun curl -i http://127.0.0.1:8002/_health to check the status of the app.\n"
	php -S localhost:8002 -t ./test-app/public/ &

run-zipkin: ## Runs zipkin server
	docker run -d -p 9411:9411 openzipkin/zipkin
	@echo "\nCheck your traces at http://localhost:9411/zipkin/\n"

clean: ## Cleans build
	rm -rf test-app
