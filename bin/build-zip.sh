#!/usr/bin/env bash
#
# Build a distributable plugin zip.
#
# Produces dist/convocept-<version>.zip containing only the files that belong
# in a release, laid out under a top-level convocept/ directory so it installs
# straight into wp-content/plugins/convocept.
#
# Usage:
#   bin/build-zip.sh              Full release build.
#   SKIP_BUILD=1 bin/build-zip.sh Reuse the existing assets/dist/ output.
#   SKIP_POT=1   bin/build-zip.sh Skip regenerating the translation template.
#
# The file list is an allowlist: anything not named here is left out, so
# development files can never leak into a public release by accident.

set -euo pipefail

# Resolve the plugin root regardless of where the script is called from.
readonly PLUGIN_SLUG="convocept"
readonly ROOT="$( cd "$( dirname "${BASH_SOURCE[0]}" )/.." && pwd )"
readonly DIST="${ROOT}/dist"
readonly STAGE="${DIST}/${PLUGIN_SLUG}"

cd "${ROOT}"

# Files and directories that ship in the release. assets/src is included
# deliberately: WordPress.org requires the uncompiled sources for any
# compiled asset, and the GPL expects the preferred form for modification.
# vendor/ is not copied; a clean autoloader is generated in the stage below.
readonly INCLUDE=(
	"convocept.php"
	"uninstall.php"
	"readme.txt"
	"CHANGELOG.md"
	"composer.json"
	"package.json"
	"src"
	"templates"
	"assets/src"
	"assets/dist"
	"languages"
)

info()  { printf '\033[0;34m==>\033[0m %s\n' "$1"; }
ok()    { printf '\033[0;32m  ✓\033[0m %s\n' "$1"; }
warn()  { printf '\033[0;33m  !\033[0m %s\n' "$1"; }
fail()  { printf '\033[0;31mError:\033[0m %s\n' "$1" >&2; exit 1; }

# ---------------------------------------------------------------------------
# 1. Read and cross-check the version.
# ---------------------------------------------------------------------------
# A mismatch between the plugin header, the constant, and the readme stable
# tag is the classic release bug: WordPress.org serves the stable tag, so a
# stale readme silently ships the wrong code to every user.

info "Checking version consistency"

header_version=$( grep -m1 '^ \* Version:' convocept.php | sed -E 's/.*Version:[[:space:]]*//' | tr -d '[:space:]' )
const_version=$( grep -m1 "define( 'CONVOCEPT_VERSION'" convocept.php | sed -E "s/.*'CONVOCEPT_VERSION',[[:space:]]*'([^']+)'.*/\1/" )
stable_tag=$( grep -m1 '^Stable tag:' readme.txt | sed -E 's/.*Stable tag:[[:space:]]*//' | tr -d '[:space:]' )

[[ -n "${header_version}" ]] || fail "Could not read Version from convocept.php header."
[[ "${header_version}" == "${const_version}" ]] \
	|| fail "Version mismatch: plugin header ${header_version} vs CONVOCEPT_VERSION ${const_version}."
# CI builds -dev versions for Plugin Check; those never match the stable tag.
if [[ "${ALLOW_DEV_VERSION:-0}" == "1" && "${header_version}" == *-dev ]]; then
	warn "Development version ${header_version}; skipping stable tag check"
else
	[[ "${header_version}" == "${stable_tag}" ]] \
		|| fail "Version mismatch: plugin header ${header_version} vs readme.txt stable tag ${stable_tag}."
fi

readonly VERSION="${header_version}"
ok "Version ${VERSION} consistent across header, constant, and readme"

# ---------------------------------------------------------------------------
# 2. Lint every PHP file.
# ---------------------------------------------------------------------------
# A parse error in a release zip fatals the site on activation, so this is
# worth the couple of seconds it costs.

if command -v php >/dev/null 2>&1; then
	info "Linting PHP"

	lint_failed=0
	while IFS= read -r file; do
		php -l "${file}" >/dev/null 2>&1 || { warn "Syntax error in ${file}"; lint_failed=1; }
	done < <( find convocept.php uninstall.php src templates -name '*.php' 2>/dev/null )

	[[ "${lint_failed}" -eq 0 ]] || fail "PHP lint failed."
	ok "No syntax errors"
else
	warn "php not found; skipping lint"
fi

# ---------------------------------------------------------------------------
# 3. Build the assets.
# ---------------------------------------------------------------------------

if [[ "${SKIP_BUILD:-0}" == "1" ]]; then
	warn "SKIP_BUILD set; reusing existing assets/dist/"
