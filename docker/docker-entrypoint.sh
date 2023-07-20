#!/usr/bin/env bash

TOOLSDB_PORT=4720
TOOLSDB_PORT_HEX=1270
# Also includes the rem_address column field to ensure that we're
# checking the local_address column
TOOLSDB_PORT_REGEX="0100007F:$TOOLSDB_PORT_HEX\s[0-9A-F]+:[0-9A-F]+"

if [ $1 == "serve" ] || [ -z $1 ]; then
    if [ -z $SKIP_PORT_CHECK ]; then
        echo "Open a new terminal and start the SSH connections."
        echo "See README for more information."
        echo
        echo "Waiting for ToolsDB SQL port ($TOOLSDB_PORT) to open..."
        echo "(Set the SKIP_PORT_CHECK environment variable to skip this.)"
        trap "exit" SIGINT SIGTERM
        while [ ! "$(grep -Pc "$TOOLSDB_PORT_REGEX" /proc/net/tcp)" -ge 1 ]; do
            # Waiting...
            sleep 1
        done
        trap SIGINT SIGTERM
        echo "Port ($TOOLSDB_PORT) opened, serving..."
    fi
    exec symfony serve
elif [ $1 == "ssh" ]; then
    # Check for /ssh
    if [ ! -d "/ssh" ]; then
        echo "/ssh directory not found. Mount your $HOME/.ssh folder to /ssh."
        exit 1
    fi

    # Copy /ssh
    cp -r /ssh /root/.ssh

    # Fix permissions
    chmod 700 -R /root/.ssh

    # Check for a username provided in the SSH config
    username=$(ssh -G login.toolforge.org | grep "user " | sed 's/^user //' -)

    # Start SSH
    symfony console toolforge:ssh --toolsdb -b 127.0.0.1 $username ${@:2}
else
    exec $@
fi