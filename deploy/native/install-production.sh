#!/usr/bin/env bash
set -euo pipefail

app_root=/var/www/note-taking-app
api_host=note-api.51-75-162-34.sslip.io
frontend_origin=https://secure-notes-zia.netlify.app
deploy_dir=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)

if [[ ! -s /etc/mongodb-keyfile ]]; then
    umask 077
    openssl rand -base64 756 > /etc/mongodb-keyfile
    chown mongod:mongod /etc/mongodb-keyfile
    chmod 0400 /etc/mongodb-keyfile
fi

install -m 0644 "$deploy_dir/mongod.conf" /etc/mongod.conf
restorecon -v /etc/mongod.conf /etc/mongodb-keyfile || true
systemctl enable --now mongod

mongo_admin_file=/root/.note-taking-app-mongodb-admin
mongo_app_file=/root/.note-taking-app-mongodb-app
if [[ ! -s "$mongo_admin_file" ]]; then
    for attempt in {1..30}; do
        if mongosh --quiet --eval 'db.adminCommand({ping:1}).ok' 2>/dev/null | grep -qx 1; then
            break
        fi
        sleep 1
    done

    if ! mongosh --quiet --eval 'try { rs.status().ok } catch (error) { 0 }' 2>/dev/null | grep -qx 1; then
        mongosh --quiet --eval 'rs.initiate({_id:"rs0",members:[{_id:0,host:"127.0.0.1:27017"}]})'
    fi

    for attempt in {1..30}; do
        if mongosh --quiet --eval 'try { rs.status().myState } catch (error) { 0 }' 2>/dev/null | grep -qx 1; then
            break
        fi
        sleep 1
    done

    mongo_admin_password=$(openssl rand -hex 24)
    mongo_app_password=$(openssl rand -hex 24)
    MONGO_ADMIN_PASSWORD="$mongo_admin_password" MONGO_APP_PASSWORD="$mongo_app_password" mongosh --quiet --eval '
        const admin = db.getSiblingDB("admin");
        admin.createUser({user:"notesAdmin",pwd:process.env.MONGO_ADMIN_PASSWORD,roles:[{role:"root",db:"admin"}]});
        const authentication = admin.auth("notesAdmin",process.env.MONGO_ADMIN_PASSWORD);
        if ((authentication.ok ?? authentication) !== 1) throw new Error("Admin authentication failed");
        db.getSiblingDB("secure_notes").createUser({user:"notesApp",pwd:process.env.MONGO_APP_PASSWORD,roles:[{role:"readWrite",db:"secure_notes"}]});
    '
    printf '%s\n' "$mongo_admin_password" > "$mongo_admin_file"
    printf '%s\n' "$mongo_app_password" > "$mongo_app_file"
    chmod 0600 "$mongo_admin_file" "$mongo_app_file"
fi

mongo_app_password=$(<"$mongo_app_file")
service_token=$(sed -n 's/^INTERNAL_SERVICE_TOKEN=//p' /etc/note-taking-app/data-service.env 2>/dev/null || true)
jwt_secret=$(sed -n 's/^JWT_SECRET=//p' "$app_root/api/.env" 2>/dev/null || true)
[[ -n "$service_token" ]] || service_token=$(openssl rand -hex 32)
[[ -n "$jwt_secret" ]] || jwt_secret=$(openssl rand -hex 32)

install -d -o root -g noteapp -m 0750 /etc/note-taking-app
cat > /etc/note-taking-app/data-service.env <<EOF
NODE_ENV=production
HOST=127.0.0.1
PORT=3001
MONGODB_URI=mongodb://notesApp:${mongo_app_password}@127.0.0.1:27017/secure_notes?authSource=secure_notes&replicaSet=rs0
INTERNAL_SERVICE_TOKEN=${service_token}
EOF

cat > /etc/note-taking-app/mongodump.yml <<EOF
uri: "mongodb://notesApp@127.0.0.1:27017/secure_notes?authSource=secure_notes&replicaSet=rs0"
password: "${mongo_app_password}"
EOF

cp /etc/note-taking-app/data-service.env "$app_root/data-service/.env"

cat > "$app_root/api/.env" <<EOF
APP_ENV=production
APP_DEBUG=false
APP_URL=https://${api_host}
JWT_SECRET=${jwt_secret}
JWT_ISSUER=secure-notes-production
JWT_TTL_MINUTES=60
DATA_SERVICE_URL=http://127.0.0.1:3001
DATA_SERVICE_TOKEN=${service_token}
FRONTEND_ORIGINS=${frontend_origin}
RATE_LIMIT_PER_MINUTE=120
EOF

chown root:noteapp /etc/note-taking-app/data-service.env "$app_root/api/.env" "$app_root/data-service/.env"
chown root:root /etc/note-taking-app/mongodump.yml
chmod 0640 /etc/note-taking-app/data-service.env "$app_root/api/.env" "$app_root/data-service/.env"
chmod 0600 /etc/note-taking-app/mongodump.yml
chown -R root:noteapp "$app_root"
chown -R noteapp:noteapp "$app_root/api/var"
chmod 0755 "$app_root" "$app_root/api" "$app_root/api/public"
chmod 0750 "$app_root/data-service"

install -m 0644 "$deploy_dir/note-taking-data.service" /etc/systemd/system/note-taking-data.service
install -m 0644 "$deploy_dir/note-taking-app.pool.conf" /etc/opt/remi/php85/php-fpm.d/note-taking-app.conf
install -m 0644 "$deploy_dir/note-api.nginx.conf" /etc/nginx/conf.d/note-api.conf
install -m 0750 "$deploy_dir/note-taking-backup.sh" /usr/local/sbin/note-taking-backup
install -m 0644 "$deploy_dir/note-taking-backup.service" /etc/systemd/system/note-taking-backup.service
install -m 0644 "$deploy_dir/note-taking-backup.timer" /etc/systemd/system/note-taking-backup.timer
install -d -o root -g root -m 0700 /var/backups/note-taking-app/mongodb

if command -v semanage >/dev/null 2>&1; then
    semanage fcontext -a -t httpd_sys_content_t "$app_root/api(/.*)?" 2>/dev/null || \
        semanage fcontext -m -t httpd_sys_content_t "$app_root/api(/.*)?"
    semanage fcontext -a -t httpd_sys_rw_content_t "$app_root/api/var(/.*)?" 2>/dev/null || \
        semanage fcontext -m -t httpd_sys_rw_content_t "$app_root/api/var(/.*)?"
fi
restorecon -R "$app_root/api"
setsebool -P httpd_can_network_connect 1

/opt/remi/php85/root/usr/sbin/php-fpm -t
nginx -t
systemctl daemon-reload
systemctl enable --now note-taking-data
systemctl enable --now note-taking-backup.timer
systemctl restart php85-php-fpm
systemctl reload nginx

sudo -u noteapp -H bash -c "cd '$app_root/data-service' && npm run indexes"
systemctl start note-taking-backup.service
curl --fail --silent --show-error --resolve "${api_host}:80:127.0.0.1" "http://${api_host}/api/ready"

certbot --nginx -d "$api_host" --non-interactive --agree-tos --register-unsafely-without-email --redirect
nginx -t
systemctl reload nginx
