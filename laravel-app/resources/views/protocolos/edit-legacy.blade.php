@extends('layouts.medforge')

@push('scripts')
<script src="/assets/vendor_components/datatable/datatables.min.js"></script>
<script src="/assets/vendor_components/tiny-editable/mindmup-editabletable.js"></script>
<script src="/assets/vendor_components/tiny-editable/numeric-input-example.js"></script>
<script src="/js/editor-protocolos.js"></script>
<script src="/js/autocomplete-operatorio.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@endpush

@section('content')
@php
    $scripts = [];
    $username = (string) (($currentUser['display_name'] ?? 'Usuario'));
    include base_path('../modules/EditorProtocolos/views/edit.php');
@endphp
@endsection
