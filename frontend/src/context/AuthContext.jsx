import React, {createContext, useContext, useState} from 'react';
import {useNavigate} from 'react-router-dom';
import {token} from '@/lib/api';

const AuthContext = createContext();

export const AuthProvider = ({ children }) => {
  const navigate = useNavigate();
  const [user, setUser] = useState(() => {
    try {
      const saved = sessionStorage.getItem('auth_user');
      return saved ? JSON.parse(saved) : null;
    } catch {
      return null;
    }
  });

  const login = (jwt, userData) => {
    token.set(jwt);
    setUser(userData);
    sessionStorage.setItem('auth_user', JSON.stringify(userData));
  };

  const logout = () => {
    token.clear();
    sessionStorage.removeItem('auth_user');
    setUser(null);
    navigate('/login');
  };

  const isAuthenticated = () => !token.isExpired();

  return (
    <AuthContext.Provider value={{ user, login, logout, isAuthenticated }}>
      {children}
    </AuthContext.Provider>
  );
};

export const useAuth = () => {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used within AuthProvider');
  return ctx;
};
