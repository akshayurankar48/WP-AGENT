#!/bin/bash
#
# Test script for plugin/theme install actions.
# Exercises the WordPress REST API at http://localhost:10068
# Tests: search, install by slug, install by name, already installed, themes.
#
# Usage: bash tests/test-install-actions.sh
#

BASE="http://localhost:10068/wp-json/wp-agent/v1"
PASS=0
FAIL=0
TOTAL=0

# Get a nonce by authenticating.
COOKIE_FILE=$(mktemp)
NONCE=$(curl -s -c "$COOKIE_FILE" -b "$COOKIE_FILE" \
  "http://localhost:10068/wp-login.php" \
  -d "log=admin&pwd=admin&wp-submit=Log+In&redirect_to=%2Fwp-admin%2F&testcookie=1" \
  -H "Cookie: wordpress_test_cookie=WP+Cookie+check" \
  -L 2>/dev/null | grep -o 'wpAgentData.*nonce.*"[^"]*"' | head -1 | grep -o '"nonce":"[^"]*"' | cut -d'"' -f4)

if [ -z "$NONCE" ]; then
  # Try fetching from any admin page.
  NONCE=$(curl -s -b "$COOKIE_FILE" "http://localhost:10068/wp-admin/admin-ajax.php?action=rest-nonce" 2>/dev/null)
fi

if [ -z "$NONCE" ] || [ "$NONCE" = "0" ]; then
  echo "WARNING: Could not get nonce. Trying with application password or cookie auth only."
  AUTH_HEADER=""
else
  AUTH_HEADER="-H \"X-WP-Nonce: $NONCE\""
fi

run_test() {
  local name="$1"
  local action="$2"
  local params="$3"
  local expect_success="$4"  # "true" or "false"
  local expect_contains="$5" # string that should appear in response

  TOTAL=$((TOTAL + 1))

  RESPONSE=$(curl -s -b "$COOKIE_FILE" \
    -H "Content-Type: application/json" \
    -H "X-WP-Nonce: $NONCE" \
    -X POST "$BASE/action/execute" \
    -d "{\"action\": \"$action\", \"params\": $params}")

  SUCCESS=$(echo "$RESPONSE" | python3 -c "import sys,json; d=json.load(sys.stdin); print(str(d.get('success', d.get('data',{}).get('success',''))).lower())" 2>/dev/null)
  MESSAGE=$(echo "$RESPONSE" | python3 -c "import sys,json; d=json.load(sys.stdin); msg=d.get('message', d.get('data',{}).get('message','')); print(msg)" 2>/dev/null)

  local passed=true

  if [ "$expect_success" = "true" ] && [ "$SUCCESS" != "true" ]; then
    passed=false
  fi
  if [ "$expect_success" = "false" ] && [ "$SUCCESS" != "false" ]; then
    passed=false
  fi
  if [ -n "$expect_contains" ] && ! echo "$MESSAGE $RESPONSE" | grep -qi "$expect_contains"; then
    passed=false
  fi

  if [ "$passed" = true ]; then
    printf "  PASS  | %s\n" "$name"
    PASS=$((PASS + 1))
  else
    printf "  FAIL  | %s\n" "$name"
    printf "         Expected success=%s, got=%s\n" "$expect_success" "$SUCCESS"
    printf "         Message: %s\n" "$MESSAGE"
    FAIL=$((FAIL + 1))
  fi
}

echo ""
echo "============================================"
echo " WP Agent — Install Actions Test Suite"
echo "============================================"
echo ""

# ---- Plugin Tests ----
echo "--- Plugin Install Tests ---"
echo ""

# Test 1: Search plugins (recommend_plugin)
run_test \
  "Search plugins for 'elementor addons'" \
  "recommend_plugin" \
  '{"operation": "search", "query": "elementor addons", "per_page": 3}' \
  "true" \
  "plugin"

# Test 2: Install plugin by exact slug
run_test \
  "Install plugin by exact slug (hello-dolly)" \
  "install_plugin" \
  '{"slug": "hello-dolly"}' \
  "true" \
  "install"

# Test 3: Already installed detection
run_test \
  "Detect already-installed plugin (hello-dolly)" \
  "install_plugin" \
  '{"slug": "hello-dolly"}' \
  "false" \
  "already installed"

# Test 4: Install plugin by name (search fallback)
run_test \
  "Install by name 'ultimate addons elementor' (search fallback)" \
  "install_plugin" \
  '{"slug": "ultimate addons for elementor"}' \
  "true" \
  "install"

# Test 5: Invalid slug format handling
run_test \
  "Empty slug returns error" \
  "install_plugin" \
  '{"slug": ""}' \
  "false" \
  "required"

# Test 6: List plugins shows installed ones
run_test \
  "List plugins includes hello-dolly" \
  "list_plugins" \
  '{}' \
  "true" \
  "hello"

echo ""

# ---- Theme Tests ----
echo "--- Theme Install Tests ---"
echo ""

# Test 7: Search themes
run_test \
  "Search themes for 'elementor'" \
  "search_theme" \
  '{"query": "elementor", "per_page": 3}' \
  "true" \
  "theme"

# Test 8: Install theme by exact slug
run_test \
  "Install theme by exact slug (flavor)" \
  "install_theme" \
  '{"slug": "flavor"}' \
  "true" \
  "install"

# Test 9: Already installed detection for themes
run_test \
  "Detect already-installed theme (flavor)" \
  "install_theme" \
  '{"slug": "flavor"}' \
  "false" \
  "already installed"

# Test 10: Install theme by name (search fallback)
run_test \
  "Install by name 'hello elementor' (search fallback)" \
  "install_theme" \
  '{"slug": "hello elementor"}' \
  "true" \
  "install"

# Test 11: Manage theme — list includes new themes
run_test \
  "List themes shows installed themes" \
  "manage_theme" \
  '{"operation": "list"}' \
  "true" \
  "theme"

# Test 12: Switch to installed theme
run_test \
  "Switch to hello-elementor theme" \
  "manage_theme" \
  '{"operation": "switch", "stylesheet": "hello-elementor"}' \
  "true" \
  "switch"

# Test 13: Get active theme confirms switch
run_test \
  "Active theme is now Hello Elementor" \
  "manage_theme" \
  '{"operation": "get_active"}' \
  "true" \
  "hello"

# Test 14: Switch back to original theme
run_test \
  "Switch back to twentytwentyfive" \
  "manage_theme" \
  '{"operation": "switch", "stylesheet": "twentytwentyfive"}' \
  "true" \
  "switch"

echo ""

# ---- Edge Case Tests ----
echo "--- Edge Case Tests ---"
echo ""

# Test 15: Non-existent plugin
run_test \
  "Non-existent plugin 'zzz-no-such-plugin-exists'" \
  "install_plugin" \
  '{"slug": "zzz-no-such-plugin-exists-12345"}' \
  "false" \
  ""

# Test 16: Non-existent theme
run_test \
  "Non-existent theme 'zzz-no-such-theme-12345'" \
  "install_theme" \
  '{"slug": "zzz-no-such-theme-exists-12345"}' \
  "false" \
  ""

echo ""
echo "============================================"
printf " Results: %d/%d passed" "$PASS" "$TOTAL"
if [ "$FAIL" -gt 0 ]; then
  printf " (%d FAILED)" "$FAIL"
fi
echo ""
echo "============================================"

# Cleanup
rm -f "$COOKIE_FILE"

exit $FAIL
