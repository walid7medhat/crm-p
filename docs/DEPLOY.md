# Deployment — main only

OIA and Luxvant both deploy from **`origin/main`**.

There is no deployment branch, release branch, or merge step in the normal release path.

Brand separation stays in application code (`config/brands.php`, host → brand middleware). Both brands pull the same `main` commit.

## Local (every release)

```bash
git checkout main
git pull --ff-only origin main
git add .
git commit -m "Your message"
git push origin main
```

## Production (every release)

On the production app root (OIA or Luxvant):

```bash
git fetch origin
git checkout main
git pull --ff-only origin main
```

Then rebuild / clear caches as your host normally requires, for example:

```bash
npm ci && npm run build
php artisan optimize:clear
```

Do **not** enable maintenance mode for a normal fast-forward deploy.

## If `git pull --ff-only` fails

`--ff-only` refuses to rewrite history. Do **not** force-reset until you know why the server diverged.

```bash
git status
git branch -vv
git log --oneline -5 HEAD
git log --oneline -5 origin/main
git log --oneline --left-right HEAD...origin/main | head -40
```

| Situation | Safe action |
|-----------|-------------|
| Clean tree, behind `origin/main` | `git pull --ff-only origin main` |
| Local uncommitted changes | Inspect `git status` / `git diff`. Stash or commit on a backup branch **before** updating. Never `git reset --hard` until you confirm nothing important is only on the server. |
| Diverged commits (left/right log shows both sides) | Create a server backup branch: `git branch backup/prod-$(date +%Y%m%d-%H%M)`; then decide whether to cherry-pick server-only commits onto a fresh `main` checkout. Ask before discarding. |
| On an old branch (`deploy/luxvant-release`, etc.) | `git fetch origin && git checkout main && git pull --ff-only origin main` after confirming no unique uncommitted server edits. |

## What not to use

- Do not merge `deploy/luxvant-release` (or any other branch) on production as part of deploy.
- Do not `git pull` without knowing the current branch.
- Do not `git push --force` to `main` unless you explicitly intend to rewrite shared history.

## Archived branches

Tips of the old deploy/reconcile branches are tagged for history:

- `archive/deploy-luxvant-release-2026-11-28`
- `archive/luxvant-reconcile-2026-11-28`

Those tags preserve unique work that was later merged into `main`. Remote branches may still exist as read-only leftovers until you delete them after verification.
