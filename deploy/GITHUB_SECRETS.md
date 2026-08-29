# GitHub Actions — secrets and deploy setup

## Where to set secrets

Open the **crm_ai** repository on GitHub:

**https://github.com/jaysoncleofas/crm_ai/settings/secrets/actions**

Or: **Settings** → **Secrets and variables** → **Actions** → **New repository secret**

### SSH fallback workflow only (`Deploy (SSH fallback)`)

| Secret | Value |
|--------|--------|
| `SSH_HOST` | `15.235.169.194` |
| `SSH_USER` | `root` |
| `SSH_PRIVATE_KEY` | Full contents of `~/.ssh/fittrack_deploy_ed25519` |
| `SSH_PORT` | (optional) only if SSH is not on port 22 |
| `GIT_CLONE_TOKEN` | (optional) PAT if clone fails with `GITHUB_TOKEN` |

CLI (from your machine):

```bash
gh secret set SSH_HOST --repo jaysoncleofas/crm_ai --body "15.235.169.194"
gh secret set SSH_USER --repo jaysoncleofas/crm_ai --body "root"
gh secret set SSH_PRIVATE_KEY --repo jaysoncleofas/crm_ai < ~/.ssh/fittrack_deploy_ed25519
```

The main **Deploy** workflow uses a **self-hosted runner** on the VPS — it does **not** need SSH secrets.

## `dial tcp …:22: i/o timeout` (31s failure)

This is **not** a missing-secret error (`Verify deploy secrets` would fail first).

GitHub’s cloud runners cannot open SSH to your server. Common causes:

1. **VPS offline** — check your provider console (power, billing, IP).
2. **Firewall** — `ufw` or cloud firewall blocks port 22 from the internet.
3. **Wrong host** — confirm `SSH_HOST` is still `15.235.169.194`.

Test from your laptop:

```bash
ssh -i ~/.ssh/fittrack_deploy_ed25519 root@15.235.169.194
```

If that works locally but GitHub fails → use the **self-hosted runner** (recommended).

## Recommended: self-hosted runner (one-time)

When SSH works from your machine:

```bash
ssh -i ~/.ssh/fittrack_deploy_ed25519 root@15.235.169.194
cd /var/www/production/crm
# GitHub → Settings → Actions → Runners → New self-hosted runner → copy token
export RUNNER_REGISTRATION_TOKEN='paste-token'
bash deploy/scripts/install-github-runner.sh
```

Then push to `main` or run **Actions → Deploy → Run workflow**. Jobs run **on the server** (outbound to GitHub only).

## Manual deploy (no Actions)

```bash
bash deploy/scripts/sync-to-server.sh
ssh -i ~/.ssh/fittrack_deploy_ed25519 root@15.235.169.194 \
  'cd /var/www/production/crm && bash deploy/scripts/finish-deploy.sh'
```
