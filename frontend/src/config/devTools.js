/**
 * Development-only helpers (the static /demo module preview and the portfolio
 * "Fill Sample" buttons) stay in the codebase but are hidden unless explicitly
 * enabled, so they never appear in a normal or presentation run — including
 * `npm run dev`. Enable locally with VITE_ENABLE_DEV_TOOLS=true in frontend/.env.
 */
export const DEV_TOOLS_ENABLED = import.meta.env.VITE_ENABLE_DEV_TOOLS === 'true'
