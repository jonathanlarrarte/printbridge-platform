#!/usr/bin/env bash
# Rebuilds the API reference docs from the live OpenAPI spec and deploys the
# static site to /var/www/developers, where Nginx can actually read it --
# www-data has no traversal rights into /root (mode 700), so the build/
# output living inside the repo checkout can't be served with `alias`
# directly.
set -euo pipefail
cd "$(dirname "$0")"

php ../artisan scramble:clear
rm -rf docs/reference
npx docusaurus gen-api-docs all
npm run build

rsync -a --delete build/ /var/www/developers/
chown -R www-data:www-data /var/www/developers

echo "Deployed to /var/www/developers -- live at https://impryxa.vekronis.com/developers/"
