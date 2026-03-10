@extends('layouts.medforge')

@section('content')
    <div class="content-header">
        <div class="d-flex align-items-center">
            <div class="me-auto">
                <h3 class="page-title">Editar usuario</h3>
                <div class="d-inline-block align-items-center">
                    <nav>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="/v2/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                            <li class="breadcrumb-item"><a href="/v2/usuarios">Usuarios</a></li>
                            <li class="breadcrumb-item active" aria-current="page">{{ $user['username'] }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
            <a href="/v2/usuarios" class="btn btn-outline-secondary btn-sm">Volver</a>
        </div>
    </div>

    <section class="content">
        @if(($status ?? null) === 'updated')
            <div class="alert alert-success">Cambios guardados correctamente.</div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <div class="box">
            <form method="POST" action="/v2/usuarios/{{ $user['id'] }}">
                @csrf
                <div class="box-body">
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label">Usuario</label>
                            <input type="text" class="form-control" value="{{ $user['username'] }}" disabled>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Nombre</label>
                            <input type="text" class="form-control" value="{{ $user['nombre'] }}" disabled>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Email</label>
                            <input type="text" class="form-control" value="{{ $user['email'] }}" disabled>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="role_id" class="form-label">Rol</label>
                        <select id="role_id" name="role_id" class="form-control">
                            <option value="">Sin rol</option>
                            @foreach($roles as $role)
                                <option value="{{ $role->id }}" {{ (int) old('role_id', $user['role_id'] ?? 0) === (int) $role->id ? 'selected' : '' }}>
                                    {{ $role->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    @if(!empty($permissionProfiles))
                        <div class="mb-4">
                            <label for="permission_profile" class="form-label">Plantilla rápida de permisos</label>
                            <div class="d-flex gap-2">
                                <select id="permission_profile" class="form-control">
                                    <option value="">Selecciona una plantilla…</option>
                                    @foreach($permissionProfiles as $profileKey => $profile)
                                        <option value="{{ $profileKey }}">
                                            {{ $profile['label'] ?? $profileKey }} - {{ $profile['description'] ?? '' }}
                                        </option>
                                    @endforeach
                                </select>
                                <button type="button" id="apply_permission_profile" class="btn btn-outline-primary">Aplicar</button>
                            </div>
                            <small class="text-muted">Reemplaza la selección actual de permisos directos por la plantilla.</small>
                        </div>
                    @endif

                    @php
                        $selected = old('permissions', $selectedPermissions ?? []);
                    @endphp
                    <div>
                        <h4 class="mb-3">Permisos directos</h4>
                        @foreach($permissions as $group => $groupPermissions)
                            <div class="border rounded p-3 mb-3">
                                <h5 class="mb-3">{{ $group }}</h5>
                                <div class="row">
                                    @foreach($groupPermissions as $permission => $label)
                                        <div class="col-md-6 col-lg-4 mb-2">
                                            <label class="d-flex align-items-start gap-2">
                                                <input
                                                    type="checkbox"
                                                    name="permissions[]"
                                                    value="{{ $permission }}"
                                                    {{ in_array($permission, $selected, true) ? 'checked' : '' }}
                                                >
                                                <span>{{ $label }}</span>
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="box-footer">
                    <button type="submit" class="btn btn-primary">Guardar cambios</button>
                </div>
            </form>
        </div>
    </section>
@endsection

@push('scripts')
    <script>
        (function () {
            const profiles = @json($permissionProfiles ?? []);
            const applyButton = document.getElementById('apply_permission_profile');
            const profileSelect = document.getElementById('permission_profile');
            if (!applyButton || !profileSelect) {
                return;
            }

            const permissionInputs = Array.from(document.querySelectorAll('input[name="permissions[]"]'));
            applyButton.addEventListener('click', function () {
                const key = profileSelect.value;
                if (!key || !profiles[key] || !Array.isArray(profiles[key].permissions)) {
                    return;
                }

                const selected = new Set(profiles[key].permissions);
                permissionInputs.forEach(function (input) {
                    input.checked = selected.has(input.value);
                });
            });
        })();
    </script>
@endpush
