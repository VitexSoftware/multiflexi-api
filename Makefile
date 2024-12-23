# vim: set tabstop=8 softtabstop=8 noexpandtab:
.PHONY: help
help: ## Displays this list of targets with descriptions
	@grep -E '^[a-zA-Z0-9_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}'

.PHONY: static-code-analysis
static-code-analysis: vendor ## Runs a static code analysis with phpstan/phpstan
	vendor/bin/phpstan analyse --configuration=phpstan-default.neon.dist --memory-limit=-1

.PHONY: static-code-analysis-baseline
static-code-analysis-baseline: check-symfony vendor ## Generates a baseline for static code analysis with phpstan/phpstan
	vendor/bin/phpstan analyze --configuration=phpstan-default.neon.dist --generate-baseline=phpstan-default-baseline.neon --memory-limit=-1

.PHONY: tests
tests: vendor
	vendor/bin/phpunit tests

.PHONY: vendor
vendor: composer.json composer.lock ## Installs composer dependencies
	composer install

.PHONY: cs
cs: ## Update Coding Standards
	composer update -d ~/Projects/Multi/multiflexi-server
	~/Projects/Multi/multiflexi-server/vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --diff --verbose

clean: ## Removes all generated files
	rm -rf vendor
	rm -f composer.lock
	rm -f README.md
	rm -rf lib docs
	rm -rf client server

#SPECURI=https://api.swaggerhub.com/apis/VitexSoftware/MultiFlexi/1.0.0/swagger.yaml

prepare:
	npm install --save-dev --save-exact prettier

server:
	npx openapi-generator-cli generate -i openapi-schema.yaml -g php-slim4 -c server.yaml -o ~/Projects/Multi/multiflexi-server; cd ~/Projects/Multi/multiflexi-server; make cs

client:
	npx  openapi-generator-cli generate -i openapi-schema.yaml -g php -o client

frontend:
	npx openapi-generator-cli generate -i openapi-schema.yaml -g typescript-angular -o frontend

slim4help:
	openapi-generator-cli config-help -g php-slim4
