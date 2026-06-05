import React, { useEffect, useMemo, useRef, useState } from 'react';
import { edgePath } from '../util';
import { NodeCard } from './NodeCard';

const NODE_WIDTH = 268;
const NODE_HEIGHT = 150;
const MIN_ZOOM = 0.35;
const MAX_ZOOM = 1.35;

export function FlowCanvas({
    nodes,
    edges,
    selectedNodeId,
    selectedEdgeId,
    onSelectNode,
    onSelectEdge,
    onClearSelection,
    onMoveNode,
    onAddEdge,
    onDeleteEdge,
    onDeleteNode,
    onDropNode,
}) {
    const wrapRef = useRef(null);
    const [drag, setDrag] = useState(null);
    const [view, setView] = useState({ x: 0, y: 0, zoom: 0.82 });
    const [pointer, setPointer] = useState(null);

    const nodeMap = useMemo(() => new Map(nodes.map((node) => [node.id, node])), [nodes]);
    const connectedByNode = useMemo(() => {
        const map = new Map();
        edges.forEach((edge) => {
            const handle = edge.sourceHandle || 'source';
            if (!map.has(edge.source)) map.set(edge.source, new Set());
            map.get(edge.source).add(handle);
        });
        return map;
    }, [edges]);
    const bounds = useMemo(() => graphBounds(nodes), [nodes]);

    useEffect(() => {
        if (nodes.length > 0 && view.x === 0 && view.y === 0) {
            fitToView();
        }
        // Run only for initial load of graph nodes.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [nodes.length]);

    function screenPoint(event) {
        const rect = wrapRef.current.getBoundingClientRect();
        return {
            x: event.clientX - rect.left,
            y: event.clientY - rect.top,
        };
    }

    function canvasPoint(event) {
        const point = screenPoint(event);
        return {
            x: (point.x - view.x) / view.zoom,
            y: (point.y - view.y) / view.zoom,
        };
    }

    function handleMouseMove(event) {
        if (!drag) return;
        const point = canvasPoint(event);
        setPointer(point);

        if (drag.type === 'node') {
            onMoveNode(drag.nodeId, Math.max(20, Math.round(point.x - drag.dx)), Math.max(20, Math.round(point.y - drag.dy)));
            return;
        }

        if (drag.type === 'pan') {
            const screen = screenPoint(event);
            setView((current) => ({
                ...current,
                x: drag.startView.x + screen.x - drag.startScreen.x,
                y: drag.startView.y + screen.y - drag.startScreen.y,
            }));
        }
    }

    function handleMouseUp() {
        if (drag?.type === 'connect') {
            setPointer(null);
        }
        setDrag(null);
    }

    function handleDrop(event) {
        event.preventDefault();
        const type = event.dataTransfer.getData('nodeType');
        if (!type) return;
        const point = canvasPoint(event);
        onDropNode(type, { x: Math.round(point.x - 130), y: Math.round(point.y - 36) });
    }

    function handleWheel(event) {
        event.preventDefault();
        const screen = screenPoint(event);
        const nextZoom = clamp(view.zoom + (event.deltaY > 0 ? -0.08 : 0.08), MIN_ZOOM, MAX_ZOOM);
        const worldBefore = {
            x: (screen.x - view.x) / view.zoom,
            y: (screen.y - view.y) / view.zoom,
        };

        setView({
            zoom: nextZoom,
            x: screen.x - worldBefore.x * nextZoom,
            y: screen.y - worldBefore.y * nextZoom,
        });
    }

    function zoomBy(delta) {
        const rect = wrapRef.current.getBoundingClientRect();
        const center = { clientX: rect.left + rect.width / 2, clientY: rect.top + rect.height / 2 };
        handleWheel({
            ...center,
            deltaY: delta > 0 ? -1 : 1,
            preventDefault: () => {},
        });
    }

    function fitToView() {
        const rect = wrapRef.current?.getBoundingClientRect();
        if (!rect || nodes.length === 0) return;
        const padding = 80;
        const zoom = clamp(Math.min((rect.width - padding) / bounds.width, (rect.height - padding) / bounds.height), MIN_ZOOM, 1);
        setView({
            zoom,
            x: Math.round((rect.width - bounds.width * zoom) / 2 - bounds.minX * zoom),
            y: Math.round((rect.height - bounds.height * zoom) / 2 - bounds.minY * zoom),
        });
    }

    function startCanvasPan(event) {
        const isBlankCanvas = event.target === event.currentTarget || event.target?.classList?.contains('fm-canvas');
        if (event.button !== 0 || !isBlankCanvas) return;
        const screen = screenPoint(event);
        setDrag({ type: 'pan', startScreen: screen, startView: view });
        onClearSelection();
    }

    function edgeMidpoint(edge) {
        const source = nodeMap.get(edge.source);
        const target = nodeMap.get(edge.target);
        if (!source || !target) return null;
        return {
            x: (source.position.x + NODE_WIDTH + target.position.x) / 2,
            y: (source.position.y + target.position.y + NODE_HEIGHT) / 2,
        };
    }

    return (
        <div
            ref={wrapRef}
            className={`fm-canvas-wrap ${drag?.type === 'pan' ? 'panning' : ''} ${drag?.type === 'connect' ? 'connecting' : ''}`}
            onMouseMove={handleMouseMove}
            onMouseUp={handleMouseUp}
            onMouseLeave={handleMouseUp}
            onMouseDown={startCanvasPan}
            onWheel={handleWheel}
            onDragOver={(event) => event.preventDefault()}
            onDrop={handleDrop}
        >
            <div
                className="fm-canvas"
                style={{ transform: `translate(${view.x}px, ${view.y}px) scale(${view.zoom})` }}
            >
                <svg className="fm-edges">
                    {edges.map((edge) => {
                        const source = nodeMap.get(edge.source);
                        const target = nodeMap.get(edge.target);
                        if (!source || !target) return null;
                        const x1 = source.position.x + NODE_WIDTH;
                        const y1 = source.position.y + NODE_HEIGHT / 2;
                        const x2 = target.position.x;
                        const y2 = target.position.y + NODE_HEIGHT / 2;
                        const mid = edgeMidpoint(edge);
                        return (
                            <g
                                key={edge.id}
                                className={`fm-edge ${edge.id === selectedEdgeId ? 'sel' : ''}`}
                                onMouseDown={(event) => event.stopPropagation()}
                                onClick={(event) => {
                                    event.stopPropagation();
                                    onSelectEdge(edge.id);
                                }}
                            >
                                <path className="fm-edge-path hit" d={edgePath(x1, y1, x2, y2)} />
                                <path className="fm-edge-path visible" d={edgePath(x1, y1, x2, y2)} />
                                <circle className="fm-edge-dot" cx={x2} cy={y2} r="4" />
                                {edge.id === selectedEdgeId && mid && (
                                    <g
                                        className="fm-edge-del"
                                        transform={`translate(${mid.x}, ${mid.y})`}
                                        onClick={(event) => {
                                            event.stopPropagation();
                                            onDeleteEdge(edge.id);
                                        }}
                                    >
                                        <circle r="11" />
                                        <text textAnchor="middle" dominantBaseline="central">×</text>
                                    </g>
                                )}
                            </g>
                        );
                    })}
                    {drag?.type === 'connect' && pointer && (
                        <path
                            className="fm-temp-edge"
                            d={edgePath(
                                drag.sourcePosition.x,
                                drag.sourcePosition.y,
                                pointer.x,
                                pointer.y,
                            )}
                        />
                    )}
                </svg>

                {nodes.map((node) => (
                    <NodeCard
                        key={node.id}
                        node={node}
                        selected={node.id === selectedNodeId}
                        connectedHandles={connectedByNode.get(node.id) || new Set()}
                        connectingHandle={drag?.type === 'connect' ? { nodeId: drag.nodeId, handleId: drag.handleId } : null}
                        onSelect={onSelectNode}
                        onDelete={onDeleteNode}
                        onDragStart={(event, currentNode) => {
                            event.stopPropagation();
                            const point = canvasPoint(event);
                            setDrag({
                                type: 'node',
                                nodeId: currentNode.id,
                                dx: point.x - currentNode.position.x,
                                dy: point.y - currentNode.position.y,
                            });
                            onSelectNode(currentNode.id);
                        }}
                        onConnectStart={(event, nodeId, handleId) => {
                            const point = canvasPoint(event);
                            setPointer(point);
                            setDrag({
                                type: 'connect',
                                nodeId,
                                handleId,
                                sourcePosition: {
                                    x: node.position.x + NODE_WIDTH,
                                    y: node.position.y + NODE_HEIGHT / 2,
                                },
                            });
                            onSelectNode(nodeId);
                        }}
                        onConnectEnd={(targetId) => {
                            if (drag?.type === 'connect' && drag.nodeId !== targetId) {
                                onAddEdge(drag.nodeId, targetId, drag.handleId);
                            }
                            setPointer(null);
                            setDrag(null);
                        }}
                    />
                ))}
            </div>
            <div className="fm-canvas-controls">
                <div className="fm-zoom-group">
                    <button type="button" title="Acercar" onClick={() => zoomBy(1)}>+</button>
                    <button type="button" title="Alejar" onClick={() => zoomBy(-1)}>−</button>
                    <button type="button" title="Ajustar" onClick={fitToView}>
                        <span className="mdi mdi-fit-to-page-outline" />
                    </button>
                </div>
                <div className="fm-zoom-label">{Math.round(view.zoom * 100)}% · {nodes.length} nodos</div>
            </div>
            {nodes.length > 0 && (
                <MiniMap nodes={nodes} bounds={bounds} view={view} />
            )}
        </div>
    );
}

function MiniMap({ nodes, bounds, view }) {
    const width = 188;
    const height = 124;
    const scale = Math.min(width / bounds.width, height / bounds.height);
    const offsetX = (width - bounds.width * scale) / 2;
    const offsetY = (height - bounds.height * scale) / 2;

    return (
        <div className="fm-minimap">
            <svg viewBox={`0 0 ${width} ${height}`}>
                {nodes.map((node) => (
                    <rect
                        key={node.id}
                        className="fm-mm-node"
                        x={offsetX + (node.position.x - bounds.minX) * scale}
                        y={offsetY + (node.position.y - bounds.minY) * scale}
                        width={NODE_WIDTH * scale}
                        height={NODE_HEIGHT * scale}
                    />
                ))}
                <rect
                    className="fm-mm-view"
                    x={offsetX + ((-view.x / view.zoom) - bounds.minX) * scale}
                    y={offsetY + ((-view.y / view.zoom) - bounds.minY) * scale}
                    width={(900 / view.zoom) * scale}
                    height={(560 / view.zoom) * scale}
                />
            </svg>
        </div>
    );
}

function graphBounds(nodes) {
    if (nodes.length === 0) {
        return { minX: 0, minY: 0, width: 1000, height: 700 };
    }

    const minX = Math.min(...nodes.map((node) => node.position.x)) - 80;
    const minY = Math.min(...nodes.map((node) => node.position.y)) - 80;
    const maxX = Math.max(...nodes.map((node) => node.position.x + NODE_WIDTH)) + 80;
    const maxY = Math.max(...nodes.map((node) => node.position.y + NODE_HEIGHT)) + 80;

    return {
        minX,
        minY,
        width: Math.max(600, maxX - minX),
        height: Math.max(400, maxY - minY),
    };
}

function clamp(value, min, max) {
    return Math.min(max, Math.max(min, value));
}
