/**
 * Contact Page Component
 */

function ContactPage() {
    const navigate = useApp().navigate;
    const [submitted, setSubmitted] = useState(false);
    const [formData, setFormData] = useState({
        name: '',
        email: '',
        phone: '',
        subject: '',
        message: ''
    });
    
    const handleChange = (e) => {
        setFormData({ ...formData, [e.target.name]: e.target.value });
    };
    
    const handleSubmit = async (e) => {
        e.preventDefault();
        try {
            await API.post('/contact', formData);
            setSubmitted(true);
        } catch (error) {
            alert('Failed to send message. Please try again.');
        }
    };
    
    return React.createElement('section', { className: 'section' },
        React.createElement('div', { className: 'container container--narrow' },
            React.createElement('div', { style: { textAlign: 'center', marginBottom: 'var(--space-2xl)' } },
                React.createElement('span', { className: 'eyebrow' }, 'Get in Touch'),
                React.createElement('h1', { className: 'section-title', style: { marginBottom: 'var(--space-sm)' } }, 'Contact Us'),
                React.createElement('p', { className: 'lede', style: { margin: '0 auto' } }, "We'd love to hear from you. Reach out for any spiritual guidance or support.")
            ),
            
            submitted && React.createElement('div', { className: 'flash flash--success', style: { marginBottom: 'var(--space-lg)' } },
                '✓ Thank you for your message. We will get back to you soon.'
            ),
            
            React.createElement('div', { className: 'grid', style: { marginBottom: 'var(--space-2xl)' } },
                React.createElement('div', { className: 'panel', style: { textAlign: 'center' } },
                    React.createElement('span', { style: { display: 'block', marginBottom: 'var(--space-sm)', fontSize: '2rem' } }, '📧'),
                    React.createElement('h3', { style: { fontFamily: 'var(--font-serif)', margin: '0 0 var(--space-xs)' } }, 'Email'),
                    React.createElement('a', { href: 'mailto:sripanchamispiritual@gmail.com', style: { color: 'var(--color-maroon)' } }, 'sripanchamispiritual@gmail.com')
                ),
                React.createElement('div', { className: 'panel', style: { textAlign: 'center' } },
                    React.createElement('span', { style: { display: 'block', marginBottom: 'var(--space-sm)', fontSize: '2rem' } }, '📍'),
                    React.createElement('h3', { style: { fontFamily: 'var(--font-serif)', margin: '0 0 var(--space-xs)' } }, 'Address'),
                    React.createElement('p', { style: { color: 'var(--color-text-muted)', fontSize: '0.9rem', margin: 0 } }, 
                        '23, 1st Cross Street Kothari Nagar,', React.createElement('br'),
                        'Ramapuram, Chennai,', React.createElement('br'),
                        'Tamil Nadu 600089'
                    )
                ),
                React.createElement('div', { className: 'panel', style: { textAlign: 'center' } },
                    React.createElement('span', { style: { display: 'block', marginBottom: 'var(--space-sm)', fontSize: '2rem' } }, '🏢'),
                    React.createElement('h3', { style: { fontFamily: 'var(--font-serif)', margin: '0 0 var(--space-xs)' } }, 'Business'),
                    React.createElement('p', { style: { color: 'var(--color-text-muted)', fontSize: '0.9rem', margin: 0 } }, 'Reg. No: 33BZRPM8732J2ZQ')
                )
            ),
            
            React.createElement('div', { className: 'admin-card', style: { marginBottom: 'var(--space-2xl)' } },
                React.createElement('div', { style: { textAlign: 'center', marginBottom: 'var(--space-lg)' } },
                    React.createElement('span', { style: { display: 'block', marginBottom: 'var(--space-sm)', fontSize: '2rem' } }, '🕐'),
                    React.createElement('h3', { style: { fontFamily: 'var(--font-serif)', margin: '0 0 var(--space-sm)' } }, 'Sacred Service Hours'),
                    React.createElement('p', { style: { color: 'var(--color-text-muted)', fontSize: '0.9rem', margin: 0 } }, 
                        'Monday – Saturday: 9:00 AM – 7:00 PM', React.createElement('br'),
                        'Sunday: 10:00 AM – 5:00 PM'
                    )
                )
            ),
            
            React.createElement('div', { className: 'admin-card', style: { marginBottom: 'var(--space-2xl)' } },
                React.createElement('h2', { style: { fontFamily: 'var(--font-serif)', textAlign: 'center', marginBottom: 'var(--space-lg)' } }, 'Send Us a Message'),
                React.createElement('form', { onSubmit: handleSubmit, className: 'admin-form', style: { maxWidth: '600px', margin: '0 auto' } },
                    React.createElement('div', { className: 'admin-form__row' },
                        React.createElement('div', { className: 'form-group' },
                            React.createElement('label', { htmlFor: 'name' }, 'Full Name'),
                            React.createElement('input', { type: 'text', id: 'name', name: 'name', value: formData.name, onChange: handleChange, required: true, placeholder: 'Your name' })
                        ),
                        React.createElement('div', { className: 'form-group' },
                            React.createElement('label', { htmlFor: 'email' }, 'Email'),
                            React.createElement('input', { type: 'email', id: 'email', name: 'email', value: formData.email, onChange: handleChange, required: true, placeholder: 'your@email.com' })
                        )
                    ),
                    React.createElement('div', { className: 'admin-form__row' },
                        React.createElement('div', { className: 'form-group' },
                            React.createElement('label', { htmlFor: 'phone' }, 'Phone'),
                            React.createElement('input', { type: 'tel', id: 'phone', name: 'phone', value: formData.phone, onChange: handleChange, placeholder: '+91 98765 43210' })
                        ),
                        React.createElement('div', { className: 'form-group' },
                            React.createElement('label', { htmlFor: 'subject' }, 'Subject'),
                            React.createElement('select', { id: 'subject', name: 'subject', value: formData.subject, onChange: handleChange, required: true },
                                React.createElement('option', { value: '' }, 'Select a subject'),
                                React.createElement('option', { value: 'general' }, 'General Inquiry'),
                                React.createElement('option', { value: 'product' }, 'Product Question'),
                                React.createElement('option', { value: 'order' }, 'Order Support'),
                                React.createElement('option', { value: 'astrology' }, 'Astrology Consultation'),
                                React.createElement('option', { value: 'temple' }, 'Temple Information'),
                                React.createElement('option', { value: 'other' }, 'Other')
                            )
                        )
                    ),
                    React.createElement('div', { className: 'form-group' },
                        React.createElement('label', { htmlFor: 'message' }, 'Message'),
                        React.createElement('textarea', { id: 'message', name: 'message', value: formData.message, onChange: handleChange, required: true, placeholder: 'How can we help you?', rows: 5 })
                    ),
                    React.createElement('button', { type: 'submit', className: 'btn btn-primary btn-block' }, 'Send Message')
                )
            ),
            
            React.createElement('div', null,
                React.createElement('h2', { style: { fontFamily: 'var(--font-serif)', textAlign: 'center', marginBottom: 'var(--space-lg)' } }, 'Find Us Here'),
                React.createElement('div', { style: { borderRadius: 'var(--radius-lg)', overflow: 'hidden', border: '1px solid var(--color-border)' } },
                    React.createElement('iframe', {
                        src: 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3888.5!2d80.1767!3d13.0166!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3a5267c07a315555%3A0x9c2b1c5c5c5c5c5c!2sRamapuram%2C%20Chennai%2C%20Tamil%20Nadu!5e0!3m2!1sen!2sin!4v1234567890',
                        width: '100%',
                        height: '400',
                        style: { border: 0 },
                        allowFullScreen: true,
                        loading: 'lazy',
                        referrerPolicy: 'no-referrer-when-downgrade'
                    })
                ),
                React.createElement('p', { style: { textAlign: 'center', marginTop: 'var(--space-md)' } },
                    React.createElement('a', { 
                        href: 'https://www.google.com/maps/search/23,+1st+Cross+Street+Kothari+Nagar,+Ramapuram,+Chennai,+Tamil+Nadu+600089', 
                        target: '_blank', 
                        rel: 'noopener', 
                        className: 'btn btn-sm btn-outline' 
                    }, 'Open in Google Maps')
                )
            )
        )
    );
}

window.ContactPage = ContactPage;
