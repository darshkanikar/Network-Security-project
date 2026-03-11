FROM php:8.4-apache@sha256:247e06ffe7f762cd7bdf144617acc42b922c6e05c7d2a2f788f5ccadfa4342b9

# Install dependencies and extensions
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libzip-dev \
    libpq-dev \
    zip \
    unzip \
    postgresql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_pgsql pgsql gd zip

# Enable Apache modules
RUN a2enmod rewrite headers

# Change Apache DocumentRoot to /var/www/html/app
ENV APACHE_DOCUMENT_ROOT /var/www/html/app
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}/!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Disable directory listing and allow .htaccess
RUN echo '<Directory /var/www/html/app>\n\
    Options -Indexes +FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
    </Directory>\n\
    <Directory /var/www/html/app/uploads>\n\
    # Prevent PHP execution in uploads directory\n\
    php_admin_flag engine off\n\
    Options -ExecCGI\n\
    RemoveHandler .php .phtml .php3 .php4 .php5\n\
    </Directory>' > /etc/apache2/conf-available/transactiwar.conf \
    && a2enconf transactiwar \
    && echo "ServerTokens Prod" >> /etc/apache2/conf-available/transactiwar.conf \
    && echo "ServerSignature Off" >> /etc/apache2/conf-available/transactiwar.conf

# --- NON-ROOT APACHE CONFIG ---
# Change Apache to listen on port 8080 instead of 80 (non-privileged)
RUN sed -i 's/Listen 80/Listen 8080/' /etc/apache2/ports.conf \
    && sed -i 's/<VirtualHost \*:80>/<VirtualHost *:8080>/' /etc/apache2/sites-available/*.conf

# Fix Apache PID and lock file locations for non-root
RUN mkdir -p /var/run/apache2 /var/lock/apache2 \
    && chown -R www-data:www-data /var/run/apache2 /var/lock/apache2 /var/log/apache2

# Harden PHP: disable error display and version exposure
RUN echo "display_errors = Off" > /usr/local/etc/php/conf.d/security.ini \
    && echo "expose_php = Off" >> /usr/local/etc/php/conf.d/security.ini \
    && echo "error_reporting = E_ALL" >> /usr/local/etc/php/conf.d/security.ini \
    && echo "log_errors = On" >> /usr/local/etc/php/conf.d/security.ini

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY ./ /var/www/html/

# Copy and make entrypoint executable
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Set permissions for uploads at build time (www-data owns it)
RUN chown -R www-data:www-data /var/www/html/app/uploads \
    && chmod -R 700 /var/www/html/app/uploads

# Expose non-privileged port
EXPOSE 8080

# Switch to non-root user
USER www-data

# Use custom entrypoint
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
