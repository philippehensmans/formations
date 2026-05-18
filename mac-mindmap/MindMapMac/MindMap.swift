import Foundation
import CoreGraphics

struct MindMap: Codable {
    var title: String = "Ma carte mentale"
    var nodes: [MindNode] = []

    init() {
        let root = MindNode(
            text: "Idée centrale",
            color: .violet,
            posX: 2000,
            posY: 1500,
            isRoot: true
        )
        nodes = [root]
    }

    var root: MindNode? { nodes.first(where: { $0.isRoot }) }

    mutating func addChild(text: String, color: NodeColor, icon: NodeIcon?, parentID: UUID) -> MindNode {
        guard let parent = nodes.first(where: { $0.id == parentID }) else {
            return MindNode(text: text)
        }
        // Choisir un angle qui évite les nœuds déjà existants
        let siblings = children(of: parentID)
        let usedAngles = siblings.map { atan2($0.posY - parent.posY, $0.posX - parent.posX) }
        var angle = Double.random(in: 0..<(2 * .pi))
        // Essayer jusqu'à 8 angles pour éviter les superpositions
        for attempt in 0..<8 {
            let candidate = Double(attempt) * (.pi / 4)
            let tooClose = usedAngles.contains { abs($0 - candidate) < .pi / 6 }
            if !tooClose { angle = candidate; break }
        }
        let distance = 180.0
        let node = MindNode(
            text: text,
            color: color,
            icon: icon,
            posX: parent.posX + cos(angle) * distance,
            posY: parent.posY + sin(angle) * distance,
            parentID: parentID
        )
        nodes.append(node)
        return node
    }

    mutating func update(_ node: MindNode) {
        guard let idx = nodes.firstIndex(where: { $0.id == node.id }) else { return }
        nodes[idx] = node
    }

    mutating func move(id: UUID, to point: CGPoint) {
        guard let idx = nodes.firstIndex(where: { $0.id == id }) else { return }
        nodes[idx].posX = point.x
        nodes[idx].posY = point.y
    }

    mutating func delete(id: UUID) {
        let children = nodes.filter { $0.parentID == id }.map { $0.id }
        nodes.removeAll { $0.id == id }
        for childID in children { delete(id: childID) }
    }

    mutating func reparent(id: UUID, newParentID: UUID) {
        guard let idx = nodes.firstIndex(where: { $0.id == id }) else { return }
        guard !isDescendant(of: id, candidate: newParentID) else { return }
        nodes[idx].parentID = newParentID
    }

    func isDescendant(of ancestorID: UUID, candidate: UUID) -> Bool {
        var current: UUID? = candidate
        while let c = current {
            if c == ancestorID { return true }
            current = nodes.first(where: { $0.id == c })?.parentID
        }
        return false
    }

    func children(of parentID: UUID) -> [MindNode] {
        nodes.filter { $0.parentID == parentID }
    }
}
