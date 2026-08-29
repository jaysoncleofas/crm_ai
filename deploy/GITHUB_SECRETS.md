# One-time: add deploy secrets to jaysoncleofas/crm_ai (same values as Mia/Timer).
#
#   gh secret set SSH_HOST --repo jaysoncleofas/crm_ai --body "15.235.169.194"
#   gh secret set SSH_USER --repo jaysoncleofas/crm_ai --body "root"
#   gh secret set SSH_PRIVATE_KEY --repo jaysoncleofas/crm_ai < ~/.ssh/fittrack_deploy_ed25519
#
# Then re-run the workflow: Actions → Deploy → Run workflow
