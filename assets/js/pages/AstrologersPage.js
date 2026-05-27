/**
 * Astrologers Page Components
 */

function AstrologersPage() {
    const { navigate } = useApp();
    const [astrologers, setAstrologers] = useState([]);
    const [loading, setLoading] = useState(true);
    
    useEffect(() => {
        API.get('/astrologers').then(data => {
            setAstrologers(data.items || []);
            setLoading(false);
        }).catch(() => setLoading(false));
    }, []);
    
    if (loading) return React.createElement('div', { className: 'section', style: { textAlign: 'center', padding: '4rem' } }, 'Loading...');
    
    return React.createElement('section', { className: 'section', style: { paddingTop: 'var(--space-xl)' } },
        React.createElement('div', { className: 'container' },
            React.createElement('div', { style: { textAlign: 'center', marginBottom: 'var(--space-2xl)' } },
                React.createElement('span', { className: 'eyebrow' }, 'Expert Guidance · Divine Wisdom'),
                React.createElement('h1', { className: 'section-title', style: { marginBottom: 'var(--space-sm)' } }, 'Our Astrologers'),
                React.createElement('p', { className: 'lede', style: { margin: '0 auto' } }, 'Consult experienced Vedic astrologers for accurate predictions and remedy guidance.')
            ),
            
            astrologers.length === 0
                ? React.createElement('div', { style: { textAlign: 'center', padding: '3rem' } },
                    React.createElement('p', { style: { color: 'var(--color-text-muted)' } }, 'No astrologers available at the moment.')
                )
                : React.createElement('div', { className: 'astrologer-grid' },
                    astrologers.map(astro =>
                        React.createElement('article', { key: astro.slug, className: 'astrologer-card' },
                            React.createElement('div', { className: 'astrologer-card__header' },
                                React.createElement('img', { className: 'astrologer-card__photo', src: astro.photo_url || 'https://placehold.co/100x100/fdfbf7/d4af37?text=Guru', alt: astro.name }),
                                React.createElement('div', null,
                                    React.createElement('h3', { className: 'astrologer-card__name' }, astro.name),
                                    React.createElement('p', { className: 'astrologer-card__speciality' }, astro.speciality || 'Vedic Astrology')
                                )
                            ),
                            React.createElement('div', { className: 'astrologer-card__body' },
                                React.createElement('div', { className: 'astrologer-card__stat' },
                                    React.createElement('span', { className: 'astrologer-card__stat-label' }, 'Experience'),
                                    React.createElement('span', { className: 'astrologer-card__stat-value' }, `${astro.experience_years || 'N/A'} yrs`)
                                ),
                                React.createElement('div', { className: 'astrologer-card__stat' },
                                    React.createElement('span', { className: 'astrologer-card__stat-label' }, 'Languages'),
                                    React.createElement('span', { className: 'astrologer-card__stat-value' }, (astro.languages || []).slice(0, 2).join(', '))
                                ),
                                React.createElement('div', { className: 'astrologer-card__stat' },
                                    React.createElement('span', { className: 'astrologer-card__stat-label' }, 'Modes'),
                                    React.createElement('span', { className: 'astrologer-card__stat-value' }, (astro.modes || ['Chat', 'Call']).join(', '))
                                )
                            ),
                            React.createElement('div', { className: 'astrologer-card__footer' },
                                React.createElement('span', { className: 'astrologer-card__price' }, Utils.formatPrice(astro.price || 0)),
                                React.createElement('button', { className: 'btn btn-sm btn-primary', onClick: () => navigate(`/astrologers/${astro.slug}`) }, 'Book Now')
                            )
                        )
                    )
                )
        )
    );
}

function AstrologerDetailPage() {
    const { navigate } = useApp();
    const [astrologer, setAstrologer] = useState(null);
    const [slots, setSlots] = useState([]);
    const [selectedDate, setSelectedDate] = useState(new Date().toISOString().split('T')[0]);
    const [loading, setLoading] = useState(true);
    
    const slug = window.location.pathname.split('/astrologers/')[1];
    
    useEffect(() => {
        API.get(`/astrologers/${slug}?date=${selectedDate}`).then(data => {
            setAstrologer(data.astrologer);
            setSlots(data.slots || []);
            setLoading(false);
        }).catch(() => setLoading(false));
    }, [slug, selectedDate]);
    
    if (loading) return React.createElement('div', { className: 'section', style: { textAlign: 'center', padding: '4rem' } }, 'Loading...');
    if (!astrologer) return React.createElement('div', { className: 'section', style: { textAlign: 'center', padding: '4rem' } },
        React.createElement('h2', null, 'Astrologer Not Found'),
        React.createElement('button', { className: 'btn btn-primary', onClick: () => navigate('/astrologers') }, 'Back to Astrologers')
    );
    
    return React.createElement('section', { className: 'section', style: { paddingTop: 'var(--space-xl)' } },
        React.createElement('div', { className: 'booking-layout' },
            // Profile
            React.createElement('div', { className: 'booking-profile' },
                React.createElement('img', { className: 'booking-profile__photo', src: astrologer.photo_url || 'https://placehold.co/100x100/fdfbf7/d4af37?text=Guru', alt: astrologer.name }),
                React.createElement('div', null,
                    React.createElement('h1', { className: 'booking-profile__name' }, astrologer.name),
                    React.createElement('p', { className: 'booking-profile__meta' }, astrologer.speciality || 'Vedic Astrology'),
                    React.createElement('p', { className: 'booking-profile__meta' }, `${astrologer.experience_years || 'N/A'} years experience`),
                    React.createElement('p', { className: 'booking-profile__meta' }, `Languages: ${(astrologer.languages || []).join(', ')}`)
                )
            ),
            
            // Slot Picker
            React.createElement('div', { className: 'slot-picker' },
                React.createElement('h3', null, 'Select Date & Time'),
                React.createElement('div', { className: 'slot-picker__form' },
                    React.createElement('div', { className: 'form-group' },
                        React.createElement('label', { htmlFor: 'date' }, 'Date'),
                        React.createElement('input', { 
                            type: 'date', 
                            id: 'date', 
                            value: selectedDate,
                            min: new Date().toISOString().split('T')[0],
                            onChange: (e) => setSelectedDate(e.target.value)
                        })
                    )
                ),
                
                slots.length === 0
                    ? React.createElement('p', { style: { color: 'var(--color-text-muted)', textAlign: 'center', padding: '2rem' } }, 'No slots available for this date.')
                    : React.createElement('div', { className: 'slot-grid' },
                        slots.map((slot, idx) =>
                            React.createElement('div', { key: idx, className: 'slot-card' },
                                React.createElement('div', { className: 'slot-card__time' }, slot.time),
                                React.createElement('button', { className: 'btn btn-sm btn-primary', onClick: () => navigate(`/astrologers/${slug}/book?slot=${slot.time}&date=${selectedDate}`) }, 'Book')
                            )
                        )
                    )
            )
        )
    );
}

window.AstrologersPage = AstrologersPage;
window.AstrologerDetailPage = AstrologerDetailPage;
