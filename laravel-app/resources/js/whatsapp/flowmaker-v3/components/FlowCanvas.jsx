import React, { useMemo, useRef, useState } from 'react';
import { edgePath } from '../util';
import { NodeCard } from './NodeCard';

const NODE_WIDTH = 268;
const NODE_HEIGHT = 150;

export function FlowCanvas({
    nodes,
    edges,
    selectedNodeId,
    onSelectNode,
    onClearSelection,
    onMoveNode,
    onDeleteNode,
    onDropNode,
}) {
    const wrapRef = useRef(null);
    const [drag, setDrag] = useState(null);

    const nodeMap = useMemo(() => new Map(nodes.map((node) => [node.id, node])), [nodes]);

    function canvasPoint(event) {
        const rect = wrapRef.current.getBoundingClientRect();
        return {
            x: event.clientX - rect.left,
            y: event.clientY - rect.top,
        };
    }

    function handleMouseMove(event) {
        if (!drag) return;
        const point = canvasPoint(event);
        onMoveNode(drag.nodeId, Math.max(20, Math.round(point.x - drag.dx)), Math.max(20, Math.round(point.y - drag.dy)));
    }

    function handleMouseUp() {
        setDrag(null);
    }

    function handleDrop(event) {
        event.preventDefault();
        const type = event.dataTransfer.getData('nodeType');
        if (!type) return;
        const point = canvasPoint(event);
        onDropNode(type, { x: Math.round(point.x - 130), y: Math.round(point.y - 36) });
    }

    return (
        <div
            ref={wrapRef}
            className="fm-canvas-wrap"
            onMouseMove={handleMouseMove}
            onMouseUp={handleMouseUp}
            onMouseLeave={handleMouseUp}
            onMouseDown={onClearSelection}
            onDragOver={(event) => event.preventDefault()}
            onDrop={handleDrop}
        >
            <div className="fm-canvas">
                <svg className="fm-edges">
                    {edges.map((edge) => {
                        const source = nodeMap.get(edge.source);
                        const target = nodeMap.get(edge.target);
                        if (!source || !target) return null;
                        const x1 = source.position.x + NODE_WIDTH;
                        const y1 = source.position.y + NODE_HEIGHT / 2;
                        const x2 = target.position.x;
                        const y2 = target.position.y + NODE_HEIGHT / 2;
                        return (
                            <path
                                key={edge.id}
                                className="fm-edge-path visible"
                                d={edgePath(x1, y1, x2, y2)}
                            />
                        );
                    })}
                </svg>

                {nodes.map((node) => (
                    <NodeCard
                        key={node.id}
                        node={node}
                        selected={node.id === selectedNodeId}
                        onSelect={onSelectNode}
                        onDelete={onDeleteNode}
                        onDragStart={(event, currentNode) => {
                            event.stopPropagation();
                            const point = canvasPoint(event);
                            setDrag({
                                nodeId: currentNode.id,
                                dx: point.x - currentNode.position.x,
                                dy: point.y - currentNode.position.y,
                            });
                            onSelectNode(currentNode.id);
                        }}
                    />
                ))}
            </div>
            <div className="fm-canvas-controls">
                <div className="fm-zoom-label">{nodes.length} nodos</div>
            </div>
        </div>
    );
}
