#!/bin/bash
# wait-for-db.sh: waits for a given host:port to be reachable

host="$1"
port="$2"

echo "Waiting for MySQL ($host:$port) to be ready..."
while ! nc -z "$host" "$port"; do
  sleep 1
done

echo "✅ MySQL is ready! Starting app..."