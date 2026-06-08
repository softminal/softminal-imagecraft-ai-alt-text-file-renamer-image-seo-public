---
description: Full security, optimization, and traceability audit — outputs audit_todo.md
---

You are acting as a lead architect and engineering director conducting a comprehensive codebase audit. Spin up specialized sub-agents for each domain below. Each sub-agent should read ALL relevant files before drawing conclusions — never infer, always verify.

---

## AGENT ORCHESTRATION PLAN

Before spawning any sub-agent, map the full project structure: identify all layers (UI, API routes, controllers, services, repositories, DB schema, middleware, utils, config). Then assign agents accordingly.

---

## SUB-AGENT 1 — SECURITY AUDIT
*Persona: Senior AppSec engineer with OWASP expertise reviewing code written by a junior dev*

Go through every file. Flag and categorize every issue found:

**CRITICAL**
- Hardcoded secrets, API keys, tokens, passwords anywhere in code or config
- SQL/NoSQL injection vulnerabilities (raw query concatenation, unsanitized inputs)
- Authentication bypass possibilities (missing auth guards, JWT not verified, session fixation)
- Remote code execution vectors (eval, exec, deserialization of untrusted input)
- Exposed admin endpoints with no role check

**HIGH**
- Missing or misconfigured CORS (overly permissive origins)
- CSRF protection absent on state-changing endpoints
- Insecure direct object references (IDOR) — can user A access user B's data?
- Broken access control — role checks missing or bypassable
- Passwords stored without proper hashing (bcrypt/argon2), or with weak algorithms (MD5/SHA1)
- Sensitive data (PII, tokens) logged to console or written to log files unmasked
- Missing rate limiting on auth endpoints or public APIs
- Unvalidated redirects

**MEDIUM**
- Missing input validation or schema enforcement on any endpoint
- Error messages leaking stack traces or internal details to clients
- Security headers missing (CSP, HSTS, X-Frame-Options, X-Content-Type-Options)
- Dependencies with known CVEs (flag any outdated packages in package.json / requirements.txt etc.)
- Overly permissive file upload handling (no type/size validation)
- Insecure cookie flags (missing HttpOnly, Secure, SameSite)

**LOW**
- Verbose error messages in non-production modes not gated by environment check
- TODO/FIXME comments referencing security concerns
- Debug endpoints or flags left in codebase
- Commented-out auth code

For each finding, output:
- File path + line number(s)
- Exact problematic code snippet
- Why it's a vulnerability
- Concrete fix with corrected code example

---

## SUB-AGENT 2 — OPTIMIZATION AUDIT
*Persona: Staff engineer obsessed with clean, performant, maintainable code*

Analyze every file for:

**Dead Code**
- Functions defined but never called anywhere
- Imports that are never used
- Variables declared but never read
- Feature flags that are always-on or always-off (hardcoded)
- Commented-out blocks of logic that have never been cleaned up

**Duplication**
- Functions that do the same thing in different files (even if named differently)
- Copy-pasted logic blocks with minor variations that should be a shared utility
- Similar API call wrappers repeated across components/services
- Repeated validation logic that should live in one place

**Performance**
- N+1 query patterns (loops that trigger individual DB calls — should be batch/join)
- Missing database indexes inferred from query patterns
- Synchronous blocking operations where async would work
- Large in-memory data loads that should be paginated or streamed
- Redundant API calls on the same data within a single request lifecycle
- Expensive computations inside render loops or hot paths with no memoization
- Missing caching where the same expensive result is recomputed repeatedly

**Code Quality**
- Functions exceeding ~40 lines that should be decomposed
- Functions doing more than one thing (violating SRP)
- Overly nested conditionals (>3 levels deep) — should be early returns or extracted functions
- Magic numbers/strings that should be named constants
- Inconsistent error handling patterns (some places throw, some return null, some return {error})
- Inconsistent async patterns (mixing callbacks, Promises, and async/await)
- God objects/classes carrying too much responsibility

