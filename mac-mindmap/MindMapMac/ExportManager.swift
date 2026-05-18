import AppKit
import UniformTypeIdentifiers

struct ExportManager {

    // MARK: - PDF

    static func exportPDF(mindmap: MindMap) {
        let html = buildHTML(mindmap: mindmap)
        guard let data = html.data(using: .utf8) else { return }

        let panel = NSSavePanel()
        panel.allowedContentTypes = [.pdf]
        panel.nameFieldStringValue = "\(mindmap.title).pdf"
        guard panel.runModal() == .OK, let url = panel.url else { return }

        // Write HTML to temp, then print to PDF
        let tmpURL = URL(fileURLWithPath: NSTemporaryDirectory()).appendingPathComponent("mindmap_export.html")
        try? data.write(to: tmpURL)

        let webView = WebViewPrinter(htmlURL: tmpURL, outputURL: url)
        webView.print()
    }

    // MARK: - RTF

    static func exportRTF(mindmap: MindMap) {
        let panel = NSSavePanel()
        panel.allowedContentTypes = [UTType(filenameExtension: "rtf")!]
        panel.nameFieldStringValue = "\(mindmap.title).rtf"
        guard panel.runModal() == .OK, let url = panel.url else { return }

        var rtf = "{\\rtf1\\ansi\\deff0 {\\fonttbl{\\f0 Arial;}}\n"
        rtf += "\\f0\\fs24\n"
        rtf += "\\b \(escapeRTF(mindmap.title))\\b0\\par\\par\n"

        func addNode(_ node: MindNode, level: Int) {
            let indent = String(repeating: "\\tab ", count: level)
            let icon = node.icon.map { $0.emoji + " " } ?? ""
            let bullet = level == 0 ? "\\b " : "• "
            let endBold = level == 0 ? "\\b0" : ""
            rtf += "\(indent)\(bullet)\(icon)\(escapeRTF(node.text))\(endBold)\\par\n"
            if !node.note.isEmpty {
                rtf += "\(indent)\\tab {\\i \(escapeRTF(node.note))}\\par\n"
            }
            if !node.fileURL.isEmpty {
                rtf += "\(indent)\\tab {Lien: \(escapeRTF(node.fileURL))}\\par\n"
            }
            for child in mindmap.children(of: node.id) {
                addNode(child, level: level + 1)
            }
        }

        if let root = mindmap.root { addNode(root, level: 0) }
        rtf += "}"

        try? rtf.write(to: url, atomically: true, encoding: .utf8)
    }

    // MARK: - Helpers

    private static func escapeRTF(_ s: String) -> String {
        s.replacingOccurrences(of: "\\", with: "\\\\")
         .replacingOccurrences(of: "{", with: "\\{")
         .replacingOccurrences(of: "}", with: "\\}")
    }

    private static func buildHTML(mindmap: MindMap) -> String {
        var html = """
        <!DOCTYPE html><html><head><meta charset="UTF-8">
        <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        h1 { color: #8b5cf6; }
        ul { list-style: none; padding-left: 24px; }
        li { margin: 6px 0; }
        .root { font-size: 18px; font-weight: bold; color: #8b5cf6; }
        .note { color: #666; font-style: italic; font-size: 0.9em; display: block; margin-left: 20px; }
        .link { color: #3b82f6; font-size: 0.85em; display: block; margin-left: 20px; }
        </style></head><body>
        <h1>\(mindmap.title)</h1><ul>
        """

        func renderNode(_ node: MindNode, isRoot: Bool) -> String {
            let icon = node.icon.map { $0.emoji + " " } ?? ""
            let cls = isRoot ? "root" : ""
            var s = "<li><span class=\"\(cls)\">\(icon)\(node.text)</span>"
            if !node.note.isEmpty { s += "<span class=\"note\">📝 \(node.note)</span>" }
            if !node.fileURL.isEmpty { s += "<span class=\"link\">🔗 <a href=\"\(node.fileURL)\">\(node.fileURL)</a></span>" }
            let children = mindmap.children(of: node.id)
            if !children.isEmpty {
                s += "<ul>" + children.map { renderNode($0, isRoot: false) }.joined() + "</ul>"
            }
            s += "</li>"
            return s
        }

        if let root = mindmap.root { html += renderNode(root, isRoot: true) }
        html += "</ul></body></html>"
        return html
    }
}

// Minimal helper to print HTML to PDF via WKWebView
import WebKit

class WebViewPrinter: NSObject, WKNavigationDelegate {
    let webView = WKWebView()
    let htmlURL: URL
    let outputURL: URL

    init(htmlURL: URL, outputURL: URL) {
        self.htmlURL = htmlURL
        self.outputURL = outputURL
        super.init()
        webView.navigationDelegate = self
    }

    func print() {
        webView.loadFileURL(htmlURL, allowingReadAccessTo: htmlURL.deletingLastPathComponent())
    }

    func webView(_ webView: WKWebView, didFinish navigation: WKNavigation!) {
        let config = WKPDFConfiguration()
        webView.createPDF(configuration: config) { [weak self] result in
            guard let self = self else { return }
            if case .success(let data) = result {
                try? data.write(to: self.outputURL)
            }
        }
    }
}
