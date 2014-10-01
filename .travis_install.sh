#!/bin/bash

if php --version | grep HHVM | read; then
    sudo add-apt-repository -y ppa:ondrej/php5 &&\
        sudo apt-get update && \
        sudo apt-get install php5-cli -y
else
    true
fi

