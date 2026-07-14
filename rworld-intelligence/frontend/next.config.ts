import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  // Allow images from any HTTPS source (for product images from scrapers etc.)
  images: {
    remotePatterns: [
      { protocol: "https", hostname: "**" },
    ],
  },
  // Expose the API URL to the browser
  env: {
    NEXT_PUBLIC_API_URL: process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000",
  },
};

export default nextConfig;
