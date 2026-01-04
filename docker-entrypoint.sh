#!/bin/bash
# Laravel Sail-style entrypoint for WordPress
# Creates a user inside the container matching the host's UID/GID

set -e

# Get UID/GID from environment, default to 1000 if not set
WWW_USER=${WWW_USER:-1000}
WWW_GROUP=${WWW_GROUP:-1000}

# Create group if it doesn't exist
if ! getent group "$WWW_GROUP" >/dev/null 2>&1; then
    groupadd -g "$WWW_GROUP" wwwgroup
fi

# Create user if it doesn't exist
if ! id -u "$WWW_USER" >/dev/null 2>&1; then
    useradd -u "$WWW_USER" -g "$WWW_GROUP" -m -s /bin/bash wwwuser
fi

# Fix permissions for wp-content
chown -R "$WWW_USER:$WWW_GROUP" /var/www/html/wp-content

# Execute the original WordPress entrypoint as the created user
exec runuser -u "$WWW_USER" -- "/usr/local/bin/docker-entrypoint.sh" "$@"
