---
name: speed-up-system-info
description: "Quickly gather and summarize system and environment information for diagnostics, setup checks, and performance triage."
---

# Speed Up System Info

Use this skill when you need a fast, repeatable workflow to collect and summarize the system and application environment for troubleshooting.

## What this skill does

- Detects the current OS and web server/PHP stack.
- Gathers key environment details from PHP, Apache/WAMP, database config, and important project settings.
- Flags missing or inconsistent information.
- Produces a concise action-oriented summary.

## Workflow

1. Confirm the goal: "Gather quick system info for troubleshooting".
2. Detect the platform and server stack.
3. Collect these core details:
   - OS and runtime environment
   - PHP version, extensions, `php.ini` path
   - Web server type (Apache/IIS/Nginx/WAMP)
   - Database driver and connection settings
   - Important config files and environment variables
4. Summarize what is complete and what needs follow-up.
5. Suggest next actions based on missing details or common setup issues.

## Decision points

- If the environment is local WAMP vs remote Linux, choose the relevant diagnostic checks.
- If database credentials are unavailable, ask for the config file location instead of guessing.
- If the user wants a quick summary, prioritize high-level info; if they want deeper diagnostics, expand into logs and service status.

## Quality criteria

- Includes a clear list of collected environment items.
- Notes any missing or invalid configuration.
- Offers a compact summary plus a short list of next troubleshooting steps.
- Uses workspace context where available, not generic system advice.

## Example prompts

- "Use this skill to speed up system info collection for the current WAMP/PHP project."
- "Gather the key environment details and tell me if any setup issues are obvious." 
- "Summarize the PHP and database configuration for this workspace quickly."
