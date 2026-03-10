FROM dhi.io/nginx:1

# Pre-create writable directories with correct ownership for the nginx user
RUN mkdir -p /var/cache/nginx/client_temp \
    /var/cache/nginx/proxy_temp \
    /var/cache/nginx/fastcgi_temp \
    /var/cache/nginx/uwsgi_temp \
    /var/cache/nginx/scgi_temp \
    /var/log/nginx \
    /run \
    && chown -R nginx:nginx /var/cache/nginx /var/log/nginx /run
