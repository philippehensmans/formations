import SwiftUI

struct NodeView: View {
    let node: MindNode
    let isDropTarget: Bool
    let onAddChild: () -> Void
    let onEdit: () -> Void
    let onDelete: () -> Void

    @State private var isHovered = false

    var body: some View {
        ZStack {
            RoundedRectangle(cornerRadius: 20)
                .fill(node.color.fill)
                .overlay(
                    RoundedRectangle(cornerRadius: 20)
                        .stroke(isDropTarget ? Color.white : node.color.stroke, lineWidth: isDropTarget ? 4 : 2.5)
                )
                .shadow(color: .black.opacity(isHovered ? 0.3 : 0.15), radius: isHovered ? 8 : 4)
                .scaleEffect(isDropTarget ? 1.08 : 1.0)
                .animation(.easeInOut(duration: 0.15), value: isDropTarget)

            HStack(spacing: 4) {
                if let icon = node.icon {
                    Text(icon.emoji).font(node.isRoot ? .body : .callout)
                }
                Text(node.text)
                    .font(node.isRoot ? .headline : .callout)
                    .fontWeight(node.isRoot ? .bold : .medium)
                    .foregroundStyle(node.color.textColor)
                    .lineLimit(2)
                if !node.note.isEmpty {
                    Text("📝").font(.caption2).opacity(0.9)
                }
                if !node.fileURL.isEmpty {
                    Text("🔗").font(.caption2).opacity(0.9)
                }
            }
            .padding(.horizontal, 14)
            .padding(.vertical, 6)
        }
        .frame(width: node.nodeWidth, height: node.nodeHeight)
        .onHover { isHovered = $0 }
        // Clic droit → menu contextuel (fiable, natif macOS)
        .contextMenu {
            Button("➕  Ajouter un enfant") { onAddChild() }
            Button("✏️  Modifier") { onEdit() }
            if !node.isRoot {
                Divider()
                Button("🗑️  Supprimer", role: .destructive) { onDelete() }
            }
        }
        // Tooltip sur les indicateurs
        .help(node.note.isEmpty ? "" : "Note : \(node.note)")
    }
}
