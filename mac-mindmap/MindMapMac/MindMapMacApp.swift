import SwiftUI

@main
struct MindMapMacApp: App {
    var body: some Scene {
        DocumentGroup(newDocument: MindMapDocument()) { file in
            ContentView(document: file.$document)
        }
        .commands {
            CommandGroup(replacing: .newItem) {
                Button("Nouveau…") {
                    NSDocumentController.shared.newDocument(nil)
                }
                .keyboardShortcut("n")
            }
        }
    }
}
