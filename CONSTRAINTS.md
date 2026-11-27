# Constraints

Durable MUST / MUST NOT rules. Each has a short reason. Add a rule only when a feature reveals one.

- **MUST** serve only `public/`. Never place PHP sources, config, tests or SQL under `public/`. *Reason: everything else may hold secrets or internals.*
- **MUST** use relative URLs in the frontend (`assets/...`, `api/...`), never root-absolute ones (`/api/...`). *Reason: the app runs at `/` under `php -S` and at `/devexpertai/` under the XAMPP Alias.*
- **MUST** keep secrets only in the git-ignored `config/config.php`. `config/config.example.php` holds placeholders (`CHANGE_ME`) only. *Reason: secrets must never reach git, logs or code.*
- **MUST** write tests as plain PHP `tests/**/*_test.php` files returning `array<string, callable>`, run by `php tests/run.php`, using the helpers in `tests/lib/assert.php`. **MUST NOT** use PHPUnit or PHP's `assert()`. *Reason: no dependencies beyond W3.CSS and the icon font; `assert()` depends on `zend.assertions`.*
- **MUST** keep W3.CSS vendored and byte-for-byte unmodified at `public/assets/vendor/w3css/w3.css`, with `SOURCE.txt` (URL, version, date, SHA-256) updated on any upgrade. App styles go in a separate stylesheet. `public/assets/vendor/**` is `-text` in `.gitattributes` so line endings are never converted. *Reason: pinned, offline, verifiable third-party code.*
- **MUST** load icons only from the vendored Material Symbols Outlined font at `public/assets/vendor/material-symbols/MaterialSymbolsOutlined.woff2` (unmodified, SHA-256 in `SOURCE.txt`, checked by `tests/bootstrap/icons_test.php`) through `public/assets/css/icons.css`. **MUST NOT** link Google Fonts, `fonts.gstatic.com` or any other CDN. *Reason: the app must work offline on XAMPP, and the user approved this font as the only extra dependency (2026-11-27).*
- **MUST** spawn PHP subprocesses with the array form of `proc_open` (`[PHP_BINARY, ...]`), and always terminate them. *Reason: on Windows the string form runs through `cmd.exe`; terminating it can orphan `php.exe`.*
- **MUST** keep `./init.sh` LF, executable (100755) and non-blocking (no long-running servers). *Reason: it is the standard gate for every session.*
- **MUST NOT** download W3.CSS (or other files from w3schools.com) with curl's default User-Agent; pass a browser User-Agent with `-A`. *Reason: the site returns 403 to curl's default User-Agent.*
