#!/bin/sh
# Push the current branch to origin, replacing the commit on GitHub
# so the "Cursor Agent" co-author line is removed.
cd "$(dirname "$0")"
git push --force-with-lease origin main
