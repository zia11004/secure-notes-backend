#!/usr/bin/env bash
set -euo pipefail

backup_directory=/var/backups/note-taking-app/mongodb
timestamp=$(date -u +%Y%m%dT%H%M%SZ)

install -d -o root -g root -m 0700 "$backup_directory"
umask 077
mongodump --config=/etc/note-taking-app/mongodump.yml \
    --archive="$backup_directory/secure-notes-${timestamp}.archive.gz" --gzip
find "$backup_directory" -type f -name 'secure-notes-*.archive.gz' -mtime +7 -delete
