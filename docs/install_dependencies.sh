#!/bin/sh

rm -rf extlib
mkdir -p extlib

# php-oauth

# we only use the Config, Logger and some Http options!
(
cd extlib
git clone https://github.com/fkooman/php-oauth.git
)
