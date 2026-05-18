import SwiftUI

struct MindNode: Codable, Identifiable, Equatable {
    var id: UUID = UUID()
    var text: String
    var note: String = ""
    var fileURL: String = ""
    var color: NodeColor = .blue
    var icon: NodeIcon? = nil
    var posX: Double = 0
    var posY: Double = 0
    var parentID: UUID? = nil
    var isRoot: Bool = false

    static let width: CGFloat = 160
    static let height: CGFloat = 40
    static let rootWidth: CGFloat = 190
    static let rootHeight: CGFloat = 48

    var nodeWidth: CGFloat { isRoot ? MindNode.rootWidth : MindNode.width }
    var nodeHeight: CGFloat { isRoot ? MindNode.rootHeight : MindNode.height }

    var center: CGPoint { CGPoint(x: posX, y: posY) }
}

enum NodeColor: String, Codable, CaseIterable {
    case violet, blue, green, yellow, orange, red, pink, gray

    var fill: Color {
        switch self {
        case .violet: return Color(hex: "8b5cf6")
        case .blue:   return Color(hex: "3b82f6")
        case .green:  return Color(hex: "22c55e")
        case .yellow: return Color(hex: "facc15")
        case .orange: return Color(hex: "f97316")
        case .red:    return Color(hex: "ef4444")
        case .pink:   return Color(hex: "ec4899")
        case .gray:   return Color(hex: "6b7280")
        }
    }

    var stroke: Color {
        switch self {
        case .violet: return Color(hex: "7c3aed")
        case .blue:   return Color(hex: "2563eb")
        case .green:  return Color(hex: "16a34a")
        case .yellow: return Color(hex: "eab308")
        case .orange: return Color(hex: "ea580c")
        case .red:    return Color(hex: "dc2626")
        case .pink:   return Color(hex: "db2777")
        case .gray:   return Color(hex: "4b5563")
        }
    }

    var textColor: Color {
        self == .yellow ? Color(hex: "1f2937") : .white
    }

    var label: String { rawValue.capitalized }
}

enum NodeIcon: String, Codable, CaseIterable {
    case idea, question, check, warning, star, target, people, tools, calendar, money

    var emoji: String {
        switch self {
        case .idea:     return "💡"
        case .question: return "❓"
        case .check:    return "✅"
        case .warning:  return "⚠️"
        case .star:     return "⭐"
        case .target:   return "🎯"
        case .people:   return "👥"
        case .tools:    return "🔧"
        case .calendar: return "📅"
        case .money:    return "💰"
        }
    }

    var label: String {
        switch self {
        case .idea:     return "Idée"
        case .question: return "Question"
        case .check:    return "Validé"
        case .warning:  return "Attention"
        case .star:     return "Important"
        case .target:   return "Objectif"
        case .people:   return "Personnes"
        case .tools:    return "Outils"
        case .calendar: return "Date"
        case .money:    return "Budget"
        }
    }
}
