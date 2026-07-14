"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";

export default function Home() {
  const router = useRouter();

  useEffect(() => {
    router.push("/login");
  }, [router]);

  return (
    <div className="flex-1 flex items-center justify-center bg-gray-950 text-white">
      <div className="text-center space-y-4">
        <div className="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-cyan-500 mx-auto"></div>
        <p className="text-xs text-gray-500 font-semibold tracking-wider uppercase">Loading RWorld Portal...</p>
      </div>
    </div>
  );
}
