import Foundation

enum AblageConfig {
    static let origin = URL(string: "https://ablage.partei-hildesheim.de")!
    static var group: String { Bundle.main.object(forInfoDictionaryKey: "AblageAppGroup") as? String ?? "" }
}
struct PendingCapture: Identifiable { let id: String; let title: String; let source: String }
enum CaptureStore {
    static func pending() throws -> [PendingCapture] {
        try prune()
        return try FileManager.default.contentsOfDirectory(at: directory(), includingPropertiesForKeys: [.contentModificationDateKey]).sorted { a, b in
            ((try? a.resourceValues(forKeys: [.contentModificationDateKey]).contentModificationDate) ?? .distantPast) > ((try? b.resourceValues(forKeys: [.contentModificationDateKey]).contentModificationDate) ?? .distantPast)
        }.compactMap { file in
            guard let data = try? Data(contentsOf: file), let clip = (try? JSONSerialization.jsonObject(with: data)) as? [String: Any], let title = clip["title"] as? String else { return nil }
            return PendingCapture(id: file.deletingPathExtension().lastPathComponent, title: title, source: URL(string: clip["url"] as? String ?? "")?.host ?? "Quelle")
        }
    }

    static func directory() throws -> URL {
        guard let root = FileManager.default.containerURL(forSecurityApplicationGroupIdentifier: AblageConfig.group) else { throw CocoaError(.fileNoSuchFile) }
        let folder = root.appendingPathComponent("Captures", isDirectory: true)
        try FileManager.default.createDirectory(at: folder, withIntermediateDirectories: true)
        return folder
    }
    static func prune() throws {
        let folder = try directory()
        for file in try FileManager.default.contentsOfDirectory(at: folder, includingPropertiesForKeys: [.contentModificationDateKey]) {
            let date = try file.resourceValues(forKeys: [.contentModificationDateKey]).contentModificationDate ?? .distantPast
            if Date().timeIntervalSince(date) > 86400 { try FileManager.default.removeItem(at: file) }
        }
    }
    static func save(_ clip: [String: Any]) throws -> String {
        guard let type = clip["type"] as? String, ["article", "page", "link"].contains(type),
              let url = clip["url"] as? String, let parsed = URL(string: url), ["https", "http"].contains(parsed.scheme ?? ""),
              let content = clip["content"] as? String, content.utf8.count <= 1_000_000,
              let archive = clip["archive"] as? String, archive.utf8.count <= 5_000_000,
              let title = clip["title"] as? String, title.count <= 300 else { throw CocoaError(.coderInvalidValue) }
        try prune()
        let id = UUID().uuidString.lowercased()
        let folder = try directory()
        let files = try FileManager.default.contentsOfDirectory(at: folder, includingPropertiesForKeys: [.contentModificationDateKey])
        guard files.count < 20 else { throw CocoaError(.fileWriteOutOfSpace) }
        let allowed = Set(["type", "url", "content", "archive", "title", "contentFormat", "note", "tags", "collectionId", "captureQuality"])
        let data = try JSONSerialization.data(withJSONObject: clip.filter { allowed.contains($0.key) })
        let file = try directory().appendingPathComponent(id + ".json")
        try data.write(to: file, options: [.atomic, .completeFileProtection])
        return id
    }
    static func load(_ id: String) throws -> [String: Any] {
        guard UUID(uuidString: id) != nil else { throw CocoaError(.coderInvalidValue) }
        try prune()
        let data = try Data(contentsOf: directory().appendingPathComponent(id.lowercased() + ".json"))
        guard let clip = try JSONSerialization.jsonObject(with: data) as? [String: Any] else { throw CocoaError(.coderInvalidValue) }
        return clip
    }
    static func update(_ id: String, clip: [String: Any]) throws {
        guard UUID(uuidString: id) != nil else { throw CocoaError(.coderInvalidValue) }
        let file = try directory().appendingPathComponent(id.lowercased() + ".json")
        guard FileManager.default.fileExists(atPath: file.path),
              let content = clip["content"] as? String, content.utf8.count <= 1_000_000,
              let archive = clip["archive"] as? String, archive.utf8.count <= 5_000_000 else { throw CocoaError(.coderInvalidValue) }
        let allowed = Set(["type", "url", "content", "archive", "title", "contentFormat", "note", "tags", "collectionId", "captureQuality"])
        let data = try JSONSerialization.data(withJSONObject: clip.filter { allowed.contains($0.key) })
        guard data.count <= 6_100_000 else { throw CocoaError(.coderInvalidValue) }
        try data.write(to: file, options: [.atomic, .completeFileProtection])
    }
    static func remove(_ id: String) { guard UUID(uuidString: id) != nil else { return }; try? FileManager.default.removeItem(at: directory().appendingPathComponent(id.lowercased() + ".json")) }
}
