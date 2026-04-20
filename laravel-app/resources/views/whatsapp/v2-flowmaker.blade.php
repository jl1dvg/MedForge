@extends('layouts.medforge')

@php
    $flowmaker = is_array($flowmaker ?? null) ? $flowmaker : [];
    $flow = is_array($flowmaker['flow'] ?? null) ? $flowmaker['flow'] : null;
    $activeVersion = is_array($flowmaker['active_version'] ?? null) ? $flowmaker['active_version'] : null;
    $versions = is_array($flowmaker['versions'] ?? null) ? $flowmaker['versions'] : [];
    $stats = is_array($flowmaker['stats'] ?? null) ? $flowmaker['stats'] : [];
    $sessions = is_array($flowmaker['sessions'] ?? null) ? $flowmaker['sessions'] : [];
    $aiAgentPreview = is_array($aiAgentPreview ?? null) ? $aiAgentPreview : [];
    $aiAgentStats = is_array($aiAgentPreview['stats'] ?? null) ? $aiAgentPreview['stats'] : [];
    $aiAgentRuns = is_array($aiAgentPreview['runs'] ?? null) ? $aiAgentPreview['runs'] : [];
    $knowledgeBase = is_array($knowledgeBase ?? null) ? $knowledgeBase : [];
    $knowledgeStats = is_array($knowledgeBase['stats'] ?? null) ? $knowledgeBase['stats'] : [];
    $knowledgeDocuments = is_array($knowledgeBase['documents'] ?? null) ? $knowledgeBase['documents'] : [];
    $templates = is_array($templates ?? null) ? $templates : [];
    $contract = is_array($contract ?? null) ? $contract : [];
    $schema = is_array($contract['schema'] ?? null) ? $contract['schema'] : [];
    $scenarios = is_array($schema['scenarios'] ?? null) ? array_values(array_filter($schema['scenarios'], 'is_array')) : [];
@endphp