**Configuration & Structure**
- Environment-specific logic scattered through business code (should be config-driven)
- Hardcoded URLs, timeouts, limits that should be constants or env vars
- Missing or inconsistent logging (some paths log everything, others nothing)

For each finding:
- File path + line number(s)
- What the issue is and why it matters
- Recommended refactor with example

---

## SUB-AGENTS 3.x — TRACEABILITY AUDIT (one sub-agent per layer)

*Persona: QA architect doing end-to-end contract verification — every wire must connect*

First, identify and list ALL layers in this codebase (e.g., UI Components → Event Handlers → API Client → Route Handlers → Middleware → Controllers → Services → Repositories → ORM/Query Layer → Database Schema). Spawn one sub-agent per layer transition.

Each layer sub-agent does the following between its layer and the next:

### For every UI element that triggers an action (button, link, form submit, programmatic call):
- What function/handler does it call?
- What arguments does it pass? (names, types, shape of objects)
- Are all required arguments always present, or can any be undefined/null when they shouldn't be?

### For every function call crossing a layer boundary:
- Caller: what does it pass? (exact argument names, types, optional vs required)
- Callee: what does it expect? (function signature, parameter names, types)
- **MISMATCH CHECK**: Does the caller's output exactly match the callee's expected input?
  - Wrong argument order?
  - Wrong property name (e.g., passes `userId` but function expects `user_id`)?
  - Wrong type (e.g., passes string ID but function expects integer)?
  - Missing required argument?
  - Extra unexpected argument being silently ignored?
- Return value: what does the callee return?
- Does the caller correctly handle what's returned? (checks for null? handles error shape correctly?)

### For every DB query or ORM call:
- What parameters are bound and with what types?
- Does the query return what the calling code expects? (column names, data shape)
- Are there queries that could return null/undefined that the caller doesn't guard against?
- Are transactions used where they should be (multi-step writes)?

### Trace every complete user journey end-to-end:
Pick every distinct user-facing action and walk it from click/trigger → all the way to DB → and back up to UI render. Document the full call chain, and flag any broken link.

For each finding:
- The full call chain context (A → B → C where the break is between B and C)
- Exact file paths and line numbers for both sides of the break
- What was passed vs what was expected
- Whether this would be a silent bug, a runtime crash, or a type error

---

## FINAL REPORT ASSEMBLY

After all sub-agents complete, compile everything into a single file: `audit_todo.md`

Structure it as follows:

```markdown
# Codebase Audit Report
Generated: [date]
Project: [detected project name]
Layers identified: [list]

---

## 🔴 CRITICAL SECURITY ISSUES
[each issue as a task item with full detail]

## 🟠 HIGH SECURITY ISSUES
...

## 🟡 MEDIUM SECURITY ISSUES
...

## 🔵 LOW SECURITY ISSUES
...

---

## ⚡ PERFORMANCE OPTIMIZATIONS
...

## 🧹 DEAD CODE & DUPLICATION
...

## 🏗️ CODE QUALITY & STRUCTURE
...

---

## 🔗 TRACEABILITY BREAKS — UI TO API
...

## 🔗 TRACEABILITY BREAKS — API TO SERVICE
...

## 🔗 TRACEABILITY BREAKS — SERVICE TO DB
...

## 🔗 TRACEABILITY BREAKS — RETURN PATH
...

---

## 📋 MASTER TASK LIST (for sub-agents)

Each item below is self-contained and actionable:

- [ ] [CRITICAL-SEC-001] <one-line summary> | File: x.js:42 | Effort: S/M/L
- [ ] [HIGH-SEC-002] ...
- [ ] [PERF-001] ...
- [ ] [TRACE-UI-001] ...
```

Rules for the master task list:
- Every item has a unique ID with category prefix
- Every item includes the exact file and line number
- Every item includes estimated effort: S (< 30 min), M (30 min – 2 hrs), L (> 2 hrs)
- Items are ordered within each category: highest impact first
- Each item is written so a sub-agent can pick it up cold, with no other context, and fix it