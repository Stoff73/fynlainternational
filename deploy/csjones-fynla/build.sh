#!/bin/bash
# =============================================================================
# Fynla International Build Script - csjones.co/fynla_inter (SUBDIRECTORY)
# =============================================================================
# Usage: ./deploy/csjones-fynla/build.sh
# Output: Builds frontend assets in public/build/
# =============================================================================
# Deploys alongside the UK Fynla (at csjones.co/fynla) in a separate
# subdirectory (csjones.co/fynla_inter) so the two dev instances do not clash.
#
# IMPORTANT: The server does not have enough memory to run npm build.
# This script builds locally. You then manually upload changed files via
# SiteGround File Manager.
# =============================================================================

set -e

# Prevent Git Bash (MSYS2) from converting /build/ to C:/Program Files/Git/build/
export MSYS_NO_PATHCONV=1

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"

echo "============================================="
echo "Fynla International Build - csjones.co/fynla_inter"
echo "============================================="
echo ""

cd "$PROJECT_ROOT"

# Set environment variables for subdirectory deployment
export NODE_ENV=production
export VITE_BASE_PATH=/fynla_inter/build/
export VITE_ROUTER_BASE=/fynla_inter/
export VITE_APP_NAME="Fynla International"
export VITE_API_BASE_URL=https://csjones.co/fynla_inter

# Awin affiliate tracking — staging defaults to disabled. Flip to true here
# (and set AWIN_ENABLED=true on the server .env) only for an attribution
# test window, then flip both back to false and rebuild.
export VITE_AWIN_ENABLED=false
export VITE_AWIN_MERCHANT_ID=126105
export VITE_AWIN_MASTER_TAG_URL=https://www.dwin1.com/126105.js
export VITE_AWIN_FALLBACK_PIXEL=https://www.awin1.com/sread.img

echo "Environment:"
echo "  NODE_ENV: $NODE_ENV"
echo "  VITE_BASE_PATH: $VITE_BASE_PATH"
echo "  VITE_ROUTER_BASE: $VITE_ROUTER_BASE"
echo "  VITE_API_BASE_URL: $VITE_API_BASE_URL"
echo "  VITE_AWIN_ENABLED: $VITE_AWIN_ENABLED"
echo ""

# Node 20+ is required for the web build. The vite-plugin-pwa → workbox-build →
# terser chain calls a global `crypto` that does not exist on Node 18, failing
# with a cryptic "crypto is not defined". Guard here so the failure is legible.
NODE_MAJOR="$(node -p 'process.versions.node.split(".")[0]' 2>/dev/null || echo 0)"
if [ "$NODE_MAJOR" -lt 20 ]; then
    echo "ERROR: Node >= 20 required for the web build (found $(node -v 2>/dev/null || echo 'none'))."
    echo "       The PWA build chain fails on Node 18 with 'crypto is not defined'."
    echo "       Run 'nvm install 20 && nvm use 20' (or upgrade Node) and retry."
    exit 1
fi

# Build frontend assets
echo "Building frontend assets..."
npm run build

if [ ! -f "public/build/manifest.json" ]; then
    echo "ERROR: Build failed - manifest.json not found"
    exit 1
fi

# Get build size
BUILD_SIZE=$(du -sh "public/build" | cut -f1)

echo ""
echo "============================================="
echo "Build complete!"
echo "============================================="
echo ""
echo "Built assets: public/build/ ($BUILD_SIZE)"
echo ""
echo "============================================="
echo "Manual Upload via SiteGround File Manager:"
echo "============================================="
echo ""
echo "1. Upload public/build/ directory to (app dir, NOT via the symlink —"
echo "   public_html/fynla_inter already points at fynla_inter-app/public):"
echo "   ~/www/csjones.co/fynla_inter-app/public/build/"
echo ""
echo "2. Upload any changed PHP files (check deployment notes) to"
echo "   ~/www/csjones.co/fynla_inter-app/"
echo ""
echo "3. If composer.json/composer.lock changed (e.g. the Laravel 12 upgrade),"
echo "   upload packs/ first (path-repo symlinks), then install deps ON the server:"
echo "   cd ~/www/csjones.co/fynla_inter-app"
echo "   composer install --no-dev --optimize-autoloader"
echo ""
echo "4. SSH to server, migrate, and clear caches (artisan lives in the APP dir,"
echo "   not the public symlink):"
echo "   cd ~/www/csjones.co/fynla_inter-app"
echo "   php artisan migrate --force"
echo "   php artisan cache:clear && php artisan route:clear && php artisan config:clear && php artisan view:clear"
echo ""
echo "DO NOT run 'npm install' or 'npm run build' on the server!"
echo ""
