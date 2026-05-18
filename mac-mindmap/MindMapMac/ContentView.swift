import SwiftUI

struct ContentView: View {
    @Binding var document: MindMapDocument

    var body: some View {
        MindMapCanvasView(mindmap: $document.mindmap)
            .frame(minWidth: 800, minHeight: 600)
            .toolbar {
                ToolbarItem(placement: .principal) {
                    Text(document.mindmap.title)
                        .font(.headline)
                        .foregroundStyle(.secondary)
                }
                ToolbarItem {
                    Menu {
                        Button("Exporter en PDF") { ExportManager.exportPDF(mindmap: document.mindmap) }
                        Button("Exporter en RTF") { ExportManager.exportRTF(mindmap: document.mindmap) }
                    } label: {
                        Label("Exporter", systemImage: "square.and.arrow.up")
                    }
                }
            }
    }
}
