#!/bin/bash
# Fix permissions for uploads directory (volume mount overrides Dockerfile permissions)
chown -R www-data:www-data /var/www/html/app/uploads
chmod -R 775 /var/www/html/app/uploads

# Start Apache in foreground
apache2-foreground
