/**
 * Cart & Checkout Page Components
 */

function CartPage() {
    const { cart, cartTotal, updateCartQty, removeFromCart, navigate, clearCart } = useApp();
    
    if (cart.length === 0) {
        return React.createElement('section', { className: 'section', style: { textAlign: 'center', padding: '4rem' } },
            React.createElement('div', { style: { fontSize: '3rem', marginBottom: 'var(--space-md)' } }, '🛒'),
            React.createElement('h2', { style: { fontFamily: 'var(--font-serif)' } }, 'Your Cart is Empty'),
            React.createElement('p', { style: { color: 'var(--color-text-muted)', marginBottom: 'var(--space-lg)' } }, 'Add some spiritual products to get started.'),
            React.createElement('button', { className: 'btn btn-primary', onClick: () => navigate('/shop') }, 'Continue Shopping')
        );
    }
    
    return React.createElement('section', { className: 'section' },
        React.createElement('div', { className: 'container' },
            React.createElement('h1', { style: { fontFamily: 'var(--font-serif)', textAlign: 'center', marginBottom: 'var(--space-2xl)' } }, 'Shopping Cart'),
            
            React.createElement('div', { className: 'cart-layout' },
                // Cart Items
                React.createElement('div', { className: 'cart-items' },
                    cart.map(item => {
                        const price = item.offer_price || item.price || 0;
                        return React.createElement('div', { key: item.slug, className: 'cart-item' },
                            React.createElement('img', { className: 'cart-item__img', src: Utils.getAssetUrl(item.image_url), alt: item.name }),
                            React.createElement('div', null,
                                React.createElement('h3', { className: 'cart-item__name' },
                                    React.createElement('a', { href: `/product/${item.slug}`, onClick: (e) => { e.preventDefault(); navigate(`/product/${item.slug}`); } }, item.name)
                                ),
                                React.createElement('p', { className: 'cart-item__meta' }, item.category)
                            ),
                            React.createElement('div', { style: { display: 'flex', alignItems: 'center', gap: 'var(--space-sm)' } },
                                React.createElement('button', { className: 'btn btn-sm', onClick: () => updateCartQty(item.slug, item.qty - 1) }, '-'),
                                React.createElement('span', null, item.qty),
                                React.createElement('button', { className: 'btn btn-sm', onClick: () => updateCartQty(item.slug, item.qty + 1) }, '+')
                            ),
                            React.createElement('span', { className: 'cart-item__price' }, Utils.formatPrice(price * item.qty)),
                            React.createElement('button', { className: 'cart-item__remove', onClick: () => removeFromCart(item.slug) }, '✕')
                        );
                    })
                ),
                
                // Cart Summary
                React.createElement('div', { className: 'cart-summary' },
                    React.createElement('h2', null, 'Order Summary'),
                    React.createElement('div', { className: 'cart-summary__row' },
                        React.createElement('span', null, 'Subtotal'),
                        React.createElement('span', null, Utils.formatPrice(cartTotal))
                    ),
                    React.createElement('div', { className: 'cart-summary__row' },
                        React.createElement('span', null, 'Shipping'),
                        React.createElement('span', null, 'Free')
                    ),
                    React.createElement('div', { className: 'cart-summary__row cart-summary__row--total' },
                        React.createElement('span', null, 'Total'),
                        React.createElement('span', null, Utils.formatPrice(cartTotal))
                    ),
                    React.createElement('button', { 
                        className: 'btn btn-primary btn-block', 
                        style: { marginTop: 'var(--space-lg)' },
                        onClick: () => navigate('/checkout')
                    }, 'Proceed to Checkout'),
                    React.createElement('button', { 
                        className: 'btn btn-ghost btn-block', 
                        style: { marginTop: 'var(--space-sm)' },
                        onClick: () => navigate('/shop')
                    }, 'Continue Shopping')
                )
            )
        )
    );
}

