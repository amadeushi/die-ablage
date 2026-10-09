import SafariServices

final class SafariWebExtensionHandler: NSObject, NSExtensionRequestHandling {
    func beginRequest(with context: NSExtensionContext) {
        let response = NSExtensionItem()
        do {
            guard let item = context.inputItems.first as? NSExtensionItem,
                  let message = item.userInfo?[SFExtensionMessageKey] as? [String: Any],
                  message["action"] as? String == "queueCapture",
                  let clip = message["clip"] as? [String: Any] else { throw CocoaError(.coderInvalidValue) }
            let id = try CaptureStore.save(clip)
            response.userInfo = [SFExtensionMessageKey: ["ok": true, "launchURL": "dieablage://capture/" + id]]
        } catch {
            response.userInfo = [SFExtensionMessageKey: ["ok": false, "error": "Die Auswahl konnte nicht übergeben werden. Bitte App-Gruppen und Dateizugriff prüfen."]]
        }
        context.completeRequest(returningItems: [response])
    }
}
