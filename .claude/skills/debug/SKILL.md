---
name: debug
description: Investigate and diagnose bugs — traces code path, identifies root cause, proposes fix
argument-hint: "[describe the bug]"
allowed-tools: Bash Read Grep Glob
---

# Debug: $ARGUMENTS

Investigate and diagnose the issue described: `$ARGUMENTS`

## Step 1: Understand the problem
Parse the user's description. Identify:
- Which feature area is affected (AI generation, scanner, batch, settings, WooCommerce, file renamer)?
- Is it a PHP backend issue or Vue frontend issue?
- Is it a data issue, logic issue, or UI issue?

## Step 2: Trace the code path
Using the CLAUDE.md dependency map, trace the full code path for the affected feature:
- Frontend: Which Vue component → which API call → which endpoint
- Backend: Which route → which controller method → which service/model
- Read each file in the chain to understand the flow

## Step 3: Identify likely causes
Based on the code path, identify:
- Where could the described problem originate?
- Check for common pitfalls listed in CLAUDE.md (settings double-prefix, model caching, load order, etc.)
- Check for null/undefined handling, missing defaults, type mismatches
- Check for race conditions (async operations, batch processing)

## Step 4: Check related recent changes
- Run `git log --oneline -20` to see recent commits
- Run `git log --oneline --all -- [affected files]` to see recent changes to the affected files
- Could a recent change have caused this regression?

## Step 5: Propose fix
- Identify the root cause
- Propose a specific fix with exact code changes
- Run impact analysis: what else could the fix affect?
- Verify the fix maintains backwards compatibility

## Step 6: Report
```
=== Debug Report: [issue summary] ===

AFFECTED AREA: [feature module]
CODE PATH: [component] → [API] → [controller] → [service]

ROOT CAUSE:
[explanation]

EVIDENCE:
[specific code references with file:line]

PROPOSED FIX:
[exact changes needed]

IMPACT:
[what else the fix affects, if anything]

RISK: Low / Medium / High
```

Then ask: "Should I apply this fix?"