@push('styles')
<style>
    .wa-flow-pagebar {
        border-radius: 28px;
        padding: 24px 26px;
        background:
            radial-gradient(circle at top left, rgba(16, 185, 129, .16), transparent 34%),
            radial-gradient(circle at top right, rgba(14, 165, 233, .14), transparent 28%),
            linear-gradient(145deg, #0f172a 0%, #1e293b 48%, #115e59 100%);
        color: #f8fafc;
        box-shadow: 0 18px 40px rgba(15, 23, 42, .16);
    }
    .wa-flow-pagebar__top {
        display: flex;
        justify-content: space-between;
        gap: 18px;
        align-items: flex-start;
    }
    .wa-flow-pagebar__title {
        font-size: 28px;
        font-weight: 800;
        line-height: 1.05;
        letter-spacing: -.03em;
    }
    .wa-flow-pagebar__subtitle {
        margin-top: 8px;
        color: rgba(248, 250, 252, .82);
        max-width: 780px;
        font-size: 14px;
        line-height: 1.6;
    }
    .wa-flow-pagebar__meta {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        justify-content: flex-end;
    }
    .wa-flow-hero-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        border-radius: 999px;
        padding: 8px 12px;
        background: rgba(255, 255, 255, .12);
        border: 1px solid rgba(255, 255, 255, .14);
        color: #f8fafc;
        font-size: 12px;
        font-weight: 700;
    }
    .wa-flow-shell {
        display: grid;
        grid-template-columns: 320px minmax(0, 1fr);
        gap: 18px;
        align-items: start;
    }
    .wa-flow-panel {
        background: linear-gradient(180deg, #fff 0%, #f8fafc 100%);
        border: 1px solid rgba(148, 163, 184, .18);
        border-radius: 24px;
        box-shadow: 0 20px 40px rgba(15, 23, 42, .05);
        overflow: hidden;
    }
    .wa-flow-panel__head {
        padding: 18px 20px;
        border-bottom: 1px solid rgba(148, 163, 184, .14);
        background:
            radial-gradient(circle at top right, rgba(15, 118, 110, .08), transparent 40%),
            #fff;
    }
    .wa-flow-panel__body {
        padding: 18px 20px;
    }
    .wa-flow-kpis {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
    }
    .wa-flow-kpi {
        padding: 16px;
        border-radius: 20px;
        background: linear-gradient(180deg, rgba(255,255,255,.98), rgba(248,250,252,.96));
        border: 1px solid rgba(148, 163, 184, .16);
        box-shadow: 0 10px 24px rgba(15, 23, 42, .04);
    }
    .wa-flow-kpi__label {
        font-size: 12px;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: .08em;
    }
    .wa-flow-kpi__value {
        margin-top: .45rem;
        font-size: 28px;
        font-weight: 800;
        letter-spacing: -.04em;
        color: #0f172a;
        line-height: 1;
    }
    .wa-flow-kpi__sub {
        margin-top: .45rem;
        font-size: 12px;
        color: #64748b;
    }
    .wa-flow-sideheading__title {
        font-size: 18px;
        font-weight: 800;
        letter-spacing: -.02em;
        color: #0f172a;
    }
    .wa-flow-sideheading__meta {
        color: #64748b;
        font-size: 13px;
        line-height: 1.5;
    }
    .wa-flow-search {
        border-radius: 14px;
        border: 1px solid rgba(148, 163, 184, .18);
        background: #fff;
        width: 100%;
        padding: .8rem .9rem;
        font-size: .92rem;
    }
    .wa-flow-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
        max-height: 740px;
        overflow: auto;
    }
    .wa-flow-item {
        border: 1px solid rgba(148, 163, 184, .16);
        border-radius: 18px;
        padding: 14px;
        background: #fff;
        cursor: pointer;
        text-align: left;
        transition: border-color .18s ease, transform .18s ease, box-shadow .18s ease;
    }
    .wa-flow-item:hover {
        transform: translateY(-1px);
        border-color: rgba(15, 118, 110, .35);
        box-shadow: 0 12px 24px rgba(15, 23, 42, .06);
    }
    .wa-flow-item.is-active {
        border-color: #0f766e;
        background: linear-gradient(180deg, rgba(15, 118, 110, .08), rgba(255, 255, 255, 1));
        box-shadow: 0 14px 28px rgba(15, 118, 110, .12);
    }
    .wa-flow-item__top {
        display: flex;
        justify-content: space-between;
        gap: .75rem;
        align-items: start;
    }
    .wa-flow-item__name {
        font-weight: 800;
        color: #0f172a;
        line-height: 1.2;
        margin-bottom: .25rem;
    }
    .wa-flow-item__meta {
        display: flex;
        flex-wrap: wrap;
        gap: .35rem;
        margin-top: .45rem;
    }
    .wa-flow-badge {
        display: inline-flex;
        align-items: center;
        gap: .25rem;
        border-radius: 999px;
        padding: .22rem .6rem;
        font-size: 11px;
        font-weight: 700;
    }
    .wa-flow-badge--stage {
        background: rgba(37, 99, 235, .10);
        color: #1d4ed8;
    }
    .wa-flow-badge--menu {
        background: rgba(15, 118, 110, .10);
        color: #0f766e;
    }
    .wa-flow-badge--count {
        background: rgba(71, 85, 105, .10);
        color: #475569;
    }
    .wa-flow-workspace {
        display: grid;
        gap: 18px;
    }
    .wa-flow-stage-shell {
        display: grid;
        gap: 16px;
    }
    .wa-flow-canvas {
        display: flex;
        flex-direction: column;
        gap: 14px;
    }
    .wa-flow-canvas-shell {
        min-height: 760px;
        background:
            radial-gradient(circle at top left, rgba(37, 99, 235, .06), transparent 24%),
            radial-gradient(circle at top right, rgba(16, 185, 129, .08), transparent 22%),
            linear-gradient(180deg, #fbfdff 0%, #f8fafc 100%);
        border: 1px solid rgba(148, 163, 184, .14);
        border-radius: 22px;
        padding: 18px;
    }
    .wa-flow-canvas-toolbar {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        gap: 12px;
        align-items: center;
        margin-bottom: 14px;
    }
    .wa-flow-canvas-toolbar__meta {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }
    .wa-flow-canvas-toolbar__actions {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }
    .wa-flow-mini-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: .45rem .75rem;
        border-radius: 999px;
        background: #fff;
        border: 1px solid rgba(148, 163, 184, .18);
        color: #334155;
        font-size: 12px;
        font-weight: 700;
    }
    .wa-flow-node-track {
        display: grid;
        gap: 12px;
        position: relative;
    }
    .wa-flow-node-track::before {
        content: "";
        position: absolute;
        top: 12px;
        bottom: 12px;
        left: 20px;
        width: 2px;
        background: linear-gradient(180deg, rgba(37, 99, 235, .18), rgba(15, 118, 110, .22));
        pointer-events: none;
    }
    .wa-flow-node {
        position: relative;
        padding-left: 28px;
    }
    .wa-flow-node.is-simulated .wa-flow-block {
        border-color: rgba(37, 99, 235, .28);
        box-shadow: 0 14px 28px rgba(37, 99, 235, .10);
    }
    .wa-flow-node.is-simulated::before {
        box-shadow: 0 0 0 4px rgba(37, 99, 235, .14);
    }
    .wa-flow-node__connector {
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 0 0 10px 30px;
        color: #64748b;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .08em;
    }
    .wa-flow-node__connector::before {
        content: "";
        width: 18px;
        height: 2px;
        border-radius: 999px;
        background: rgba(148, 163, 184, .7);
    }
    .wa-flow-node__connector i {
        font-size: 14px;
        color: #94a3b8;
    }
    .wa-flow-node::before {
        content: "";
        position: absolute;
        left: 12px;
        top: 18px;
        width: 18px;
        height: 18px;
        border-radius: 999px;
        border: 3px solid #fff;
        box-shadow: 0 0 0 2px rgba(148, 163, 184, .24);
        background: #cbd5e1;
    }
    .wa-flow-node--scenario::before {
        background: #2563eb;
    }
    .wa-flow-node--conditions::before {
        background: #f59e0b;
    }
    .wa-flow-node--actions::before {
        background: #0f766e;
    }
    .wa-flow-node--transitions::before {
        background: #7c3aed;
    }
    .wa-flow-inline-grid {
        display: grid;
        grid-template-columns: 1.15fr .85fr;
        gap: 18px;
    }
    .wa-flow-inspector-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
    }
    .wa-flow-soft-panel {
        border: 1px solid rgba(148, 163, 184, .14);
        border-radius: 20px;
        background: linear-gradient(180deg, rgba(255,255,255,.98), rgba(248,250,252,.96));
        padding: 16px;
    }
    .wa-flow-soft-panel--full {
        grid-column: 1 / -1;
    }
    .wa-flow-stage {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        border-radius: 999px;
        background: rgba(37, 99, 235, .10);
        color: #1d4ed8;
        padding: .3rem .7rem;
        font-size: .74rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .05em;
    }
    .wa-flow-block {
        border: 1px solid rgba(148, 163, 184, .16);
        border-radius: 20px;
        background: #fff;
        overflow: hidden;
        box-shadow: 0 10px 24px rgba(15, 23, 42, .04);
    }
    .wa-flow-block__head {
        padding: 14px 16px;
        border-bottom: 1px solid rgba(148, 163, 184, .14);
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: .75rem;
        background: radial-gradient(circle at top left, rgba(14, 165, 233, .05), transparent 32%), #f8fafc;
    }
    .wa-flow-block__body {
        padding: 16px;
    }
    .wa-flow-block__meta {
        display: flex;
        flex-wrap: wrap;
        gap: .45rem;
        margin-top: .45rem;
    }
    .wa-flow-chip-row {
        display: flex;
        flex-wrap: wrap;
        gap: .5rem;
    }
    .wa-flow-chip {
        border-radius: 14px;
        padding: .6rem .8rem;
        background: #f8fafc;
        border: 1px solid rgba(148, 163, 184, .14);
        font-size: 12px;
        color: #0f172a;
    }
    .wa-flow-action {
        border-radius: 18px;
        border: 1px solid rgba(148, 163, 184, .16);
        background: linear-gradient(180deg, #ffffff, #fbfdff);
        padding: 14px;
        display: flex;
        flex-direction: column;
        gap: .55rem;
    }
    .wa-flow-action--message {
        border-color: rgba(14, 165, 233, .18);
        background: linear-gradient(180deg, rgba(240, 249, 255, .92), #ffffff);
    }
    .wa-flow-action--template {
        border-color: rgba(79, 70, 229, .18);
        background: linear-gradient(180deg, rgba(238, 242, 255, .92), #ffffff);
    }
    .wa-flow-action--handoff {
        border-color: rgba(245, 158, 11, .24);
        background: linear-gradient(180deg, rgba(255, 251, 235, .95), #ffffff);
    }
    .wa-flow-action--state {
        border-color: rgba(15, 118, 110, .18);
        background: linear-gradient(180deg, rgba(240, 253, 250, .95), #ffffff);
    }
    .wa-flow-action.is-runtime-hit,
    .wa-flow-transition-card.is-runtime-hit {
        border-color: rgba(37, 99, 235, .34);
        box-shadow: 0 12px 24px rgba(37, 99, 235, .12);
    }
    .wa-flow-action__top {
        display: flex;
        justify-content: space-between;
        gap: .75rem;
        align-items: start;
    }
    .wa-flow-action__label {
        font-weight: 800;
        color: #0f172a;
    }
    .wa-flow-node-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: .38rem .72rem;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: .04em;
        text-transform: uppercase;
        border: 1px solid transparent;
    }
    .wa-flow-node-badge--draft {
        background: rgba(148, 163, 184, .14);
        color: #475569;
        border-color: rgba(148, 163, 184, .22);
    }
    .wa-flow-node-badge--published {
        background: rgba(37, 99, 235, .10);
        color: #1d4ed8;
        border-color: rgba(37, 99, 235, .18);
    }
    .wa-flow-node-badge--warning {
        background: rgba(245, 158, 11, .10);
        color: #b45309;
        border-color: rgba(245, 158, 11, .18);
    }
    .wa-flow-node-badge--match {
        background: rgba(15, 118, 110, .10);
        color: #0f766e;
        border-color: rgba(15, 118, 110, .18);
    }
    .wa-flow-node-badge--mismatch {
        background: rgba(220, 38, 38, .10);
        color: #b91c1c;
        border-color: rgba(220, 38, 38, .18);
    }
    .wa-flow-transition-card {
        border-radius: 18px;
        border: 1px solid rgba(148, 163, 184, .16);
        background: linear-gradient(180deg, #ffffff, #fbfdff);
        padding: 14px;
        display: flex;
        flex-direction: column;
        gap: .7rem;
    }
    .wa-flow-transition-card__route {
        display: flex;
        align-items: center;
        gap: .5rem;
        flex-wrap: wrap;
        color: #334155;
        font-size: 12px;
        font-weight: 700;
    }
    .wa-flow-transition-card__route i {
        color: #7c3aed;
        font-size: 16px;
    }
    .wa-flow-code {
        background: #0f172a;
        color: #e2e8f0;
        border-radius: 16px;
        padding: 1rem;
        font-size: .78rem;
        line-height: 1.5;
        max-height: 360px;
        overflow: auto;
        white-space: pre-wrap;
        word-break: break-word;
    }
    .wa-flow-technical details {
        border: 1px solid rgba(148, 163, 184, .16);
        border-radius: 16px;
        background: #fff;
        padding: 10px 12px;
    }
    .wa-flow-technical summary {
        cursor: pointer;
        font-size: 12px;
        font-weight: 800;
        color: #334155;
        list-style: none;
    }
    .wa-flow-technical summary::-webkit-details-marker {
        display: none;
    }
    .wa-flow-toggle {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        padding: 12px 14px;
        border-radius: 16px;
        border: 1px solid rgba(148, 163, 184, .16);
        background: linear-gradient(180deg, rgba(255,255,255,.98), rgba(248,250,252,.96));
    }
    .wa-flow-toggle__copy {
        display: grid;
        gap: 4px;
    }
    .wa-flow-toggle__label {
        font-size: 13px;
        font-weight: 800;
        color: #0f172a;
    }
    .wa-flow-toggle__hint {
        font-size: 12px;
        color: #64748b;
    }
    .wa-flow-switch {
        position: relative;
        display: inline-flex;
        align-items: center;
        width: 56px;
        height: 32px;
        flex-shrink: 0;
    }
    .wa-flow-switch input {
        position: absolute;
        inset: 0;
        opacity: 0;
        cursor: pointer;
    }
    .wa-flow-switch__track {
        width: 56px;
        height: 32px;
        border-radius: 999px;
        background: rgba(148, 163, 184, .36);
        transition: background .18s ease;
    }
    .wa-flow-switch__thumb {
        position: absolute;
        top: 4px;
        left: 4px;
        width: 24px;
        height: 24px;
        border-radius: 999px;
        background: #fff;
        box-shadow: 0 4px 12px rgba(15, 23, 42, .18);
        transition: transform .18s ease;
    }
    .wa-flow-switch input:checked + .wa-flow-switch__track {
        background: #2563eb;
    }
    .wa-flow-switch input:checked ~ .wa-flow-switch__thumb {
        transform: translateX(24px);
    }
    .wa-flow-map {
        display: grid;
        gap: 12px;
        margin-bottom: 16px;
    }
    .wa-flow-map__title {
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: #64748b;
        font-weight: 800;
    }
    .wa-flow-map__grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 12px;
    }
    .wa-flow-map-card {
        border-radius: 18px;
        border: 1px solid rgba(148, 163, 184, .16);
        background: linear-gradient(180deg, #ffffff, #fbfdff);
        padding: 14px;
        text-align: left;
        cursor: pointer;
        display: grid;
        gap: 8px;
    }
    .wa-flow-map-card.is-active {
        border-color: #2563eb;
        box-shadow: 0 14px 28px rgba(37, 99, 235, .10);
        background: linear-gradient(180deg, rgba(37, 99, 235, .08), rgba(255,255,255,1));
    }
    .wa-flow-map-card__top {
        display: flex;
        justify-content: space-between;
        gap: 8px;
        align-items: flex-start;
    }
    .wa-flow-map-card__name {
        font-size: 14px;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.2;
    }
    .wa-flow-map-card__meta {
        font-size: 12px;
        color: #64748b;
    }
    .wa-flow-map-card__routes {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }
    .wa-flow-map-route {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border-radius: 999px;
        padding: .3rem .55rem;
        background: rgba(124, 58, 237, .08);
        color: #6d28d9;
        font-size: 11px;
        font-weight: 700;
    }
    .wa-flow-preview {
        background: radial-gradient(circle at top right, rgba(14,165,233,.05), transparent 24%), #f8fafc;
        border: 1px solid rgba(148, 163, 184, .16);
        border-radius: 16px;
        padding: 1rem;
        min-height: 200px;
        white-space: pre-wrap;
        word-break: break-word;
        font-size: .82rem;
        color: #0f172a;
    }
    .wa-flow-empty {
        padding: 2rem 1rem;
        text-align: center;
        color: #64748b;
    }
    .wa-flow-section-title {
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: #64748b;
        margin-bottom: .75rem;
    }
    .wa-flow-inline-note {
        font-size: 12px;
        color: #64748b;
        margin-top: .6rem;
    }
    .wa-flow-runtime-strip {
        display: grid;
        gap: 12px;
        margin-bottom: 12px;
    }
    .wa-flow-runtime-strip__card {
        border: 1px solid rgba(37, 99, 235, .14);
        border-radius: 18px;
        background: linear-gradient(180deg, rgba(255,255,255,.98), rgba(239,246,255,.96));
        padding: 14px;
    }
    .wa-flow-runtime-strip__title {
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: #2563eb;
        margin-bottom: .45rem;
        font-weight: 800;
    }
    .wa-flow-runtime-strip__body {
        color: #0f172a;
        font-size: 13px;
        line-height: 1.6;
        white-space: pre-wrap;
    }
    .wa-flow-timeline {
        display: flex;
        flex-direction: column;
        gap: .6rem;
        max-height: 240px;
        overflow: auto;
    }
    .wa-flow-version-stack {
        display: grid;
        gap: 12px;
    }
    .wa-flow-version-card {
        border: 1px solid rgba(148, 163, 184, .16);
        border-radius: 18px;
        background: #fff;
        padding: 14px;
        cursor: pointer;
        text-align: left;
        transition: border-color .18s ease, transform .18s ease, box-shadow .18s ease;
    }
    .wa-flow-version-card:hover {
        transform: translateY(-1px);
        border-color: rgba(37, 99, 235, .30);
        box-shadow: 0 12px 24px rgba(15, 23, 42, .06);
    }
    .wa-flow-version-card.is-active {
        border-color: #2563eb;
        background: linear-gradient(180deg, rgba(37, 99, 235, .08), rgba(255, 255, 255, 1));
        box-shadow: 0 14px 28px rgba(37, 99, 235, .10);
    }
    .wa-flow-version-card__top {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        align-items: flex-start;
    }
    .wa-flow-version-card__name {
        font-size: 16px;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.1;
    }
    .wa-flow-version-card__meta {
        margin-top: .55rem;
        display: flex;
        flex-wrap: wrap;
        gap: .35rem;
    }
    .wa-flow-version-compare {
        display: grid;
        gap: 14px;
    }
    .wa-flow-version-compare__grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
    }
    .wa-flow-version-stat {
        border: 1px solid rgba(148, 163, 184, .14);
        border-radius: 18px;
        background: linear-gradient(180deg, rgba(255,255,255,.98), rgba(248,250,252,.96));
        padding: 14px;
    }
    .wa-flow-version-stat__label {
        font-size: 11px;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: .08em;
    }
    .wa-flow-version-stat__value {
        margin-top: .45rem;
        font-size: 24px;
        font-weight: 800;
        line-height: 1;
        color: #0f172a;
    }
    .wa-flow-version-diff {
        border: 1px dashed rgba(148, 163, 184, .24);
        border-radius: 18px;
        padding: 14px;
        background: rgba(248, 250, 252, .9);
        color: #334155;
        font-size: 13px;
        line-height: 1.65;
        white-space: pre-wrap;
    }
    .wa-kb-grid {
        display: grid;
        grid-template-columns: 1.1fr .9fr;
        gap: 18px;
    }
    .wa-kb-list {
        display: grid;
        gap: 10px;
        max-height: 420px;
        overflow: auto;
    }
    .wa-kb-card {
        border: 1px solid rgba(148, 163, 184, .16);
        border-radius: 18px;
        background: #fff;
        padding: 14px;
    }
    .wa-kb-card__title {
        font-size: 15px;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.2;
    }
    .wa-kb-card__summary {
        margin-top: .5rem;
        font-size: 13px;
        color: #475569;
        line-height: 1.55;
    }
    .wa-kb-card__meta {
        display: flex;
        flex-wrap: wrap;
        gap: .35rem;
        margin-top: .65rem;
    }
    .wa-ai-run-card {
        border-radius: 18px;
        border: 1px solid rgba(148, 163, 184, .18);
        background: linear-gradient(180deg, #fff 0%, #f8fafc 100%);
        padding: 14px;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .wa-ai-run-card__top {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        align-items: flex-start;
    }
    .wa-ai-run-card__title {
        font-size: 14px;
        font-weight: 700;
        color: #0f172a;
    }
    .wa-ai-run-card__meta,
    .wa-ai-run-card__response {
        font-size: 12px;
        color: #64748b;
        line-height: 1.5;
    }
    .wa-ai-run-card__sources {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }
    .wa-flow-timeline__item {
        border-left: 3px solid rgba(15, 118, 110, .35);
        padding-left: .75rem;
    }
    .wa-flow-table td, .wa-flow-table th {
        font-size: .84rem;
        vertical-align: middle;
    }
    .wa-flow-stack {
        display: grid;
        gap: 18px;
    }
    .wa-flow-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .85rem;
    }
    .wa-flow-inline-actions {
        display: flex;
        flex-wrap: wrap;
        gap: .6rem;
    }
    .wa-flow-editor-field label {
        display: block;
        font-size: .76rem;
        text-transform: uppercase;
        letter-spacing: .05em;
        color: #64748b;
        margin-bottom: .35rem;
    }
    .wa-flow-editor-field input,
    .wa-flow-editor-field select,
    .wa-flow-editor-field textarea {
        width: 100%;
        border-radius: 12px;
        border: 1px solid rgba(15, 23, 42, .12);
        padding: .65rem .75rem;
        font-size: .88rem;
        background: #fff;
    }
    .wa-flow-editor-field textarea {
        min-height: 96px;
        resize: vertical;
    }
    @media (max-width: 1400px) {
        .wa-flow-shell {
            grid-template-columns: 290px minmax(0, 1fr);
        }
        .wa-flow-inline-grid {
            grid-template-columns: 1fr;
        }
    }
    @media (max-width: 992px) {
        .wa-flow-kpis {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .wa-flow-shell {
            grid-template-columns: 1fr;
        }
        .wa-flow-inspector-grid {
            grid-template-columns: 1fr;
        }
        .wa-flow-version-compare__grid {
            grid-template-columns: 1fr;
        }
        .wa-kb-grid {
            grid-template-columns: 1fr;
        }
    }
    @media (max-width: 767px) {
        .wa-flow-pagebar {
            padding: 20px 18px;
            border-radius: 24px;
        }
        .wa-flow-pagebar__top {
            flex-direction: column;
        }
        .wa-flow-panel__head,
        .wa-flow-panel__body {
            padding: 16px;
        }
        .wa-flow-kpis {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
</style>
@endpush

@section('content')
<section class="content">
    <div class="row g-3">
        <div class="col-12">
            <div class="wa-flow-pagebar">
                <div class="wa-flow-pagebar__top">
                    <div>
                        <div class="wa-flow-pagebar__title">Flowmaker y automatización</div>
                        <div class="wa-flow-pagebar__subtitle">
                            Consola operativa para revisar escenarios, editar condiciones y acciones, publicar versiones y medir paridad con legacy antes del corte real.
                        </div>
                    </div>
                    <div class="wa-flow-pagebar__meta">
                        <span class="wa-flow-hero-pill"><i class="mdi mdi-graph-outline"></i> {{ $flow['status'] ?? 'sin-configurar' }}</span>
                        <span class="wa-flow-hero-pill"><i class="mdi mdi-source-branch"></i> versión {{ $activeVersion['version'] ?? '—' }}</span>
                        <span class="wa-flow-hero-pill"><i class="mdi mdi-lightning-bolt-outline"></i> shadow listo</span>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-10 align-items-center mt-18">
                    <a href="/v2/whatsapp/api/flowmaker/contract" target="_blank" rel="noopener" class="btn btn-primary">Ver contrato JSON</a>
                    <button type="button" class="btn btn-success" id="wa-flow-publish-btn">Publicar JSON</button>
                    <span id="wa-flow-status" class="text-light" style="font-size:.84rem;"></span>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="wa-flow-kpis">
                <div class="wa-flow-kpi">
                    <div class="wa-flow-kpi__label">Versión activa</div>
                    <div class="wa-flow-kpi__value">{{ $activeVersion['version'] ?? '—' }}</div>
                    <div class="wa-flow-kpi__sub">{{ $activeVersion['published_at'] ?? 'Sin publicación' }}</div>
                </div>
                <div class="wa-flow-kpi">
                    <div class="wa-flow-kpi__label">Escenarios</div>
                    <div class="wa-flow-kpi__value">{{ count($scenarios) }}</div>
                    <div class="wa-flow-kpi__sub">Steps {{ $stats['steps'] ?? 0 }} · Acciones {{ $stats['actions'] ?? 0 }}</div>
                </div>
                <div class="wa-flow-kpi">
                    <div class="wa-flow-kpi__label">Sesiones activas</div>
                    <div class="wa-flow-kpi__value">{{ $stats['active_sessions'] ?? 0 }}</div>
                    <div class="wa-flow-kpi__sub">Input {{ $stats['sessions_waiting_input'] ?? 0 }} · Response {{ $stats['sessions_waiting_response'] ?? 0 }}</div>
                </div>
                <div class="wa-flow-kpi">
                    <div class="wa-flow-kpi__label">Filtros y horarios</div>
                    <div class="wa-flow-kpi__value">{{ $stats['filters'] ?? 0 }}</div>
                    <div class="wa-flow-kpi__sub">Schedules {{ $stats['schedules'] ?? 0 }} · Transiciones {{ $stats['transitions'] ?? 0 }}</div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="wa-flow-shell">
                <div class="wa-flow-panel">
                    <div class="wa-flow-panel__head">
                        <div class="d-flex justify-content-between align-items-center gap-10">
                            <div>
                                <div class="wa-flow-sideheading__title">Escenarios</div>
                                <div class="wa-flow-sideheading__meta">Selector lateral con la lógica publicada y acceso rápido al editor.</div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="wa-flow-add-scenario-btn">Nuevo</button>
                        </div>
                    </div>
                    <div class="wa-flow-panel__body">
                        <input type="text" class="wa-flow-search mb-15" id="wa-flow-search" placeholder="Buscar escenario, stage o acción">
                        <div class="wa-flow-list" id="wa-flow-scenario-list"></div>
                    </div>
                </div>

                <div class="wa-flow-workspace">
                    <div class="wa-flow-panel">
                        <div class="wa-flow-panel__head d-flex justify-content-between align-items-center gap-10">
                            <div>
                                <div class="wa-flow-sideheading__title" id="wa-flow-canvas-title">Escenario</div>
                                <div class="wa-flow-sideheading__meta" id="wa-flow-canvas-subtitle">Configuración del escenario, acciones y estados desde el canvas.</div>
                            </div>
                            <span class="wa-flow-stage" id="wa-flow-stage-badge">Sin stage</span>
                        </div>
                        <div class="wa-flow-panel__body">
                            <div class="wa-flow-canvas-shell">
                                <div class="wa-flow-canvas-toolbar">
                                    <div class="wa-flow-canvas-toolbar__meta" id="wa-flow-inspector-summary">
                                        <div class="wa-flow-mini-pill">Sin selección</div>
                                    </div>
                                    <div class="wa-flow-canvas-toolbar__actions">
                                        <span class="wa-flow-mini-pill"><i class="mdi mdi-vector-polyline"></i> canvas-first</span>
                                        <span class="wa-flow-mini-pill"><i class="mdi mdi-shield-check-outline"></i> shadow parity</span>
                                        <span class="wa-flow-mini-pill"><i class="mdi mdi-history"></i> versionado</span>
                                    </div>
                                </div>
                                <div class="wa-flow-canvas wa-flow-node-track" id="wa-flow-canvas">
                                    <div class="wa-flow-empty">No hay escenarios configurados.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="wa-flow-inline-grid">
                        <div class="wa-flow-panel">
                            <div class="wa-flow-panel__head">
                                <div class="wa-flow-sideheading__title">Simulación operativa</div>
                                <div class="wa-flow-sideheading__meta">Prueba el escenario actual, compara con legacy y revisa la ruta tomada sin salir del builder.</div>
                            </div>
                            <div class="wa-flow-panel__body">
                                <div class="wa-flow-stack">
                                    <div class="wa-flow-form-grid">
                                        <div>
                                            <label class="form-label">Número</label>
                                            <input id="wa-flow-sim-number" class="form-control" value="{{ $sessions[0]['wa_number'] ?? '593999111222' }}">
                                        </div>
                                        <div>
                                            <label class="form-label">Mensaje</label>
                                            <input id="wa-flow-sim-text" class="form-control" value="hola">
                                        </div>
                                    </div>
                                    <div>
                                        <label class="form-label">Contexto JSON opcional</label>
                                        <textarea id="wa-flow-sim-context" class="form-control" rows="5">{}</textarea>
                                    </div>
                                    <div class="d-flex flex-wrap gap-10">
                                        <button type="button" class="btn btn-primary" id="wa-flow-sim-btn">Simular mensaje</button>
                                        <button type="button" class="btn btn-outline-dark" id="wa-flow-compare-btn">Comparar con legacy</button>
                                    </div>
                                    <div>
                                        <div class="wa-flow-section-title">Resultado de simulación</div>
                                        <div class="wa-flow-preview" id="wa-flow-sim-output">Ejecuta una simulación para ver escenario matcheado, facts y acciones disparadas sin tocar el webhook real.</div>
                                    </div>
                                    <div>
                                        <div class="wa-flow-section-title">Shadow compare</div>
                                        <div class="wa-flow-preview" id="wa-flow-compare-output">Comparar con legacy ayuda a validar paridad antes de mover el runtime del webhook.</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="wa-flow-panel">
                            <div class="wa-flow-panel__head">
                                <div class="wa-flow-sideheading__title">Estado del runtime</div>
                                <div class="wa-flow-sideheading__meta">Readiness, resumen de paridad y runs recientes para publicar con criterio.</div>
                            </div>
                            <div class="wa-flow-panel__body">
                                <div class="wa-flow-inspector-grid">
                                    <div class="wa-flow-soft-panel wa-flow-soft-panel--full">
                                        <div class="wa-flow-section-title d-flex justify-content-between align-items-center">
                                            <span>Fase 6 está lista para cierre</span>
                                            <button type="button" class="btn btn-xs btn-outline-dark" id="wa-flow-shadow-refresh-btn">Actualizar</button>
                                        </div>
                                        <div class="wa-flow-preview" id="wa-flow-readiness-output">Todavía no se evaluó si Fase 6 está lista para cierre.</div>
                                    </div>
                                    <div class="wa-flow-soft-panel">
                                        <div class="wa-flow-section-title">Paridad del shadow runtime</div>
                                        <div class="wa-flow-preview" id="wa-flow-shadow-summary-output">Todavía no se cargó el resumen de paridad del shadow runtime.</div>
                                    </div>
                                    <div class="wa-flow-soft-panel">
                                        <div class="wa-flow-section-title">Shadow runs recientes</div>
                                        <div class="wa-flow-preview" id="wa-flow-shadow-runs-output">Todavía no se cargan runs del webhook en modo sombra.</div>
                                    </div>
                                    <div class="wa-flow-soft-panel wa-flow-soft-panel--full wa-flow-technical">
                                        <details>
                                            <summary>Payload técnico a publicar</summary>
                                            <div class="wa-flow-inline-note mt-12">Solo úsalo si necesitas revisar o pegar JSON manualmente. La operación normal debe hacerse desde el editor visual.</div>
                                            <textarea id="wa-flow-payload" class="form-control mt-12" rows="10">{{ json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</textarea>
                                        </details>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="wa-flow-inline-grid">
                        <div class="wa-flow-panel">
                            <div class="wa-flow-panel__head">
                                <div class="wa-flow-sideheading__title">Versiones recientes</div>
                                <div class="wa-flow-sideheading__meta">Timeline corto para comparar draft vs published y preparar rollback cuando exista endpoint.</div>
                            </div>
                            <div class="wa-flow-panel__body">
                                <div class="wa-flow-version-stack" id="wa-flow-version-list">
                                    @forelse($versions as $version)
                                        <button type="button" class="wa-flow-version-card {{ ($version['id'] ?? null) === ($activeVersion['id'] ?? null) ? 'is-active' : '' }}" data-version-id="{{ $version['id'] ?? '' }}">
                                            <div class="wa-flow-version-card__top">
                                                <div>
                                                    <div class="wa-flow-version-card__name">Versión {{ $version['version'] ?? '—' }}</div>
                                                    <div class="small text-muted">{{ $version['published_at'] ?? $version['created_at'] ?? '—' }}</div>
                                                </div>
                                                <span class="wa-flow-badge wa-flow-badge--stage">{{ $version['status'] ?? '—' }}</span>
                                            </div>
                                            <div class="wa-flow-version-card__meta">
                                                <span class="wa-flow-badge wa-flow-badge--count">
                                                    {{ count(($version['entry_settings']['flow']['scenarios'] ?? $version['entry_settings']['scenarios'] ?? [])) }} escenarios
                                                </span>
                                                <span class="wa-flow-badge wa-flow-badge--count">
                                                    id {{ $version['id'] ?? '—' }}
                                                </span>
                                            </div>
                                        </button>
                                    @empty
                                        <div class="wa-flow-empty">Aún no hay versiones publicadas.</div>
                                    @endforelse
                                </div>
                                <div class="wa-flow-inline-note mt-12">Rollback operativo pendiente de backend. Por ahora el builder compara y prepara la publicación.</div>
                            </div>
                        </div>

                        <div class="wa-flow-panel">
                            <div class="wa-flow-panel__head">
                                <div class="wa-flow-sideheading__title">Draft vs published</div>
                                <div class="wa-flow-sideheading__meta">Compara el payload actual contra la versión seleccionada sin salir del workspace.</div>
                            </div>
                            <div class="wa-flow-panel__body">
                                <div class="wa-flow-version-compare">
                                    <div class="wa-flow-version-compare__grid" id="wa-flow-version-stats">
                                        <div class="wa-flow-version-stat">
                                            <div class="wa-flow-version-stat__label">Versión</div>
                                            <div class="wa-flow-version-stat__value">—</div>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="wa-flow-section-title">Resumen de diferencias</div>
                                        <div class="wa-flow-version-diff" id="wa-flow-version-diff">Selecciona una versión para comparar contra el draft actual.</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="wa-flow-panel">
                            <div class="wa-flow-panel__head">
                                <div class="wa-flow-sideheading__title">Sesiones activas</div>
                                <div class="wa-flow-sideheading__meta">Conversaciones en curso y punto actual del runtime.</div>
                            </div>
                            <div class="wa-flow-panel__body">
                                <div class="wa-flow-timeline">
                                    @forelse($sessions as $session)
                                        <div class="wa-flow-timeline__item">
                                            <div class="fw-700">{{ $session['wa_number'] }}</div>
                                            <div class="small text-muted">{{ $session['scenario_id'] ?? '—' }} · {{ $session['awaiting'] ?? '—' }}</div>
                                            <div class="small text-muted">{{ $session['last_interaction_at'] ?? '—' }}</div>
                                        </div>
                                    @empty
                                        <div class="wa-flow-empty">No hay sesiones activas.</div>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="wa-flow-panel">
                        <div class="wa-flow-panel__head">
                            <div class="wa-flow-sideheading__title">Knowledge Base IA</div>
                            <div class="wa-flow-sideheading__meta">Base documental inicial para FAQs, sedes, seguros, pre y post operatorios, lista para alimentar el nodo AI Agent.</div>
                        </div>
                        <div class="wa-flow-panel__body">
                            <div class="wa-flow-kpis mb-15">
                                <div class="wa-flow-kpi">
                                    <div class="wa-flow-kpi__label">Documentos</div>
                                    <div class="wa-flow-kpi__value" id="wa-kb-total">{{ $knowledgeStats['total'] ?? 0 }}</div>
                                    <div class="wa-flow-kpi__sub">Base total indexada</div>
                                </div>
                                <div class="wa-flow-kpi">
                                    <div class="wa-flow-kpi__label">Publicados</div>
                                    <div class="wa-flow-kpi__value" id="wa-kb-published">{{ $knowledgeStats['published'] ?? 0 }}</div>
                                    <div class="wa-flow-kpi__sub">Listos para consulta</div>
                                </div>
                                <div class="wa-flow-kpi">
                                    <div class="wa-flow-kpi__label">Draft</div>
                                    <div class="wa-flow-kpi__value" id="wa-kb-draft">{{ $knowledgeStats['draft'] ?? 0 }}</div>
                                    <div class="wa-flow-kpi__sub">Pendientes de curación</div>
                                </div>
                                <div class="wa-flow-kpi">
                                    <div class="wa-flow-kpi__label">Fuentes</div>
                                    <div class="wa-flow-kpi__value" id="wa-kb-sources">{{ $knowledgeStats['sources'] ?? 0 }}</div>
                                    <div class="wa-flow-kpi__sub">Tipos de origen documentado</div>
                                </div>
                            </div>

                            <div class="wa-kb-grid">
                                <div>
                                    <div class="wa-flow-section-title">Documentos recientes</div>
                                    <div class="wa-kb-list" id="wa-kb-list">
                                        @forelse($knowledgeDocuments as $document)
                                            <div class="wa-kb-card">
                                                <div class="wa-kb-card__title">{{ $document['title'] ?? 'Documento KB' }}</div>
                                                <div class="wa-kb-card__summary">{{ $document['summary'] ?? 'Sin resumen.' }}</div>
                                                <div class="wa-kb-card__meta">
                                                    <span class="wa-flow-badge wa-flow-badge--stage">{{ $document['status'] ?? 'draft' }}</span>
                                                    <span class="wa-flow-badge wa-flow-badge--count">{{ $document['metadata']['tipo_contenido'] ?? 'faq' }}</span>
                                                    <span class="wa-flow-badge wa-flow-badge--count">{{ $document['metadata']['audiencia'] ?? 'paciente' }}</span>
                                                    <span class="wa-flow-badge wa-flow-badge--count">{{ $document['metadata']['sede'] ?? 'global' }}</span>
                                                </div>
                                            </div>
                                        @empty
                                            <div class="wa-flow-empty">Todavía no hay documentos en la Knowledge Base.</div>
                                        @endforelse
                                    </div>
                                </div>

                                <div>
                                    <div class="wa-flow-section-title">Alta rápida de documento</div>
                                    <div class="wa-flow-stack">
                                        <div class="wa-flow-form-grid">
                                            <div class="wa-flow-editor-field">
                                                <label>Título</label>
                                                <input type="text" id="wa-kb-title" placeholder="Consentimiento y uso de datos">
                                            </div>
                                            <div class="wa-flow-editor-field">
                                                <label>Estado</label>
                                                <select id="wa-kb-status">
                                                    <option value="draft">draft</option>
                                                    <option value="published">published</option>
                                                </select>
                                            </div>
                                            <div class="wa-flow-editor-field">
                                                <label>Sede</label>
                                                <input type="text" id="wa-kb-sede" placeholder="Matriz">
                                            </div>
                                            <div class="wa-flow-editor-field">
                                                <label>Especialidad</label>
                                                <input type="text" id="wa-kb-especialidad" placeholder="Oftalmología">
                                            </div>
                                            <div class="wa-flow-editor-field">
                                                <label>Tipo de contenido</label>
                                                <select id="wa-kb-type">
                                                    <option value="faq">faq</option>
                                                    <option value="policy">policy</option>
                                                    <option value="preoperatorio">preoperatorio</option>
                                                    <option value="postoperatorio">postoperatorio</option>
                                                    <option value="seguros">seguros</option>
                                                    <option value="consentimiento">consentimiento</option>
                                                </select>
                                            </div>
                                            <div class="wa-flow-editor-field">
                                                <label>Audiencia</label>
                                                <select id="wa-kb-audiencia">
                                                    <option value="paciente">paciente</option>
                                                    <option value="agente">agente</option>
                                                    <option value="supervisor">supervisor</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="wa-flow-editor-field">
                                            <label>Contenido</label>
                                            <textarea id="wa-kb-content" placeholder="Texto base para grounding controlado del AI Agent."></textarea>
                                        </div>
                                        <div class="d-flex flex-wrap gap-10 align-items-center">
                                            <button type="button" class="btn btn-primary" id="wa-kb-save-btn">Guardar documento KB</button>
                                            <span class="small text-muted" id="wa-kb-status-node">La base documental todavía no tiene integración con AI Agent.</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="wa-flow-panel">
                        <div class="wa-flow-panel__head">
                            <div class="wa-flow-sideheading__title">AI Agent preview</div>
                            <div class="wa-flow-sideheading__meta">Runs recientes del nodo `AI Agent` en modo preview. Todavía no ejecuta webhook real ni tools sensibles.</div>
                        </div>
                        <div class="wa-flow-panel__body">
                            <div class="wa-flow-kpis mb-15">
                                <div class="wa-flow-kpi">
                                    <div class="wa-flow-kpi__label">Runs</div>
                                    <div class="wa-flow-kpi__value" id="wa-ai-total">{{ $aiAgentStats['total_runs'] ?? 0 }}</div>
                                    <div class="wa-flow-kpi__sub">Preview persistido</div>
                                </div>
                                <div class="wa-flow-kpi">
                                    <div class="wa-flow-kpi__label">Handoff sugerido</div>
                                    <div class="wa-flow-kpi__value" id="wa-ai-handoff">{{ $aiAgentStats['handoff_suggested'] ?? 0 }}</div>
                                    <div class="wa-flow-kpi__sub">Por baja confianza</div>
                                </div>
                                <div class="wa-flow-kpi">
                                    <div class="wa-flow-kpi__label">Alta confianza</div>
                                    <div class="wa-flow-kpi__value" id="wa-ai-high">{{ $aiAgentStats['high_confidence'] ?? 0 }}</div>
                                    <div class="wa-flow-kpi__sub">>= 0.75</div>
                                </div>
                                <div class="wa-flow-kpi">
                                    <div class="wa-flow-kpi__label">Confianza media</div>
                                    <div class="wa-flow-kpi__value" id="wa-ai-avg">{{ $aiAgentStats['avg_confidence'] ?? 0 }}</div>
                                    <div class="wa-flow-kpi__sub">Preview</div>
                                </div>
                                <div class="wa-flow-kpi">
                                    <div class="wa-flow-kpi__label">Fallback runs</div>
                                    <div class="wa-flow-kpi__value" id="wa-ai-fallback">{{ $aiAgentStats['fallback_runs'] ?? 0 }}</div>
                                    <div class="wa-flow-kpi__sub">Guardrail activo</div>
                                </div>
                                <div class="wa-flow-kpi">
                                    <div class="wa-flow-kpi__label">Grounding medio</div>
                                    <div class="wa-flow-kpi__value" id="wa-ai-grounding">{{ $aiAgentStats['avg_grounding'] ?? 0 }}</div>
                                    <div class="wa-flow-kpi__sub">Score por run</div>
                                </div>
                                <div class="wa-flow-kpi">
                                    <div class="wa-flow-kpi__label">Safety medio</div>
                                    <div class="wa-flow-kpi__value" id="wa-ai-safety">{{ $aiAgentStats['avg_safety'] ?? 0 }}</div>
                                    <div class="wa-flow-kpi__sub">Guardrail básico</div>
                                </div>
                            </div>
                            <div class="wa-kb-list" id="wa-ai-runs-list">
                                @forelse($aiAgentRuns as $run)
                                    <div class="wa-ai-run-card">
                                        <div class="wa-ai-run-card__top">
                                            <div>
                                                <div class="wa-ai-run-card__title">{{ $run['scenario_id'] ?? 'AI Agent' }} · {{ $run['classification'] ?? 'general' }}</div>
                                                <div class="wa-ai-run-card__meta">{{ $run['wa_number'] ?? 'sin número' }} · conf {{ $run['confidence'] ?? 0 }} · {{ $run['created_at'] ?? '—' }}</div>
                                            </div>
                                            <span class="wa-flow-node-badge wa-flow-node-badge--{{ !empty($run['fallback_used']) ? 'warning' : (!empty($run['suggested_handoff']) ? 'draft' : 'match') }}">
                                                {{ $run['decision'] ?? (!empty($run['suggested_handoff']) ? 'respond_handoff' : 'respond') }}
                                            </span>
                                        </div>
                                        <div class="wa-ai-run-card__response">{{ $run['response_text'] ?? 'Sin respuesta sugerida todavía.' }}</div>
                                        <div class="wa-ai-run-card__meta">fallback={{ !empty($run['fallback_used']) ? 'sí' : 'no' }} · handoff={{ !empty($run['suggested_handoff']) ? 'sí' : 'no' }}</div>
                                        @if(!empty($run['handoff_reasons']))
                                            <div class="wa-ai-run-card__sources">
                                                @foreach(($run['handoff_reasons'] ?? []) as $reason)
                                                    <span class="wa-flow-badge wa-flow-badge--count">{{ $reason }}</span>
                                                @endforeach
                                            </div>
                                        @endif
                                        @if(!empty($run['scores']))
                                            <div class="wa-ai-run-card__meta">
                                                grounding {{ $run['scores']['grounding'] ?? '0' }} · safety {{ $run['scores']['safety'] ?? '0' }} · overall {{ $run['scores']['overall'] ?? '0' }}
                                            </div>
                                        @endif
                                        <div class="wa-ai-run-card__sources">
                                            @foreach(($run['matched_documents'] ?? []) as $document)
                                                <span class="wa-flow-badge wa-flow-badge--count">{{ $document['title'] ?? 'doc' }}</span>
                                            @endforeach
                                        </div>
                                    </div>
                                @empty
                                    <div class="wa-flow-empty">Todavía no hay ejecuciones del nodo AI Agent.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const initialSchema = @json($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    const initialTemplates = @json($templates, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    const activeVersion = @json($activeVersion, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    const versionsData = @json($versions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    const publishButton = document.getElementById('wa-flow-publish-btn');
    const payloadField = document.getElementById('wa-flow-payload');
    const statusNode = document.getElementById('wa-flow-status');
    const scenarioList = document.getElementById('wa-flow-scenario-list');
    const searchInput = document.getElementById('wa-flow-search');
    const addScenarioButton = document.getElementById('wa-flow-add-scenario-btn');
    const canvas = document.getElementById('wa-flow-canvas');
    const canvasTitle = document.getElementById('wa-flow-canvas-title');
    const canvasSubtitle = document.getElementById('wa-flow-canvas-subtitle');
    const stageBadge = document.getElementById('wa-flow-stage-badge');
    const inspectorSummary = document.getElementById('wa-flow-inspector-summary');
    const simButton = document.getElementById('wa-flow-sim-btn');
    const compareButton = document.getElementById('wa-flow-compare-btn');
    const shadowRefreshButton = document.getElementById('wa-flow-shadow-refresh-btn');
    const simNumber = document.getElementById('wa-flow-sim-number');
    const simText = document.getElementById('wa-flow-sim-text');
    const simContext = document.getElementById('wa-flow-sim-context');
    const simOutput = document.getElementById('wa-flow-sim-output');
    const compareOutput = document.getElementById('wa-flow-compare-output');
    const shadowRunsOutput = document.getElementById('wa-flow-shadow-runs-output');
    const shadowSummaryOutput = document.getElementById('wa-flow-shadow-summary-output');
    const readinessOutput = document.getElementById('wa-flow-readiness-output');
    const versionList = document.getElementById('wa-flow-version-list');
    const versionStats = document.getElementById('wa-flow-version-stats');
    const versionDiff = document.getElementById('wa-flow-version-diff');
    const kbList = document.getElementById('wa-kb-list');
    const kbSaveButton = document.getElementById('wa-kb-save-btn');
    const kbStatusNode = document.getElementById('wa-kb-status-node');
    const kbTitle = document.getElementById('wa-kb-title');
    const kbContent = document.getElementById('wa-kb-content');
    const kbStatus = document.getElementById('wa-kb-status');
    const kbSede = document.getElementById('wa-kb-sede');
    const kbEspecialidad = document.getElementById('wa-kb-especialidad');
    const kbType = document.getElementById('wa-kb-type');
    const kbAudiencia = document.getElementById('wa-kb-audiencia');
    const kbTotal = document.getElementById('wa-kb-total');
    const kbPublished = document.getElementById('wa-kb-published');
    const kbDraft = document.getElementById('wa-kb-draft');
    const kbSources = document.getElementById('wa-kb-sources');
    const aiRunsList = document.getElementById('wa-ai-runs-list');
    const aiTotal = document.getElementById('wa-ai-total');
    const aiHandoff = document.getElementById('wa-ai-handoff');
    const aiHigh = document.getElementById('wa-ai-high');
    const aiAvg = document.getElementById('wa-ai-avg');
    const aiFallback = document.getElementById('wa-ai-fallback');
    const aiGrounding = document.getElementById('wa-ai-grounding');
    const aiSafety = document.getElementById('wa-ai-safety');

    let editorSchema = JSON.parse(JSON.stringify(initialSchema || {}));
    const templateOptions = Array.isArray(initialTemplates) ? initialTemplates : [];
    let selectedVersionId = activeVersion?.id || versionsData?.[0]?.id || null;
    let selectedScenarioId = null;
    let latestShadowRows = [];
    let latestSimulation = null;
    let latestCompare = null;
    let knowledgeBaseState = {
        documents: @json($knowledgeDocuments, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        stats: @json($knowledgeStats, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    };
    let aiAgentState = {
        runs: @json($aiAgentRuns, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        stats: @json($aiAgentStats, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    };

    const escapeHtml = (value) => {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    };

    const pretty = (value) => JSON.stringify(value ?? {}, null, 2);
    const getScenarios = () => Array.isArray(editorSchema.scenarios) ? editorSchema.scenarios : [];
    const selectedScenario = () => getScenarios().find((item) => String(item?.id) === String(selectedScenarioId)) || null;
    const safeId = () => 'scenario_' + Math.random().toString(36).slice(2, 8);

    const actionTypeLabels = {
        send_message: 'Enviar mensaje',
        send_buttons: 'Enviar botones',
        send_list: 'Enviar lista',
        send_template: 'Enviar template',
        send_sequence: 'Enviar secuencia',
        set_state: 'Cambiar estado',
        set_context: 'Guardar contexto',
        store_consent: 'Guardar consentimiento',
        handoff_agent: 'Derivar a agente',
        ai_agent: 'AI Agent',
    };
    const actionLabel = (action) => actionTypeLabels[String(action?.type || '')] || String(action?.type || 'accion').replaceAll('_', ' ');
    const actionTypeSelectOptions = (selectedType = 'send_message') => ['send_message', 'send_buttons', 'send_list', 'send_template', 'send_sequence', 'set_state', 'set_context', 'store_consent', 'handoff_agent', 'ai_agent']
        .map((type) => `
            <option value="${type}" ${type === selectedType ? 'selected' : ''}>${escapeHtml(actionTypeLabels[type] || type)}</option>
        `).join('');
    const humanizeHandoffReason = (reason) => ({
        low_confidence: 'baja confianza',
        no_grounding: 'sin grounding',
        node_requested_handoff: 'handoff forzado',
        safety_guardrail: 'guardrail safety',
        user_requested_human: 'solicitó humano',
        window_closed: 'ventana cerrada',
    }[reason] || reason || 'motivo');
    const humanizeDecision = (decision) => ({
        respond: 'respuesta normal',
        fallback: 'fallback',
        respond_handoff: 'respuesta + handoff',
        fallback_handoff: 'fallback + handoff',
    }[decision] || decision || 'respond');
    const humanizeScenarioStatus = (status) => ({
        draft: 'Borrador',
        published: 'Publicado',
        paused: 'Pausado',
    }[String(status || 'published')] || String(status || 'published'));
    const stageLabels = {
        arrival: 'Llegada',
        validation: 'Validación',
        consent: 'Consentimiento',
        menu: 'Menú',
        scheduling: 'Agendamiento',
        results: 'Resultados',
        post: 'Post consulta',
        custom: 'Personalizado',
    };
    const conditionTypeLabels = {
        always: 'Siempre',
        is_first_time: 'Es primera vez',
        has_consent: 'Tiene consentimiento',
        state_is: 'Estado actual es',
        awaiting_is: 'Está esperando',
        message_in: 'Mensaje es una opción exacta',
        message_contains: 'Mensaje contiene texto',
        message_matches: 'Mensaje cumple patrón',
        last_interaction_gt: 'Minutos desde último mensaje',
        patient_found: 'Paciente encontrado',
        context_flag: 'Contexto contiene bandera',
    };
    const transitionConditionTypeLabels = {
        always: 'Siempre',
        message_in: 'Mensaje es una opción exacta',
        message_contains: 'Mensaje contiene texto',
        message_matches: 'Mensaje cumple patrón',
        state_is: 'Estado actual es',
        awaiting_is: 'Está esperando',
        context_flag: 'Contexto contiene bandera',
    };
    const conditionValueLabel = (type) => ({
        always: 'Sin valor',
        is_first_time: 'Sí o no',
        has_consent: 'Sí o no',
        state_is: 'Nombre del estado',
        awaiting_is: 'Campo esperado',
        message_in: 'Opciones separadas por coma',
        message_contains: 'Texto o palabra clave',
        message_matches: 'Patrón regex',
        last_interaction_gt: 'Minutos',
        patient_found: 'Sí o no',
        context_flag: 'Nombre de bandera',
    }[type] || 'Valor');
    const conditionHint = (type) => ({
        always: 'Este escenario siempre entra si llegó hasta aquí.',
        is_first_time: 'Útil para diferenciar primera interacción vs paciente recurrente.',
        has_consent: 'Usa true/false según el consentimiento guardado.',
        state_is: 'Activa este escenario solo si la conversación está en un estado exacto.',
        awaiting_is: 'Útil cuando esperas cédula, respuesta o un dato puntual.',
        message_in: 'Compara contra respuestas exactas como hola, menú, sí.',
        message_contains: 'Busca palabras dentro del mensaje.',
        message_matches: 'Usa un patrón cuando necesites validar formato.',
        last_interaction_gt: 'Se activa si ya pasó cierta cantidad de minutos.',
        patient_found: 'Usa true/false según la identificación del paciente.',
        context_flag: 'Revisa una bandera guardada en contexto.',
    }[type] || '');
    const transitionHint = (type) => ({
        always: 'Sale siempre por esta ruta.',
        message_in: 'La salida depende de una opción exacta.',
        message_contains: 'La salida depende de texto contenido.',
        message_matches: 'La salida depende de un formato.',
        state_is: 'La salida depende del estado actual.',
        awaiting_is: 'La salida depende del campo esperado.',
        context_flag: 'La salida depende de una bandera del contexto.',
    }[type] || '');
    const decisionBadgeTone = (run) => {
        if (run?.fallback_used) {
            return 'warning';
        }
        if (run?.suggested_handoff) {
            return 'draft';
        }
        return 'match';
    };
    const ensureScenarioDefaults = (scenario) => {
        if (!scenario || typeof scenario !== 'object') {
            return;
        }
        if (typeof scenario.id !== 'string' || scenario.id.trim() === '') {
            const baseName = typeof scenario.name === 'string' && scenario.name.trim() !== ''
                ? scenario.name.trim().toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '')
                : 'scenario';
            scenario.id = `${baseName || 'scenario'}_${Math.random().toString(36).slice(2, 6)}`;
        }
        if (!['draft', 'published', 'paused'].includes(String(scenario.status || 'published'))) {
            scenario.status = 'published';
        }
        if (!Array.isArray(scenario.conditions)) {
            scenario.conditions = [];
        }
        if (!Array.isArray(scenario.actions)) {
            scenario.actions = [];
        }
        if (!Array.isArray(scenario.transitions)) {
            scenario.transitions = [];
        }
        scenario.actions.forEach((action) => ensureActionDefaults(action));
    };
    const ensureMessageBody = (action, fallback = '') => {
        if (!action.message || typeof action.message !== 'object') {
            action.message = {type: 'text', body: fallback};
        }
        if (typeof action.message.type !== 'string' || action.message.type === '') {
            action.message.type = 'text';
        }
        if (typeof action.message.body !== 'string') {
            action.message.body = fallback;
        }
    };
    const ensureButtonsDefaults = (action) => {
        ensureMessageBody(action);
        action.message.type = 'buttons';
        if (!Array.isArray(action.message.buttons) || !action.message.buttons.length) {
            action.message.buttons = [
                {id: 'opcion_1', title: 'Opción 1'},
                {id: 'opcion_2', title: 'Opción 2'},
            ];
        }
    };
    const ensureListDefaults = (action) => {
        ensureMessageBody(action);
        action.message.type = 'list';
        if (typeof action.message.button_text !== 'string' || action.message.button_text === '') {
            action.message.button_text = 'Ver opciones';
        }
        if (!Array.isArray(action.message.sections) || !action.message.sections.length) {
            action.message.sections = [
                {
                    title: 'Opciones',
                    rows: [
                        {id: 'opcion_1', title: 'Opción 1', description: ''},
                        {id: 'opcion_2', title: 'Opción 2', description: ''},
                    ],
                },
            ];
        }
    };
    const ensureTemplateDefaults = (action) => {
        if (!action.template || typeof action.template !== 'object') {
            const firstTemplate = templateOptions[0] || null;
            action.template = {
                name: firstTemplate?.code || '',
                code: firstTemplate?.code || '',
            };
        }
        if (typeof action.template.name !== 'string') {
            action.template.name = action.template.code || '';
        }
    };
    const ensureActionDefaults = (action) => {
        if (!action || typeof action !== 'object') {
            return;
        }
        switch (action.type) {
            case 'send_message':
                ensureMessageBody(action, 'Nuevo mensaje');
                break;
            case 'send_buttons':
                ensureButtonsDefaults(action);
                break;
            case 'send_list':
                ensureListDefaults(action);
                break;
            case 'send_template':
                ensureTemplateDefaults(action);
                break;
            case 'set_state':
                if (typeof action.state !== 'string') {
                    action.state = '';
                }
                break;
            case 'handoff_agent':
                if (typeof action.note !== 'string') {
                    action.note = '';
                }
                if (!Number.isFinite(Number(action.role_id))) {
                    action.role_id = '';
                }
                break;
            case 'ai_agent':
                ensureAiAgentDefaults(action);
                break;
            default:
                if (action.message && typeof action.message === 'object') {
                    ensureMessageBody(action);
                }
                break;
        }
    };
    const ensureAiAgentDefaults = (action) => {
        if (!action || action.type !== 'ai_agent') {
            return;
        }
        if (typeof action.instructions !== 'string') {
            action.instructions = '';
        }
        if (typeof action.fallback_message !== 'string') {
            action.fallback_message = 'No encontré grounding suficiente en la Knowledge Base para responder con seguridad.';
        }
        if (!Number.isFinite(Number(action.handoff_threshold))) {
            action.handoff_threshold = 0.45;
        }
        if (!Number.isFinite(Number(action.reply_threshold))) {
            action.reply_threshold = 0.45;
        }
        if (typeof action.handoff !== 'boolean') {
            action.handoff = false;
        }
        if (!action.kb_filters || typeof action.kb_filters !== 'object') {
            action.kb_filters = {};
        }
    };
    const updateAiAgentState = (action, field, value) => {
        ensureAiAgentDefaults(action);
        if (!action) {
            return;
        }
        if (field.startsWith('kb_filters.')) {
            const key = field.replace('kb_filters.', '');
            action.kb_filters = action.kb_filters || {};
            action.kb_filters[key] = value;
            return;
        }
        if (field === 'handoff') {
            action.handoff = value === true || value === '1' || value === 'true';
            return;
        }
        if (['handoff_threshold', 'reply_threshold'].includes(field)) {
            const numeric = Number(value);
            action[field] = Number.isFinite(numeric) ? numeric : 0;
            return;
        }
        action[field] = value;
    };
    const setNestedValue = (target, path, value) => {
        if (!target || typeof target !== 'object' || !path) {
            return;
        }
        const parts = String(path).split('.').filter(Boolean);
        if (!parts.length) {
            return;
        }
        let cursor = target;
        parts.forEach((part, index) => {
            const isLast = index === parts.length - 1;
            const nextPart = parts[index + 1];
            const nextIsIndex = /^\d+$/.test(String(nextPart || ''));

            if (isLast) {
                cursor[part] = value;
                return;
            }

            if (cursor[part] === undefined || cursor[part] === null) {
                cursor[part] = nextIsIndex ? [] : {};
            }
            cursor = cursor[part];
        });
    };
    const updateActionConfig = (action, field, value) => {
        if (!action || !field) {
            return;
        }
        ensureActionDefaults(action);

        if (action.type === 'ai_agent') {
            updateAiAgentState(action, field, value);
            return;
        }

        if (field === 'role_id') {
            action.role_id = value === '' ? '' : Number(value);
            return;
        }

        if (field === 'template.code') {
            ensureTemplateDefaults(action);
            const selectedTemplate = templateOptions.find((template) => String(template.code || '') === String(value || '')) || null;
            action.template.code = String(value || '');
            action.template.name = selectedTemplate?.name || selectedTemplate?.code || String(value || '');
            return;
        }

        if (field === 'template.name') {
            ensureTemplateDefaults(action);
            action.template.name = String(value || '');
            return;
        }

        setNestedValue(action, field, value);
    };
    const templateSelectOptions = (selectedValue = '') => {
        const current = String(selectedValue || '');
        return [
            '<option value="">Selecciona template</option>',
            ...templateOptions.map((template) => `
                <option value="${escapeHtml(template.code || '')}" ${String(template.code || '') === current ? 'selected' : ''}>
                    ${escapeHtml(template.name || template.code || 'template')}
                </option>
            `),
        ].join('');
    };
    const scenarioSelectOptions = (selectedValue = '') => {
        const current = String(selectedValue || '');
        const options = getScenarios().map((scenario) => `
            <option value="${escapeHtml(scenario.id || '')}" ${String(scenario.id || '') === current ? 'selected' : ''}>
                ${escapeHtml(scenario.name || scenario.id || 'Escenario')}
            </option>
        `).join('');
        return [
            '<option value="">Sin destino</option>',
            options,
        ].join('');
    };
    const extractVersionFlow = (version) => {
        const entrySettings = version?.entry_settings;
        if (entrySettings && typeof entrySettings === 'object') {
            return entrySettings.flow && typeof entrySettings.flow === 'object'
                ? entrySettings.flow
                : entrySettings;
        }
        return {};
    };
    const normalizeEditorSchema = (schema) => {
        const normalized = schema && typeof schema === 'object'
            ? JSON.parse(JSON.stringify(schema))
            : {};

        if (!Array.isArray(normalized.scenarios) || !normalized.scenarios.length) {
            const fallbackFlow = extractVersionFlow(activeVersion);
            if (Array.isArray(fallbackFlow?.scenarios) && fallbackFlow.scenarios.length) {
                normalized.scenarios = JSON.parse(JSON.stringify(fallbackFlow.scenarios));
                if (typeof normalized.name !== 'string' || normalized.name === '') {
                    normalized.name = fallbackFlow.name || '';
                }
                if (typeof normalized.description !== 'string' || normalized.description === '') {
                    normalized.description = fallbackFlow.description || '';
                }
                if (normalized.settings === undefined && fallbackFlow.settings !== undefined) {
                    normalized.settings = JSON.parse(JSON.stringify(fallbackFlow.settings));
                }
            } else {
                normalized.scenarios = [];
            }
        }

        normalized.scenarios.forEach((scenario) => ensureScenarioDefaults(scenario));

        return normalized;
    };
    editorSchema = normalizeEditorSchema(editorSchema);
    selectedScenarioId = editorSchema.scenarios[0]?.id || null;
    const activeVersionScenarioIds = () => {
        const flow = extractVersionFlow(activeVersion);
        const scenarios = Array.isArray(flow?.scenarios) ? flow.scenarios : [];
        if (!scenarios.length) {
            return new Set();
        }
        return new Set(scenarios.map((scenario) => String(scenario?.id || '')).filter(Boolean));
    };
    const simulationMatchesScenario = (scenario) => Boolean(latestSimulation?.matched)
        && String(latestSimulation?.scenario?.id || '') === String(scenario?.id || '');
    const compareMatchesScenario = (scenario) => Boolean(latestCompare?.laravel?.matched)
        && String(latestCompare?.laravel?.scenario?.id || '') === String(scenario?.id || '');
    const formatSimulationSummary = (result) => {
        if (!result || !result.matched) {
            return 'No hubo match para esta entrada.';
        }
        const actionTypes = Array.isArray(result.actions) ? result.actions.map((action) => action?.type).filter(Boolean) : [];
        return [
            `Escenario: ${result.scenario?.id || '—'}`,
            `Acciones: ${actionTypes.length ? actionTypes.join(', ') : 'none'}`,
            `Handoff: ${result.handoff_requested ? 'sí' : 'no'}`,
            `State final: ${result.context_after?.state || result.facts?.state || '—'}`,
        ].join('\n');
    };
    const formatCompareSummary = (result) => {
        if (!result || !result.parity) {
            return 'Todavía no se comparó contra legacy.';
        }
        return [
            `Paridad: ${result.parity.same_match && result.parity.same_scenario && result.parity.same_handoff && result.parity.same_action_types ? 'ok' : 'con diferencias'}`,
            `Laravel: ${result.laravel?.scenario?.id || '—'} · Legacy: ${result.legacy?.scenario?.id || '—'}`,
            `Acciones: ${(result.execution_preview?.action_types || []).join(', ') || 'none'}`,
            `Mismatch reasons: ${(result.parity.mismatch_reasons || []).join(', ') || 'none'}`,
        ].join('\n');
    };
    const runtimeActionTypes = () => {
        const simulationTypes = Array.isArray(latestSimulation?.actions)
            ? latestSimulation.actions.map((action) => action?.type).filter(Boolean)
            : [];
        const compareTypes = Array.isArray(latestCompare?.execution_preview?.action_types)
            ? latestCompare.execution_preview.action_types.filter(Boolean)
            : [];
        return Array.from(new Set([...simulationTypes, ...compareTypes]));
    };
    const actionTone = (type) => {
        if (['send_message', 'send_buttons', 'send_list', 'send_sequence'].includes(type)) {
            return 'message';
        }
        if (type === 'send_template') {
            return 'template';
        }
        if (type === 'handoff_agent') {
            return 'handoff';
        }
        if (type === 'ai_agent') {
            return 'template';
        }
        if (['set_state', 'set_context', 'store_consent'].includes(type)) {
            return 'state';
        }
        return 'message';
    };
    const moveItem = (items, index, direction) => {
        if (!Array.isArray(items)) {
            return false;
        }
        const target = index + direction;
        if (index < 0 || target < 0 || index >= items.length || target >= items.length) {
            return false;
        }
        const [item] = items.splice(index, 1);
        items.splice(target, 0, item);
        return true;
    };
    const scenarioShadowMismatches = (scenario) => {
        if (!scenario || !Array.isArray(latestShadowRows) || !latestShadowRows.length) {
            return [];
        }
        return latestShadowRows.filter((row) => {
            const laravelScenario = String(row?.laravel_scenario || '');
            return laravelScenario === String(scenario.id || '')
                && (row?.parity?.same_match === false
                    || row?.parity?.same_scenario === false
                    || row?.parity?.same_handoff === false
                    || row?.parity?.same_action_types === false);
        });
    };
    const scenarioNodeStatuses = (scenario, actions, conditions, transitions) => {
        const mismatches = scenarioShadowMismatches(scenario);
        const publishedIds = activeVersionScenarioIds();
        return {
            scenario: [
                publishedIds.has(String(scenario?.id || ''))
                    ? {variant: 'published', label: 'Published'}
                    : {variant: 'draft', label: 'Draft'},
                ...(simulationMatchesScenario(scenario) ? [{variant: 'match', label: 'Simulado'}] : []),
                ...(compareMatchesScenario(scenario) && latestCompare?.parity?.same_scenario ? [{variant: 'match', label: 'Parity ok'}] : []),
                ...(mismatches.length ? [{variant: 'mismatch', label: `${mismatches.length} shadow mismatch`}] : []),
            ],
            conditions: conditions.length
                ? [{variant: 'match', label: `${conditions.length} reglas activas`}]
                : [{variant: 'warning', label: 'Ruta directa'}],
            actions: actions.length
                ? [{variant: 'match', label: `${actions.length} acciones listas`}]
                : [{variant: 'warning', label: 'Sin acciones'}],
            transitions: transitions.length
                ? [{variant: 'match', label: `${transitions.length} salidas`}]
                : [{variant: 'warning', label: 'Sin transición'}],
        };
    };
    const renderNodeBadges = (items) => (items || []).map((item) => `
        <span class="wa-flow-node-badge wa-flow-node-badge--${escapeHtml(item.variant || 'draft')}">${escapeHtml(item.label || '')}</span>
    `).join('');
    const selectedVersion = () => (Array.isArray(versionsData) ? versionsData : []).find((item) => Number(item?.id) === Number(selectedVersionId)) || null;
    const summarizeVersionDiff = (draftFlow, versionFlow) => {
        const draftScenarios = Array.isArray(draftFlow?.scenarios) ? draftFlow.scenarios : [];
        const versionScenarios = Array.isArray(versionFlow?.scenarios) ? versionFlow.scenarios : [];
        const draftIds = new Set(draftScenarios.map((scenario) => String(scenario?.id || '')).filter(Boolean));
        const versionIds = new Set(versionScenarios.map((scenario) => String(scenario?.id || '')).filter(Boolean));
        const onlyDraft = [...draftIds].filter((id) => !versionIds.has(id));
        const onlyVersion = [...versionIds].filter((id) => !draftIds.has(id));
        const changed = draftScenarios.filter((scenario) => {
            const match = versionScenarios.find((item) => String(item?.id || '') === String(scenario?.id || ''));
            return match && JSON.stringify(match) !== JSON.stringify(scenario);
        }).map((scenario) => String(scenario?.id || ''));

        return [
            `draft escenarios=${draftScenarios.length} · published escenarios=${versionScenarios.length}`,
            onlyDraft.length ? `solo en draft: ${onlyDraft.join(', ')}` : 'solo en draft: none',
            onlyVersion.length ? `solo en published: ${onlyVersion.join(', ')}` : 'solo en published: none',
            changed.length ? `cambiados: ${changed.join(', ')}` : 'cambiados: none',
        ].join('\n');
    };
    const renderFlowMap = () => {
        const scenarios = getScenarios();
        if (!scenarios.length) {
            return '<div class="wa-flow-empty">Todavía no hay escenarios en el flujo.</div>';
        }
        return `
            <div class="wa-flow-map">
                <div class="wa-flow-map__title">Mapa del flujo</div>
                <div class="wa-flow-map__grid">
                    ${scenarios.map((scenario) => {
                        ensureScenarioDefaults(scenario);
                        const outgoing = Array.isArray(scenario.transitions) ? scenario.transitions : [];
                        return `
                            <button type="button" class="wa-flow-map-card ${String(scenario.id) === String(selectedScenarioId) ? 'is-active' : ''}" data-map-scenario-id="${escapeHtml(scenario.id || '')}">
                                <div class="wa-flow-map-card__top">
                                    <div>
                                        <div class="wa-flow-map-card__name">${escapeHtml(scenario.name || scenario.id || 'Escenario')}</div>
                                        <div class="wa-flow-map-card__meta">${escapeHtml(stageLabels[scenario.stage] || scenario.stage || 'Personalizado')} · ${escapeHtml(humanizeScenarioStatus(scenario.status || 'published'))}</div>
                                    </div>
                                    ${scenario.intercept_menu ? '<span class="wa-flow-badge wa-flow-badge--menu">menú</span>' : ''}
                                </div>
                                <div class="wa-flow-map-card__routes">
                                    ${outgoing.length ? outgoing.map((transition) => `
                                        <span class="wa-flow-map-route"><i class="mdi mdi-arrow-right"></i> ${escapeHtml(transition.target || transition.to || 'sin destino')}</span>
                                    `).join('') : '<span class="wa-flow-badge wa-flow-badge--count">sin salida visible</span>'}
                                </div>
                            </button>
                        `;
                    }).join('')}
                </div>
            </div>
        `;
    };
    const renderVersionWorkspace = () => {
        if (!versionList || !versionStats || !versionDiff) {
            return;
        }

        const version = selectedVersion();
        versionList.querySelectorAll('[data-version-id]').forEach((node) => {
            node.classList.toggle('is-active', Number(node.getAttribute('data-version-id')) === Number(selectedVersionId));
        });

        if (!version) {
            versionStats.innerHTML = `
                <div class="wa-flow-version-stat">
                    <div class="wa-flow-version-stat__label">Versión</div>
                    <div class="wa-flow-version-stat__value">—</div>
                </div>
            `;
            versionDiff.textContent = 'No hay versiones disponibles para comparar.';
            return;
        }

        const draftFlow = editorSchema || {};
        const versionFlow = extractVersionFlow(version);
        const draftScenarios = Array.isArray(draftFlow.scenarios) ? draftFlow.scenarios : [];
        const versionScenarios = Array.isArray(versionFlow.scenarios) ? versionFlow.scenarios : [];

        versionStats.innerHTML = `
            <div class="wa-flow-version-stat">
                <div class="wa-flow-version-stat__label">Versión</div>
                <div class="wa-flow-version-stat__value">v${escapeHtml(version.version ?? '—')}</div>
            </div>
            <div class="wa-flow-version-stat">
                <div class="wa-flow-version-stat__label">Escenarios</div>
                <div class="wa-flow-version-stat__value">${versionScenarios.length}</div>
            </div>
            <div class="wa-flow-version-stat">
                <div class="wa-flow-version-stat__label">Draft actual</div>
                <div class="wa-flow-version-stat__value">${draftScenarios.length}</div>
            </div>
        `;
        versionDiff.textContent = summarizeVersionDiff(draftFlow, versionFlow);
    };
    const renderKnowledgeBase = () => {
        if (!kbList) {
            return;
        }
        const documents = Array.isArray(knowledgeBaseState.documents) ? knowledgeBaseState.documents : [];
        const stats = knowledgeBaseState.stats || {};

        if (kbTotal) kbTotal.textContent = String(stats.total || 0);
        if (kbPublished) kbPublished.textContent = String(stats.published || 0);
        if (kbDraft) kbDraft.textContent = String(stats.draft || 0);
        if (kbSources) kbSources.textContent = String(stats.sources || 0);

        if (!documents.length) {
            kbList.innerHTML = '<div class="wa-flow-empty">Todavía no hay documentos en la Knowledge Base.</div>';
            return;
        }

        kbList.innerHTML = documents.map((document) => `
            <div class="wa-kb-card">
                <div class="wa-kb-card__title">${escapeHtml(document.title || 'Documento KB')}</div>
                <div class="wa-kb-card__summary">${escapeHtml(document.summary || 'Sin resumen.')}</div>
                <div class="wa-kb-card__meta">
                    <span class="wa-flow-badge wa-flow-badge--stage">${escapeHtml(document.status || 'draft')}</span>
                    <span class="wa-flow-badge wa-flow-badge--count">${escapeHtml(document.metadata?.tipo_contenido || 'faq')}</span>
                    <span class="wa-flow-badge wa-flow-badge--count">${escapeHtml(document.metadata?.audiencia || 'paciente')}</span>
                    <span class="wa-flow-badge wa-flow-badge--count">${escapeHtml(document.metadata?.sede || 'global')}</span>
                </div>
            </div>
        `).join('');
    };
    const renderAiRuns = () => {
        if (aiTotal) aiTotal.textContent = String(aiAgentState?.stats?.total_runs || 0);
        if (aiHandoff) aiHandoff.textContent = String(aiAgentState?.stats?.handoff_suggested || 0);
        if (aiHigh) aiHigh.textContent = String(aiAgentState?.stats?.high_confidence || 0);
        if (aiAvg) aiAvg.textContent = String(aiAgentState?.stats?.avg_confidence || 0);
        if (aiFallback) aiFallback.textContent = String(aiAgentState?.stats?.fallback_runs || 0);
        if (aiGrounding) aiGrounding.textContent = String(aiAgentState?.stats?.avg_grounding || 0);
        if (aiSafety) aiSafety.textContent = String(aiAgentState?.stats?.avg_safety || 0);
        if (!aiRunsList) {
            return;
        }

        const runs = Array.isArray(aiAgentState?.runs) ? aiAgentState.runs : [];
        if (!runs.length) {
            aiRunsList.innerHTML = '<div class="wa-flow-empty">Todavía no hay ejecuciones del nodo AI Agent.</div>';
            return;
        }

        aiRunsList.innerHTML = runs.map((run) => `
            <div class="wa-ai-run-card">
                <div class="wa-ai-run-card__top">
                    <div>
                        <div class="wa-ai-run-card__title">${escapeHtml(run.scenario_id || 'AI Agent')} · ${escapeHtml(run.classification || 'general')}</div>
                        <div class="wa-ai-run-card__meta">${escapeHtml(run.wa_number || 'sin número')} · conf ${escapeHtml(run.confidence ?? 0)} · ${escapeHtml(run.created_at || '—')}</div>
                    </div>
                    <span class="wa-flow-node-badge wa-flow-node-badge--${decisionBadgeTone(run)}">${escapeHtml(humanizeDecision(run.decision || (run.suggested_handoff ? 'respond_handoff' : 'respond')))}</span>
                </div>
                <div class="wa-ai-run-card__response">${escapeHtml(run.response_text || 'Sin respuesta sugerida todavía.')}</div>
                <div class="wa-ai-run-card__meta">
                    fallback ${run.fallback_used ? 'sí' : 'no'} · handoff ${run.suggested_handoff ? 'sí' : 'no'} · grounding ${escapeHtml(run?.scores?.grounding ?? 0)} · safety ${escapeHtml(run?.scores?.safety ?? 0)}
                </div>
                ${(Array.isArray(run.handoff_reasons) && run.handoff_reasons.length) ? `
                    <div class="wa-ai-run-card__sources">
                        ${run.handoff_reasons.map((reason) => `
                            <span class="wa-flow-badge wa-flow-badge--count">${escapeHtml(humanizeHandoffReason(reason))}</span>
                        `).join('')}
                    </div>
                ` : ''}
                ${(run.evaluation && typeof run.evaluation === 'object') ? `
                    <div class="wa-ai-run-card__meta">
                        grounding ${escapeHtml(run?.evaluation?.grounding?.status || '—')} · safety ${escapeHtml(run?.evaluation?.safety?.status || '—')}
                    </div>
                ` : ''}
                <div class="wa-ai-run-card__sources">
                    ${(Array.isArray(run.matched_documents) ? run.matched_documents : []).map((document) => `
                        <span class="wa-flow-badge wa-flow-badge--count">${escapeHtml(document.title || 'doc')}</span>
                    `).join('')}
                </div>
            </div>
        `).join('');
    };
    const loadKnowledgeBase = async () => {
        if (!kbList) {
            return;
        }
        try {
            const response = await fetch('/v2/whatsapp/api/knowledge-base?limit=8', {credentials: 'same-origin'});
            const data = await response.json();
            knowledgeBaseState = {
                documents: Array.isArray(data?.data) ? data.data : [],
                stats: data?.stats || {},
            };
            renderKnowledgeBase();
        } catch (error) {
            if (kbStatusNode) {
                kbStatusNode.textContent = 'No fue posible cargar la Knowledge Base.';
            }
        }
    };
    const loadAiRuns = async () => {
        try {
            const response = await fetch('/v2/whatsapp/api/flowmaker/ai-runs?limit=8', {credentials: 'same-origin'});
            const data = await response.json();
            const runs = Array.isArray(data?.data) ? data.data : [];
            aiAgentState = {
                runs,
                stats: data?.stats || {},
            };
        } catch (error) {
            aiAgentState = {
                runs: Array.isArray(aiAgentState?.runs) ? aiAgentState.runs : [],
                stats: aiAgentState?.stats || {},
            };
        }
        renderAiRuns();
    };

    const syncPayloadField = () => {
        if (payloadField) {
            payloadField.value = JSON.stringify(editorSchema, null, 2);
        }
        renderVersionWorkspace();
    };

    const ensureScenarioShape = (scenario) => {
        ensureScenarioDefaults(scenario);
    };

    const addScenario = () => {
        const scenario = {
            id: safeId(),
            name: 'Nuevo escenario',
            description: '',
            status: 'draft',
            stage: 'custom',
            intercept_menu: false,
            conditions: [],
            actions: [],
            transitions: [],
        };
        getScenarios().push(scenario);
        selectedScenarioId = scenario.id;
        syncPayloadField();
        renderScenarioList();
        renderScenarioCanvas();
    };

    const duplicateScenario = () => {
        const scenario = selectedScenario();
        if (!scenario) {
            return;
        }

        const clone = JSON.parse(JSON.stringify(scenario));
        clone.id = safeId();
        clone.name = `${clone.name || 'Escenario'} copia`;
        clone.status = 'draft';
        getScenarios().push(clone);
        selectedScenarioId = clone.id;
        syncPayloadField();
        renderScenarioList();
        renderScenarioCanvas();
    };

    const removeScenario = () => {
        const index = getScenarios().findIndex((item) => String(item?.id) === String(selectedScenarioId));
        if (index === -1) {
            return;
        }
        getScenarios().splice(index, 1);
        selectedScenarioId = getScenarios()[Math.max(0, index - 1)]?.id || getScenarios()[0]?.id || null;
        syncPayloadField();
        renderScenarioList();
        renderScenarioCanvas();
    };

    const addCondition = () => {
        const scenario = selectedScenario();
        if (!scenario) {
            return;
        }
        ensureScenarioShape(scenario);
        scenario.conditions.push({type: 'always'});
        syncPayloadField();
        renderScenarioList();
        renderScenarioCanvas();
    };

    const addAction = () => {
        const scenario = selectedScenario();
        if (!scenario) {
            return;
        }
        ensureScenarioShape(scenario);
        scenario.actions.push({
            type: 'send_message',
            message: {type: 'text', body: 'Nuevo mensaje'},
        });
        syncPayloadField();
        renderScenarioList();
        renderScenarioCanvas();
    };
    const addTransition = () => {
        const scenario = selectedScenario();
        if (!scenario) {
            return;
        }
        ensureScenarioShape(scenario);
        scenario.transitions.push({
            target: '',
            condition: {type: 'always', value: ''},
        });
        syncPayloadField();
        renderScenarioList();
        renderScenarioCanvas();
    };
    const moveScenario = (direction) => {
        const scenarios = getScenarios();
        const index = scenarios.findIndex((item) => String(item?.id) === String(selectedScenarioId));
        if (!moveItem(scenarios, index, direction)) {
            return;
        }
        syncPayloadField();
        renderScenarioList();
        renderScenarioCanvas();
    };

    const removeCondition = (index) => {
        const scenario = selectedScenario();
        if (!scenario || !Array.isArray(scenario.conditions)) {
            return;
        }
        scenario.conditions.splice(index, 1);
        syncPayloadField();
        renderScenarioCanvas();
    };

    const removeAction = (index) => {
        const scenario = selectedScenario();
        if (!scenario || !Array.isArray(scenario.actions)) {
            return;
        }
        scenario.actions.splice(index, 1);
        syncPayloadField();
        renderScenarioList();
        renderScenarioCanvas();
    };
    const removeTransition = (index) => {
        const scenario = selectedScenario();
        if (!scenario || !Array.isArray(scenario.transitions)) {
            return;
        }
        scenario.transitions.splice(index, 1);
        syncPayloadField();
        renderScenarioList();
        renderScenarioCanvas();
    };
    const moveCondition = (index, direction) => {
        const scenario = selectedScenario();
        if (!scenario || !moveItem(scenario.conditions, index, direction)) {
            return;
        }
        syncPayloadField();
        renderScenarioCanvas();
    };
    const moveAction = (index, direction) => {
        const scenario = selectedScenario();
        if (!scenario || !moveItem(scenario.actions, index, direction)) {
            return;
        }
        syncPayloadField();
        renderScenarioList();
        renderScenarioCanvas();
    };
    const moveTransition = (index, direction) => {
        const scenario = selectedScenario();
        if (!scenario || !moveItem(scenario.transitions, index, direction)) {
            return;
        }
        syncPayloadField();
        renderScenarioList();
        renderScenarioCanvas();
    };

    const renderScenarioList = () => {
        if (!scenarioList) {
            return;
        }

        if (!selectedScenario() && getScenarios().length > 0) {
            selectedScenarioId = getScenarios()[0]?.id || null;
        }

        const term = (searchInput?.value || '').trim().toLowerCase();
        const rows = getScenarios().filter((scenario) => {
            if (!term) {
                return true;
            }

            const haystack = [
                scenario.id,
                scenario.name,
                scenario.stage,
                ...(Array.isArray(scenario.actions) ? scenario.actions.map((item) => item?.type) : []),
            ].join(' ').toLowerCase();

            return haystack.includes(term);
        });

        if (!rows.length) {
            scenarioList.innerHTML = `<div class="wa-flow-empty">No se encontraron escenarios para el filtro "${escapeHtml(term)}". Limpia la búsqueda para ver todos.</div>`;
            return;
        }

        scenarioList.innerHTML = rows.map((scenario) => {
            ensureScenarioDefaults(scenario);
            const isActive = scenario.id === selectedScenarioId;
            const actionCount = Array.isArray(scenario.actions) ? scenario.actions.length : 0;
            const conditionCount = Array.isArray(scenario.conditions) ? scenario.conditions.length : 0;

            return `
                <button type="button" class="wa-flow-item ${isActive ? 'is-active' : ''}" data-scenario-id="${escapeHtml(scenario.id)}">
                    <div class="wa-flow-item__top">
                        <div>
                            <div class="wa-flow-item__name">${escapeHtml(scenario.name || scenario.id || 'Escenario')}</div>
                            <div class="small text-muted">${escapeHtml(scenario.description || 'Sin descripción')}</div>
                        </div>
                    </div>
                    <div class="wa-flow-item__meta">
                        <span class="wa-flow-badge wa-flow-badge--${scenario.status === 'published' ? 'menu' : 'count'}">${escapeHtml(humanizeScenarioStatus(scenario.status || 'published'))}</span>
                        <span class="wa-flow-badge wa-flow-badge--stage">${escapeHtml(stageLabels[scenario.stage] || scenario.stage || 'Personalizado')}</span>
                        ${scenario.intercept_menu ? '<span class="wa-flow-badge wa-flow-badge--menu">menu</span>' : ''}
                        <span class="wa-flow-badge wa-flow-badge--count">${actionCount} acciones</span>
                        <span class="wa-flow-badge wa-flow-badge--count">${conditionCount} condiciones</span>
                    </div>
                </button>
            `;
        }).join('');

        scenarioList.querySelectorAll('[data-scenario-id]').forEach((node) => {
            node.addEventListener('click', () => {
                selectedScenarioId = node.getAttribute('data-scenario-id');
                renderScenarioList();
                renderScenarioCanvas();
            });
        });
    };

    versionList?.querySelectorAll('[data-version-id]').forEach((node) => {
        node.addEventListener('click', () => {
            selectedVersionId = Number(node.getAttribute('data-version-id'));
            renderVersionWorkspace();
        });
    });

    const renderScenarioCanvas = () => {
        if (!canvas || !canvasTitle || !canvasSubtitle || !stageBadge || !inspectorSummary) {
            return;
        }

        const scenario = selectedScenario();
        if (!scenario) {
            canvasTitle.textContent = 'Escenario';
            canvasSubtitle.textContent = 'Selecciona un escenario para revisar condiciones y acciones.';
            stageBadge.textContent = 'Sin stage';
            inspectorSummary.innerHTML = '<div class="wa-flow-chip">Sin selección</div>';
            canvas.innerHTML = '<div class="wa-flow-empty">Selecciona un escenario del listado lateral.</div>';
            return;
        }
        ensureScenarioDefaults(scenario);

        const actions = Array.isArray(scenario.actions) ? scenario.actions : [];
        const conditions = Array.isArray(scenario.conditions) ? scenario.conditions : [];
        const transitions = Array.isArray(scenario.transitions) ? scenario.transitions : [];
        const nodeStatuses = scenarioNodeStatuses(scenario, actions, conditions, transitions);
        const activeActionTypes = runtimeActionTypes();
        const runtimeCards = [
            simulationMatchesScenario(scenario) ? `
                <div class="wa-flow-runtime-strip__card">
                    <div class="wa-flow-runtime-strip__title">Simulación activa</div>
                    <div class="wa-flow-runtime-strip__body">${escapeHtml(formatSimulationSummary(latestSimulation))}</div>
                </div>
            ` : '',
            compareMatchesScenario(scenario) ? `
                <div class="wa-flow-runtime-strip__card">
                    <div class="wa-flow-runtime-strip__title">Compare con legacy</div>
                    <div class="wa-flow-runtime-strip__body">${escapeHtml(formatCompareSummary(latestCompare))}</div>
                </div>
            ` : '',
        ].filter(Boolean).join('');

        canvasTitle.textContent = scenario.name || scenario.id || 'Escenario';
        canvasSubtitle.textContent = scenario.description || 'Sin descripción adicional.';
        stageBadge.textContent = stageLabels[scenario.stage] || scenario.stage || 'Personalizado';

        inspectorSummary.innerHTML = [
            `<div class="wa-flow-mini-pill"><i class="mdi mdi-identifier"></i> ${escapeHtml(scenario.id || '—')}</div>`,
            `<div class="wa-flow-mini-pill"><i class="mdi mdi-publish"></i> ${escapeHtml(humanizeScenarioStatus(scenario.status || 'published'))}</div>`,
            `<div class="wa-flow-mini-pill"><i class="mdi mdi-shape-outline"></i> ${escapeHtml(stageLabels[scenario.stage] || scenario.stage || 'Personalizado')}</div>`,
            `<div class="wa-flow-mini-pill"><i class="mdi mdi-flash-outline"></i> ${actions.length} acciones</div>`,
            `<div class="wa-flow-mini-pill"><i class="mdi mdi-tune-variant"></i> ${conditions.length} condiciones</div>`,
            scenario.intercept_menu ? '<div class="wa-flow-mini-pill"><i class="mdi mdi-menu"></i> intercepta menú</div>' : '',
        ].filter(Boolean).join('');

        const conditionHtml = conditions.length
            ? `<div class="d-flex flex-column gap-10">${conditions.map((condition, index) => `
                <div class="wa-flow-action">
                    <div class="wa-flow-action__top">
                        <div>
                            <div class="wa-flow-action__label">Condición ${index + 1}</div>
                            <div class="small text-muted">${escapeHtml(conditionHint(condition.type || 'always'))}</div>
                        </div>
                        <div class="wa-flow-inline-actions">
                            <button type="button" class="btn btn-xs btn-outline-dark" data-move-condition="${index}" data-direction="-1">↑</button>
                            <button type="button" class="btn btn-xs btn-outline-dark" data-move-condition="${index}" data-direction="1">↓</button>
                            <button type="button" class="btn btn-xs btn-outline-danger" data-remove-condition="${index}">Quitar</button>
                        </div>
                    </div>
                    <div class="wa-flow-form-grid">
                        <div class="wa-flow-editor-field">
                            <label>Qué se valida</label>
                            <select data-condition-field="${index}" data-field="type">
                                ${['always','is_first_time','has_consent','state_is','awaiting_is','message_in','message_contains','message_matches','last_interaction_gt','patient_found','context_flag'].map((type) => `
                                    <option value="${type}" ${condition.type === type ? 'selected' : ''}>${escapeHtml(conditionTypeLabels[type] || type)}</option>
                                `).join('')}
                            </select>
                        </div>
                        <div class="wa-flow-editor-field">
                            <label>${escapeHtml(conditionValueLabel(condition.type || 'always'))}</label>
                            <input type="text" data-condition-field="${index}" data-field="value" placeholder="${escapeHtml(conditionValueLabel(condition.type || 'always'))}" value="${escapeHtml(condition.value ?? '')}">
                        </div>
                        <div class="wa-flow-editor-field">
                            <label>Minutos</label>
                            <input type="number" min="0" data-condition-field="${index}" data-field="minutes" value="${escapeHtml(condition.minutes ?? '')}">
                        </div>
                    </div>
                </div>
            `).join('')}</div>`
            : '<div class="text-muted">Este escenario no tiene condiciones explícitas. Funciona como regla directa.</div>';

        const actionHtml = actions.length
            ? actions.map((action, index) => {
                ensureAiAgentDefaults(action);
                ensureActionDefaults(action);
                const messageBody = action?.message?.body ?? action?.message ?? '';
                const templateName = action?.template?.name ?? action?.template ?? '';
                const actionValue = messageBody || templateName || action?.instructions || action?.state || '';
                const type = String(action?.type || 'accion');
                const tone = actionTone(type);
                const isRuntimeHit = activeActionTypes.includes(type);
                const buttonEditor = type === 'send_buttons' ? `
                    <div class="wa-flow-form-grid">
                        <div class="wa-flow-editor-field" style="grid-column: 1 / -1;">
                            <label>Texto del mensaje</label>
                            <textarea data-action-field="${index}" data-field="value">${escapeHtml(action?.message?.body || '')}</textarea>
                        </div>
                        ${(Array.isArray(action?.message?.buttons) ? action.message.buttons : []).map((button, buttonIndex) => `
                            <div class="wa-flow-editor-field">
                                <label>Botón ${buttonIndex + 1} · título</label>
                                <input type="text" data-action-config="${index}" data-field="message.buttons.${buttonIndex}.title" value="${escapeHtml(button?.title || '')}">
                            </div>
                            <div class="wa-flow-editor-field">
                                <label>Botón ${buttonIndex + 1} · id</label>
                                <input type="text" data-action-config="${index}" data-field="message.buttons.${buttonIndex}.id" value="${escapeHtml(button?.id || '')}">
                            </div>
                            <div class="wa-flow-editor-field">
                                <label>&nbsp;</label>
                                <button type="button" class="btn btn-outline-danger" data-action-remove-button="${index}" data-button-index="${buttonIndex}">Quitar botón</button>
                            </div>
                        `).join('')}
                    </div>
                    <div class="wa-flow-inline-actions mt-10">
                        <button type="button" class="btn btn-sm btn-outline-primary" data-action-add-button="${index}">Agregar botón</button>
                    </div>
                ` : '';
                const listEditor = type === 'send_list' ? `
                    <div class="wa-flow-form-grid">
                        <div class="wa-flow-editor-field" style="grid-column: 1 / -1;">
                            <label>Texto del mensaje</label>
                            <textarea data-action-field="${index}" data-field="value">${escapeHtml(action?.message?.body || '')}</textarea>
                        </div>
                        <div class="wa-flow-editor-field">
                            <label>Texto del botón</label>
                            <input type="text" data-action-config="${index}" data-field="message.button_text" value="${escapeHtml(action?.message?.button_text || '')}">
                        </div>
                        <div class="wa-flow-editor-field">
                            <label>Título de sección</label>
                            <input type="text" data-action-config="${index}" data-field="message.sections.0.title" value="${escapeHtml(action?.message?.sections?.[0]?.title || '')}">
                        </div>
                        ${(Array.isArray(action?.message?.sections?.[0]?.rows) ? action.message.sections[0].rows : []).map((row, rowIndex) => `
                            <div class="wa-flow-editor-field">
                                <label>Fila ${rowIndex + 1} · título</label>
                                <input type="text" data-action-config="${index}" data-field="message.sections.0.rows.${rowIndex}.title" value="${escapeHtml(row?.title || '')}">
                            </div>
                            <div class="wa-flow-editor-field">
                                <label>Fila ${rowIndex + 1} · id</label>
                                <input type="text" data-action-config="${index}" data-field="message.sections.0.rows.${rowIndex}.id" value="${escapeHtml(row?.id || '')}">
                            </div>
                            <div class="wa-flow-editor-field">
                                <label>Fila ${rowIndex + 1} · descripción</label>
                                <input type="text" data-action-config="${index}" data-field="message.sections.0.rows.${rowIndex}.description" value="${escapeHtml(row?.description || '')}">
                            </div>
                            <div class="wa-flow-editor-field">
                                <label>&nbsp;</label>
                                <button type="button" class="btn btn-outline-danger" data-action-remove-row="${index}" data-row-index="${rowIndex}">Quitar fila</button>
                            </div>
                        `).join('')}
                    </div>
                    <div class="wa-flow-inline-actions mt-10">
                        <button type="button" class="btn btn-sm btn-outline-primary" data-action-add-row="${index}">Agregar opción</button>
                    </div>
                ` : '';
                const templateEditor = type === 'send_template' ? `
                    <div class="wa-flow-form-grid">
                        <div class="wa-flow-editor-field">
                            <label>Template</label>
                            <select data-action-config="${index}" data-field="template.code">
                                ${templateSelectOptions(action?.template?.code || action?.template?.name || '')}
                            </select>
                        </div>
                        <div class="wa-flow-editor-field">
                            <label>Nombre visible</label>
                            <input type="text" data-action-config="${index}" data-field="template.name" value="${escapeHtml(action?.template?.name || action?.template?.code || '')}">
                        </div>
                    </div>
                ` : '';
                const handoffEditor = type === 'handoff_agent' ? `
                    <div class="wa-flow-form-grid">
                        <div class="wa-flow-editor-field">
                            <label>Role ID</label>
                            <input type="number" min="1" data-action-config="${index}" data-field="role_id" value="${escapeHtml(action?.role_id || '')}">
                        </div>
                        <div class="wa-flow-editor-field" style="grid-column: 1 / -1;">
                            <label>Nota operativa</label>
                            <textarea data-action-config="${index}" data-field="note">${escapeHtml(action?.note || '')}</textarea>
                        </div>
                    </div>
                ` : '';
                const stateEditor = type === 'set_state' ? `
                    <div class="wa-flow-form-grid">
                        <div class="wa-flow-editor-field">
                            <label>Nuevo state</label>
                            <input type="text" data-action-field="${index}" data-field="value" value="${escapeHtml(action?.state || '')}">
                        </div>
                    </div>
                ` : '';
                const aiAgentConfig = type === 'ai_agent' ? `
                    <div class="wa-flow-form-grid">
                        <div class="wa-flow-editor-field" style="grid-column: 1 / -1;">
                            <label>Instructions</label>
                            <textarea data-action-field="${index}" data-field="value">${escapeHtml(action.instructions || '')}</textarea>
                        </div>
                        <div class="wa-flow-editor-field" style="grid-column: 1 / -1;">
                            <label>Fallback message</label>
                            <textarea data-action-config="${index}" data-field="fallback_message">${escapeHtml(action.fallback_message || '')}</textarea>
                        </div>
                        <div class="wa-flow-editor-field">
                            <label>Reply threshold</label>
                            <input type="number" min="0" max="1" step="0.05" data-action-config="${index}" data-field="reply_threshold" value="${escapeHtml(action.reply_threshold ?? 0.45)}">
                        </div>
                        <div class="wa-flow-editor-field">
                            <label>Handoff threshold</label>
                            <input type="number" min="0" max="1" step="0.05" data-action-config="${index}" data-field="handoff_threshold" value="${escapeHtml(action.handoff_threshold ?? 0.45)}">
                        </div>
                        <div class="wa-flow-editor-field">
                            <label>Forzar handoff</label>
                            <select data-action-config="${index}" data-field="handoff">
                                <option value="0" ${!action.handoff ? 'selected' : ''}>No</option>
                                <option value="1" ${action.handoff ? 'selected' : ''}>Sí</option>
                            </select>
                        </div>
                        <div class="wa-flow-editor-field">
                            <label>KB sede</label>
                            <input type="text" data-action-config="${index}" data-field="kb_filters.sede" value="${escapeHtml(action?.kb_filters?.sede || '')}">
                        </div>
                        <div class="wa-flow-editor-field">
                            <label>KB especialidad</label>
                            <input type="text" data-action-config="${index}" data-field="kb_filters.especialidad" value="${escapeHtml(action?.kb_filters?.especialidad || '')}">
                        </div>
                        <div class="wa-flow-editor-field">
                            <label>KB tipo contenido</label>
                            <input type="text" data-action-config="${index}" data-field="kb_filters.tipo_contenido" value="${escapeHtml(action?.kb_filters?.tipo_contenido || '')}">
                        </div>
                        <div class="wa-flow-editor-field">
                            <label>KB audiencia</label>
                            <input type="text" data-action-config="${index}" data-field="kb_filters.audiencia" value="${escapeHtml(action?.kb_filters?.audiencia || '')}">
                        </div>
                    </div>
                    <div class="wa-flow-inline-note">Configura thresholds, fallback y filtros KB para que el preview del AI Agent sea auditable.</div>
                ` : '';
                const defaultValueEditor = !['send_buttons', 'send_list', 'send_template', 'handoff_agent', 'set_state', 'ai_agent'].includes(type) ? `
                    <div class="wa-flow-form-grid">
                        <div class="wa-flow-editor-field" style="grid-column: 1 / -1;">
                            <label>Valor principal</label>
                            <textarea data-action-field="${index}" data-field="value">${escapeHtml(actionValue)}</textarea>
                        </div>
                    </div>
                ` : '';
                return `
                <div class="wa-flow-action wa-flow-action--${escapeHtml(tone)} ${isRuntimeHit ? 'is-runtime-hit' : ''}">
                    <div class="wa-flow-action__top">
                        <div class="wa-flow-action__label">${index + 1}. ${escapeHtml(actionLabel(action))}</div>
                        <div class="wa-flow-inline-actions">
                            <span class="wa-flow-badge wa-flow-badge--count">${escapeHtml(actionTypeLabels[type] || type)}</span>
                            ${isRuntimeHit ? '<span class="wa-flow-node-badge wa-flow-node-badge--match">ruta activa</span>' : ''}
                            <button type="button" class="btn btn-xs btn-outline-dark" data-move-action="${index}" data-direction="-1">↑</button>
                            <button type="button" class="btn btn-xs btn-outline-dark" data-move-action="${index}" data-direction="1">↓</button>
                            <button type="button" class="btn btn-xs btn-outline-danger" data-remove-action="${index}">Quitar</button>
                        </div>
                    </div>
                    <div class="wa-flow-form-grid">
                        <div class="wa-flow-editor-field">
                            <label>Tipo</label>
                            <select data-action-field="${index}" data-field="type">
                                ${actionTypeSelectOptions(action.type || 'send_message')}
                            </select>
                        </div>
                    </div>
                    ${defaultValueEditor}
                    ${buttonEditor}
                    ${listEditor}
                    ${templateEditor}
                    ${handoffEditor}
                    ${stateEditor}
                    ${aiAgentConfig}
                    <div class="wa-flow-technical">
                        <details>
                            <summary>Ver JSON técnico de esta acción</summary>
                            <div class="wa-flow-code mt-12">${escapeHtml(pretty(action))}</div>
                        </details>
                    </div>
                </div>
            `; }).join('')
            : '<div class="text-muted">Este escenario todavía no tiene acciones publicadas.</div>';

        const transitionHtml = transitions.length
            ? `<div class="d-flex flex-column gap-10">${transitions.map((transition, index) => `
                <div class="wa-flow-transition-card ${compareMatchesScenario(scenario) || simulationMatchesScenario(scenario) ? 'is-runtime-hit' : ''}">
                    <div class="wa-flow-action__top">
                        <div>
                            <div class="wa-flow-action__label">Ruta ${index + 1}</div>
                            <div class="small text-muted">${escapeHtml(transitionHint(transition?.condition?.type || 'always'))}</div>
                            <div class="wa-flow-transition-card__route">
                                <span>${escapeHtml(scenario.id || 'escenario')}</span>
                                <i class="mdi mdi-arrow-right"></i>
                                <span>${escapeHtml(transition.target || transition.to || 'sin destino')}</span>
                            </div>
                        </div>
                        <div class="wa-flow-inline-actions">
                            <button type="button" class="btn btn-xs btn-outline-dark" data-move-transition="${index}" data-direction="-1">↑</button>
                            <button type="button" class="btn btn-xs btn-outline-dark" data-move-transition="${index}" data-direction="1">↓</button>
                            <button type="button" class="btn btn-xs btn-outline-danger" data-remove-transition="${index}">Quitar</button>
                        </div>
                    </div>
                    <div class="wa-flow-form-grid">
                        <div class="wa-flow-editor-field">
                            <label>Destino</label>
                            <select data-transition-field="${index}" data-field="target">
                                ${scenarioSelectOptions(transition.target || transition.to || '')}
                            </select>
                        </div>
                        <div class="wa-flow-editor-field">
                            <label>Cuándo sale por aquí</label>
                            <select data-transition-field="${index}" data-field="condition_type">
                                ${['always','message_in','message_contains','message_matches','state_is','awaiting_is','context_flag'].map((type) => `
                                    <option value="${type}" ${transition?.condition?.type === type ? 'selected' : ''}>${escapeHtml(transitionConditionTypeLabels[type] || type)}</option>
                                `).join('')}
                            </select>
                        </div>
                        <div class="wa-flow-editor-field">
                            <label>Valor esperado</label>
                            <input type="text" data-transition-field="${index}" data-field="condition_value" value="${escapeHtml(transition?.condition?.value ?? '')}">
                        </div>
                    </div>
                </div>
            `).join('')}</div>`
            : '<div class="text-muted">Las transiciones visibles todavía se derivan del contrato publicado.</div><div class="wa-flow-inline-note">Agrega una salida explícita para documentar a qué escenario o handoff salta este flujo.</div>';

        canvas.innerHTML = `
            ${runtimeCards ? `<div class="wa-flow-runtime-strip">${runtimeCards}</div>` : ''}
            ${renderFlowMap()}
            <div class="wa-flow-node wa-flow-node--scenario ${simulationMatchesScenario(scenario) ? 'is-simulated' : ''}">
            <div class="wa-flow-block">
                <div class="wa-flow-block__head">
                    <div>
                        <div class="fw-700">Nodo base del escenario</div>
                        <div class="small text-muted">Configuración del escenario: identidad, stage e interceptación del flujo.</div>
                        <div class="wa-flow-block__meta">${renderNodeBadges(nodeStatuses.scenario)}</div>
                    </div>
                    <div class="wa-flow-inline-actions">
                        <button type="button" class="btn btn-sm btn-outline-dark" id="wa-flow-move-scenario-up-btn">Subir</button>
                        <button type="button" class="btn btn-sm btn-outline-dark" id="wa-flow-move-scenario-down-btn">Bajar</button>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="wa-flow-duplicate-scenario-btn">Duplicar</button>
                        <button type="button" class="btn btn-sm btn-outline-danger" id="wa-flow-remove-scenario-btn">Eliminar</button>
                    </div>
                </div>
                <div class="wa-flow-block__body">
                    <div class="wa-flow-form-grid">
                        <div class="wa-flow-editor-field">
                            <label>ID</label>
                            <input type="text" id="wa-flow-edit-id" value="${escapeHtml(scenario.id || '')}">
                        </div>
                        <div class="wa-flow-editor-field">
                            <label>Nombre</label>
                            <input type="text" id="wa-flow-edit-name" value="${escapeHtml(scenario.name || '')}">
                        </div>
                        <div class="wa-flow-editor-field">
                            <label>Stage</label>
                            <select id="wa-flow-edit-stage">
                                ${['arrival','validation','consent','menu','scheduling','results','post','custom'].map((stage) => `
                                    <option value="${stage}" ${scenario.stage === stage ? 'selected' : ''}>${stage}</option>
                                `).join('')}
                            </select>
                        </div>
                        <div class="wa-flow-editor-field">
                            <label>Activo en runtime</label>
                            <div class="wa-flow-toggle">
                                <div class="wa-flow-toggle__copy">
                                    <div class="wa-flow-toggle__label">${scenario.status === 'published' ? 'Escenario activo' : 'Escenario inactivo'}</div>
                                    <div class="wa-flow-toggle__hint">Si está apagado no participa en producción cuando publiques.</div>
                                </div>
                                <label class="wa-flow-switch">
                                    <input type="checkbox" id="wa-flow-edit-enabled" ${scenario.status === 'published' ? 'checked' : ''}>
                                    <span class="wa-flow-switch__track"></span>
                                    <span class="wa-flow-switch__thumb"></span>
                                </label>
                            </div>
                            <div class="wa-flow-inline-note">Apagado usa estado Pausado. Si quieres seguir editándolo sin activarlo, usa el estado Borrador debajo.</div>
                        </div>
                        <div class="wa-flow-editor-field">
                            <label>Estado avanzado</label>
                            <select id="wa-flow-edit-status">
                                ${['draft','published','paused'].map((status) => `
                                    <option value="${status}" ${scenario.status === status ? 'selected' : ''}>${humanizeScenarioStatus(status)}</option>
                                `).join('')}
                            </select>
                            <div class="wa-flow-inline-note">Solo los escenarios en estado Publicado entran al runtime y al publish final.</div>
                        </div>
                        <div class="wa-flow-editor-field">
                            <label>Intercepta menú</label>
                            <select id="wa-flow-edit-intercept">
                                <option value="0" ${!scenario.intercept_menu ? 'selected' : ''}>No</option>
                                <option value="1" ${scenario.intercept_menu ? 'selected' : ''}>Sí</option>
                            </select>
                        </div>
                        <div class="wa-flow-editor-field" style="grid-column: 1 / -1;">
                            <label>Descripción</label>
                            <textarea id="wa-flow-edit-description">${escapeHtml(scenario.description || '')}</textarea>
                        </div>
                    </div>
                </div>
            </div>
            </div>
            <div class="wa-flow-node__connector"><i class="mdi mdi-arrow-down"></i> valida reglas antes de actuar</div>
            <div class="wa-flow-node wa-flow-node--conditions">
            <div class="wa-flow-block">
                <div class="wa-flow-block__head">
                    <div>
                        <div class="fw-700">Nodo de condiciones</div>
                        <div class="small text-muted">Qué tiene que pasar para que este escenario haga match.</div>
                        <div class="wa-flow-block__meta">${renderNodeBadges(nodeStatuses.conditions)}</div>
                    </div>
                    <div class="wa-flow-inline-actions">
                        <span class="wa-flow-badge wa-flow-badge--count">${conditions.length}</span>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="wa-flow-add-condition-btn">Agregar condición</button>
                    </div>
                </div>
                <div class="wa-flow-block__body">${conditionHtml}</div>
            </div>
            </div>
            <div class="wa-flow-node__connector"><i class="mdi mdi-arrow-down"></i> ejecuta la secuencia de acciones</div>
            <div class="wa-flow-node wa-flow-node--actions">
            <div class="wa-flow-block">
                <div class="wa-flow-block__head">
                    <div>
                        <div class="fw-700">Secuencia de acciones</div>
                        <div class="small text-muted">Mensajes, templates, estado y handoff en el orden de ejecución.</div>
                        <div class="wa-flow-block__meta">${renderNodeBadges(nodeStatuses.actions)}</div>
                    </div>
                    <div class="wa-flow-inline-actions">
                        <span class="wa-flow-badge wa-flow-badge--count">${actions.length}</span>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="wa-flow-add-action-btn">Agregar acción</button>
                    </div>
                </div>
                <div class="wa-flow-block__body"><div class="d-flex flex-column gap-10">${actionHtml}</div></div>
            </div>
            </div>
            <div class="wa-flow-node__connector"><i class="mdi mdi-arrow-down"></i> decide la salida o el handoff</div>
            <div class="wa-flow-node wa-flow-node--transitions">
            <div class="wa-flow-block">
                <div class="wa-flow-block__head">
                    <div>
                        <div class="fw-700">Transiciones y handoff</div>
                        <div class="small text-muted">Ruta siguiente y desvíos visibles al final del escenario.</div>
                        <div class="wa-flow-block__meta">${renderNodeBadges(nodeStatuses.transitions)}</div>
                    </div>
                    <div class="wa-flow-inline-actions">
                        <span class="wa-flow-badge wa-flow-badge--count">${transitions.length}</span>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="wa-flow-add-transition-btn">Agregar transición</button>
                    </div>
                </div>
                <div class="wa-flow-block__body">${transitionHtml}</div>
            </div>
            </div>
        `;

        canvas.querySelector('#wa-flow-edit-id')?.addEventListener('input', (event) => {
            scenario.id = event.target.value.trim();
            selectedScenarioId = scenario.id;
            syncPayloadField();
            renderScenarioList();
            renderScenarioCanvas();
        });
        canvas.querySelector('#wa-flow-edit-name')?.addEventListener('input', (event) => {
            scenario.name = event.target.value;
            syncPayloadField();
            renderScenarioList();
        });
        canvas.querySelector('#wa-flow-edit-stage')?.addEventListener('change', (event) => {
            scenario.stage = event.target.value;
            syncPayloadField();
            renderScenarioList();
            renderScenarioCanvas();
        });
        canvas.querySelector('#wa-flow-edit-enabled')?.addEventListener('change', (event) => {
            scenario.status = event.target.checked ? 'published' : 'paused';
            syncPayloadField();
            renderScenarioList();
            renderScenarioCanvas();
        });
        canvas.querySelector('#wa-flow-edit-status')?.addEventListener('change', (event) => {
            scenario.status = event.target.value;
            syncPayloadField();
            renderScenarioList();
            renderScenarioCanvas();
        });
        canvas.querySelector('#wa-flow-edit-intercept')?.addEventListener('change', (event) => {
            scenario.intercept_menu = event.target.value === '1';
            syncPayloadField();
            renderScenarioList();
            renderScenarioCanvas();
        });
        canvas.querySelector('#wa-flow-edit-description')?.addEventListener('input', (event) => {
            scenario.description = event.target.value;
            syncPayloadField();
            renderScenarioList();
        });
        canvas.querySelector('#wa-flow-move-scenario-up-btn')?.addEventListener('click', () => moveScenario(-1));
        canvas.querySelector('#wa-flow-move-scenario-down-btn')?.addEventListener('click', () => moveScenario(1));
        canvas.querySelector('#wa-flow-duplicate-scenario-btn')?.addEventListener('click', duplicateScenario);
        canvas.querySelector('#wa-flow-remove-scenario-btn')?.addEventListener('click', removeScenario);
        canvas.querySelector('#wa-flow-add-condition-btn')?.addEventListener('click', addCondition);
        canvas.querySelector('#wa-flow-add-action-btn')?.addEventListener('click', addAction);
        canvas.querySelector('#wa-flow-add-transition-btn')?.addEventListener('click', addTransition);
        canvas.querySelectorAll('[data-map-scenario-id]').forEach((node) => {
            node.addEventListener('click', () => {
                selectedScenarioId = node.getAttribute('data-map-scenario-id');
                renderScenarioList();
                renderScenarioCanvas();
            });
        });

        canvas.querySelectorAll('[data-condition-field]').forEach((node) => {
            node.addEventListener(node.tagName === 'SELECT' ? 'change' : 'input', () => {
                const index = Number(node.getAttribute('data-condition-field'));
                const field = node.getAttribute('data-field');
                if (!Number.isInteger(index) || !field || !scenario.conditions[index]) {
                    return;
                }
                const value = node.value;
                if (field === 'minutes') {
                    scenario.conditions[index][field] = value === '' ? null : Number(value);
                } else {
                    scenario.conditions[index][field] = value;
                }
                syncPayloadField();
                renderScenarioList();
            });
        });
        canvas.querySelectorAll('[data-remove-condition]').forEach((node) => {
            node.addEventListener('click', () => removeCondition(Number(node.getAttribute('data-remove-condition'))));
        });
        canvas.querySelectorAll('[data-move-condition]').forEach((node) => {
            node.addEventListener('click', () => moveCondition(
                Number(node.getAttribute('data-move-condition')),
                Number(node.getAttribute('data-direction'))
            ));
        });

        canvas.querySelectorAll('[data-action-field]').forEach((node) => {
            node.addEventListener(node.tagName === 'SELECT' ? 'change' : 'input', () => {
                const index = Number(node.getAttribute('data-action-field'));
                const field = node.getAttribute('data-field');
                const action = scenario.actions[index];
                if (!Number.isInteger(index) || !field || !action) {
                    return;
                }

                if (field === 'type') {
                    action.type = node.value;
                    ensureActionDefaults(action);
                }

                if (field === 'value') {
                    const raw = node.value;
                    if (action.type === 'send_template') {
                        action.template = {name: raw};
                    } else if (action.type === 'ai_agent') {
                        action.instructions = raw;
                    } else if (action.type === 'set_state') {
                        action.state = raw;
                    } else {
                        action.message = typeof action.message === 'object' && action.message !== null
                            ? {...action.message, type: action.message.type || 'text', body: raw}
                            : {type: 'text', body: raw};
                    }
                }

                syncPayloadField();
                renderScenarioList();
                renderScenarioCanvas();
            });
        });
        canvas.querySelectorAll('[data-action-config]').forEach((node) => {
            node.addEventListener(node.tagName === 'SELECT' ? 'change' : 'input', () => {
                const index = Number(node.getAttribute('data-action-config'));
                const field = node.getAttribute('data-field');
                const action = scenario.actions[index];
                if (!Number.isInteger(index) || !field || !action) {
                    return;
                }
                updateActionConfig(action, field, node.value);
                syncPayloadField();
                renderScenarioList();
                renderScenarioCanvas();
            });
        });
        canvas.querySelectorAll('[data-action-add-button]').forEach((node) => {
            node.addEventListener('click', () => {
                const index = Number(node.getAttribute('data-action-add-button'));
                const action = scenario.actions[index];
                if (!Number.isInteger(index) || !action) {
                    return;
                }
                ensureButtonsDefaults(action);
                const nextNumber = action.message.buttons.length + 1;
                action.message.buttons.push({
                    id: `opcion_${nextNumber}`,
                    title: `Opción ${nextNumber}`,
                });
                syncPayloadField();
                renderScenarioCanvas();
            });
        });
        canvas.querySelectorAll('[data-action-remove-button]').forEach((node) => {
            node.addEventListener('click', () => {
                const index = Number(node.getAttribute('data-action-remove-button'));
                const buttonIndex = Number(node.getAttribute('data-button-index'));
                const action = scenario.actions[index];
                if (!Number.isInteger(index) || !Number.isInteger(buttonIndex) || !action) {
                    return;
                }
                ensureButtonsDefaults(action);
                action.message.buttons.splice(buttonIndex, 1);
                if (!action.message.buttons.length) {
                    action.message.buttons.push({id: 'opcion_1', title: 'Opción 1'});
                }
                syncPayloadField();
                renderScenarioCanvas();
            });
        });
        canvas.querySelectorAll('[data-action-add-row]').forEach((node) => {
            node.addEventListener('click', () => {
                const index = Number(node.getAttribute('data-action-add-row'));
                const action = scenario.actions[index];
                if (!Number.isInteger(index) || !action) {
                    return;
                }
                ensureListDefaults(action);
                const rows = action.message.sections[0].rows;
                const nextNumber = rows.length + 1;
                rows.push({
                    id: `opcion_${nextNumber}`,
                    title: `Opción ${nextNumber}`,
                    description: '',
                });
                syncPayloadField();
                renderScenarioCanvas();
            });
        });
        canvas.querySelectorAll('[data-action-remove-row]').forEach((node) => {
            node.addEventListener('click', () => {
                const index = Number(node.getAttribute('data-action-remove-row'));
                const rowIndex = Number(node.getAttribute('data-row-index'));
                const action = scenario.actions[index];
                if (!Number.isInteger(index) || !Number.isInteger(rowIndex) || !action) {
                    return;
                }
                ensureListDefaults(action);
                const rows = action.message.sections[0].rows;
                rows.splice(rowIndex, 1);
                if (!rows.length) {
                    rows.push({id: 'opcion_1', title: 'Opción 1', description: ''});
                }
                syncPayloadField();
                renderScenarioCanvas();
            });
        });
        canvas.querySelectorAll('[data-remove-action]').forEach((node) => {
            node.addEventListener('click', () => removeAction(Number(node.getAttribute('data-remove-action'))));
        });
        canvas.querySelectorAll('[data-move-action]').forEach((node) => {
            node.addEventListener('click', () => moveAction(
                Number(node.getAttribute('data-move-action')),
                Number(node.getAttribute('data-direction'))
            ));
        });
        canvas.querySelectorAll('[data-transition-field]').forEach((node) => {
            node.addEventListener(node.tagName === 'SELECT' ? 'change' : 'input', () => {
                const index = Number(node.getAttribute('data-transition-field'));
                const field = node.getAttribute('data-field');
                const transition = scenario.transitions[index];
                if (!Number.isInteger(index) || !field || !transition) {
                    return;
                }

                if (field === 'target') {
                    transition.target = node.value;
                    delete transition.to;
                }

                if (field === 'condition_type') {
                    transition.condition = transition.condition || {};
                    transition.condition.type = node.value;
                }

                if (field === 'condition_value') {
                    transition.condition = transition.condition || {};
                    transition.condition.value = node.value;
                }

                syncPayloadField();
                renderScenarioList();
                renderScenarioCanvas();
            });
        });
        canvas.querySelectorAll('[data-remove-transition]').forEach((node) => {
            node.addEventListener('click', () => removeTransition(Number(node.getAttribute('data-remove-transition'))));
        });
        canvas.querySelectorAll('[data-move-transition]').forEach((node) => {
            node.addEventListener('click', () => moveTransition(
                Number(node.getAttribute('data-move-transition')),
                Number(node.getAttribute('data-direction'))
            ));
        });
    };

    publishButton?.addEventListener('click', async function () {
        statusNode.textContent = 'Publicando...';
        publishButton.disabled = true;

        let payload;
        try {
            payload = JSON.parse(payloadField.value);
        } catch (error) {
            statusNode.textContent = 'JSON inválido. Revisa el payload.';
            publishButton.disabled = false;
            return;
        }

        try {
            const response = await fetch('/v2/whatsapp/api/flowmaker/publish', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({flow: payload}),
                credentials: 'same-origin',
            });
            const raw = await response.text();
            let data = null;
            try {
                data = raw ? JSON.parse(raw) : null;
            } catch (error) {
                data = null;
            }
            statusNode.textContent = data?.message
                || (!response.ok ? (raw.trim() || `Error HTTP ${response.status}`) : 'Publicado.');
            if (response.ok) {
                window.setTimeout(function () { window.location.reload(); }, 800);
            }
        } catch (error) {
            statusNode.textContent = 'No fue posible publicar el flujo desde Laravel.';
        } finally {
            publishButton.disabled = false;
        }
    });

    simButton?.addEventListener('click', async function () {
        simOutput.textContent = 'Simulando...';

        const params = new URLSearchParams({
            wa_number: simNumber?.value || '',
            text: simText?.value || '',
            context: simContext?.value || '{}'
        });

        try {
            const response = await fetch('/v2/whatsapp/api/flowmaker/simulate?' + params.toString(), {
                credentials: 'same-origin'
            });
            const data = await response.json();
            latestSimulation = data;
            if (data?.scenario?.id) {
                selectedScenarioId = data.scenario.id;
                renderScenarioList();
                renderScenarioCanvas();
            }
            simOutput.textContent = `${formatSimulationSummary(data)}\n\n${JSON.stringify(data, null, 2)}`;
            await loadAiRuns();
        } catch (error) {
            simOutput.textContent = 'No fue posible ejecutar la simulación.';
        }
    });

    compareButton?.addEventListener('click', async function () {
        compareOutput.textContent = 'Comparando...';

        const params = new URLSearchParams({
            wa_number: simNumber?.value || '',
            text: simText?.value || '',
            context: simContext?.value || '{}'
        });

        try {
            const response = await fetch('/v2/whatsapp/api/flowmaker/compare?' + params.toString(), {
                credentials: 'same-origin'
            });
            const data = await response.json();
            latestCompare = data;
            if (data?.laravel?.scenario?.id) {
                selectedScenarioId = data.laravel.scenario.id;
                renderScenarioList();
                renderScenarioCanvas();
            }
            compareOutput.textContent = `${formatCompareSummary(data)}\n\n${JSON.stringify(data, null, 2)}`;
        } catch (error) {
            compareOutput.textContent = 'No fue posible comparar Laravel con legacy.';
        }
    });

    const loadShadowRuns = async function () {
        if (!shadowRunsOutput || !shadowSummaryOutput || !readinessOutput) {
            return;
        }

        shadowRunsOutput.textContent = 'Cargando shadow runs...';
        shadowSummaryOutput.textContent = 'Cargando resumen de shadow runtime...';
        readinessOutput.textContent = 'Evaluando readiness de Fase 6...';

        try {
            const [readinessResponse, summaryResponse, runsResponse] = await Promise.all([
                fetch('/v2/whatsapp/api/flowmaker/readiness?limit=100', {credentials: 'same-origin'}),
                fetch('/v2/whatsapp/api/flowmaker/shadow-summary?limit=100', {credentials: 'same-origin'}),
                fetch('/v2/whatsapp/api/flowmaker/shadow-runs?limit=8&mismatches_only=1', {credentials: 'same-origin'}),
            ]);
            const readinessData = await readinessResponse.json();
            const summaryData = await summaryResponse.json();
            const data = await runsResponse.json();
            const readiness = readinessData?.data || {};
            const summary = summaryData?.data || {};
            const rows = Array.isArray(data?.data) ? data.data : [];
            latestShadowRows = rows;

            readinessOutput.textContent = [
                `ready_for_phase_7=${readiness.ready_for_phase_7 ? 'true' : 'false'}`,
                Array.isArray(readiness.blocking_checks) && readiness.blocking_checks.length ? `blocking=${readiness.blocking_checks.join(', ')}` : 'blocking=none',
                '',
                ...((readiness.checks || []).map((check) => `- ${check.label}: expected ${check.expected} · actual ${check.actual} · ${check.passed ? 'ok' : 'fail'}`)),
            ].filter(Boolean).join('\n');

            shadowSummaryOutput.textContent = [
                `runs=${summary.total_runs || 0} · mismatches=${summary.mismatch_runs || 0} · dry_run=${summary.dry_run_runs || 0}`,
                '',
                'Motivos principales:',
                ...((summary.top_mismatch_reasons || []).map((row) => `- ${row.reason}: ${row.count}`)),
                '',
                'Brechas de escenario:',
                ...((summary.top_scenario_gaps || []).map((row) => `- ${row.pair}: ${row.count}`)),
            ].filter(Boolean).join('\n');

            if (!rows.length) {
                shadowRunsOutput.textContent = 'No hay mismatches recientes registrados por el webhook shadow.';
                renderScenarioCanvas();
                return;
            }

            shadowRunsOutput.textContent = rows.map((row) => {
                return [
                    `#${row.id} · ${row.created_at || '-'}`,
                    `${row.wa_number || '-'} · mode=${row.execution_mode || '-'} · laravel=${row.laravel_scenario || '-'} · legacy=${row.legacy_scenario || '-'}`,
                    `match=${row.parity?.same_match ? 'ok' : 'diff'} · scenario=${row.parity?.same_scenario ? 'ok' : 'diff'} · handoff=${row.parity?.same_handoff ? 'ok' : 'diff'} · actions=${row.parity?.same_action_types ? 'ok' : 'diff'}`,
                    Array.isArray(row.parity?.mismatch_reasons) && row.parity.mismatch_reasons.length ? `reasons=${row.parity.mismatch_reasons.join(', ')}` : '',
                    row.execution_preview?.action_types?.length ? `would=${row.execution_preview.action_types.join(', ')}` : '',
                    row.message_text || '',
                ].filter(Boolean).join('\n');
            }).join('\n\n');
            renderScenarioCanvas();
        } catch (error) {
            latestShadowRows = [];
            readinessOutput.textContent = 'No fue posible evaluar el readiness de Fase 6.';
            shadowSummaryOutput.textContent = 'No fue posible cargar el resumen del shadow runtime.';
            shadowRunsOutput.textContent = 'No fue posible cargar los runs recientes del shadow webhook.';
            renderScenarioCanvas();
        }
    };

    payloadField?.addEventListener('change', function () {
        try {
            const parsed = JSON.parse(payloadField.value || '{}');
            editorSchema = normalizeEditorSchema(parsed && typeof parsed === 'object' ? parsed : {});
            if (!selectedScenario() && getScenarios().length > 0) {
                selectedScenarioId = getScenarios()[0].id;
            }
            renderScenarioList();
            renderScenarioCanvas();
            renderVersionWorkspace();
            statusNode.textContent = 'Payload sincronizado desde el editor JSON.';
        } catch (error) {
            statusNode.textContent = 'El payload JSON no se pudo interpretar.';
        }
    });

    searchInput?.addEventListener('input', renderScenarioList);
    addScenarioButton?.addEventListener('click', addScenario);
    shadowRefreshButton?.addEventListener('click', loadShadowRuns);
    kbSaveButton?.addEventListener('click', async function () {
        if (!kbTitle || !kbContent) {
            return;
        }

        kbStatusNode.textContent = 'Guardando documento KB...';
        kbSaveButton.disabled = true;

        try {
            const response = await fetch('/v2/whatsapp/api/knowledge-base', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    title: kbTitle.value,
                    content: kbContent.value,
                    status: kbStatus?.value || 'draft',
                    sede: kbSede?.value || '',
                    especialidad: kbEspecialidad?.value || '',
                    tipo_contenido: kbType?.value || 'faq',
                    audiencia: kbAudiencia?.value || 'paciente',
                    source_type: 'manual',
                    source_label: 'Flowmaker KB',
                }),
            });
            const data = await response.json();
            if (!response.ok || !data?.ok) {
                kbStatusNode.textContent = data?.error || 'No fue posible guardar el documento KB.';
                return;
            }

            kbTitle.value = '';
            kbContent.value = '';
            kbStatusNode.textContent = 'Documento KB guardado correctamente.';
            await loadKnowledgeBase();
        } catch (error) {
            kbStatusNode.textContent = 'No fue posible guardar el documento KB.';
        } finally {
            kbSaveButton.disabled = false;
        }
    });

    syncPayloadField();
    renderScenarioList();
    renderScenarioCanvas();
    renderVersionWorkspace();
    renderKnowledgeBase();
    renderAiRuns();
    loadShadowRuns();
    loadKnowledgeBase();
    loadAiRuns();
});
</script>
@endpush
