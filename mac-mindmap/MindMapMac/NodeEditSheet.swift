import SwiftUI

struct NodeEditSheet: View {
    @State private var node: MindNode
    let onSave: (MindNode) -> Void
    @Environment(\.dismiss) private var dismiss

    // Grille 2 colonnes pour les icônes
    private let iconColumns = Array(repeating: GridItem(.fixed(44), spacing: 6), count: 6)

    init(node: MindNode, onSave: @escaping (MindNode) -> Void) {
        _node = State(initialValue: node)
        self.onSave = onSave
    }

    var body: some View {
        VStack(alignment: .leading, spacing: 0) {

            // Titre
            Text("Modifier le nœud")
                .font(.title3)
                .fontWeight(.semibold)
                .padding(.horizontal, 20)
                .padding(.top, 20)
                .padding(.bottom, 16)

            Divider()

            ScrollView {
                VStack(alignment: .leading, spacing: 14) {

                    // Texte
                    GroupBox {
                        VStack(alignment: .leading, spacing: 6) {
                            sectionLabel("Texte")
                            TextField("Texte du nœud", text: $node.text)
                                .textFieldStyle(.roundedBorder)
                        }
                        .padding(4)
                    }

                    // Note
                    GroupBox {
                        VStack(alignment: .leading, spacing: 6) {
                            sectionLabel("Note (optionnelle)")
                            TextEditor(text: $node.note)
                                .frame(minHeight: 60, maxHeight: 100)
                                .font(.body)
                                .padding(4)
                                .background(Color(nsColor: .textBackgroundColor))
                                .cornerRadius(6)
                                .overlay(RoundedRectangle(cornerRadius: 6).stroke(Color.gray.opacity(0.3)))
                        }
                        .padding(4)
                    }

                    // Lien URL
                    GroupBox {
                        VStack(alignment: .leading, spacing: 6) {
                            sectionLabel("Lien URL (optionnel)")
                            TextField("https://…", text: $node.fileURL)
                                .textFieldStyle(.roundedBorder)
                        }
                        .padding(4)
                    }

                    // Couleur
                    GroupBox {
                        VStack(alignment: .leading, spacing: 8) {
                            sectionLabel("Couleur")
                            HStack(spacing: 10) {
                                ForEach(NodeColor.allCases, id: \.self) { color in
                                    colorButton(color)
                                }
                            }
                        }
                        .padding(4)
                    }

                    // Icône
                    GroupBox {
                        VStack(alignment: .leading, spacing: 8) {
                            sectionLabel("Icône")
                            LazyVGrid(columns: iconColumns, spacing: 6) {
                                iconButton(nil)
                                ForEach(NodeIcon.allCases, id: \.self) { icon in
                                    iconButton(icon)
                                }
                            }
                        }
                        .padding(4)
                    }
                }
                .padding(16)
            }

            Divider()

            // Boutons
            HStack {
                Spacer()
                Button("Annuler") { dismiss() }
                    .keyboardShortcut(.escape)
                Button("Enregistrer") {
                    let trimmed = node.text.trimmingCharacters(in: .whitespaces)
                    guard !trimmed.isEmpty else { return }
                    node.text = trimmed
                    onSave(node)
                    dismiss()
                }
                .keyboardShortcut(.return)
                .buttonStyle(.borderedProminent)
            }
            .padding(.horizontal, 20)
            .padding(.vertical, 14)
        }
        .frame(width: 380, height: 540)
    }

    // MARK: - Sous-composants

    @ViewBuilder
    private func sectionLabel(_ text: String) -> some View {
        Text(text)
            .font(.caption)
            .fontWeight(.medium)
            .foregroundStyle(.secondary)
    }

    @ViewBuilder
    private func colorButton(_ color: NodeColor) -> some View {
        let selected = node.color == color
        ZStack {
            Circle().fill(color.fill)
            if selected {
                Circle()
                    .strokeBorder(Color.primary, lineWidth: 2.5)
                    .padding(-3)
                Circle()
                    .strokeBorder(Color.white, lineWidth: 2)
                    .padding(-1)
            }
        }
        .frame(width: 30, height: 30)
        .onTapGesture { node.color = color }
        .help(color.label)
        .animation(.easeInOut(duration: 0.1), value: selected)
    }

    @ViewBuilder
    private func iconButton(_ icon: NodeIcon?) -> some View {
        let selected = node.icon == icon
        Text(icon?.emoji ?? "∅")
            .font(.title3)
            .frame(width: 44, height: 38)
            .background(selected ? Color.accentColor.opacity(0.18) : Color(nsColor: .controlBackgroundColor))
            .cornerRadius(7)
            .overlay(
                RoundedRectangle(cornerRadius: 7)
                    .stroke(selected ? Color.accentColor : Color.gray.opacity(0.2), lineWidth: selected ? 1.5 : 1)
            )
            .onTapGesture { node.icon = icon }
            .help(icon?.label ?? "Aucune icône")
            .animation(.easeInOut(duration: 0.1), value: selected)
    }
}
