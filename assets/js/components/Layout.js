/**
 * React Components for Sri Panchami Spiritual
 * Using React via CDN - no build step
 */

const { useState, useEffect, useContext, createContext } = React;

// ============================================
// Context for Global State
// ============================================
const AppContext = createContext();

const useApp = () => useContext(AppContext);

// ============================================
// Layout Components
// ============================================

// Header Component
function Header() {
    const { cartCount, navigate } = useApp();
    const [menuOpen, setMenuOpen] = useState(false);
    
    return React.createElement('header', { className: 'site-header', id: 'site-header' },
        React.createElement('a', { href: '/', className: 'brand', onClick: (e) => { e.preventDefault(); navigate('/'); } },
            React.createElement('img', { src: '/assets/images/logo-square.jpeg', alt: 'Sri Panchami Spiritual logo' }),
            React.createElement('span', null, 'Sri Panchami Spiritual')
        ),
        React.createElement('button', { 
            className: 'menu-toggle', 
            onClick: () => setMenuOpen(!menuOpen),
            'aria-expanded': menuOpen 
        }, '☰'),
        React.createElement('nav', { id: 'primary-nav', className: menuOpen ? 'open' : '' },
            React.createElement(NavLink, { href: '/', label: 'Home' }),
            React.createElement(NavLink, { href: '/shop', label: 'Shop' }),
            React.createElement(NavLink, { href: '/temples', label: 'Temples' }),
            React.createElement(NavLink, { href: '/astrologers', label: 'Astrologers' }),
            React.createElement(NavLink, { href: '/about', label: 'About Us' }),
            React.createElement(NavLink, { href: '/contact', label: 'Contact Us' }),
            React.createElement(NavLink, { href: '/login', label: 'Login' })
        ),
        React.createElement('div', { className: 'header-actions' },
            React.createElement('button', { 
                className: 'cart-btn', 
                'aria-label': 'Shopping cart',
                onClick: () => navigate('/cart')
            },
                '🛒',
                cartCount > 0 && React.createElement('span', { className: 'cart-count' }, cartCount)
            )
        )
    );
}

function NavLink({ href, label }) {
    const { navigate } = useApp();
    return React.createElement('a', { 
        href: href, 
        onClick: (e) => { e.preventDefault(); navigate(href); }
    }, label);
}

// Footer Component
function Footer() {
    const { navigate } = useApp();
    
    return React.createElement('footer', { className: 'site-footer' },
        React.createElement('div', { className: 'footer-grid' },
            React.createElement('div', null,
                React.createElement('span', { className: 'footer-brand' }, 'Sri Panchami Spiritual'),
                React.createElement('p', { className: 'footer-desc' }, 
                    'Authentic spiritual products, sacred jewellery, expert Vedic astrology and temple guidance in Chennai, Tamil Nadu.'
                )
            ),
            React.createElement('div', null,
                React.createElement('h4', { className: 'footer-heading' }, 'Shop'),
                React.createElement('ul', { className: 'footer-links' },
                    React.createElement('li', null, React.createElement('a', { href: '/shop', onClick: (e) => { e.preventDefault(); navigate('/shop'); } }, 'All Products')),
                    React.createElement('li', null, React.createElement('a', { href: '/temples', onClick: (e) => { e.preventDefault(); navigate('/temples'); } }, 'Temples')),
                    React.createElement('li', null, React.createElement('a', { href: '/astrologers', onClick: (e) => { e.preventDefault(); navigate('/astrologers'); } }, 'Astrologers')),
                    React.createElement('li', null, React.createElement('a', { href: '/about', onClick: (e) => { e.preventDefault(); navigate('/about'); } }, 'About Us')),
                    React.createElement('li', null, React.createElement('a', { href: '/contact', onClick: (e) => { e.preventDefault(); navigate('/contact'); } }, 'Contact'))
                )
            ),
            React.createElement('div', null,
                React.createElement('h4', { className: 'footer-heading' }, 'Services'),
                React.createElement('ul', { className: 'footer-links' },
                    React.createElement('li', null, React.createElement('a', { href: '/astrologers', onClick: (e) => { e.preventDefault(); navigate('/astrologers'); } }, 'Astrologers')),
                    React.createElement('li', null, React.createElement('a', { href: '/temples', onClick: (e) => { e.preventDefault(); navigate('/temples'); } }, 'Temples')),
                    React.createElement('li', null, React.createElement('a', { href: '/about', onClick: (e) => { e.preventDefault(); navigate('/about'); } }, 'About Us')),
                    React.createElement('li', null, React.createElement('a', { href: '/contact', onClick: (e) => { e.preventDefault(); navigate('/contact'); } }, 'Contact'))
                )
            ),
            React.createElement('div', null,
                React.createElement('h4', { className: 'footer-heading' }, 'Contact'),
                React.createElement('ul', { className: 'footer-links' },
                    React.createElement('li', null, '23, 1st Cross Street Kothari Nagar'),
                    React.createElement('li', null, 'Ramapuram, Chennai, Tamil Nadu 600089'),
                    React.createElement('li', null, React.createElement('a', { href: 'mailto:sripanchamispiritual@gmail.com' }, 'sripanchamispiritual@gmail.com'))
                )
            )
        ),
        React.createElement('div', { className: 'footer-bottom' },
            `© ${new Date().getFullYear()} Sri Panchami Spiritual · Chennai, Tamil Nadu`
        )
    );
}

// Bottom Navigation (Mobile)
function BottomNav() {
    const { navigate } = useApp();
    const currentPath = window.location.pathname;
    
    const items = [
        { path: '/', icon: '🏠', label: 'Home' },
        { path: '/shop', icon: '🛍️', label: 'Shop' },
        { path: '/temples', icon: '🛕', label: 'Temples' },
        { path: '/astrologers', icon: '⭐', label: 'Astro' },
        { path: '/cart', icon: '🛒', label: 'Cart' }
    ];
    
    return React.createElement('nav', { className: 'bottom-nav' },
        React.createElement('div', { className: 'nav-grid' },
            items.map(item => 
                React.createElement('a', {
                    key: item.path,
                    href: item.path,
                    className: `nav-item ${currentPath === item.path ? 'active' : ''}`,
                    onClick: (e) => { e.preventDefault(); navigate(item.path); }
                },
                    React.createElement('span', { className: 'icon' }, item.icon),
                    React.createElement('span', null, item.label)
                )
            )
        )
    );
}

// Layout Wrapper
function Layout({ children }) {
    return React.createElement(React.Fragment, null,
        React.createElement(Header),
        React.createElement('main', null, children),
        React.createElement(BottomNav),
        React.createElement(Footer)
    );
}

// Export components
window.Header = Header;
window.Footer = Footer;
window.BottomNav = BottomNav;
window.Layout = Layout;
