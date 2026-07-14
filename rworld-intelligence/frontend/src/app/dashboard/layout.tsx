"use client";

import { useEffect, useState } from "react";
import { useRouter, usePathname } from "next/navigation";
import Link from "next/link";

interface NavItem {
  name: string;
  href: string;
  icon: string;
}

export default function DashboardLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const [username, setUsername] = useState("");
  const [role, setRole] = useState("");
  const [authorized, setAuthorized] = useState(false);
  const [sidebarOpen, setSidebarOpen] = useState(true);
  const router = useRouter();
  const pathname = usePathname();

  useEffect(() => {
    const token = localStorage.getItem("rworld_token");
    const storedUsername = localStorage.getItem("rworld_username");
    const storedRole = localStorage.getItem("rworld_role");

    if (!token) {
      router.push("/login");
    } else {
      setUsername(storedUsername || "User");
      setRole(storedRole || "user");
      setAuthorized(true);
    }
  }, [router]);

  const handleLogout = () => {
    localStorage.clear();
    router.push("/login");
  };

  const navItems: NavItem[] = [
    { name: "Overview Console", href: "/dashboard", icon: "📊" },
    { name: "Artee ERP", href: "/dashboard/erp", icon: "📦" },
    { name: "AI Shipping Agent", href: "/dashboard/shipping-agent", icon: "✈️" },
    { name: "Fabric Scraper", href: "/dashboard/fabric-scraper", icon: "🌐" },
    { name: "RoofIQ AI", href: "/dashboard/roofiq", icon: "🏠" },
    { name: "Attendance", href: "/dashboard/attendance", icon: "🕒" },
    { name: "Utility Management", href: "/dashboard/utility", icon: "⚡" },
    { name: "System Settings", href: "/dashboard/settings", icon: "⚙️" },
  ];

  if (!authorized) {
    return (
      <div className="flex-1 flex items-center justify-center bg-gray-950 text-white">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-cyan-500 mx-auto"></div>
          <p className="mt-4 text-gray-400">Authenticating session...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="flex-1 flex min-h-screen bg-gray-950 text-white">
      {/* Sidebar Navigation */}
      <div
        className={`${
          sidebarOpen ? "w-64" : "w-20"
        } glass-panel border-r border-gray-900 transition-all duration-300 flex flex-col z-20`}
      >
        {/* Header Branding */}
        <div className="p-6 border-b border-gray-900 flex items-center justify-between">
          {sidebarOpen ? (
            <span className="text-xl font-bold tracking-wider text-gradient-cyan">
              RWORLD INTEL
            </span>
          ) : (
            <span className="text-xl font-bold tracking-wider text-gradient-cyan mx-auto">
              RW
            </span>
          )}
          <button
            onClick={() => setSidebarOpen(!sidebarOpen)}
            className="text-gray-400 hover:text-white transition-colors cursor-pointer text-sm"
          >
            {sidebarOpen ? "◀" : "▶"}
          </button>
        </div>

        {/* Navigation Items */}
        <nav className="flex-1 p-4 space-y-2">
          {navItems.map((item) => {
            const isActive = pathname === item.href;
            return (
              <Link
                key={item.name}
                href={item.href}
                className={`flex items-center space-x-4 px-4 py-3 rounded-lg transition-all duration-200 ${
                  isActive
                    ? "bg-cyan-500/10 border-l-4 border-cyan-500 text-cyan-300 font-semibold"
                    : "text-gray-400 hover:bg-gray-900 hover:text-white"
                }`}
              >
                <span className="text-lg">{item.icon}</span>
                {sidebarOpen && <span className="text-sm">{item.name}</span>}
              </Link>
            );
          })}
        </nav>

        {/* Profile Card and Logout */}
        <div className="p-4 border-t border-gray-900">
          {sidebarOpen ? (
            <div className="flex items-center justify-between mb-4">
              <div className="flex flex-col">
                <span className="text-sm font-semibold text-gray-200">{username}</span>
                <span className="text-xs text-cyan-400 capitalize">{role}</span>
              </div>
              <button
                onClick={handleLogout}
                className="text-xs px-2 py-1 bg-red-950/40 border border-red-500/20 text-red-300 rounded hover:bg-red-900/40 transition-colors cursor-pointer"
              >
                Exit
              </button>
            </div>
          ) : (
            <button
              onClick={handleLogout}
              className="w-full text-center text-lg text-red-400 hover:text-red-300 py-2 cursor-pointer"
              title="Logout"
            >
              🚪
            </button>
          )}
        </div>
      </div>

      {/* Main Content Workspace */}
      <div className="flex-1 flex flex-col overflow-y-auto">
        <header className="h-16 border-b border-gray-900 px-8 flex items-center justify-between bg-gray-950/40 backdrop-blur-md">
          <h2 className="text-lg font-bold text-gray-200 capitalize">
            {pathname.split("/").pop() || "Overview"}
          </h2>
          <div className="flex items-center space-x-4">
            <span className="w-2 h-2 bg-emerald-500 rounded-full animate-ping"></span>
            <span className="text-xs text-gray-400">Gateway Status: Online</span>
          </div>
        </header>

        <main className="flex-1 p-8 relative">
          <div className="absolute top-10 right-10 w-80 h-80 bg-cyan-500/5 rounded-full blur-3xl -z-10 pointer-events-none"></div>
          {children}
        </main>
      </div>
    </div>
  );
}