else
	command -v npm >/dev/null 2>&1 || fail "npm is required to build assets."

	info "Installing dependencies"
	if [[ -f package-lock.json ]]; then
		npm ci --silent
	else
		npm install --silent
	fi
	ok "Dependencies ready"

	info "Building assets"
	# Keep the log quiet on success, but show everything when the build fails.
	build_log="$( mktemp )"
	if npm run build --silent >"${build_log}" 2>&1; then
		ok "Assets built"
	else
		cat "${build_log}" >&2
		rm -f "${build_log}"
		fail "Asset build failed."
	fi
	rm -f "${build_log}"
fi

# The front end and the settings screen cannot work without these, so never ship a zip missing them.
for asset in assets/dist/loader.js assets/dist/convocept.js assets/dist/convocept.css assets/dist/admin/index.js; do
	[[ -f "${asset}" ]] || fail "${asset} is missing. Run without SKIP_BUILD."
done

if [[ "${SKIP_BUILD:-0}" != "1" ]]; then
	info "Checking size budget"
	npx --no-install size-limit >/dev/null || fail "Size budget exceeded. Run npm run size for details."
	ok "Within size budget"
fi

# ---------------------------------------------------------------------------
# 4. Refresh the translation template.
# ---------------------------------------------------------------------------

if [[ "${SKIP_POT:-0}" == "1" ]]; then
	warn "SKIP_POT set; keeping existing languages/convocept.pot"
elif command -v wp >/dev/null 2>&1; then
	info "Generating translation template"
	mkdir -p languages
	# Scan the built admin and block scripts, not assets/src: WordPress loads a
	# script's translations from a JSON file named after the md5 of the path
	# in the .po reference, so references must point at the files it loads.
	# A bare "dist" would also exclude assets/dist: exclusions match any folder name.
	wp i18n make-pot . languages/convocept.pot --exclude=node_modules,vendor,assets/src,dist/convocept,bin,tests --allow-root >/dev/null 2>&1 \
		&& ok "languages/convocept.pot updated" \
		|| warn "make-pot failed; shipping the existing .pot"
else
	warn "wp-cli not found; shipping the existing .pot"
fi

# ---------------------------------------------------------------------------
# 5. Stage the release files.
# ---------------------------------------------------------------------------

info "Staging release files"

rm -rf "${STAGE}"
mkdir -p "${STAGE}"

for item in "${INCLUDE[@]}"; do
	if [[ -e "${item}" ]]; then
		mkdir -p "${STAGE}/$( dirname "${item}" )"
		cp -R "${item}" "${STAGE}/${item}"
	else
		warn "Skipping missing ${item}"
	fi
done

# Generate a production autoloader: no dev packages, authoritative classmap.
command -v composer >/dev/null 2>&1 || fail "composer is required to build the autoloader."
composer install --working-dir="${STAGE}" --no-dev --classmap-authoritative --no-interaction --quiet \
	|| fail "composer install failed in the stage directory."
rm -f "${STAGE}/composer.lock"
[[ -f "${STAGE}/vendor/autoload.php" ]] || fail "Autoloader was not generated."
ok "Production autoloader generated"

# src/ holds PHP only; anything else there is a leftover and must not ship.
find "${STAGE}/src" -type f ! -name '*.php' -delete
find "${STAGE}/src" -type d -empty -delete

# Strip artifacts that ride along inside copied directories.
find "${STAGE}" -name '.DS_Store' -delete
find "${STAGE}" -name '*.map' -delete
find "${STAGE}" -name 'Thumbs.db' -delete

ok "Staged $( find "${STAGE}" -type f | wc -l | tr -d ' ' ) files"

# ---------------------------------------------------------------------------
# 6. Zip it.
# ---------------------------------------------------------------------------

command -v zip >/dev/null 2>&1 || fail "zip is required."

readonly ZIP="${DIST}/${PLUGIN_SLUG}-${VERSION}.zip"

info "Creating archive"
rm -f "${ZIP}"

# Zip from dist/ so the archive root is convocept/, not an absolute path.
( cd "${DIST}" && zip -rq "${ZIP}" "${PLUGIN_SLUG}" -x '*.DS_Store' )

rm -rf "${STAGE}"

ok "$( basename "${ZIP}" ) ($( du -h "${ZIP}" | cut -f1 | tr -d ' ' ))"

printf '\n\033[0;32mDone.\033[0m %s\n' "${ZIP}"
printf 'Inspect with: unzip -l %s\n' "${ZIP}"

# Deliberately not suggesting an install command: when the plugin is being
# developed inside a WordPress tree, installing this zip over the same slug
# replaces the working directory and destroys uncommitted source. Test the
# archive on a separate site.
printf 'Test it on a separate site — installing over this working copy would replace the source.\n'
