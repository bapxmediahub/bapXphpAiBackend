import { createContext, useContext, useReducer, useEffect } from 'react';

const AppContext = createContext(null);

const initialState = {
  cart: JSON.parse(localStorage.getItem('cart') || '[]'),
  user: JSON.parse(localStorage.getItem('user') || 'null'),
  products: [],
  categories: [],
  astrologers: [],
  temples: [],
  loading: false,
  notification: null
};

function appReducer(state, action) {
  switch (action.type) {
    case 'SET_CART':
      return { ...state, cart: action.payload };
    case 'ADD_TO_CART': {
      const existing = state.cart.find(item => item.slug === action.payload.slug);
      let newCart;
      if (existing) {
        newCart = state.cart.map(item =>
          item.slug === action.payload.slug
            ? { ...item, qty: item.qty + (action.payload.qty || 1) }
            : item
        );
      } else {
        newCart = [...state.cart, { ...action.payload, qty: action.payload.qty || 1 }];
      }
      localStorage.setItem('cart', JSON.stringify(newCart));
      return { ...state, cart: newCart };
    }
    case 'REMOVE_FROM_CART': {
      const newCart = state.cart.filter(item => item.slug !== action.payload);
      localStorage.setItem('cart', JSON.stringify(newCart));
      return { ...state, cart: newCart };
    }
    case 'UPDATE_CART_QTY': {
      const newCart = state.cart.map(item =>
        item.slug === action.payload.slug
          ? { ...item, qty: Math.max(1, action.payload.qty) }
          : item
      );
      localStorage.setItem('cart', JSON.stringify(newCart));
      return { ...state, cart: newCart };
    }
    case 'CLEAR_CART':
      localStorage.setItem('cart', '[]');
      return { ...state, cart: [] };
    case 'SET_USER':
      localStorage.setItem('user', JSON.stringify(action.payload));
      return { ...state, user: action.payload };
    case 'LOGOUT':
      localStorage.removeItem('user');
      return { ...state, user: null };
    case 'SET_PRODUCTS':
      return { ...state, products: action.payload };
    case 'SET_CATEGORIES':
      return { ...state, categories: action.payload };
    case 'SET_ASTROLOGERS':
      return { ...state, astrologers: action.payload };
    case 'SET_TEMPLES':
      return { ...state, temples: action.payload };
    case 'SET_LOADING':
      return { ...state, loading: action.payload };
    case 'SET_NOTIFICATION':
      return { ...state, notification: action.payload };
    default:
      return state;
  }
}

export function AppProvider({ children }) {
  const [state, dispatch] = useReducer(appReducer, initialState);

  const cartTotal = state.cart.reduce((sum, item) => {
    const price = item.offer_price || item.price || 0;
    return sum + price * item.qty;
  }, 0);

  const cartCount = state.cart.reduce((sum, item) => sum + item.qty, 0);

  const showNotification = (message, type = 'success') => {
    dispatch({ type: 'SET_NOTIFICATION', payload: { message, type } });
    setTimeout(() => {
      dispatch({ type: 'SET_NOTIFICATION', payload: null });
    }, 3000);
  };

  const value = {
    ...state,
    cartTotal,
    cartCount,
    dispatch,
    showNotification
  };

  return (
    <AppContext.Provider value={value}>
      {children}
    </AppContext.Provider>
  );
}

export function useApp() {
  const context = useContext(AppContext);
  if (!context) {
    throw new Error('useApp must be used within an AppProvider');
  }
  return context;
}
