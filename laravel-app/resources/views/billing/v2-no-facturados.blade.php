@extends('layouts.medforge')

@push('styles')
    <style>
        .row-select.form-check-input {
            position: static !important;
            left: auto !important;
            opacity: 1 !important;
            margin: 0;
        }

        .table-group-row td {
            background-color: #f8f9fa;
        }

        .table-group-row .form-check-input {
            position: static !important;
            left: auto !important;
            opacity: 1 !important;
            margin: 0;
        }

        .preview-modal-body {
            max-height: 70vh;
            overflow-y: auto;
            position: relative;
            padding-bottom: 96px;
        }

        .preview-totals-bar {
            position: sticky;
            bottom: -7rem;
            background: #fff4c5;
            z-index: 2;
        }

        .preview-rules small {
            color: #6c757d;
        }

        .preview-tabs .nav-link {
            border-radius: 999px;
        }

        .preview-accordion .accordion-button:not(.collapsed) {
            background: #eef2ff;
            color: #1f2a44;
        }
    </style>
@endpush

@section('content')
    <section class="content-header">
        <div class="d-flex align-items-center">
            <div class="me-auto">
                <h3 class="page-title">Procedimientos no facturados</h3>
                <div class="d-inline-block align-items-center">
                    <nav>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="/v2/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                            <li class="breadcrumb-item"><a href="/v2/billing">Billing</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Revision de pendientes</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        @include('billing.components.v2-no-facturados-table')
    </section>

    @include('billing.components.v2-no-facturados-preview-modal')
@endsection

@push('scripts')
    <script src="/assets/vendor_components/datatable/datatables.min.js"></script>
    <script src="/assets/vendor_components/jquery.peity/jquery.peity.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="/js/pages/billing/v2-no-facturados.js"></script>
@endpush
