import React, {useEffect} from 'react';
import {Navigate, Outlet, Route, Routes} from 'react-router-dom';
import {AuthProvider, useAuth} from '@/context/AuthContext';
import {ToastProvider, useToast} from '@/components/Toast';
import {setAuthErrorHandler} from '@/lib/api';
import AppNav from '@/components/AppNav';
import Login from '@/pages/auth/Login';
import ForgotPassword from '@/pages/auth/ForgotPassword';
import Dashboard from '@/pages/dashboard/Dashboard';
import Profile from '@/pages/dashboard/Profile';
// {/* >>> examples */}
import ProjectsList from '@/pages/examples/ProjectsList';
import ProjectDetail from '@/pages/examples/ProjectDetail';

// {/* <<< examples */}

function ProtectedLayout() {
  const { isAuthenticated } = useAuth();
  if (!isAuthenticated()) {
    return <Navigate to="/login" replace />;
  }
  return (
    <div className="min-h-screen">
      <AppNav />
      <main className="mx-auto max-w-5xl px-6 py-8">
        <Outlet />
      </main>
    </div>
  );
}

// Wire the requester's centralised auth-error capture into the toast system.
function AuthErrorBridge() {
  const { showToast } = useToast();
  const { logout } = useAuth();
  useEffect(() => {
    setAuthErrorHandler((status) => {
      if (status === 403) {
        showToast('You need administrator privileges to perform this action.', 'error');
      } else if (status === 401) {
        showToast('Your session has expired — please sign in again.', 'error');
        logout();
      }
    });
    return () => setAuthErrorHandler(null);
  }, [showToast, logout]);
  return null;
}

export default function App() {
  return (
    <AuthProvider>
      <ToastProvider>
        <AuthErrorBridge />
        <Routes>
          <Route path="/login" element={<Login />} />
          <Route path="/forgot-password" element={<ForgotPassword />} />

          <Route element={<ProtectedLayout />}>
            <Route path="/dashboard" element={<Dashboard />} />
            <Route path="/profile" element={<Profile />} />
            {/* >>> examples */}
            <Route path="/projects" element={<ProjectsList />} />
            <Route path="/projects/:id" element={<ProjectDetail />} />
            {/* <<< examples */}
          </Route>

          <Route path="*" element={<Navigate to="/dashboard" replace />} />
        </Routes>
      </ToastProvider>
    </AuthProvider>
  );
}
