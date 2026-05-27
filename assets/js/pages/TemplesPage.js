/**
 * Temples Page Components
 */

function TemplesPage() {
    const { navigate } = useApp();
    const [temples, setTemples] = useState([]);
    const [loading, setLoading] = useState(true);
    
    useEffect(() => {
        API.get('/temples').then(data => {
            setTemples(data.items || []);
            setLoading(false);
        }).catch(() => setLoading(false));
    }, []);
    
    if (loading) return React.createElement('div', { className: 'section', style: { textAlign: 'center', padding: '4rem' } }, 'Loading...');
    
    return React.createElement('section', { className: 'section', style: { paddingTop: 'var(--space-xl)' } },
        React.createElement('div', { className: 'container' },
            React.createElement('div', { style: { textAlign: 'center', marginBottom: 'var(--space-2xl)' } },
                React.createElement('span', { className: 'eyebrow' }, 'Sacred Spaces · Divine Energy'),
                React.createElement('h1', { className: 'section-title', style: { marginBottom: 'var(--space-sm)' } }, 'Our Temples'),
                React.createElement('p', { className: 'lede', style: { margin: '0 auto' } }, 'Visit our sacred spaces for divine blessings and spiritual awakening.')
            ),
            
            temples.length === 0
                ? React.createElement('div', { style: { textAlign: 'center', padding: '3rem' } },
                    React.createElement('p', { style: { color: 'var(--color-text-muted)' } }, 'No temples listed at the moment.')
                )
                : React.createElement('div', { className: 'showcase-grid' },
                    temples.map(temple =>
                        React.createElement('article', { key: temple.slug, className: 'showcase-card reveal' },
                            React.createElement('div', { style: { background: 'var(--color-bg-alt)', borderRadius: 'var(--radius-md)', marginBottom: 'var(--space-md)', height: '180px', display: 'flex', alignItems: 'center', justifyContent: 'center' } },
                                temple.image_url 
                                    ? React.createElement('img', { src: temple.image_url, alt: temple.name, style: { width: '100%', height: '100%', objectFit: 'cover', borderRadius: 'var(--radius-md)' } })
                                    : React.createElement('span', { style: { fontSize: '3rem' } }, '🛕')
                            ),
                            React.createElement('h2', null, temple.name),
                            React.createElement('p', null, temple.description),
                            temple.address && React.createElement('p', { style: { marginTop: 'var(--space-sm)', fontSize: '0.85rem', color: 'var(--color-text-muted)' } },
                                '📍 ', temple.address
                            ),
                            temple.timings && React.createElement('p', { style: { fontSize: '0.85rem', color: 'var(--color-text-muted)' } },
                                '🕐 ', temple.timings
                            ),
                            React.createElement('div', { style: { marginTop: 'var(--space-md)', display: 'flex', gap: 'var(--space-xs)' } },
                                React.createElement('button', { className: 'btn btn-sm btn-primary', onClick: () => navigate(`/temples/${temple.slug}`) }, 'View Details'),
                                temple.map_link && React.createElement('a', { href: temple.map_link, target: '_blank', rel: 'noopener', className: 'btn btn-sm btn-outline' }, 'Get Directions')
                            )
                        )
                    )
                )
        )
    );
}

