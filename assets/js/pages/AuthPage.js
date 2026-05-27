/**
 * Auth Pages (Login/Register)
 */

function LoginPage() {
    const { navigate } = useApp();
    const [isLogin, setIsLogin] = useState(true);
    const [formData, setFormData] = useState({
        email: '',
        password: '',
        name: ''
    });
    
    const handleChange = (e) => {
        setFormData({ ...formData, [e.target.name]: e.target.value });
    };
    
    const handleSubmit = async (e) => {
        e.preventDefault();
        try {
            const endpoint = isLogin ? '/login' : '/register';
            await API.post(endpoint, formData);
            navigate('/');
        } catch (error) {
            alert(isLogin ? 'Login failed' : 'Registration failed');
        }
    };
    
    return React.createElement('div', { className: 'auth-page' },
        React.createElement('div', { className: 'auth-visual' },
            React.createElement('h2', null, 'Welcome Back'),
            React.createElement('p', null, 'Sign in to access your account and continue your spiritual journey.')
        ),
        React.createElement('div', { className: 'auth-form-container' },
            React.createElement('div', { className: 'auth-card' },
                React.createElement('h1', null, isLogin ? 'Login' : 'Create Account'),
                React.createElement('p', null, isLogin ? 'Sign in to your account' : 'Join our spiritual community'),
                
                React.createElement('form', { className: 'auth-form', onSubmit: handleSubmit },
                    !isLogin && React.createElement('div', { className: 'form-group' },
                        React.createElement('label', { htmlFor: 'name' }, 'Full Name'),
                        React.createElement('input', { type: 'text', id: 'name', name: 'name', value: formData.name, onChange: handleChange, required: !isLogin })
                    ),
                    React.createElement('div', { className: 'form-group' },
                        React.createElement('label', { htmlFor: 'email' }, 'Email'),
                        React.createElement('input', { type: 'email', id: 'email', name: 'email', value: formData.email, onChange: handleChange, required: true })
                    ),
                    React.createElement('div', { className: 'form-group' },
                        React.createElement('label', { htmlFor: 'password' }, 'Password'),
                        React.createElement('input', { type: 'password', id: 'password', name: 'password', value: formData.password, onChange: handleChange, required: true })
                    ),
                    React.createElement('button', { type: 'submit', className: 'btn btn-primary btn-block' }, isLogin ? 'Sign In' : 'Create Account')
                ),
                
                React.createElement('div', { className: 'auth-divider' }, 'or'),
                
                React.createElement('button', { className: 'btn-google', onClick: () => window.location.href = '/auth/google' },
                    React.createElement('img', { src: 'https://www.google.com/favicon.ico', alt: 'Google', width: 20 }),
                    'Continue with Google'
                ),
                
                React.createElement('div', { className: 'auth-footer' },
                    isLogin ? "Don't have an account? " : "Already have an account? ",
                    React.createElement('a', { href: '#', onClick: (e) => { e.preventDefault(); setIsLogin(!isLogin); } }, isLogin ? 'Register' : 'Login')
                )
            )
        )
    );
}

function RegisterPage() {
    return React.createElement(LoginPage, { initialMode: 'register' });
}

window.LoginPage = LoginPage;
window.RegisterPage = RegisterPage;
