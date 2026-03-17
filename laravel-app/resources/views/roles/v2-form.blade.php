@extends('layouts.medforge')

@section('content')
    <div class="content-header">
        <div class="d-flex align-items-center">
            <div class="me-auto">
                <h3 class="page-title">{{ $role['id'] ? 'Editar rol' : 'Nuevo rol' }}</h3>
                <div class="d-inline-block align-items-center">
                    <nav>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="/v2/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                            <li class="breadcrumb-item"><a href="/roles">Roles</a></li>
                            <li class="breadcrumb-item active" aria-current="page">{{ $role['id'] ? $role['name'] : 'Nuevo' }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
            <a href="/roles" class="btn btn-outline-secondary btn-sm">Volver</a>
        </div>
    </div>

    <section class="content">
        @if(($status ?? null) === 'updated')
            <div class="alert alert-success">Rol actualizado correctamente.</div>
        @elseif(($status ?? null) === 'created')
            <div class="alert alert-success">Rol creado correctamente.</div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <div class="box">
            <form method="POST" action="{{ $formAction }}">
                @csrf
                <div class="box-body">
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label for="name" class="form-label">Nombre del rol</label>
                            <input
                                id="name"
                                name="name"
                                type="text"
                                class="form-control"
                                maxlength="120"
                                value="{{ old('name', $role['name']) }}"
                                required
                            >
                        </div>
                        <div class="col-md-6">
                            <label for="description" class="form-label">Descripción</label>
                            <input
                                id="description"
                                name="description"
                                type="text"
                                class="form-control"
                                value="{{ old('description', $role['description']) }}"
                            >
                        </div>
                    </div>

                    @php
                        $selected = old('permissions', $selectedPermissions ?? []);
                    @endphp
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

                <div class="box-footer">
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </section>
@endsection
