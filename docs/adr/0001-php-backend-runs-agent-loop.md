# ADR 0001: The PHP backend runs the agent loop and acts as the MCP client

## Status
Accepted

## Context

AI Prefill needs an LLM to query the hotel's data through the project's own stdio MCP server. A stdio MCP server only works if some process starts it and acts as the MCP client. The original notes listed "Claude Code" as a model, and the AI connection must be provider-agnostic. Both priority providers (the Anthropic API and DevExpert inference) accept the Anthropic Messages format.

## Decision

The PHP backend starts the MCP server as a subprocess, acts as the MCP client, and runs the tool-use loop against the LLM through a provider interface. The first and only adapter speaks the Anthropic Messages format.

## Alternatives Considered

- **Claude Code CLI in headless mode as the MCP client:** quick to prototype, but tied to Claude and adds a runtime dependency on the CLI.
- **Both options, chosen by configuration:** roughly twice the integration work for a proof of concept.
- **Provider-hosted remote MCP:** needs a publicly reachable HTTP MCP, which a local XAMPP setup can't provide.

## Consequences

- The project hand-writes an MCP client (stdio JSON-RPC, handshake, tools/list, tools/call) and a tool-use loop in PHP with no libraries.
- Switching to any Messages-compatible provider is configuration only. OpenAI-compatible providers need a new adapter.
- Tool-calling support on DevExpert inference is unverified, so it must be tested early.
- Moving to hosting later may change the MCP transport to HTTP. The loop and the adapter stay the same.
