# Technical Discovery

## Product Surface

Mobile-first web app. Home after login is the dashboard (`get-work-orders-summary`: counts by status, open work orders by priority). Collapsible side menu: dashboard, work order list, new work order (manual), new work order with AI, logout. A floating AI button on the work order list screen (only there) opens AI Prefill.

## Candidate Stack

| Layer | Choice |
| --- | --- |
| Frontend | HTML5, CSS3, W3.CSS, plain JavaScript only where strictly needed. No other dependencies. |
| Backend | PHP 8, no frameworks or libraries. Three modules: API, AI connector, MCP server. |
| Data | MySQL / MariaDB (XAMPP) |
| Runtime | Local Windows + XAMPP (Apache + PHP + MariaDB) |

## Data and Storage

Tables for users, auth tokens, work orders, status history, typologies L1/L2/L3, locations, location types, plus an AI Prefill log (see Observability). Seed data: a realistic set of locations, the typology tree with default priorities, a few users and the MCP service account.

## Integrations

### AI Prefill flow

The PHP backend runs the agent loop; see [ADR 0001](adr/0001-php-backend-runs-agent-loop.md).

1. `POST /get-work-order-inference` receives the description and the caller's token.
2. The AI connector starts the MCP server as a stdio subprocess (`php mcp/server.php`), performs the MCP handshake and lists its tools.
3. It calls the LLM provider (Anthropic Messages format) with the description, the creator's identity (to resolve "asígnamelo"), the MCP tools, and an instruction to return a fixed JSON result.
4. It relays tool calls between the LLM and the MCP server until the model returns its final answer, with a cap on iterations.
5. The backend validates every returned ID against the database, blanks invalid or inconsistent fields, adds prefill notes, and returns the result to the frontend.

### LLM providers

- One provider interface with one implementation: the **Anthropic Messages adapter**. Each provider is configured with base URL, API key and model.
- Priority configurations: the Anthropic API (Claude models), and DevExpert inference (`https://inference.devexpert.io/v1/messages`, model alias `chat`, bearer key).
- An OpenAI-compatible adapter can be added later behind the same interface.

### MCP server

- stdio transport, plain PHP, JSON-RPC over stdin/stdout.
- **Read-only** tools that wrap the API: list locations and location types, list typologies by level and parent, list assignable users, read work orders.
- Authenticates to the API with its own service account (username and password from configuration), so it uses the same token flow as the frontend.
- An API key check: the connector passes the key to the subprocess (environment variable) and the server refuses to run without the expected key. With stdio this mainly guards against accidental misuse rather than remote access.

## Authentication and Authorization

- `POST /login` checks the username and password (`password_hash` / `password_verify`) and returns a random token stored server-side with an expiry. Every other endpoint requires the token. All endpoints are POST.
- `POST /logout` revokes the token. Expired tokens are rejected.
- There is one role: any valid user token can call every endpoint. Per-endpoint permissions are deferred.
- The service account token can only be used for the read endpoints the MCP needs (a minimal safeguard, not a role system).

## Deployment and Operations

Local only: Windows + XAMPP. LLM API keys, the MCP API key, the service account credentials and the DB credentials live in a configuration file kept out of git (`config/config.php`, copied from the committed `config/config.example.php`). Hosting, PWA and an HTTP MCP transport are deferred decisions.

Serving (decided in `bootstrap-project-skeleton`, see `ARCHITECTURE.md`): only `public/` is the web root. Dev and automated checks use `php -S 127.0.0.1:8080 -t public`; XAMPP Apache serves the same folder at `http://localhost/devexpertai/` through an `Alias` snippet (`config/apache.conf.example`, copied to the git-ignored `config/apache.conf` and included from `httpd-vhosts.conf`). The frontend uses relative URLs only.

## Testing and Verification

- Plain PHP test scripts for lifecycle rules, ID validation and auth, run by `php tests/run.php` (decided; PHPUnit not approved). Test files are `tests/**/*_test.php` returning `array<string, callable>` and use `tests/lib/assert.php`, not `assert()`. `./init.sh` runs a PHP syntax check plus the runner.
- An evaluation script that runs the ~20 sample descriptions through the inference endpoint and reports field accuracy, invalid IDs and latency.
- A manual end-to-end check of the MVP slice on a mobile viewport.

## Observability

- **AI Prefill log:** each AI Prefill records the timestamp, user, provider/model, description, tool calls made, final raw output, validated result, latency and token usage if provided. Needed to debug wrong prefills and to run the evaluation.
- **Error log:** API errors go to the PHP error log with request context (never passwords or keys).

## Constraints

- No dependencies beyond W3.CSS: the HTTP client (cURL extension), the JSON-RPC and MCP handling, and routing are all hand-written.
- The `proc_open` subprocess handling for the stdio MCP has to work on Windows (XAMPP PHP).
- DevExpert keys expire and have a weekly quota, so evaluation runs consume that quota.
