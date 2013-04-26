#!/bin/sh

rm -rf extlib
mkdir -p extlib

(
cd extlib
git clone https://github.com/fkooman/php-rest-service.git
git clone https://github.com/fkooman/php-oauth-lib-rs.git
)
