#!/usr/bin/env bash
# Kill PHP processes using ports 8000-8010 (artisan serve)
# Run this in your terminal when you see "Address already in use"

for port in 8000 8001 8002 8003 8004 8005 8006 8007 8008 8009 8010; do
  pids=$(lsof -ti :$port 2>/dev/null)
  if [ -n "$pids" ]; then
    echo "$pids" | xargs kill -9 2>/dev/null && echo "Freed port $port"
  fi
done
echo "Done. Run: php artisan serve"
