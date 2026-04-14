import Navbar from '@/components/landing/Navbar';
import Hero from '@/components/landing/Hero';
import TrustBar from '@/components/landing/TrustBar';
import DashboardMock from '@/components/landing/DashboardMock';
import Features from '@/components/landing/Features';
import Pricing from '@/components/landing/Pricing';
import FAQ from '@/components/landing/FAQ';
import Footer from '@/components/landing/Footer';

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
        title: t.hero?.title || 'ServiceHub | FoodTech SaaS',
        description: t.hero?.description || 'Connect with providers and customers seamlessly.',
        keywords: ['foodtech', 'saas', 'service provider', 'customer management', 'platform'],
    };
}

export default function LandingPage() {
    // This is now a Server Component. It renders the skeleton of the landing page.
    // Interactive parts like Navbar and Hero handle their own Client-side state.

    return (
        <main className="relative bg-slate-50 selection:bg-primary/20 selection:text-primary min-h-screen overflow-x-hidden">

            {/* Structural Elements */}
            <Navbar />

            {/* Sections */}
            <Hero />
            <TrustBar />
            <DashboardMock />
            <Features />
            <Pricing />
            <FAQ />

            {/* Footer */}
            <Footer />

            {/* Floating CTA (Post-Hero) */}
            <div className="fixed bottom-8 right-8 z-40 hidden md:block">
                <a
                    href="/register"
                    className="px-6 py-3 bg-accent text-white rounded-full font-black text-xs uppercase tracking-widest shadow-2xl shadow-accent/20 hover:bg-accent-dark transition-all active:scale-95 flex items-center gap-2"
                >
                    Get Started
                </a>
            </div>

        </main>
    );
}
