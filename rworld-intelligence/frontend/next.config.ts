import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  // Allow images from any HTTPS source
  images: {
    remotePatterns: [
      { protocol: "https", hostname: "**" },
    ],
  },
  // Expose the API URL to the browser
  env: {
    NEXT_PUBLIC_API_URL: process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000",
  },
  // Don't fail production builds on TypeScript errors
  typescript: {
    ignoreBuildErrors: true,
  },
  // Don't fail production builds on ESLint errors
  eslint: {
    ignoreDuringBuilds: true,
  },
};

export default nextConfig;
