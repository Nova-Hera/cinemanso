#!/usr/bin/env bash
# Runs on the server, invoked by .github/workflows/deploy.yml after the release tarball
# has been uploaded to /srv/docker/cinemanso/release.tar.gz.
set -euo pipefail

cd /srv/docker/cinemanso

sudo rm -rf app.new
mkdir app.new
tar -xzf release.tar.gz -C app.new
rm -f release.tar.gz

# public/storage -> uploaded files (the real directory is mounted from shared/storage).
rm -rf app.new/public/storage
ln -sfn ../storage/app/public app.new/public/storage

# bootstrap/cache is written by the container user (www-data, uid 33).
sudo chown -R 33:33 app.new/bootstrap/cache

# Swap the code directory; storage/ and .env live in shared/ and are mounted into the container.
sudo rm -rf app.old
[ -d app ] && mv app app.old
mv app.new app

# --pull: always rebuild on the latest base image so PHP security releases are picked up.
sudo docker compose build --pull
sudo docker compose up -d --remove-orphans
# The bind mount points at the new inode only after the container is recreated.
sudo docker compose up -d --force-recreate app

for i in $(seq 1 30); do
  if sudo docker exec cinemanso php artisan --version >/dev/null 2>&1; then break; fi
  sleep 2
done

sudo docker exec cinemanso php artisan migrate --force
sudo docker exec cinemanso php artisan optimize:clear
sudo docker exec cinemanso php artisan optimize

sudo rm -rf app.old
echo "deploy ok"
