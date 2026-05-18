import SwiftUI

// MARK: - Canvas view (infinite pan + zoom)

struct MindMapCanvasView: View {
    @Binding var mindmap: MindMap

    // Camera state
    @State private var scale: CGFloat = 1.0
    @State private var lastScale: CGFloat = 1.0
    @State private var panOffset: CGSize = .zero
    @State private var panStart: CGSize = .zero

    // Interaction state
    @State private var editingNode: MindNode? = nil
    @State private var draggedID: UUID? = nil
    @State private var dragTargetID: UUID? = nil
    @State private var dragTranslation: CGSize = .zero

    // Toolbar state
    @State private var currentColor: NodeColor = .blue
    @State private var currentIcon: NodeIcon? = nil

    private let canvasW: CGFloat = 8000
    private let canvasH: CGFloat = 6000

    var body: some View {
        GeometryReader { geo in
            ZStack(alignment: .topLeading) {
                canvasLayer(geo: geo)
                toolbarOverlay
            }
            .onAppear { centerOnRoot(in: geo.size) }
        }
    }

    // MARK: - Canvas layer

    private func canvasLayer(geo: GeometryProxy) -> some View {
        ZStack(alignment: .topLeading) {
            // Background grid
            Canvas { ctx, size in
                drawGrid(ctx: ctx, size: size)
            }
            .frame(width: canvasW, height: canvasH)

            // Connections
            Canvas { ctx, size in
                drawConnections(ctx: ctx)
            }
            .frame(width: canvasW, height: canvasH)
            .allowsHitTesting(false)

            // Nodes
            ForEach(mindmap.nodes) { node in
                let pos = displayPosition(for: node)
                NodeView(
                    node: node,
                    isDropTarget: dragTargetID == node.id,
                    onAddChild: { addChild(to: node.id) },
                    onEdit: { editingNode = node },
                    onDelete: { mindmap.delete(id: node.id) }
                )
                .position(pos)
                .gesture(nodeDragGesture(node: node, geoSize: geo.size))
                .onTapGesture(count: 2) { editingNode = node }
            }
        }
        .frame(width: canvasW, height: canvasH)
        .scaleEffect(scale, anchor: .topLeading)
        .offset(panOffset)
        .gesture(panGesture())
        .gesture(magnifyGesture())
        .clipped()
        .sheet(item: $editingNode) { node in
            NodeEditSheet(node: node) { updated in mindmap.update(updated) }
        }
    }

    // MARK: - Toolbar overlay

    private var toolbarOverlay: some View {
        VStack {
            HStack(spacing: 8) {
                // Zoom controls
                Group {
                    toolBtn("−") { zoomOut() }
                    toolBtn("+") { zoomIn() }
                    toolBtn("⊙") { resetView() }
                }

                Divider().frame(height: 24)

                // Colors
                ForEach(NodeColor.allCases, id: \.self) { c in
                    Circle()
                        .fill(c.fill)
                        .frame(width: 22, height: 22)
                        .overlay(Circle().stroke(Color.primary, lineWidth: currentColor == c ? 2.5 : 0))
                        .onTapGesture { currentColor = c }
                        .help(c.label)
                }

                Divider().frame(height: 24)

                // Icons
                ForEach(NodeIcon.allCases, id: \.self) { icon in
                    Text(icon.emoji)
                        .font(.callout)
                        .frame(width: 28, height: 28)
                        .background(currentIcon == icon ? Color.accentColor.opacity(0.2) : Color.clear)
                        .cornerRadius(6)
                        .onTapGesture { currentIcon = currentIcon == icon ? nil : icon }
                        .help(icon.label)
                }
                Text("∅")
                    .font(.callout)
                    .frame(width: 28, height: 28)
                    .background(currentIcon == nil ? Color.accentColor.opacity(0.2) : Color.clear)
                    .cornerRadius(6)
                    .onTapGesture { currentIcon = nil }
                    .help("Aucune icône")

                Spacer()

                // Export
                Button("PDF") { ExportManager.exportPDF(mindmap: mindmap) }
                    .buttonStyle(.bordered)
                Button("RTF") { ExportManager.exportRTF(mindmap: mindmap) }
                    .buttonStyle(.bordered)
            }
            .padding(.horizontal, 12)
            .padding(.vertical, 6)
            .background(.ultraThinMaterial, in: RoundedRectangle(cornerRadius: 10))
            .padding(10)

            Spacer()
        }
    }

    private func toolBtn(_ label: String, action: @escaping () -> Void) -> some View {
        Button(action: action) {
            Text(label).frame(width: 24, height: 24)
        }
        .buttonStyle(.bordered)
    }

    // MARK: - Node positioning

    // Nodes are stored in absolute canvas coords.
    // During drag, we show a translated position without modifying the model yet.
    private func displayPosition(for node: MindNode) -> CGPoint {
        if draggedID == node.id {
            return CGPoint(
                x: node.posX + dragTranslation.width / scale,
                y: node.posY + dragTranslation.height / scale
            )
        }
        return CGPoint(x: node.posX, y: node.posY)
    }

    // MARK: - Gestures

