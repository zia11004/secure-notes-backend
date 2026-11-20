#!/usr/bin/env bash
set -euo pipefail

mongo_uri="mongodb://${MONGO_USERNAME}:${MONGO_PASSWORD}@mongo:27017/admin?directConnection=true"

for attempt in {1..30}; do
  if mongosh "$mongo_uri" --quiet --eval 'db.adminCommand({ ping: 1 })' >/dev/null 2>&1; then
    break
  fi
  sleep 2
done

mongosh "$mongo_uri" --quiet --eval '
  try {
    rs.status();
  } catch (error) {
    if (error.codeName !== "NotYetInitialized") throw error;
    rs.initiate({ _id: "rs0", members: [{ _id: 0, host: "mongo:27017" }] });
  }
'

for attempt in {1..30}; do
  if mongosh "$mongo_uri" --quiet --eval 'if (!db.hello().isWritablePrimary) quit(1)' >/dev/null; then
    mongosh "$mongo_uri" --quiet --eval '
      const appDb = db.getSiblingDB("secure_notes");
      if (!appDb.getUser(process.env.MONGO_APP_USERNAME)) {
        appDb.createUser({
          user: process.env.MONGO_APP_USERNAME,
          pwd: process.env.MONGO_APP_PASSWORD,
          roles: [{ role: "readWrite", db: "secure_notes" }],
        });
      }
    '
    echo 'MongoDB replica set and app user are ready.'
    exit 0
  fi
  sleep 2
done

echo 'MongoDB replica set did not become writable.' >&2
exit 1
