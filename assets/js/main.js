/**
 * Main App Entry Point
 * Initializes React SPA with routing
 */

const { useState, useEffect } = React;

// Main App Component
function App() {
    const [cartCount, setCartCount] = useState(AppStore.cartCount);
    const [, forceUpdate] = useState({});
    
    useEffect(() => {
        // Subscribe to store updates
        const unsubscribe = AppStore.subscribe((state) => {
            setCartCount(state.cart.reduce((sum, item) => sum + item.qty, 0));
            forceUpdate({});
        });
        
        // Initial data load
        API.get('/').then(data => {
            AppStore.setState({
                products: data.products || [],
                categories: data.categories || [],
                astrologers: data.astrologers || [],
                temples: data.temples || []
            });
        });
        
        // Handle navigation
        const handleNavigate = () => {
            AppRouter.render();
        };
        
        window.addEventListener('popstate', handleNavigate);
        
        return () => {
            unsubscribe();
            window.removeEventListener('popstate', handleNavigate);
        };
    }, []);
    
    // Get current page component
    const path = window.location.pathname;
    let PageComponent;
    
    // Route matching
    if (path === '/') {
        PageComponent = window.HomePage;
    } else if (path === '/shop' || path.startsWith('/shop?')) {
        PageComponent = window.ShopPage;
    } else if (path.startsWith('/product/')) {
        PageComponent = window.ProductPage;
    } else if (path === '/cart') {
        PageComponent = window.CartPage;
    } else if (path === '/checkout') {
        PageComponent = window.CheckoutPage;
    } else if (path === '/order-success') {
        PageComponent = window.OrderSuccessPage;
    } else if (path === '/astrologers') {
        PageComponent = window.AstrologersPage;
    } else if (path.startsWith('/astrologers/')) {
        PageComponent = window.AstrologerDetailPage;
    } else if (path === '/temples') {
        PageComponent = window.TemplesPage;
    } else if (path.startsWith('/temples/')) {
        PageComponent = window.TempleDetailPage;
    } else if (path === '/contact') {
        PageComponent = window.ContactPage;
    } else if (path === '/login' || path === '/register') {
        PageComponent = window.LoginPage;
    } else if (path === '/about') {
        PageComponent = window.AboutPage;
    } else if (path === '/sri-panchami-spiritual') {
        PageComponent = window.SpiritualPage;
    } else {
        PageComponent = window.NotFoundPage;
    }
    
    const navigate = (path) => {
        window.history.pushState({}, '', path);
        window.scrollTo(0, 0);
        AppRouter.render();
        forceUpdate({});
    };
    
    const contextValue = {
        cartCount,
        cartTotal: AppStore.cartTotal,
        cart: AppStore.state.cart,
        addToCart: AppStore.addToCart,
        removeFromCart: AppStore.removeFromCart,
        updateCartQty: AppStore.updateCartQty,
        clearCart: AppStore.clearCart,
        navigate
    };
    
    return React.createElement(AppContext.Provider, { value: contextValue },
        React.createElement(Layout, null,
            PageComponent && React.createElement(PageComponent)
        )
    );
}

// Initialize app when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('root');
    if (root) {
        const rootElement = ReactDOM.createRoot(root);
        rootElement.render(React.createElement(App));
        
        // Override AppRouter.render to use React
        AppRouter.render = () => {
            rootElement.render(React.createElement(App));
        };
    }
});
