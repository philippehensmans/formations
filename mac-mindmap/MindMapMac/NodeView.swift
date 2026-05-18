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

            if isHovered {
                nodeActions
            }
        }
        .frame(width: node.nodeWidth, height: node.nodeHeight)
        .onHover { isHovered = $0 }
    }

    private var nodeActions: some View {
        HStack(spacing: 3) {
            actionButton(label: "+", color: .green, help: "Ajouter un enfant", action: onAddChild)
            actionButton(label: "✏️", color: .blue, help: "Modifier", action: onEdit)
            if !node.isRoot {
                actionButton(label: "×", color: .red, help: "Supprimer", action: onDelete)
            }
        }
        .offset(y: -(node.nodeHeight / 2 + 14))
    }

    private func actionButton(label: String, color: Color, help: String, action: @escaping () -> Void) -> some View {
        Button(action: action) {
            Text(label)
                .font(.caption)
                .fontWeight(.bold)
                .foregroundStyle(.white)
                .frame(width: 22, height: 22)
                .background(color)
                .clipShape(Circle())
        }
        .buttonStyle(.plain)
        .help(help)
    }
}
