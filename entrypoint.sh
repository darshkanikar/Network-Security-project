#!/bin/bash
# Entrypoint runs as www-data (non-root)
# Permissions are set at build time in Dockerfile

# Start Apache in foreground
exec apache2-foreground
