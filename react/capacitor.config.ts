import type { CapacitorConfig } from '@capacitor/cli';
import { loadEnv } from 'vite';

const env = loadEnv('', process.cwd(), 'CAPACITOR_');

const config: CapacitorConfig = {
  appId: env.CAPACITOR_APP_ID,
  appName: 'IAP Sample',
  webDir: 'dist',
  ios: {
    path: '../ios',
  },
};

export default config;
