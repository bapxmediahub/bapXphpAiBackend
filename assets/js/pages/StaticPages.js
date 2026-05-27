/**
 * Static Pages (About, Spiritual)
 */

function AboutPage() {
    return React.createElement('section', { className: 'section', style: { paddingTop: 'var(--space-xl)' } },
        React.createElement('div', { className: 'container container--narrow' },
            React.createElement('div', { style: { textAlign: 'center', marginBottom: 'var(--space-2xl)' } },
                React.createElement('span', { className: 'eyebrow' }, 'Our Story'),
                React.createElement('h1', { className: 'section-title' }, 'About Us'),
                React.createElement('p', { className: 'lede' }, 'Dedicated to bringing authentic spiritual products and services to devotees worldwide.')
            ),
            
            React.createElement('div', { className: 'panel', style: { marginBottom: 'var(--space-xl)' } },
                React.createElement('h2', { style: { fontFamily: 'var(--font-serif)' } }, 'Our Mission'),
                React.createElement('p', null, 'Sri Panchami Spiritual is committed to providing genuine spiritual products, expert astrology consultations, and temple guidance. We source our products directly from trusted artisans and ensure every item carries divine energy.')
            ),
            
            React.createElement('div', { className: 'panel' },
                React.createElement('h2', { style: { fontFamily: 'var(--font-serif)' } }, 'Why Choose Us'),
                React.createElement('ul', { style: { paddingLeft: 'var(--space-lg)' } },
                    React.createElement('li', null, 'Authentic and tested spiritual products'),
                    React.createElement('li', null, 'Expert Vedic astrologers with years of experience'),
                    React.createElement('li', null, 'Temple partnership for genuine pooja items'),
                    React.createElement('li', null, 'Secure payment via Razorpay'),
                    React.createElement('li', null, 'Fast delivery across India')
                )
            )
        )
    );
}

function SpiritualPage() {
    return React.createElement('section', { className: 'section', style: { paddingTop: 'var(--space-xl)' } },
        React.createElement('div', { className: 'container container--narrow' },
            React.createElement('div', { style: { textAlign: 'center', marginBottom: 'var(--space-2xl)' } },
                React.createElement('span', { className: 'eyebrow' }, 'Divine Journey'),
                React.createElement('h1', { className: 'section-title' }, 'Sri Panchami Spiritual'),
                React.createElement('p', { className: 'lede' }, 'Your trusted partner for spiritual growth and divine protection.')
            ),
            
            React.createElement('div', { className: 'panel' },
                React.createElement('p', null, 'Welcome to Sri Panchami Spiritual, your destination for authentic spiritual products, sacred jewelry, expert Vedic astrology, and temple guidance. We are dedicated to helping you on your spiritual journey with genuine products and services.')
            )
        )
    );
}

// 404 Page
function NotFoundPage() {
    const { navigate } = useApp();
    
    return React.createElement('section', { className: 'section', style: { textAlign: 'center', padding: '4rem' } },
        React.createElement('h1', { style: { fontSize: '4rem', fontFamily: 'var(--font-serif)' } }, '404'),
        React.createElement('p', { style: { color: 'var(--color-text-muted)', marginBottom: 'var(--space-lg)' } }, 'Page not found'),
        React.createElement('button', { className: 'btn btn-primary', onClick: () => navigate('/') }, 'Go Home')
    );
}

window.AboutPage = AboutPage;
window.SpiritualPage = SpiritualPage;
window.NotFoundPage = NotFoundPage;