function TempleDetailPage() {
    const { navigate } = useApp();
    const [temple, setTemple] = useState(null);
    const [loading, setLoading] = useState(true);
    
    const slug = window.location.pathname.split('/temples/')[1];
    
    useEffect(() => {
        API.get(`/temples/${slug}`).then(data => {
            setTemple(data.temple);
            setLoading(false);
        }).catch(() => setLoading(false));
    }, [slug]);
    
    if (loading) return React.createElement('div', { className: 'section', style: { textAlign: 'center', padding: '4rem' } }, 'Loading...');
    if (!temple) return React.createElement('div', { className: 'section', style: { textAlign: 'center', padding: '4rem' } },
        React.createElement('h2', null, 'Temple Not Found'),
        React.createElement('button', { className: 'btn btn-primary', onClick: () => navigate('/temples') }, 'Back to Temples')
    );
    
    return React.createElement('section', { className: 'section', style: { paddingTop: 'var(--space-xl)' } },
        React.createElement('div', { className: 'container container--narrow' },
            React.createElement('div', { style: { textAlign: 'center', marginBottom: 'var(--space-2xl)' } },
                React.createElement('div', { style: { background: 'var(--color-bg-alt)', borderRadius: 'var(--radius-lg)', marginBottom: 'var(--space-lg)', height: '250px', display: 'flex', alignItems: 'center', justifyContent: 'center' } },
                    temple.image_url 
                        ? React.createElement('img', { src: temple.image_url, alt: temple.name, style: { width: '100%', height: '100%', objectFit: 'cover', borderRadius: 'var(--radius-lg)' } })
                        : React.createElement('span', { style: { fontSize: '4rem' } }, '🛕')
                ),
                React.createElement('span', { className: 'eyebrow' }, 'Sacred Space'),
                React.createElement('h1', { style: { fontFamily: 'var(--font-serif)', margin: 'var(--space-sm) 0' } }, temple.name),
                React.createElement('p', { className: 'lede', style: { margin: '0 auto' } }, temple.description)
            ),
            
            React.createElement('div', { className: 'panel', style: { marginBottom: 'var(--space-xl)' } },
                React.createElement('div', { style: { display: 'grid', gap: 'var(--space-md)' } },
                    temple.address && React.createElement('div', { style: { display: 'flex', alignItems: 'center', gap: 'var(--space-sm)' } },
                        React.createElement('span', null, '📍'),
                        React.createElement('span', null, temple.address)
                    ),
                    temple.phone && React.createElement('div', { style: { display: 'flex', alignItems: 'center', gap: 'var(--space-sm)' } },
                        React.createElement('span', null, '📞'),
                        React.createElement('a', { href: `tel:${temple.phone.replace(/\s/g, '')}`, style: { color: 'var(--color-maroon)' } }, temple.phone)
                    ),
                    temple.timings && React.createElement('div', { style: { display: 'flex', alignItems: 'center', gap: 'var(--space-sm)' } },
                        React.createElement('span', null, '🕐'),
                        React.createElement('span', null, temple.timings)
                    ),
                    temple.pooja_types && temple.pooja_types.length > 0 && React.createElement('div', { style: { display: 'flex', alignItems: 'flex-start', gap: 'var(--space-sm)' } },
                        React.createElement('span', null, '🙏'),
                        React.createElement('div', null,
                            React.createElement('strong', null, 'Available Poojas:'),
                            React.createElement('div', { style: { display: 'flex', flexWrap: 'wrap', gap: 'var(--space-xs)', marginTop: 'var(--space-xs)' } },
                                temple.pooja_types.map(pooja => 
                                    React.createElement('span', { key: pooja, className: 'badge badge--info' }, pooja)
                                )
                            )
                        )
                    )
                )
            ),
            
            temple.map_embed_url && React.createElement('div', { style: { marginBottom: 'var(--space-xl)' } },
                React.createElement('h2', { style: { fontFamily: 'var(--font-serif)', textAlign: 'center', marginBottom: 'var(--space-lg)' } }, 'Location Map'),
                React.createElement('div', { style: { borderRadius: 'var(--radius-lg)', overflow: 'hidden', border: '1px solid var(--color-border)' } },
                    React.createElement('iframe', {
                        src: temple.map_embed_url,
                        width: '100%',
                        height: '400',
                        style: { border: 0 },
                        allowFullScreen: true,
                        loading: 'lazy',
                        referrerPolicy: 'no-referrer-when-downgrade'
                    })
                ),
                temple.map_link && React.createElement('p', { style: { textAlign: 'center', marginTop: 'var(--space-md)' } },
                    React.createElement('a', { href: temple.map_link, target: '_blank', rel: 'noopener', className: 'btn btn-sm btn-outline' }, 'Open in Google Maps')
                )
            ),
            
            React.createElement('div', { style: { textAlign: 'center' } },
                React.createElement('button', { className: 'btn btn-primary', onClick: () => navigate('/contact') }, 'Contact Us'),
                React.createElement('button', { className: 'btn btn-ghost', style: { marginLeft: 'var(--space-sm)' }, onClick: () => navigate('/temples') }, 'View All Temples')
            )
        )
    );
}

window.TemplesPage = TemplesPage;
window.TempleDetailPage = TempleDetailPage;
