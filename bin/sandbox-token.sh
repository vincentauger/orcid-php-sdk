#!/usr/bin/env bash
#
# One-off dev helper: get a 3-legged ORCID *sandbox* token for your test record
# and save it in .env (ORCID_ACCESS_TOKEN, ORCID_REFRESH_TOKEN, ORCID_ORCID_ID). Not shipped with the package.
#
# Usage: bin/sandbox-token.sh
#
set -euo pipefail

cd "$(dirname "$0")/.."

OAUTH_URL="https://sandbox.orcid.org"
SCOPES="/read-limited /activities/update /person/update"

die() { echo "Error: $*" >&2; exit 1; }

[[ -f .env ]] || die "no .env file, run: cp .env.example .env"

env_get() { grep -E "^$1=" .env | tail -n 1 | cut -d= -f2- || true; }

env_set() {
    if grep -qE "^$1=" .env; then
        sed -i.bak "s|^$1=.*|$1=$2|" .env && rm -f .env.bak
    else
        echo "$1=$2" >> .env
    fi
}

command -v jq >/dev/null || die "jq is required"

urlencode() { jq -rn --arg v "$1" '$v | @uri'; }

# Print the given fields of an OAuth token response, one per line, or fail with ORCID's error
token_fields() {
    local response
    response="$(cat)"

    if ! jq -e '.access_token // empty' <<< "$response" >/dev/null 2>&1; then
        die "ORCID returned an error: $response"
    fi

    for field in "$@"; do
        jq -r --arg f "$field" '.[$f] // ""' <<< "$response"
    done
}

[[ "$(env_get ORCID_ENVIRONMENT)" == "sandbox" ]] || die "ORCID_ENVIRONMENT must be 'sandbox' in .env"

CLIENT_ID="$(env_get ORCID_CLIENT_ID)"
CLIENT_SECRET="$(env_get ORCID_CLIENT_SECRET)"
REDIRECT_URI="$(env_get ORCID_REDIRECT_URI)"

[[ -n "$CLIENT_ID" && -n "$CLIENT_SECRET" ]] || die "set ORCID_CLIENT_ID and ORCID_CLIENT_SECRET in .env"
[[ -n "$REDIRECT_URI" ]] || die "set ORCID_REDIRECT_URI in .env (a redirect URI registered on your sandbox client)"

# 1. Send the record owner to ORCID

AUTHORIZE_URL="$OAUTH_URL/oauth/authorize?client_id=$(urlencode "$CLIENT_ID")&response_type=code&scope=$(urlencode "$SCOPES")&redirect_uri=$(urlencode "$REDIRECT_URI")"

echo "1. Sign in to the sandbox as your test record and open:"
echo
echo "   $AUTHORIZE_URL"
echo
echo "2. Authorize. You will be redirected to $REDIRECT_URI?code=..."
echo "   (it does not matter if that page fails to load)"
echo
read -r -p "Paste the URL you were redirected to (or just the code): " INPUT

if [[ "$INPUT" == *"code="* ]]; then
    CODE="${INPUT##*code=}"
    CODE="${CODE%%&*}"
else
    CODE="$INPUT"
fi

[[ -n "$CODE" ]] || die "no authorization code found"

# 2. Exchange the code for a token

RESPONSE="$(curl -sS -X POST "$OAUTH_URL/oauth/token" \
    -H "Accept: application/json" \
    --data-urlencode "client_id=$CLIENT_ID" \
    --data-urlencode "client_secret=$CLIENT_SECRET" \
    --data-urlencode "grant_type=authorization_code" \
    --data-urlencode "code=$CODE" \
    --data-urlencode "redirect_uri=$REDIRECT_URI")"

FIELDS="$(token_fields access_token refresh_token orcid scope <<< "$RESPONSE")"
{ read -r ACCESS_TOKEN; read -r REFRESH_TOKEN; read -r ORCID_ID; read -r GRANTED_SCOPES; } <<< "$FIELDS"

env_set ORCID_ACCESS_TOKEN "$ACCESS_TOKEN"
env_set ORCID_REFRESH_TOKEN "$REFRESH_TOKEN"
env_set ORCID_ORCID_ID "$ORCID_ID"

echo
echo "Saved ORCID_ACCESS_TOKEN, ORCID_REFRESH_TOKEN and ORCID_ORCID_ID ($ORCID_ID) to .env"
echo "Granted scopes: $GRANTED_SCOPES"

