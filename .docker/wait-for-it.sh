#!/usr/bin/env bash

host="$1"
port="$2"

echo "Waiting for $host:$port..."
while ! nc -z "$host" "$port"; do
sleep 0.5
done

echo "$host:$port is available"