    private func nodeDragGesture(node: MindNode, geoSize: CGSize) -> some Gesture {
        DragGesture(minimumDistance: 3)
            .onChanged { value in
                draggedID = node.id
                dragTranslation = value.translation

                // Find drop target
                let currentPos = CGPoint(
                    x: node.posX + value.translation.width / scale,
                    y: node.posY + value.translation.height / scale
                )
                dragTargetID = mindmap.nodes.first { candidate in
                    guard candidate.id != node.id else { return false }
                    let dx = candidate.posX - currentPos.x
                    let dy = candidate.posY - currentPos.y
                    let hw = candidate.nodeWidth / 2
                    let hh = candidate.nodeHeight / 2
                    return abs(dx) < hw && abs(dy) < hh
                }?.id
            }
            .onEnded { value in
                defer {
                    draggedID = nil
                    dragTranslation = .zero
                    dragTargetID = nil
                }
                let newPos = CGPoint(
                    x: node.posX + value.translation.width / scale,
                    y: node.posY + value.translation.height / scale
                )
                if let targetID = dragTargetID, !node.isRoot, targetID != node.parentID {
                    mindmap.reparent(id: node.id, newParentID: targetID)
                } else {
                    mindmap.move(id: node.id, to: newPos)
                }
            }
    }

    private func panGesture() -> some Gesture {
        DragGesture(minimumDistance: 5)
            .onChanged { value in
                panOffset = CGSize(
                    width: panStart.width + value.translation.width,
                    height: panStart.height + value.translation.height
                )
            }
            .onEnded { _ in panStart = panOffset }
    }

    private func magnifyGesture() -> some Gesture {
        MagnificationGesture()
            .onChanged { value in
                scale = max(0.2, min(3.0, lastScale * value))
            }
            .onEnded { _ in lastScale = scale }
    }

    // MARK: - Zoom helpers

    private func zoomIn()  { withAnimation { scale = min(3.0, scale * 1.25); lastScale = scale } }
    private func zoomOut() { withAnimation { scale = max(0.2, scale * 0.8); lastScale = scale } }
    private func resetView() {
        withAnimation {
            scale = 1.0
            lastScale = 1.0
            panOffset = .zero
            panStart = .zero
        }
    }

    private func centerOnRoot(in size: CGSize) {
        guard let root = mindmap.root else { return }
        panOffset = CGSize(
            width: size.width / 2 - root.posX * scale,
            height: size.height / 2 - root.posY * scale
        )
        panStart = panOffset
    }

    // MARK: - Add child

    private func addChild(to parentID: UUID) {
        let panel = AddNodePanel()
        guard let text = panel.run(), !text.trimmingCharacters(in: .whitespaces).isEmpty else { return }
        _ = mindmap.addChild(text: text, color: currentColor, icon: currentIcon, parentID: parentID)
    }

    // MARK: - Drawing

    private func drawGrid(ctx: GraphicsContext, size: CGSize) {
        let step: CGFloat = 20
        var path = Path()
        var x: CGFloat = 0
        while x <= size.width { path.move(to: CGPoint(x: x, y: 0)); path.addLine(to: CGPoint(x: x, y: size.height)); x += step }
        var y: CGFloat = 0
        while y <= size.height { path.move(to: CGPoint(x: 0, y: y)); path.addLine(to: CGPoint(x: size.width, y: y)); y += step }
        ctx.stroke(path, with: .color(Color(hex: "e2e8f0")), lineWidth: 0.5)
    }

    private func drawConnections(ctx: GraphicsContext) {
        for node in mindmap.nodes {
            guard let parentID = node.parentID,
                  let parent = mindmap.nodes.first(where: { $0.id == parentID }) else { continue }

            let start = displayPosition(for: parent)
            let end = displayPosition(for: node)
            let midX = (start.x + end.x) / 2

            // Shadow
            var shadow = Path()
            shadow.move(to: start)
            shadow.addCurve(to: end,
                            control1: CGPoint(x: midX, y: start.y),
                            control2: CGPoint(x: midX, y: end.y))
            ctx.stroke(shadow, with: .color(.black.opacity(0.08)), style: StrokeStyle(lineWidth: 8, lineCap: .round))

            // Main curve
            var path = Path()
            path.move(to: start)
            path.addCurve(to: end,
                          control1: CGPoint(x: midX, y: start.y),
                          control2: CGPoint(x: midX, y: end.y))
            ctx.stroke(path, with: .color(node.color.stroke), style: StrokeStyle(lineWidth: 3.5, lineCap: .round))
        }
    }
}

// MARK: - Simple text input panel

private class AddNodePanel {
    func run() -> String? {
        let alert = NSAlert()
        alert.messageText = "Nouveau nœud"
        alert.informativeText = "Saisissez le texte du nœud :"
        alert.addButton(withTitle: "Ajouter")
        alert.addButton(withTitle: "Annuler")
        let field = NSTextField(frame: NSRect(x: 0, y: 0, width: 280, height: 24))
        field.placeholderString = "Texte…"
        alert.accessoryView = field
        alert.window.initialFirstResponder = field
        let response = alert.runModal()
        return response == .alertFirstButtonReturn ? field.stringValue : nil
    }
}
