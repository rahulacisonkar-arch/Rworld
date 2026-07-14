/**
 * Central API base URL utility.
 * In production (Vercel), set NEXT_PUBLIC_API_URL as an environment variable.
 * Falls back to localhost for local development.
 */
export const API_BASE_URL =
  process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000";
