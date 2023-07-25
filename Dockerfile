FROM docker-registry.tools.wmflabs.org/toolforge-php74-sssd-web:latest AS dependencies
# ===============================================
#  COMPOSER INSTALL
# ===============================================
ENV COPYPATROL_ROOT=/app
WORKDIR ${COPYPATROL_ROOT}

# Install unzip for safety
RUN apt update && apt install -y unzip

# Install dependencies
COPY composer.* ${COPYPATROL_ROOT}
RUN composer install

FROM docker-registry.tools.wmflabs.org/toolforge-php74-sssd-web:latest AS base
# ===============================================
#  BASE IMAGE
# ===============================================
ENV COPYPATROL_ROOT=/app
WORKDIR ${COPYPATROL_ROOT}

# == WORK ==

# Disable file error logging for Lighttpd (enables error logging to stderr)
RUN sed -i 's!server.errorlog!# server.errorlog!g' /etc/lighttpd/lighttpd.conf

# Enable required Lighttpd modules (rewrite, php)
RUN lighty-enable-mod fastcgi-php
RUN lighty-enable-mod rewrite

# add XDebug (if needed)
RUN apt-get clean && \
    apt-get update && \
    DEBIAN_FRONTEND=noninteractive && \
    apt-get install --yes php7.4-xdebug && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*

# Add rewrite rules
RUN echo 'url.rewrite-if-not-file += ( "^(/.*)" => "/index.php$0" )' >> /etc/lighttpd/conf-enabled/90-copypatrol.conf

## Only these two copy statements below actually matter. Everything before this was
## just to set up a Toolforge-like environment for local development.
# Copy vendor files
COPY --from=dependencies ${COPYPATROL_ROOT}/vendor ${COPYPATROL_ROOT}/vendor

# Copy files
COPY . ${COPYPATROL_ROOT}

# Symlink CopyPatrol public to document root
RUN rm -rf /var/www/html
RUN ln -s ${COPYPATROL_ROOT}/public /var/www/html

# Set start command (enable FastCGI and start lighttpd)
CMD [ "lighttpd", "-D", "-f", "/etc/lighttpd/lighttpd.conf" ]

FROM base as production
# ===============================================
#  PRODUCTION IMAGE
# ===============================================
RUN phpdismod xdebug

FROM base as development
# ===============================================
#  DEVELOPMENT IMAGE
# ===============================================
RUN echo -e "error_reporting=E_ALL\\n\
\\n\
[xdebug]\\n\
xdebug.remote_enable=1\\n\
xdebug.mode=develop,coverage,debug,profile\\n\
xdebug.start_with_request=yes\\n\
xdebug.log=/tmp/xdebug.log\\n\
xdebug.log_level=0\\n\
xdebug.remote_host=host.docker.internal\n\
# XDebug 3\\n\
xdebug.client_host=host.docker.internal\\n" >> /etc/php/7.4/mods-available/xdebug.ini
