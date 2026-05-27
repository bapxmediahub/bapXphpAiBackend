/**
 * App Initialization
 * Register routes and start the app
 */

document.addEventListener('DOMContentLoaded', () => {
    // Register all routes
    Router.register('/', HomePage);
    Router.register('/shop', ShopPage);
    Router.register('/product/{slug}', ProductPage);
    Router.register('/cart', CartPage);
    Router.register('/checkout', CheckoutPage);
    Router.register('/order-success', OrderSuccessPage);
    Router.register('/astrologers', AstrologersPage);
    Router.register('/temples', TemplesPage);
    Router.register('/contact', ContactPage);
    Router.register('/about', AboutPage);
    Router.register('/login', LoginPage);
    Router.register('/register', RegisterPage);

    // Init router
    Router.init();
    Cart.updateBadge();
});

// Login Page
function LoginPage(root) {
    root.innerHTML = '';
    root.className = '';
    const section = document.createElement('section');
    section.className = 'auth-page';
    section.innerHTML = `
        <div class="auth-visual">
            <h2>Welcome Back</h2>
            <p>Sign in to access your account and continue your spiritual journey.</p>
        </div>
        <div class="auth-form-container">
            <div class="auth-card">
                <h1>Login</h1>
                <p>Sign in to your account</p>
                <form class="auth-form" id="login-form">
                    <div class="form-group"><label>Email</label><input type="email" name="email" required></div>
                    <div class="form-group"><label>Password</label><input type="password" name="password" required></div>
                    <button type="submit" class="btn btn-primary btn-block">Sign In</button>
                </form>
                <div class="auth-divider">or</div>
                <a href="/auth/google" class="btn-google" style="display:flex;align-items:center;justify-content:center;gap:var(--space-sm);width:100%;padding:0.75rem;border:1.5px solid var(--color-border);border-radius:var(--radius-md);background:var(--color-white);color:var(--color-ink);text-decoration:none">
                    <img src="https://www.google.com/favicon.ico" alt="Google" width="20">Continue with Google
                </a>
                <div class="auth-footer">Don't have an account? <a href="/register" data-link>Register</a></div>
            </div>
        </div>
    `;
    
    section.querySelector('#login-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const data = Object.fromEntries(new FormData(e.target));
        try {
            await API.post('/login', data);
            Router.navigate('/');
        } catch (err) {
            alert('Login failed.');
        }
    });
    
    root.appendChild(section);
}

// Register Page
function RegisterPage(root) {
    root.innerHTML = '';
    root.className = '';
    const section = document.createElement('section');
    section.className = 'auth-page';
    section.innerHTML = `
        <div class="auth-visual">
            <h2>Join Us</h2>
            <p>Create an account to start your spiritual journey.</p>
        </div>
        <div class="auth-form-container">
            <div class="auth-card">
                <h1>Create Account</h1>
                <p>Join our spiritual community</p>
                <form class="auth-form" id="register-form">
                    <div class="form-group"><label>Name</label><input type="text" name="name" required></div>
                    <div class="form-group"><label>Email</label><input type="email" name="email" required></div>
                    <div class="form-group"><label>Password</label><input type="password" name="password" required></div>
                    <button type="submit" class="btn btn-primary btn-block">Create Account</button>
                </form>
                <div class="auth-footer">Already have an account? <a href="/login" data-link>Login</a></div>
            </div>
        </div>
    `;
    
    section.querySelector('#register-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const data = Object.fromEntries(new FormData(e.target));
        try {
            await API.post('/register', data);
            Router.navigate('/');
        } catch (err) {
            alert('Registration failed.');
        }
    });
    
    root.appendChild(section);
}

window.LoginPage = LoginPage;
window.RegisterPage = RegisterPage;
