import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import IapTest from './IapTest';

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <IapTest />
  </StrictMode>,
);
