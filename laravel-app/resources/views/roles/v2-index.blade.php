@extends('layouts.medforge')

@section('content')
    <div class="content-header">
        <div class="d-flex align-items-center">
            <div class="me-auto">
                <h3 class="page-title">Roles</h3>
                <div class="d-inline-block align-items-center">
                    <nav>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="/v2/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                            <li class="breadcrumb-item active" aria-current="page">Roles</li>
                        </ol>
                    </nav>
                </div>
            </div>
            <a href="/roles/create" class="btn btn-primary btn-sm">Nuevo rol</a>
        </div>
    </div>

    <section class="content">
        @if(($status ?? null) === 'created')
            <div class="alert alert-success">Rol creado correctamente.</div>
        @elseif(($status ?? null) === 'updated')
            <div class="alert alert-success">Rol actualizado correctamente.</div>
        @elseif(($status ?? null) === 'deleted')
            <div class="alert alert-success">Rol eliminado correctamente.</div>
        @elseif(($status ?? null) === 'role_in_use')
            <div class="alert alert-warning">No se puede eliminar: el rol está asignado a usuarios.</div>
        @elseif(($status ?? null) === 'not_found')
            <div class="alert alert-danger">El rol no existe.</div>
        @endif

        <div class="box">
            <div class="box-body table-responsive">
                <table class="table table-striped table-hover table-sm align-middle">
                    <thead class="bg-primary">
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Descripción</th>
                            <th>Usuarios asignados</th>
                            <th>Permisos</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($roles as $role)
                            <tr>
                                <td>{{ $role['id'] }}</td>
                                <td>{{ $role['name'] }}</td>
                                <td>{{ $role['description'] !== '' ? $role['description'] : '—' }}</td>
                                <td>{{ $role['users_count'] }}</td>
                                <td>
                                    @if(empty($role['permissions_list']))
                                        <span class="badge bg-secondary">Sin permisos</span>
                                    @else
                                        @foreach($role['permissions_list'] as $permission)
                                            <span class="badge bg-light text-dark border me-1 mb-1">
                                                {{ $permissionLabels[$permission] ?? $permission }}
                                            </span>
                                        @endforeach
                                    @endif
                                </td>
                                <td class="d-flex gap-2">
                                    <a href="/roles/{{ $role['id'] }}/edit" class="btn btn-sm btn-primary">Editar</a>
                                    <form method="POST" action="/roles/{{ $role['id'] }}/delete" onsubmit="return confirm('¿Eliminar este rol?');">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted">No hay roles registrados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
@endsection
