ARG PHP_VERSION="8.2"

FROM php:${PHP_VERSION} AS base
# ===============================================
#  BASE IMAGE
#  Used to set up dependencies and other things required in both the vendor and main stages.
# ===============================================
ARG PHP_EXTENSIONS="apcu intl pdo_mysql"
ENV COPYPATROL_ROOT=/app
ENV DEBIAN_FRONTEND=noninteractive
WORKDIR ${COPYPATROL_ROOT}

# Add mlocati/php-extension-installer
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

# Install required PHP extensions
RUN install-php-extensions $PHP_EXTENSIONS

# Add Composer
RUN curl https://getcomposer.org/installer | php && \
    mv composer.phar /usr/local/bin/composer

# Add copypatrol user for user-level command execution
RUN useradd -mrs /bin/bash copypatrol

# Set permission on app folder
RUN chown copypatrol:copypatrol ${COPYPATROL_ROOT}

FROM base AS vendor
# ===============================================
#  COMPOSER INSTALL
#  Post-install scripts are run in a later stage.
# ===============================================

# Install unzip for safety
RUN apt-get clean && \
    apt-get update && \
    DEBIAN_FRONTEND=noninteractive && \
    apt-get install --yes unzip && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*

# Copy composer lock file, Symfony config, and bin/ folder
COPY composer.* ${COPYPATROL_ROOT}

# Set permissions for app directory
RUN chown -R copypatrol:copypatrol ${COPYPATROL_ROOT} && \
    chmod -R 755 ${COPYPATROL_ROOT}

USER copypatrol
RUN composer install --no-scripts

FROM base AS development
# ===============================================
#  DEVELOPMENT IMAGE
# ===============================================
ENV COPYPATROL_ROOT=/app
WORKDIR ${COPYPATROL_ROOT}

RUN install-php-extensions xdebug

# Add XDebug configuration
RUN echo -e "error_reporting=E_ALL\\n\
\\n\
[xdebug]\\n\
xdebug.mode=develop,coverage,debug,profile\\n\
xdebug.start_with_request=yes\\n\
xdebug.log=/tmp/xdebug.log\\n\
xdebug.log_level=0\\n\
xdebug.client_host=host.docker.internal\\n" >> /usr/local/etc/php/conf.d/xdebug.ini

# Install OpenSSH (client), sudo, Symfony CLI, and unzip
RUN curl -1sLf 'https://dl.cloudsmith.io/public/symfony/stable/setup.deb.sh' | bash && \
    apt-get update && \
    apt-get install --yes openssh-client sudo symfony-cli unzip && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*

# Add copypatrol-ssh user for SSH access
RUN useradd -mrs /bin/bash copypatrol-ssh

# Copy files
COPY --chown=copypatrol:copypatrol --chmod=755 . ${COPYPATROL_ROOT}

# Copy vendor files
COPY --from=vendor --chown=copypatrol:copypatrol --chmod=755 \
    ${COPYPATROL_ROOT}/vendor ${COPYPATROL_ROOT}/vendor/

# Run post-install scripts (which we skipped in the vendor container)
RUN sudo -u copypatrol composer run-script post-install-cmd

# Copy the entrypoint file, convert from CRLF to LF (if not
# already LF), set permissions, and link
RUN tr -d '\015' </app/docker/docker-entrypoint.sh >/docker-entrypoint.sh && \
    chmod 700 /docker-entrypoint.sh && \
    ln -s /docker-entrypoint.sh /usr/local/bin/start

# Set start command (development entrypoint)
ENTRYPOINT [ "/docker-entrypoint.sh" ]

FROM base AS production
# ===============================================
#  PRODUCTION IMAGE
# ===============================================
ENV COPYPATROL_ROOT=/app
WORKDIR ${COPYPATROL_ROOT}

# Install Symfony CLI
RUN curl -1sLf 'https://dl.cloudsmith.io/public/symfony/stable/setup.deb.sh' | bash && \
    apt-get update && \
    apt-get install --yes symfony-cli && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*

# Copy files
COPY --chown=copypatrol:copypatrol --chmod=755 . ${COPYPATROL_ROOT}

# Copy vendor files
COPY --from=vendor --chown=copypatrol:copypatrol --chmod=755 \
    ${COPYPATROL_ROOT}/vendor ${COPYPATROL_ROOT}/vendor/

# Switch to `copypatrol` user
USER copypatrol

# Run post-install scripts (which we skipped in the vendor stages)
RUN composer run-script post-install-cmd

# Set start command (symfony serve)
ENTRYPOINT [ "symfony" ]
CMD [ "serve" ]
