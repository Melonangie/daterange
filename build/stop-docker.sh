#!/bin/sh

# -------------------------------------------------
# File: stop-docker.sh
#
# Description: Stops Docker-composer
#
# History: 8-AUG-19 - Created
#
# -------------------------------------------------

echo  "\n(INFO) $(date +"%d/%m/%y %H:%M") - Stops docker-composer\n"
docker-compose stop
echo "Y" | docker-compose rm -v

echo "\n(INFO) $(date +"%d/%m/%y %H:%M") - Stops containers\n"
docker stop $(docker ps -a -q)
docker rm $(docker ps -a -q)

echo "\n(INFO) $(date +"%d/%m/%y %H:%M") - Deletes created resources\n"
echo "y" | docker system prune -a
echo "y" | docker volume prune
rm -fr mysql/db_data
rm -fr mysql/db_mysql_data
#sudo rm -fr mysql/db_data
#sudo rm -fr mysql/db_mysql_data

echo "\n(INFO) $(date +"%d/%m/%y %H:%M") - Deletes secrets\n"
docker secret rm db_mysql_password db_mysql_root_password
rm ../db_mysql_password
rm ../db_mysql_root_password

echo "\n(INFO) $(date +"%d/%m/%y %H:%M") - List of running conteiners\n"
docker ps -a
