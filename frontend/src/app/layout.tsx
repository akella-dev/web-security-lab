import type { Metadata } from "next";

import "./globals.css";
import "./investigation.css";
import "./honey-shop.css";
import "./academy.css";

export const metadata: Metadata = {
  title: "Web Security Lab — практикум по безопасности",
  description: "Локальный учебный полигон безопасной веб-разработки",
  robots: { index: false, follow: false },
};

export default function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  return (
    <html lang="ru">
      <body>{children}</body>
    </html>
  );
}
