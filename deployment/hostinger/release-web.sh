#!/usr/bin/env bash
# Builds the storefront from a clean checkout of a git ref and releases it to Hostinger.
# Hostinger's shared plan cannot run the Next.js build (process limits), so the build runs
# here and the self-contained bundle is uploaded; the server only runs it (DEPLOYMENT.md).
#
# Usage: deployment/hostinger/release-web.sh [git-ref]      (default: origin/main)
set -euo pipefail

REF="${1:-origin/main}"
SSH_TARGET="${SSH_TARGET:-u462102226@45.84.204.68}"
SSH_PORT="${SSH_PORT:-65002}"
SITE_URL="${SITE_URL:-https://www.chrixlin.com}"
API_URL="${API_URL:-https://api.chrixlin.com/api/v1}"
STORE_NAME="${STORE_NAME:-Chrixlin}"
ROOT="$(git rev-parse --show-toplevel)"
WORK="$(mktemp -d /tmp/ecom-web-release.XXXXXX)"
ssh_run() { ssh -o BatchMode=yes -p "$SSH_PORT" "$SSH_TARGET" "$@"; }
cleanup() { git -C "$ROOT" worktree remove --force "$WORK/src" 2>/dev/null || true; rm -rf "$WORK"; }
trap cleanup EXIT

git -C "$ROOT" fetch -q origin
SHA="$(git -C "$ROOT" rev-parse --short "$REF")"
echo "→ Building storefront $SHA"
git -C "$ROOT" worktree add -q --detach "$WORK/src" "$SHA"
cd "$WORK/src/frontend"
npm ci --no-audit --no-fund --loglevel=error >/dev/null
NEXT_TELEMETRY_DISABLED=1 NEXT_PUBLIC_API_URL="$API_URL" NEXT_PUBLIC_SITE_URL="$SITE_URL" NEXT_PUBLIC_STORE_NAME="$STORE_NAME" \
  npx next build >"$WORK/build.log" 2>&1 || { tail -30 "$WORK/build.log"; exit 1; }

echo "→ Assembling the standalone bundle for Linux"
S=.next/standalone
cp -R .next/static "$S/.next/static"
cp -R public "$S/public"
# The image optimiser (sharp) ships per-platform binaries: replace the Mac ones with Linux x64.
V=$(node -p "require('./node_modules/sharp/package.json').optionalDependencies['@img/sharp-linux-x64']")
L=$(node -p "require('./node_modules/sharp/package.json').optionalDependencies['@img/sharp-libvips-linux-x64']")
rm -rf "$S"/node_modules/@img/sharp-darwin-* "$S"/node_modules/@img/sharp-libvips-darwin-*
(cd "$WORK" && npm pack --silent "@img/sharp-linux-x64@$V" "@img/sharp-libvips-linux-x64@$L" >/dev/null)
for p in sharp-linux-x64 sharp-libvips-linux-x64; do
  mkdir -p "$S/node_modules/@img/$p" && tar -xzf "$WORK"/img-$p-*.tgz -C "$S/node_modules/@img/$p" --strip-components=1
done
cp "$ROOT/deployment/hostinger/app.js" "$S/app.js"

echo "→ Uploading release $SHA"
(cd "$S" && COPYFILE_DISABLE=1 tar --no-xattrs -czf - .) | ssh_run "set -e
  R=\$HOME/apps/ecom-web/releases/$SHA; rm -rf \$R; mkdir -p \$R \$HOME/apps/ecom-web/shared; tar -xzf - -C \$R 2>/dev/null
  ln -sfn \$HOME/apps/ecom-web/shared/runtime.env \$R/runtime.env
  ln -sfn \$R \$HOME/apps/ecom-web/current
  mkdir -p \$R/tmp && touch \$R/tmp/restart.txt
  ls -1dt \$HOME/apps/ecom-web/releases/* | tail -n +4 | xargs -r rm -rf   # keep the last 3 releases
  echo \"current → \$(basename \$(readlink \$HOME/apps/ecom-web/current))\""

echo "→ Checking $SITE_URL"
curl -fsS -o /dev/null -w "home %{http_code} in %{time_total}s\n" "$SITE_URL/" || echo "(site not reachable at its public address yet — DNS may still point elsewhere)"
