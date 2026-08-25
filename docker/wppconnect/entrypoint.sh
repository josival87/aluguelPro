#!/bin/sh
set -eu

profile_root=/usr/src/wpp-server/userDataDir

if [ -d "$profile_root" ]; then
    find "$profile_root" -mindepth 2 -maxdepth 2 -name SingletonCookie -delete
    find "$profile_root" -mindepth 2 -maxdepth 2 -name SingletonLock -delete
    find "$profile_root" -mindepth 2 -maxdepth 2 -name SingletonSocket -delete
fi

exec node dist/server.js
