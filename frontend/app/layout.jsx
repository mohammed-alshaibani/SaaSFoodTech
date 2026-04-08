import { IBM_Plex_Sans_Arabic } from "next/font/google";
import { Inter } from "next/font/google";
import { AppProvider } from "@/context/AppContext";
import { I18nProvider } from "@/context/I18nContext";
import DynamicLayout from "@/components/DynamicLayout";
import "./globals.css";

const ibmPlexArabic = IBM_Plex_Sans_Arabic({
  variable: "--font-ibm-plex-arabic",
  subsets: ["arabic"],
  weight: ["300", "400", "500", "600", "700"],
});

const inter = Inter({
  variable: "--font-inter",
  subsets: ["latin"],
});

export const metadata = {
  title: "ServiceHub | Enterprise Service Marketplace",
  description: "Simplifying modern service delivery for businesses and providers.",
};

export default function RootLayout({ children }) {
  return (
    <html lang="ar" dir="rtl" className={`${inter.variable} ${ibmPlexArabic.variable} h-full antialiased h-screen overflow-x-hidden`}>
      <body className="min-h-full flex flex-col bg-slate-50 text-slate-900 font-sans">
        <I18nProvider>
          <AppProvider>
            <DynamicLayout>
              {children}
            </DynamicLayout>
          </AppProvider>
        </I18nProvider>
      </body>
    </html>
  );
}
