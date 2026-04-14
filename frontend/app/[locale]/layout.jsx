import { IBM_Plex_Sans_Arabic } from "next/font/google";
import { Inter } from "next/font/google";
import { AppProvider } from "@/context/AppContext";
import { I18nProvider } from "@/context/I18nContext";
import DynamicLayout from "@/components/DynamicLayout";
import { Suspense } from "react";
import "../globals.css";

const ibmPlexArabic = IBM_Plex_Sans_Arabic({
  variable: "--font-ibm-plex-arabic",
  subsets: ["arabic"],
  weight: ["300", "400", "500", "600", "700"],
});

const inter = Inter({ variable: "--font-inter", subsets: ["latin"] });

export async function generateStaticParams() {
  return [{ locale: 'en' }, { locale: 'ar' }];
}

async function getTranslations(locale) {
  try {
    const translations = await import(`../../public/locales/${locale}/common.json`);
    return translations.default;
  } catch (e) {
    return {};
  }
}

export async function generateMetadata({ params }) {
  const { locale } = params;
  const t = await getTranslations(locale);
  return {
    title: t.common?.title || "ServiceHub | Enterprise Service Marketplace",
    description: t.common?.description || "Simplifying modern service delivery.",
  };
}

export default async function RootLayout({ children, params }) {
  const { locale } = params;
  const translations = await getTranslations(locale);

  return (
    <html lang={locale} dir={locale === 'ar' ? 'rtl' : 'ltr'} className={`${inter.variable} ${ibmPlexArabic.variable} h-full antialiased h-screen overflow-x-hidden`}>
      <body className="min-h-full flex flex-col bg-slate-50 text-slate-900 font-sans">
        <I18nProvider initialLocale={locale} initialTranslations={translations}>
          <AppProvider>
            <DynamicLayout>
              <Suspense fallback={<div className="min-h-screen flex items-center justify-center bg-slate-50">
                <div className="w-8 h-8 border-4 border-blue-600 border-t-transparent rounded-full animate-spin" />
              </div>}>
                {children}
              </Suspense>
            </DynamicLayout>
          </AppProvider>
        </I18nProvider>
      </body>
    </html>
  );
}
