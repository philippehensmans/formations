import SwiftUI
import UniformTypeIdentifiers

extension UTType {
    static let mindmapDocument = UTType(exportedAs: "com.formations.mindmap")
}

struct MindMapDocument: FileDocument {
    static var readableContentTypes: [UTType] { [.mindmapDocument] }

    var mindmap: MindMap

    init() {
        mindmap = MindMap()
    }

    init(configuration: ReadConfiguration) throws {
        guard let data = configuration.file.regularFileContents else {
            throw CocoaError(.fileReadCorruptFile)
        }
        mindmap = try JSONDecoder().decode(MindMap.self, from: data)
    }

    func fileWrapper(configuration: WriteConfiguration) throws -> FileWrapper {
        let encoder = JSONEncoder()
        encoder.outputFormatting = .prettyPrinted
        let data = try encoder.encode(mindmap)
        return FileWrapper(regularFileWithContents: data)
    }
}
