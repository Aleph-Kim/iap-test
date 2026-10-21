import Capacitor
import StoreKit

@objc(IAPPlugin)
public class IAPPlugin: CAPPlugin, CAPBridgedPlugin {
    public let identifier = "IAPPlugin"
    public let jsName = "IAP"
    public let pluginMethods: [CAPPluginMethod] = [
        CAPPluginMethod(name: "getProducts", returnType: CAPPluginReturnPromise),
        CAPPluginMethod(name: "purchase", returnType: CAPPluginReturnPromise),
        CAPPluginMethod(name: "getUnfinished", returnType: CAPPluginReturnPromise),
        CAPPluginMethod(name: "finish", returnType: CAPPluginReturnPromise),
    ]

    private var updatesTask: Task<Void, Never>?

    override public func load() {
        // 앱 밖에서 완료된 거래(Ask to Buy 승인, 결제 지연 후 완료 등)를 JS로 전달
        updatesTask = Task { [weak self] in
            for await result in Transaction.updates {
                self?.notifyListeners("transaction", data: IAPPlugin.toJS(result))
            }
        }
    }

    deinit {
        updatesTask?.cancel()
    }

    @objc func getProducts(_ call: CAPPluginCall) {
        let productIds = call.getArray("productIds", String.self) ?? []

        Task {
            do {
                let products = try await Product.products(for: productIds)
                call.resolve([
                    "products": products.map { ["id": $0.id, "title": $0.displayName, "price": $0.displayPrice] },
                ])
            } catch {
                call.reject(error.localizedDescription)
            }
        }
    }

    @objc func purchase(_ call: CAPPluginCall) {
        guard let productId = call.getString("productId") else {
            return call.reject("productId is required")
        }

        Task {
            do {
                guard let product = try await Product.products(for: [productId]).first else {
                    return call.reject("Product not found", "PRODUCT_NOT_FOUND")
                }

                switch try await product.purchase() {
                case .success(let result):
                    // finish()는 서버 검증·지급이 끝난 뒤 JS에서 호출
                    call.resolve(IAPPlugin.toJS(result))
                case .userCancelled:
                    call.reject("User cancelled", "USER_CANCELLED")
                case .pending:
                    call.reject("Purchase pending", "PENDING")
                @unknown default:
                    call.reject("Unknown purchase result")
                }
            } catch {
                call.reject(error.localizedDescription)
            }
        }
    }

    /**
     * 지급 전에 앱이 종료되는 등으로 finish되지 않은 거래 조회 (앱 시작 시 재처리용)
     */
    @objc func getUnfinished(_ call: CAPPluginCall) {
        Task {
            var transactions: [[String: Any]] = []
            for await result in Transaction.unfinished {
                transactions.append(IAPPlugin.toJS(result))
            }
            call.resolve(["transactions": transactions])
        }
    }

    @objc func finish(_ call: CAPPluginCall) {
        guard let transactionId = call.getString("transactionId").flatMap(UInt64.init) else {
            return call.reject("transactionId is required")
        }

        Task {
            for await result in Transaction.unfinished where result.unsafePayloadValue.id == transactionId {
                await result.unsafePayloadValue.finish()
            }
            call.resolve()
        }
    }

    /**
     * 서버 서명 검증용 jwsRepresentation 원본 전달 (앱 내 검증 생략)
     */
    private static func toJS(_ result: VerificationResult<Transaction>) -> [String: Any] {
        let transaction = result.unsafePayloadValue

        return [
            "transactionId": String(transaction.id),
            "productId": transaction.productID,
            "jws": result.jwsRepresentation,
        ]
    }
}
