import SwiftUI

struct NodeEditSheet: View {
    @State private var node: MindNode
    let onSave: (MindNode) -> Void
    @Environment(\.dismiss) private var dismiss

    init(node: MindNode, onSave: @escaping (MindNode) -> Void) {
        _node = State(initialValue: node)
        self.onSave = onSave
    }

    var body: some View {
        VStack(alignment: .leading, spacing: 16) {
            Text("Modifier le nœud")
                .font(.headline)

            Group {
                label("Texte")
                TextField("Texte du nœud", text: $node.text)
                    .textFieldStyle(.roundedBorder)

                label("Note")
                TextEditor(text: $node.note)
                    .frame(height: 70)
                    .border(Color.gray.opacity(0.3))
                    .cornerRadius(4)

                label("Lien URL")
                TextField("https://...", text: $node.fileURL)
                    .textFieldStyle(.roundedBorder)
            }

            label("Couleur")
            HStack(spacing: 8) {
                ForEach(NodeColor.allCases, id: \.self) { color in
                    Circle()
                        .fill(color.fill)
                        .frame(width: 28, height: 28)
                        .overlay(
                            Circle()
                                .stroke(Color.black, lineWidth: node.color == color ? 3 : 0)
                                .padding(2)
                        )
                        .onTapGesture { node.color = color }
                        .help(color.label)
                }
            }

            label("Icône")
            HStack(spacing: 6) {
                iconButton(nil)
                ForEach(NodeIcon.allCases, id: \.self) { icon in
                    iconButton(icon)
                }
            }

            Divider()

            HStack {
                Spacer()
                Button("Annuler") { dismiss() }
                    .keyboardShortcut(.escape)
                Button("Enregistrer") {
                    if !node.text.trimmingCharacters(in: .whitespaces).isEmpty {
                        onSave(node)
                        dismiss()
                    }
                }
                .keyboardShortcut(.return)
                .buttonStyle(.borderedProminent)
            }
        }
        .padding(20)
        .frame(width: 420)
    }

    @ViewBuilder
    private func label(_ text: String) -> some View {
        Text(text)
            .font(.caption)
            .foregroundStyle(.secondary)
    }

    @ViewBuilder
    private func iconButton(_ icon: NodeIcon?) -> some View {
        let selected = node.icon == icon
        Text(icon?.emoji ?? "∅")
            .font(.title3)
            .frame(width: 34, height: 34)
            .background(selected ? Color.accentColor.opacity(0.2) : Color.clear)
            .cornerRadius(6)
            .overlay(RoundedRectangle(cornerRadius: 6).stroke(selected ? Color.accentColor : Color.clear, lineWidth: 1.5))
            .onTapGesture { node.icon = icon }
            .help(icon?.label ?? "Aucune icône")
    }
}
