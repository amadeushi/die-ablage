import SwiftUI
import UIKit
import WebKit

@main struct AblageApp: App {
    @StateObject private var model = LibraryModel()
    var body: some Scene {
        WindowGroup {
            NavigationStack {
                LibraryBrowser(model: model)
                    .navigationTitle("Die ABLAGE")
                    .navigationBarTitleDisplayMode(.inline)
                    .toolbar {
                        ToolbarItem(placement: .topBarLeading) {
                            Button { model.webView.goBack() } label: { Image(systemName: "chevron.left") }
                                .accessibilityLabel("Zurück")
                        }
                        ToolbarItem(placement: .topBarTrailing) {
                            Button { model.webView.reload() } label: { Image(systemName: "arrow.clockwise") }
                                .accessibilityLabel("Neu laden")
                        }
                        ToolbarItem(placement: .topBarTrailing) {
                            Button { model.loadDrafts() } label: { Image(systemName: "tray.full") }
                                .accessibilityLabel("Ungespeicherte Entwürfe")
                        }
                        ToolbarItem(placement: .topBarTrailing) {
                            Button { model.showHelp = true } label: { Image(systemName: "questionmark.circle") }
                                .accessibilityLabel("Clipping-Hilfe")
                        }
                    }
            }
            .tint(Color(red: 0.70, green: 0.07, blue: 0.16))
            .sheet(isPresented: $model.showHelp) {
                NavigationStack {
                    List {
                        Section("Webseite clippen") {
                            Text("Öffne die Quelle in Safari und melde dich dort bei Bedarf an.")
                            Text("Aktiviere Die ABLAGE in den Safari-Erweiterungen. Öffne danach die Erweiterung und wähle Bereiche auf der Seite aus.")
                            Text("Prüfe die Auswahl und tippe auf In der App öffnen. Melde dich in der App an und speichere den Clip nach der Vorschau.")
                        }
                        Section("Deine Inhalte") {
                            Text("Bibliothek, Suche, PDF-Lesetexte, Markdown, persönliche Textmarkierungen und Freigaben nutzt du über dein bestehendes Teamkonto.")
                            Text("Safari und die App haben getrennte Anmeldungen. Zugangsdaten zur Quellseite werden nicht übertragen.")
                        }
                    }.navigationTitle("So geht’s")
                        .toolbar { ToolbarItem(placement: .confirmationAction) { Button("Fertig") { model.showHelp = false } } }
                }
            }
            .sheet(isPresented: $model.showDrafts) {
                NavigationStack {
                    List {
                        if model.pending.isEmpty { Text("Keine ungespeicherten Entwürfe. Neue Auswahlen übergibst du aus Safari.") }
                        ForEach(model.pending) { draft in
                            Button { model.showDrafts = false; model.receive(URL(string: "dieablage://capture/" + draft.id)!) } label: {
                                VStack(alignment: .leading, spacing: 4) { Text(draft.title); Text(draft.source).font(.caption).foregroundStyle(.secondary) }
                            }
                        }
                        Text("Entwürfe bleiben bis zu 24 Stunden auf diesem Gerät verfügbar. Nach dem Speichern oder Verwerfen werden sie entfernt.").font(.footnote)
                    }.navigationTitle("Entwürfe")
                        .toolbar { ToolbarItem(placement: .confirmationAction) { Button("Fertig") { model.showDrafts = false } } }
                }
            }
            .alert("Clip-Übergabe", isPresented: Binding(get: { model.error != nil }, set: { if !$0 { model.error = nil } })) {
                Button("OK", role: .cancel) { model.error = nil }
            } message: { Text(model.error ?? "") }
            .onOpenURL { model.receive($0) }
        }
    }
}
@MainActor final class LibraryModel: ObservableObject {
    let webView: WKWebView
    @Published var showHelp = false
    @Published var showDrafts = false
    @Published var pending: [PendingCapture] = []
    func loadDrafts() { do { pending = try CaptureStore.pending(); showDrafts = true } catch { self.error = "Entwürfe konnten nicht geladen werden. Prüfe die App-Gruppen-Einrichtung." } }
    @Published var error: String?
    var captureID: String?
    init() {
        let configuration = WKWebViewConfiguration()
        configuration.websiteDataStore = .default()
        webView = WKWebView(frame: .zero, configuration: configuration)
        webView.allowsBackForwardNavigationGestures = true
    }
    func receive(_ url: URL) {
        guard url.scheme == "dieablage", url.host == "capture", let id = url.pathComponents.last, UUID(uuidString: id) != nil else { return }
        do { _ = try CaptureStore.load(id); captureID = id; webView.load(URLRequest(url: AblageConfig.origin)) }
        catch { self.error = "Die Auswahl ist nicht mehr verfügbar. Bitte innerhalb von 24 Stunden aus Safari übernehmen." }
    }
}
struct LibraryBrowser: UIViewRepresentable {
    @ObservedObject var model: LibraryModel
    func makeCoordinator() -> Coordinator { Coordinator(model) }
    func makeUIView(context: Context) -> WKWebView {
        let web = model.webView
        web.navigationDelegate = context.coordinator
        web.uiDelegate = context.coordinator
        web.configuration.userContentController.add(context.coordinator, name: "captureAck")
        web.load(URLRequest(url: AblageConfig.origin))
        return web
    }
    func updateUIView(_ view: WKWebView, context: Context) {}
    @MainActor final class Coordinator: NSObject, WKNavigationDelegate, WKUIDelegate, WKScriptMessageHandler {
        let model: LibraryModel
        init(_ model: LibraryModel) { self.model = model }
        func webView(_ web: WKWebView, decidePolicyFor action: WKNavigationAction, decisionHandler: @escaping (WKNavigationActionPolicy) -> Void) {
            guard let url = action.request.url else { decisionHandler(.cancel); return }
            if url.scheme == "https", url.host == AblageConfig.origin.host { decisionHandler(.allow) }
            else { decisionHandler(.cancel); if ["https", "http", "mailto"].contains(url.scheme ?? "") { UIApplication.shared.open(url) } }
        }
        func webView(_ web: WKWebView, createWebViewWith configuration: WKWebViewConfiguration, for action: WKNavigationAction, windowFeatures: WKWindowFeatures) -> WKWebView? {
            if action.targetFrame == nil { web.load(action.request) }; return nil
        }
        func webView(_ web: WKWebView, didFinish navigation: WKNavigation!) {
            guard web.url?.host == AblageConfig.origin.host, let id = model.captureID else { return }
            do {
                let clip = try CaptureStore.load(id)
                let json = try JSONSerialization.data(withJSONObject: ["channel": "lesewerk-capture", "id": id, "clip": clip])
                guard let payload = String(data: json, encoding: .utf8) else { return }
                let script = """
                (()=>{const payload=\(payload);let tries=0;const send=()=>window.postMessage(payload,location.origin);const timer=setInterval(()=>{if(++tries>600)clearInterval(timer);else send();},1000);window.addEventListener('message',e=>{if(e.source!==window||e.origin!==location.origin)return;if(e.data?.id===payload.id){if(e.data.channel==='lesewerk-received')clearInterval(timer);if(['lesewerk-saved','lesewerk-discarded'].includes(e.data.channel)){clearInterval(timer);window.webkit.messageHandlers.captureAck.postMessage({id:payload.id,action:'finish'});}if(e.data.channel==='lesewerk-draft')window.webkit.messageHandlers.captureAck.postMessage({id:payload.id,action:'update',clip:e.data.clip});}if(e.data?.channel==='lesewerk-ready')send();});send();})();
                """
                web.evaluateJavaScript(script)
            } catch { model.error = "Die Auswahl ist abgelaufen. Bitte erneut clippen." }
        }
        func userContentController(_ controller: WKUserContentController, didReceive message: WKScriptMessage) {
            guard message.frameInfo.isMainFrame, message.frameInfo.securityOrigin.host == AblageConfig.origin.host,
                  let body = message.body as? [String: Any], let id = body["id"] as? String, id == model.captureID else { return }
            if body["action"] as? String == "finish" { CaptureStore.remove(id); model.captureID = nil }
            else if body["action"] as? String == "update", let clip = body["clip"] as? [String: Any] { try? CaptureStore.update(id, clip: clip) }
        }
        func webView(_ web: WKWebView, didFailProvisionalNavigation navigation: WKNavigation!, withError error: Error) {
            if (error as NSError).code != NSURLErrorCancelled { model.error = "Die Bibliothek ist nicht erreichbar. Prüfe die Verbindung und tippe auf Neu laden." }
        }
    }
}
