# Risks and Open Questions

## Blocking Next Phase

None. Discovery is sufficient to plan the MVP slice. The first research task below should run before the AI connector is built in detail.

## Implementation-Time Questions

- **Token lifetime:** *initial stance:* 8 hours, with no refresh. Logging in again issues a new token.
- **Test tooling:** are plain PHP test scripts enough, or is PHPUnit allowed as a dev-only exception to the "no dependencies" rule?
- **Editable fields:** which work order fields can a manual edit change? *Initial stance:* everything except creator, created at and description.
- **Seed data:** who writes the realistic typology tree and the locations used in the evaluation set?

## Later / Not MVP

- Hosting deployment, and with it a PWA and an HTTP MCP transport.
- Per-endpoint permissions and several roles.
- An OpenAI-compatible LLM adapter.
- Reopening closed work orders.
- Assignee suggestions by specialty or workload.

## Assumptions

- Descriptions are written in Spanish.
- Typology names and location names are distinctive enough for an LLM to match them through MCP lookups.
- All users can see and edit all work orders.
- Response times of about 15 seconds are acceptable for AI Prefill on mobile.

## Risks

- **Tool calling on DevExpert inference is undocumented,** and the `chat` alias's backing model can change. Prefill may fail or behave inconsistently on that provider.
- **Hand-written MCP client and stdio subprocess on Windows** (`proc_open`, pipe buffering, process cleanup) may be fragile.
- **Latency:** several tool-call rounds per prefill could exceed the 15-second target.
- **Quota and key expiry** on DevExpert can block evaluation runs.
- **Hallucinated IDs** if backend validation is skipped. That validation is mandatory.

## Research Tasks

1. A tool-calling spike: send one Messages request with a tool definition to both providers and confirm that `tool_use` and `tool_result` round-trip correctly.
2. A stdio spike: PHP `proc_open` starts `php mcp/server.php` on XAMPP Windows, completes the initialize and tools/list exchange, and shuts down cleanly.
3. Measure prefill latency on 3–5 samples per provider before building the UI.
