#!/bin/sh

# -------------------------------------------------
# File: run-docker.sh
#
# Description: Starts Docker-composer
#
# History: 8-AUG-19 - Created
#
# -------------------------------------------------

echo "\n(INFO) $(date +"%d/%m/%y %H:%M") - Creating passwords files\n"
openssl rand -base64 25 > ../db_mysql_password
openssl rand -base64 25 > ../db_mysql_root_password
ls -la .. | grep mysql

echo "\n(INFO) $(date +"%d/%m/%y %H:%M") - List of running conteiners\n"
docker ps -a

echo "\n(INFO) $(date +"%d/%m/%y %H:%M") - Running docker-composer\n"
docker-compose up --build
