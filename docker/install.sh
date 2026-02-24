#!/bin/bash

# Ensure we are in the docker directory
cd "$(dirname "$0")"

echo "Configuring phpBB..."

# Check if phpBB container is running
if ! docker compose ps --services --filter "status=running" | grep -q "phpbb"; then
    echo "Error: phpBB container is not running."
    echo "Please start the containers first with: docker compose up -d"
    exit 1
fi


echo "Installing Composer dependencies..."
docker compose exec phpbb php ../composer.phar install

echo "Backing up and removing existing config.php..."
docker compose exec phpbb sh -c 'if [ -f config.php ]; then cp --backup=numbered config.php config.php.bak && rm config.php; fi'

echo "Installing phpBB..."
docker compose exec phpbb php install/phpbbcli.php install install-config.yml

echo "Configuring development environment..."

# Add DEBUG mode
docker compose exec phpbb sh -c "echo \"@define('DEBUG', true);\" >> config.php"

# Change environment to development
docker compose exec phpbb sed -i '/^.*PHPBB_ENVIRONMENT.*$/s/production/development/' config.php

# PHP Configuration
PHP_INI="/usr/local/etc/php/php.ini"

# Check if php.ini exists, if not copy development template
docker compose exec phpbb sh -c "if [ ! -f $PHP_INI ]; then cp /usr/local/etc/php/php.ini-development $PHP_INI; fi"

echo "Setting memory_limit to 1024M..."
docker compose exec phpbb sed -i 's/memory_limit = .*/memory_limit = 1024M/' "$PHP_INI"

# Restart apache to apply php.ini changes
echo "Restarting Apache..."
docker compose exec phpbb apache2ctl graceful

echo "---------------------------------------------------"
echo "Installation complete!"
echo "Your board is ready at http://localhost:8080"
echo "---------------------------------------------------"
