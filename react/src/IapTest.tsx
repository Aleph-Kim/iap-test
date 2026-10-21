import { useEffect, useState } from 'react';
import { IAP, type IapProduct, type IapTransaction } from './iap';
import './IapTest.css';

const API_URL = import.meta.env.VITE_API_URL;
const API_TOKEN = import.meta.env.VITE_API_TOKEN;
const PRODUCT_IDS: string[] = import.meta.env.VITE_PRODUCT_IDS.split(',');

export default function IapTest() {
  const [products, setProducts] = useState<IapProduct[]>([]);
  const [logs, setLogs] = useState<string[]>([]);

  const log = (message: string) => setLogs((prev) => [`[${new Date().toLocaleTimeString()}] ${message}`, ...prev]);

  /**
   * 서버 검증·지급 성공 후에만 finish 호출 (실패 거래는 getUnfinished로 재처리)
   */
  const verifyAndFinish = async (transaction: IapTransaction) => {
    const response = await fetch(`${API_URL}/iap/apple/verify`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        Authorization: `Bearer ${API_TOKEN}`,
      },
      body: JSON.stringify({ jws: transaction.jws }),
    });

    if (!response.ok) {
      log(`서버 검증 실패 (${response.status}): ${(await response.json()).message}`);
      return;
    }

    await IAP.finish({ transactionId: transaction.transactionId });
    log(`✅ 지급 완료: ${transaction.productId} (${transaction.transactionId})`);
  };

  useEffect(() => {
    const listener = IAP.addListener('transaction', (transaction) => {
      log(`앱 외부 거래 수신: ${transaction.productId}`);
      verifyAndFinish(transaction);
    });

    return () => {
      listener.then((handle) => handle.remove());
    };
  }, []);

  const run = async (label: string, action: () => Promise<void>) => {
    try {
      await action();
    } catch (error: any) {
      log(`${label} 실패: ${error.code ?? ''} ${error.message}`);
    }
  };

  const loadProducts = () =>
    run('상품 조회', async () => {
      const { products } = await IAP.getProducts({ productIds: PRODUCT_IDS });
      setProducts(products);
      log(`상품 ${products.length}개 조회`);
    });

  const purchase = (productId: string) =>
    run('구매', async () => {
      try {
        const transaction = await IAP.purchase({ productId });
        log(`결제 완료, 서버 검증 요청: ${transaction.transactionId}`);
        await verifyAndFinish(transaction);
      } catch (error: any) {
        if (error.code === 'USER_CANCELLED') {
          log('사용자가 구매 취소');
          return;
        }
        throw error;
      }
    });

  const processUnfinished = () =>
    run('미완료 거래 처리', async () => {
      const { transactions } = await IAP.getUnfinished();
      log(`미완료 거래 ${transactions.length}건`);
      for (const transaction of transactions) {
        await verifyAndFinish(transaction);
      }
    });

  return (
    <main className="screen">
      <h1 className="large-title">인앱결제 테스트</h1>

      <section className="section" aria-labelledby="products-header">
        <h2 id="products-header" className="section-header">상품</h2>
        <ul className="group">
          {products.length === 0 && <li className="row row-empty">조회된 상품 없음</li>}
          {products.map((product) => (
            <li key={product.id} className="row">
              <div className="row-content">
                <div className="row-title">{product.title}</div>
                <div className="row-subtitle">{product.price}</div>
              </div>
              <button className="capsule-button" onClick={() => purchase(product.id)} aria-label={`${product.title} 구매`}>
                구매
              </button>
            </li>
          ))}
          <li className="row row-flush">
            <button className="row row-button" onClick={loadProducts}>
              상품 조회
            </button>
          </li>
        </ul>
      </section>

      <section className="section" aria-labelledby="transactions-header">
        <h2 id="transactions-header" className="section-header">거래</h2>
        <div className="group">
          <button className="row row-button" onClick={processUnfinished}>
            미완료 거래 처리
          </button>
        </div>
        <p className="section-footer">서버 검증 실패 등으로 finish되지 않은 거래의 재검증</p>
      </section>

      <section className="section" aria-labelledby="logs-header">
        <h2 id="logs-header" className="section-header">로그</h2>
        <ul className="group" aria-live="polite">
          {logs.length === 0 && <li className="row row-empty">기록 없음</li>}
          {logs.map((entry, index) => (
            <li key={logs.length - index} className="row log-row">
              {entry}
            </li>
          ))}
        </ul>
      </section>
    </main>
  );
}