function CheckoutPage() {
    const { cart, cartTotal, clearCart, navigate } = useApp();
    const [formData, setFormData] = useState({
        name: '',
        email: '',
        phone: '',
        address: '',
        city: '',
        state: '',
        pincode: ''
    });
    
    const handleChange = (e) => {
        setFormData({ ...formData, [e.target.name]: e.target.value });
    };
    
    const handleSubmit = async (e) => {
        e.preventDefault();
        try {
            await API.post('/checkout/create-order', {
                ...formData,
                items: cart,
                total: cartTotal
            });
            clearCart();
            navigate('/order-success');
        } catch (error) {
            alert('Order creation failed. Please try again.');
        }
    };
    
    if (cart.length === 0) {
        return React.createElement('section', { className: 'section', style: { textAlign: 'center', padding: '4rem' } },
            React.createElement('h2', null, 'Nothing to Checkout'),
            React.createElement('button', { className: 'btn btn-primary', onClick: () => navigate('/shop') }, 'Go Shopping')
        );
    }
    
    return React.createElement('section', { className: 'section' },
        React.createElement('div', { className: 'container' },
            React.createElement('h1', { style: { fontFamily: 'var(--font-serif)', textAlign: 'center', marginBottom: 'var(--space-2xl)' } }, 'Checkout'),
            
            React.createElement('div', { className: 'checkout-layout' },
                // Checkout Form
                React.createElement('form', { className: 'checkout-form', onSubmit: handleSubmit },
                    React.createElement('h2', null, 'Shipping Details'),
                    
                    React.createElement('div', { className: 'checkout-form__row' },
                        React.createElement('div', { className: 'form-group' },
                            React.createElement('label', { htmlFor: 'name' }, 'Full Name'),
                            React.createElement('input', { type: 'text', id: 'name', name: 'name', value: formData.name, onChange: handleChange, required: true })
                        ),
                        React.createElement('div', { className: 'form-group' },
                            React.createElement('label', { htmlFor: 'email' }, 'Email'),
                            React.createElement('input', { type: 'email', id: 'email', name: 'email', value: formData.email, onChange: handleChange, required: true })
                        )
                    ),
                    
                    React.createElement('div', { className: 'checkout-form__row' },
                        React.createElement('div', { className: 'form-group' },
                            React.createElement('label', { htmlFor: 'phone' }, 'Phone'),
                            React.createElement('input', { type: 'tel', id: 'phone', name: 'phone', value: formData.phone, onChange: handleChange, required: true })
                        ),
                        React.createElement('div', { className: 'form-group' },
                            React.createElement('label', { htmlFor: 'pincode' }, 'Pincode'),
                            React.createElement('input', { type: 'text', id: 'pincode', name: 'pincode', value: formData.pincode, onChange: handleChange, required: true })
                        )
                    ),
                    
                    React.createElement('div', { className: 'form-group' },
                        React.createElement('label', { htmlFor: 'address' }, 'Address'),
                        React.createElement('textarea', { id: 'address', name: 'address', value: formData.address, onChange: handleChange, required: true, rows: 3 })
                    ),
                    
                    React.createElement('div', { className: 'checkout-form__row' },
                        React.createElement('div', { className: 'form-group' },
                            React.createElement('label', { htmlFor: 'city' }, 'City'),
                            React.createElement('input', { type: 'text', id: 'city', name: 'city', value: formData.city, onChange: handleChange, required: true })
                        ),
                        React.createElement('div', { className: 'form-group' },
                            React.createElement('label', { htmlFor: 'state' }, 'State'),
                            React.createElement('input', { type: 'text', id: 'state', name: 'state', value: formData.state, onChange: handleChange, required: true })
                        )
                    ),
                    
                    React.createElement('button', { type: 'submit', className: 'btn btn-primary btn-block', style: { marginTop: 'var(--space-lg)' } },
                        'Place Order'
                    )
                ),
                
                // Order Summary
                React.createElement('div', { className: 'checkout-summary' },
                    React.createElement('h2', null, 'Order Summary'),
                    React.createElement('div', { style: { maxHeight: '300px', overflowY: 'auto' } },
                        cart.map(item => {
                            const price = item.offer_price || item.price || 0;
                            return React.createElement('div', { key: item.slug, className: 'checkout-item' },
                                React.createElement('img', { className: 'checkout-item__img', src: Utils.getAssetUrl(item.image_url), alt: item.name }),
                                React.createElement('div', null,
                                    React.createElement('div', { className: 'checkout-item__name' }, item.name),
                                    React.createElement('div', { className: 'checkout-item__meta' }, `Qty: ${item.qty}`)
                                ),
                                React.createElement('span', { className: 'checkout-item__price' }, Utils.formatPrice(price * item.qty))
                            );
                        })
                    ),
                    React.createElement('div', { className: 'cart-summary__row cart-summary__row--total', style: { marginTop: 'var(--space-md)', paddingTop: 'var(--space-md)', borderTop: '1px solid var(--color-border)' } },
                        React.createElement('span', null, 'Total'),
                        React.createElement('span', null, Utils.formatPrice(cartTotal))
                    )
                )
            )
        )
    );
}

function OrderSuccessPage() {
    return React.createElement('section', { className: 'section', style: { textAlign: 'center', padding: '4rem' } },
        React.createElement('div', { style: { fontSize: '4rem', marginBottom: 'var(--space-md)' } }, '✓'),
        React.createElement('h1', { style: { fontFamily: 'var(--font-serif)' } }, 'Order Placed Successfully!'),
        React.createElement('p', { style: { color: 'var(--color-text-muted)', marginBottom: 'var(--space-lg)' } }, 'Thank you for your order. We will contact you soon.'),
        React.createElement('button', { className: 'btn btn-primary', onClick: () => AppRouter.navigate('/shop') }, 'Continue Shopping')
    );
}

window.CartPage = CartPage;
window.CheckoutPage = CheckoutPage;
window.OrderSuccessPage = OrderSuccessPage;
