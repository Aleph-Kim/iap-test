import { registerPlugin, type PluginListenerHandle } from '@capacitor/core';

export interface IapProduct {
  id: string;
  title: string;
  price: string;
}

export interface IapTransaction {
  transactionId: string;
  productId: string;
  jws: string;
}

interface IapPlugin {
  getProducts(options: { productIds: string[] }): Promise<{ products: IapProduct[] }>;
  purchase(options: { productId: string }): Promise<IapTransaction>;
  getUnfinished(): Promise<{ transactions: IapTransaction[] }>;
  finish(options: { transactionId: string }): Promise<void>;
  addListener(event: 'transaction', listener: (transaction: IapTransaction) => void): Promise<PluginListenerHandle>;
}

// IAPPlugin.swift의 jsName과 같은 이름 필요
export const IAP = registerPlugin<IapPlugin>('IAP');
