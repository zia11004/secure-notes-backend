#!/usr/bin/env bash
set -euo pipefail

if [[ ! -f /keyfile/key ]]; then
  dd if=/dev/urandom bs=756 count=1 status=none | base64 > /keyfile/key
  chown mongodb:mongodb /keyfile/key
  chmod 400 /keyfile/key
fi
