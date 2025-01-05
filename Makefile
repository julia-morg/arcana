SHELL := /bin/bash

help: # show help
	@grep -E '^(Makefile:)?[a-zA-Z_-]+:.*?# .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":[^:]*?# "}; {sub(/^Makefile:/, "", $$1); if (length($$1) > 30) {printf "\033[36m%s\033[0m\n%31s%s\n", $$1, "", $$2} else {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}}'

up: # up all
	docker compose down
	docker compose build
	docker compose up -d

