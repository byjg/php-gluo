import React, {createContext, useCallback, useContext, useState} from 'react';
import {AlertTriangle, CheckCircle2, Info, X} from 'lucide-react';
import {cn} from '@/lib/utils';

const ToastContext = createContext();

let nextId = 0;

export function ToastProvider({ children }) {
  const [toasts, setToasts] = useState([]);

  const dismiss = useCallback((id) => {
    setToasts((list) => list.filter((t) => t.id !== id));
  }, []);

  const showToast = useCallback(
    (message, type = 'error') => {
      const id = ++nextId;
      setToasts((list) => [...list, { id, message, type }]);
      setTimeout(() => dismiss(id), 5000);
    },
    [dismiss]
  );

  return (
    <ToastContext.Provider value={{ showToast }}>
      {children}
      <div className="pointer-events-none fixed inset-x-0 top-4 z-50 flex flex-col items-center gap-2 px-4">
        {toasts.map((t) => (
          <ToastItem key={t.id} toast={t} onDismiss={() => dismiss(t.id)} />
        ))}
      </div>
    </ToastContext.Provider>
  );
}

const STYLES = {
  error: { cls: 'border-red-200 bg-red-50 text-red-800', Icon: AlertTriangle },
  success: { cls: 'border-green-200 bg-green-50 text-green-800', Icon: CheckCircle2 },
  info: { cls: 'border-slate-200 bg-white text-slate-800', Icon: Info },
};

function ToastItem({ toast, onDismiss }) {
  const { cls, Icon } = STYLES[toast.type] || STYLES.info;
  return (
    <div
      className={cn(
        'pointer-events-auto flex w-full max-w-md items-start gap-3 rounded-md border px-4 py-3 text-sm shadow-md',
        cls
      )}
      role="alert"
    >
      <Icon size={18} className="mt-0.5 shrink-0" />
      <span className="flex-1">{toast.message}</span>
      <button onClick={onDismiss} className="shrink-0 opacity-60 hover:opacity-100" aria-label="Dismiss">
        <X size={16} />
      </button>
    </div>
  );
}

export function useToast() {
  const ctx = useContext(ToastContext);
  if (!ctx) throw new Error('useToast must be used within ToastProvider');
  return ctx;
}
