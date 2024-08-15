#!/usr/bin/env bash

TROVE_HOST=${TROVE_REMOTE_HOST:-${TROVE_HOST:-"$(grep TROVE_REMOTE_HOST= /app/.env | cut -c 19-)"}}
TROVE_PORT=4721
TROVE_PORT_HEX=1271
# Also includes the rem_address column field to ensure that we're
# checking the local_address column
TROVE_PORT_REGEX="0100007F:$TROVE_PORT_HEX\s[0-9A-F]+:[0-9A-F]+"

if [ "$1" == "serve" ] || [ -z "$1" ]; then
    if [ -d "/ssh" ] && [ -z "$SKIP_PORT_CHECK" ]; then
        echo "Open a new terminal and start the SSH connections."
        echo "See README for more information."
        echo
        echo "Waiting for TROVE SQL port ($TROVE_PORT) to open..."
        echo "(Set the SKIP_PORT_CHECK environment variable to skip this.)"
        trap "exit" SIGINT SIGTERM
        while [ ! "$(grep -Pc "$TROVE_PORT_REGEX" /proc/net/tcp)" -ge 1 ]; do
            # Waiting...
            sleep 1
        done
        trap SIGINT SIGTERM
        echo "Port ($TROVE_PORT) opened, serving..."
    fi
    exec sudo -u copypatrol-ssh symfony serve
elif [ "$1" == "ssh" ]; then
    # Check for /ssh
    if [ ! -d "/ssh" ]; then
        # shellcheck disable=SC2016
        echo '/ssh directory not found. Mount your $HOME/.ssh folder to /ssh.'
        exit 1
    fi

    # Copy /ssh
    rm -rf /home/copypatrol-ssh/.ssh
    cp -r /ssh /home/copypatrol-ssh/.ssh

    # Fix permissions
    chmod 700 -R /home/copypatrol-ssh/.ssh
    chown copypatrol-ssh:copypatrol-ssh -R /home/copypatrol-ssh/.ssh

    # Start SSH
    # shellcheck disable=SC2086
    exec sudo -u copypatrol-ssh symfony console toolforge:ssh --trove="$TROVE_HOST" -b 127.0.0.1 ${*:2}
else
    exec $@
fi
