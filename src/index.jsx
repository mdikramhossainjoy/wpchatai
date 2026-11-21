import React from 'react';
import ReactDOM from 'react-dom/client';
import { App } from './App';
import { SettingsPage } from './components/SettingsPage';
import { ToastProvider } from './components/Toast';
import './styles/index.css';

document.addEventListener('DOMContentLoaded', () => {
  const config = window.wpchatConfig || {
    restUrl: '/wp-json/wpchat/v1/',
    nonce: '',
    currentUser: 'Admin',
    siteName: 'WordPress',
    adminUrl: '/wp-admin/',
    defaultMode: 'copilot',
    hasApiKey: false,
    pluginVersion: '1.0.0',
  };

  // 1. Mount Floating Drawer in wp-admin
  const rootElement = document.getElementById('wpchat-root');
  if (rootElement) {
    const root = ReactDOM.createRoot(rootElement);
    root.render(
      <React.StrictMode>
        <ToastProvider>
          <App config={config} />
        </ToastProvider>
      </React.StrictMode>
    );
  }

  // 2. Mount Dedicated Settings Page on WPChat menu screen
  const settingsMount = document.getElementById('wpchat-settings-mount');
  if (settingsMount) {
    const settingsRoot = ReactDOM.createRoot(settingsMount);
    settingsRoot.render(
      <React.StrictMode>
        <ToastProvider>
          <SettingsPage config={config} />
        </ToastProvider>
      </React.StrictMode>
    );
  }
});
