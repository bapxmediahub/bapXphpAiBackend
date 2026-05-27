/**
 * App Init - Register routes
 */
document.addEventListener('DOMContentLoaded', () => {
    Router.reg('/', HomePage);
    Router.reg('/shop', ShopPage);
    Router.reg('/product/{slug}', ProductPage);
    Router.reg('/astrologers', AstrologersPage);
    Router.reg('/temples', TemplesPage);
    Router.reg('/contact', ContactPage);
    Router.reg('/about', AboutPage);
    Router.reg('/cart', CartPage);
    Router.reg('/checkout', CheckoutPage);
    Router.reg('/order-success', OrderSuccessPage);
    Router.reg('*', NotFoundPage);
    Router.init();
});
