@extends('layouts.medforge')

@push('scripts')
<script src="/js/pages/list.js"></script>
@endpush

@section('content')
@php
    $scripts = [];
    $username = (string) (($currentUser['display_name'] ?? 'Usuario'));
    include base_path('../modules/EditorProtocolos/views/index.php');
@endphp
@endsection
