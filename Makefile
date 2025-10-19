SHELL := /bin/bash

.PHONY: up down logs deploy sh fortunes-prod

help: # show help
	@grep -E '^(Makefile:)?[a-zA-Z_-]+:.*?# .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":[^:]*?# "}; {sub(/^Makefile:/, "", $$1); if (length($$1) > 30) {printf "\033[36m%s\033[0m\n%31s%s\n", $$1, "", $$2} else {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}}'

deploy: # deploy prod
	cd ansible &&  ansible-playbook playbooks/deploy.yml --inventory=inventory.yml

fortunes-prod: # fill fortunes on production
	ssh -i ~/.ssh/my.pem ubuntu@ec2-13-60-197-221.eu-north-1.compute.amazonaws.com "docker exec -i arcana php artisan app:fill-fortunes"

memes-prod: # fill memes on production
	ssh -i ~/.ssh/my.pem ubuntu@ec2-13-60-197-221.eu-north-1.compute.amazonaws.com "docker exec -i arcana php artisan app:fill-memes"

set-commands-prod: # set tg bot commands on prod
	ssh -i ~/.ssh/my.pem ubuntu@ec2-13-60-197-221.eu-north-1.compute.amazonaws.com "docker exec -i arcana php artisan app:set-commands"

up: # up local
	docker compose up -d --build

down: # down local stack
	docker compose down

logs: # follow logs
	docker compose logs -f

sh: # Login into arcana container
	docker compose exec -it arcana bash || [ $$? -eq 130 ]
